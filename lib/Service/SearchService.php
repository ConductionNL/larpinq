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
 *
 * @psalm-suppress UnusedClass Injected via Nextcloud DI container.
 * @psalm-suppress UndefinedClass GuzzleHttp is an optional dependency.
 */
class SearchService
{

    /**
     * HTTP client for making async requests
     *
     * @var \GuzzleHttp\Client
     */
    public \GuzzleHttp\Client $client;

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
        $this->client = new \GuzzleHttp\Client();
    }//end __construct()

    /**
     * Merges two facet aggregation arrays into one combined array
     *
     * @param array<int, array{_id: string, count: int}> $existingAggregation The existing aggregation data
     * @param array<int, array{_id: string, count: int}> $newAggregation      The new aggregation data to merge
     *
     * @return array<int, array{_id: string|int, count: int}> The merged aggregation array
     */
    public function mergeFacets(array $existingAggregation, array $newAggregation): array
    {
        $results = [];
        /** @var array<string, int> $existingAggregationMapped */
        $existingAggregationMapped = [];
        /** @var array<string, int> $newAggregationMapped */
        $newAggregationMapped      = [];

        foreach ($existingAggregation as $value) {
            $existingAggregationMapped[(string) $value['_id']] = (int) $value['count'];
        }

        foreach ($newAggregation as $value) {
            if (isset($existingAggregationMapped[(string) $value['_id']]) === true) {
                $newAggregationMapped[(string) $value['_id']] = $existingAggregationMapped[(string) $value['_id']] + (int) $value['count'];
            } else {
                $newAggregationMapped[(string) $value['_id']] = (int) $value['count'];
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

        $result = $existingAggregations ?? [];

        /** @psalm-suppress MixedAssignment,MixedArgument Aggregation values are dynamic arrays */
        foreach ($newAggregations as $key => $aggregation) {
            if (isset($result[$key]) === false) {
                $result[$key] = $aggregation;
            } else {
                $result[$key] = $this->mergeFacets(
                    existingAggregation: $result[$key],
                    newAggregation: $aggregation
                );
            }
        }

        return $result;
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
     *
     * @psalm-suppress PossiblyUnusedParam $dbConfig and $catalogi reserved for future distributed search.
     * @psalm-suppress MixedArgumentTypeCoercion Response data from external HTTP APIs contains mixed types.
     * @psalm-suppress UnusedVariable $promises accumulated in loop then settled.
     * @psalm-suppress MixedInferredReturnType, UndefinedThisPropertyFetch Dynamic services injected at runtime.
     */
    public function search(array $parameters, array $elasticConfig, array $dbConfig, array $catalogi=[]): array
    {
        /** @var array{results: array, facets: array} $localResults */
        $localResults = [
            'results' => [],
            'facets'  => [],
        ];

        $totalResults = 0;
        if (isset($parameters['.limit']) === true) {
            $limit = (int) $parameters['.limit'];
        } else {
            $limit = 30;
        }

        if (isset($parameters['.page']) === true) {
            $page = (int) $parameters['.page'];
        } else {
            $page = 1;
        }

        /**
         * @psalm-suppress UndefinedThisPropertyFetch
         * @psalm-suppress MixedMethodCall Dynamically injected service.
         */
        if ($elasticConfig['location'] !== '' && isset($this->elasticService) === true) {
            /** @var array{results: array, facets: array} $localResults */
            $localResults = $this->elasticService->searchObject(
                filters: $parameters,
                config: $elasticConfig,
                totalResults: $totalResults,
            );
        }

        /**
         * @psalm-suppress UndefinedThisPropertyFetch
         * @psalm-suppress MixedMethodCall Dynamically injected service.
         *
         * @var array<int, array<string, mixed>> $directory
         */
        $directory = isset($this->directoryService) === true ? $this->directoryService->listDirectory(limit: 1000) : [];

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

        /** @psalm-suppress UnusedVariable $promises is accumulated in the loop and settled below. */
        $promises = [];
        foreach ($directory as $instance) {
            /** @var array{search?: string, default?: bool, catalogId?: string} $instance */
            $instance['search'] = $this->urlGenerator->getAbsoluteURL(
                $this->urlGenerator->linkToRoute(routeName: "opencatalogi.directory.index")
            );

            if (($instance['default'] ?? false) === false
                || (isset($parameters['.catalogi']) === true
                && in_array($instance['catalogId'] ?? '', (array) $parameters['.catalogi']) === false)
            ) {
                continue;
            }

            $searchEndpoints[(string) $instance['search']][] = $instance['catalogId'] ?? '';
        }

        unset($parameters['.catalogi']);

        /** @psalm-suppress MixedAssignment Search endpoints from directory */
        foreach ($searchEndpoints as $searchEndpoint => $catalogi) {
            $parameters['_catalogi'] = $catalogi;

            $promises[] = $this->client->getAsync(
                uri: (string) $searchEndpoint,
                options: ['query' => $parameters]
            );
        }

        /**
         * @psalm-suppress MixedAssignment GuzzleHttp promise results.
         * @psalm-suppress MixedMethodCall GuzzleHttp returns mixed from settle().
         */
        $responses = \GuzzleHttp\Promise\Utils::settle($promises)->wait();

        /**
         * @psalm-suppress MixedAssignment GuzzleHttp response entries.
         * @psalm-suppress MixedArrayAccess GuzzleHttp response structure is dynamic.
         * @psalm-suppress MixedMethodCall GuzzleHttp response methods are dynamic.
         * @psalm-suppress MixedArgument GuzzleHttp response values are dynamic.
         * @psalm-suppress MixedArgumentTypeCoercion GuzzleHttp response values are dynamic.
         */
        foreach ($responses as $response) {
            /** @var array{state: string, value?: \Psr\Http\Message\ResponseInterface} $response */
            if ($response['state'] === 'fulfilled' && isset($response['value'])) {
                /** @var array<string, mixed> $responseData */
                $responseData = json_decode(
                    json: $response['value']->getBody()->getContents(),
                    associative: true
                );

                /** @var array $remoteResults */
                $remoteResults = $responseData['results'] ?? [];
                $results = array_merge(
                    $results,
                    $remoteResults
                );

                usort($results, [$this, 'sortResultArray']);

                /** @var array|null $remoteFacets */
                $remoteFacets = $responseData['facets'] ?? null;
                $aggregations = $this->mergeAggregations(
                    existingAggregations: $aggregations,
                    newAggregations: $remoteFacets
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
                if (isset($vars[$nameKey]) === false || is_array($vars[$nameKey]) === false) {
                    $vars[$nameKey] = [];
                }

                /** @var array $subVars */
                $subVars = &$vars[$nameKey];
                $this->recursiveRequestQueryKey(
                    vars: $subVars,
                    name: $name,
                    nameKey: $key,
                    value: $value
                );
            } else {
                if (isset($vars[$nameKey]) === false || is_array($vars[$nameKey]) === false) {
                    $vars[$nameKey] = [];
                }

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
     * @param array<string,mixed> $filters        Query parameters from request.
     * @param array<int,string>   $fieldsToSearch Database field names to filter/search on.
     *
     * @return array<string,mixed> $filters
     */
    public function createMongoDBSearchFilter(array $filters, array $fieldsToSearch): array
    {
        if (isset($filters['_search']) === true) {
            $searchRegex    = ['$regex' => (string) $filters['_search'], '$options' => 'i'];
            $filters['$or'] = [];

            foreach ($fieldsToSearch as $field) {
                $filters['$or'][] = [$field => $searchRegex];
            }

            unset($filters['_search']);
        }

        /** @psalm-suppress MixedAssignment Filter values from request */
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
            /** @psalm-suppress MixedAssignment Field names from array */
            foreach ($fieldsToSearch as $field) {
                $searchConditions[] = "LOWER($field) LIKE :search";
            }
        }

        return $searchConditions;

    }//end createMySQLSearchConditions()

    /**
     * This function unsets all keys starting with _ from filters.
     *
     * @param array<string,mixed> $filters Query parameters from request.
     *
     * @return array<string,mixed> $filters
     */
    public function unsetSpecialQueryParams(array $filters): array
    {
        foreach ($filters as $key => $_value) {
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
            $searchParams['search'] = '%'.strtolower((string) $filters['_search']).'%';
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
            /** @psalm-suppress MixedAssignment Order values from request */
            foreach ($filters['_order'] as $field => $direction) {
                if (strtoupper((string) $direction) === 'DESC') {
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
            /** @psalm-suppress MixedAssignment Order values from request */
            foreach ($filters['_order'] as $field => $direction) {
                if (strtoupper((string) $direction) === 'DESC') {
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

            $bracketPos = strpos($key, '[');
            $nameKey    = ($bracketPos !== false) ? substr($key, 0, $bracketPos) : $key;
            $this->recursiveRequestQueryKey(
                vars: $vars,
                name: $key,
                nameKey: $nameKey,
                value: $value
            );
        }//end foreach

        return $vars;
    }//end parseQueryString()
}//end class
