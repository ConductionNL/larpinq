<?php
/**
 * Search service for LarpingApp
 *
 * @category  Service
 * @package   OCA\LarpingApp\Service
 * @author    Ruben Linde <ruben@larpingapp.com>
 * @copyright 2024 Ruben Linde
 * @license   https://www.gnu.org/licenses/agpl-3.0.html GNU AGPL v3 or later
 * @link      https://larpingapp.com
 */

declare(strict_types=1);

namespace OCA\LarpingApp\Service;

use GuzzleHttp\Client;
use GuzzleHttp\Promise\Utils;
use OCP\IURLGenerator;
use Symfony\Component\Uid\Uuid;

/**
 * Service for performing distributed search across catalogues
 *
 * @category  Service
 * @package   OCA\LarpingApp\Service
 * @author    Ruben Linde <ruben@larpingapp.com>
 * @copyright 2024 Ruben Linde
 * @license   https://www.gnu.org/licenses/agpl-3.0.html GNU AGPL v3 or later
 * @link      https://larpingapp.com
 */
class SearchService
{

    /**
     * HTTP client for making async requests
     *
     * @var Client
     */
    public $client;

    public const BASE_OBJECT = [
        'database'   => 'objects',
        'collection' => 'json',
    ];

    /**
     * Constructor for SearchService
     *
     * @param IURLGenerator $urlGenerator URL generator service
     */
    public function __construct(
        private readonly IURLGenerator $urlGenerator,
    ) {
        $this->client = new Client();
    }//end __construct()

    /**
     * Merges two facet aggregation arrays into one combined array
     *
     * @param array $existingAggregation The existing aggregation data
     * @param array $newAggregation      The new aggregation data to merge
     *
     * @return array The merged aggregation array
     */
    public function mergeFacets(array $existingAggregation, array $newAggregation): array
    {
        $results = [];
        $existingAggregationMapped = [];
        $newAggregationMapped      = [];

        foreach ($existingAggregation as $value) {
            $existingAggregationMapped[$value['_id']] = $value['count'];
        }

        foreach ($newAggregation as $value) {
            if (isset($existingAggregationMapped[$value['_id']]) === true) {
                $newAggregationMapped[$value['_id']] = $existingAggregationMapped[$value['_id']] + $value['count'];
            } else {
                $newAggregationMapped[$value['_id']] = $value['count'];
            }
        }

        $mergedDiff = array_merge(
            array_diff($existingAggregationMapped, $newAggregationMapped),
            array_diff($newAggregationMapped, $existingAggregationMapped)
        );
        foreach ($mergedDiff as $key => $value) {
            $results[] = ['_id' => $key, 'count' => $value];
        }

        return $results;
    }//end mergeFacets()

    /**
     * Merges two aggregation sets into one combined set
     *
     * @param array|null $existingAggregations The existing aggregations
     * @param array|null $newAggregations      The new aggregations to merge
     *
     * @return array The merged aggregations
     */
    private function mergeAggregations(?array $existingAggregations, ?array $newAggregations): array
    {
        if ($newAggregations === null) {
            return [];
        }

        foreach ($newAggregations as $key => $aggregation) {
            if (isset($existingAggregations[$key]) === false) {
                $existingAggregations[$key] = $aggregation;
            } else {
                $existingAggregations[$key] = $this->mergeFacets(
                    existingAggregation: $existingAggregations[$key],
                    newAggregation: $aggregation
                );
            }
        }

        return $existingAggregations;
    }//end mergeAggregations()

    /**
     * Sorts result array by score for usort comparison
     *
     * @param array $a First result element
     * @param array $b Second result element
     *
     * @return int Comparison result
     */
    public function sortResultArray(array $a, array $b): int
    {
        return $a['_score'] <=> $b['_score'];
    }//end sortResultArray()

    /**
     * Performs a distributed search across local and remote catalogues
     *
     * @param array $parameters    Search parameters from the request
     * @param array $elasticConfig Elasticsearch configuration settings
     * @param array $dbConfig      Database configuration settings
     * @param array $catalogi      Optional list of catalogue IDs to filter on
     *
     * @return array Search results including results, facets, pagination metadata
     */
    public function search(array $parameters, array $elasticConfig, array $dbConfig, array $catalogi=[]): array
    {

        $localResults['results'] = [];
        $localResults['facets']  = [];

        $totalResults = 0;
        if (isset($parameters['.limit']) === true) {
            $limit = $parameters['.limit'];
        } else {
            $limit = 30;
        }

        if (isset($parameters['.page']) === true) {
            $page = $parameters['.page'];
        } else {
            $page = 1;
        }

        if ($elasticConfig['location'] !== '') {
            $localResults = $this->elasticService->searchObject(
                filters: $parameters,
                config: $elasticConfig,
                totalResults: $totalResults,
            );
        }

        $directory = $this->directoryService->listDirectory(limit: 1000);

        // $directory = $this->objectService->findObjects(filters: ['_schema' => 'directory'], config: $dbConfig).
        if (count($directory) === 0) {
            $pages = (int) ceil($totalResults / $limit);
            if ($pages === 0) {
                $pages = 1;
            }

            return [
                'results' => $localResults['results'],
                'facets'  => $localResults['facets'],
                'count'   => count($localResults['results']),
                'limit'   => $limit,
                'page'    => $page,
                'pages'   => $pages,
                'total'   => $totalResults,
            ];
        }

        $results      = $localResults['results'];
        $aggregations = $localResults['facets'];

        $searchEndpoints = [];

        $promises = [];
        foreach ($directory as $instance) {
            $instance['search'] = $this->urlGenerator->getAbsoluteURL(
                $this->urlGenerator->linkToRoute(routeName: "opencatalogi.directory.index")
            );

            if ($instance['default'] === false
                || (isset($parameters['.catalogi']) === true
                && in_array($instance['catalogId'], $parameters['.catalogi']) === false)
            ) {
                continue;
            }

            $searchEndpoints[$instance['search']][] = $instance['catalogId'];
        }

        unset($parameters['.catalogi']);

        foreach ($searchEndpoints as $searchEndpoint => $catalogi) {
            $parameters['_catalogi'] = $catalogi;

            $promises[] = $this->client->getAsync(
                uri: $searchEndpoint,
                options: ['query' => $parameters]
            );
        }

        $responses = Utils::settle($promises)->wait();

        foreach ($responses as $response) {
            if ($response['state'] === 'fulfilled') {
                $responseData = json_decode(
                    json: $response['value']->getBody()->getContents(),
                    associative: true
                );

                $results = array_merge(
                    $results,
                    $responseData['results']
                );

                usort($results, [$this, 'sortResultArray']);

                $aggregations = $this->mergeAggregations(
                    existingAggregations: $aggregations,
                    newAggregations: $responseData['facets']
                );
            }
        }

        $pages = (int) ceil($totalResults / $limit);
        if ($pages === 0) {
            $pages = 1;
        }

        return [
            'results' => $results,
            'facets'  => $aggregations,
            'count'   => count($results),
            'limit'   => $limit,
            'page'    => $page,
            'pages'   => $pages,
            'total'   => $totalResults,
        ];
    }//end search()

    /**
     * This function adds a single query param to the given $vars array. ?$name=$value
     * Will check if request query $name has [...] inside the parameter, like this: ?queryParam[$nameKey]=$value.
     * Works recursive, so in case we have ?queryParam[$nameKey][$anotherNameKey][etc][etc]=$value.
     * Also checks for queryParams ending on [] like: ?queryParam[$nameKey][] (or just ?queryParam[]), if this is the case
     * this function will add given value to an array of [queryParam][$nameKey][] = $value or [queryParam][] = $value.
     * If none of the above this function will just add [queryParam] = $value to $vars.
     *
     * @param array  $vars    The vars array we are going to store the query parameter in
     * @param string $name    The full $name of the query param, like this: ?$name=$value
     * @param string $nameKey The full $name of the query param, unless it contains [] like: ?queryParam[$nameKey]=$value
     * @param string $value   The full $value of the query param, like this: ?$name=$value
     *
     * @return void
     */
    private function recursiveRequestQueryKey(array &$vars, string $name, string $nameKey, string $value): void
    {
        $matchesCount = preg_match(pattern: '/(\[[^[\]]*])/', subject: $name, matches:$matches);
        if ($matchesCount > 0) {
            $key  = $matches[0];
            $name = str_replace(search: $key,  replace:'', subject: $name);
            $key  = trim(string: $key, characters: '[]');
            if (empty($key) === false) {
                $vars[$nameKey] = ($vars[$nameKey] ?? []);
                $this->recursiveRequestQueryKey(
                    vars: $vars[$nameKey],
                    name: $name,
                    nameKey: $key,
                    value: $value
                );
            } else {
                $vars[$nameKey][] = $value;
            }
        } else {
            $vars[$nameKey] = $value;
        }

    }//end recursiveRequestQueryKey()

    /**
     * This function creates a mongodb filter array.
     *
     * Also unsets _search in filters !
     *
     * @param array $filters        Query parameters from request.
     * @param array $fieldsToSearch Database field names to filter/search on.
     *
     * @return array $filters
     */
    public function createMongoDBSearchFilter(array $filters, array $fieldsToSearch): array
    {
        if (isset($filters['_search']) === true) {
            $searchRegex    = ['$regex' => $filters['_search'], '$options' => 'i'];
            $filters['$or'] = [];

            foreach ($fieldsToSearch as $field) {
                $filters['$or'][] = [$field => $searchRegex];
            }

            unset($filters['_search']);
        }

        foreach ($filters as $field => $value) {
            if ($value === 'IS NOT NULL') {
                $filters[$field] = ['$ne' => null];
            }

            if ($value === 'IS NULL') {
                $filters[$field] = ['$eq' => null];
            }
        }

        return $filters;

    }//end createMongoDBSearchFilter()

    /**
     * This function creates mysql search conditions based on given filters from request.
     *
     * @param array $filters        Query parameters from request.
     * @param array $fieldsToSearch Fields to search on in sql.
     *
     * @return array $searchConditions
     */
    public function createMySQLSearchConditions(array $filters, array $fieldsToSearch): array
    {
        $searchConditions = [];
        if (isset($filters['_search']) === true) {
            foreach ($fieldsToSearch as $field) {
                $searchConditions[] = "LOWER($field) LIKE :search";
            }
        }

        return $searchConditions;

    }//end createMySQLSearchConditions()

    /**
     * This function unsets all keys starting with _ from filters.
     *
     * @param array $filters Query parameters from request.
     *
     * @return array $filters
     */
    public function unsetSpecialQueryParams(array $filters): array
    {
        foreach ($filters as $key => $value) {
            if (str_starts_with($key, '_') === true) {
                unset($filters[$key]);
            }
        }

        return $filters;

    }//end unsetSpecialQueryParams()

    /**
     * This function creates mysql search parameters based on given filters from request.
     *
     * @param array $filters Query parameters from request.
     *
     * @return array $searchParams
     */
    public function createMySQLSearchParams(array $filters): array
    {
        $searchParams = [];
        if (isset($filters['_search']) === true) {
            $searchParams['search'] = '%'.strtolower($filters['_search']).'%';
        }

        return $searchParams;

    }//end createMySQLSearchParams()

    /**
     * This function creates an sort array based on given order param from request.
     *
     * @param array $filters Query parameters from request.
     *
     * @return array $sort
     */
    public function createSortForMySQL(array $filters): array
    {
        $sort = [];
        if (isset($filters['_order']) === true && is_array($filters['_order']) === true) {
            foreach ($filters['_order'] as $field => $direction) {
                if (strtoupper($direction) === 'DESC') {
                    $direction = 'DESC';
                } else {
                    $direction = 'ASC';
                }

                $sort[$field] = $direction;
            }
        }

        return $sort;

    }//end createSortForMySQL()

    /**
     * This function creates an sort array based on given order param from request.
     *
     * @param array $filters Query parameters from request.
     *
     * @return array $sort
     *
     * @todo Not functional yet. Needs to be fixed (see PublicationsController->index).
     */
    public function createSortForMongoDB(array $filters): array
    {
        $sort = [];
        if (isset($filters['_order']) === true && is_array($filters['_order']) === true) {
            foreach ($filters['_order'] as $field => $direction) {
                if (strtoupper($direction) === 'DESC') {
                    $sort[$field] = -1;
                } else {
                    $sort[$field] = 1;
                }
            }
        }

        return $sort;

    }//end createSortForMongoDB()

    /**
     * Parses the request query string and returns it as an array of queries.
     *
     * @param string $queryString The input query string from the request.
     *
     * @return array The resulting array of query parameters.
     */
    public function parseQueryString(string $queryString=''): array
    {
        $pairs = explode(separator: '&', string: $queryString);
        $vars  = [];

        foreach ($pairs as $pair) {
            $kvpair = explode(separator: '=', string: $pair);

            $key   = urldecode(string: $kvpair[0]);
            $value = '';
            if (count(value: $kvpair) === 2) {
                $value = urldecode(string: $kvpair[1]);
            }

            $this->recursiveRequestQueryKey(
                vars: $vars,
                name: $key,
                nameKey: substr(
                    string: $key,
                    offset: 0,
                    length: strpos(
                        haystack: $key,
                        needle: '['
                    )
                ),
                value: $value
            );
        }//end foreach

        return $vars;
    }//end parseQueryString()
}//end class
