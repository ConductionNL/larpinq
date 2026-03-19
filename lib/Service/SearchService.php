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
     * @var Client
     */
    public Client $client;

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
     * @param array<int, array{_id: string, count: int}> $existingAggregation The existing aggregation data
     * @param array<int, array{_id: string, count: int}> $newAggregation      The new aggregation data to merge
     *
     * @return array<int, array{_id: string|int, count: int}> The merged aggregation array
     *
     * @SuppressWarnings(PHPMD.ElseExpression)
     */
    public function mergeFacets(array $existingAggregation, array $newAggregation): array
    {
        $results = [];

        // @var array<string, int> $existingMap
        $existingMap = [];

        // @var array<string, int> $newMap
        $newMap = [];

        foreach ($existingAggregation as $value) {
            $existingMap[$value['_id']] = $value['count'];
        }

        foreach ($newAggregation as $value) {
            if (isset($existingMap[$value['_id']]) === true) {
                $newMap[$value['_id']] = $existingMap[$value['_id']] + $value['count'];
            } else {
                $newMap[$value['_id']] = $value['count'];
            }
        }

        $mergedDiff = array_merge(
            array_diff($existingMap, $newMap),
            array_diff($newMap, $existingMap)
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
     *
     * @SuppressWarnings(PHPMD.ElseExpression)
     */
    private function mergeAggregations(?array $existingAggregations, ?array $newAggregations): array
    {
        if ($newAggregations === null) {
            return [];
        }

        $result = $existingAggregations ?? [];

        // @psalm-suppress MixedAssignment,MixedArgument Aggregation values are dynamic arrays
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
     * @param array $first  First result element
     * @param array $second Second result element
     *
     * @return int Comparison result
     */
    public function sortResultArray(array $first, array $second): int
    {
        return $first['_score'] <=> $second['_score'];
    }//end sortResultArray()

    /**
     * Extract pagination parameters from search parameters.
     *
     * @param array $parameters The search parameters.
     *
     * @return array{limit: int, page: int} The extracted pagination values.
     */
    private function extractPaginationParams(array $parameters): array
    {
        $limit = 30;
        if (isset($parameters['.limit']) === true) {
            $limit = (int) $parameters['.limit'];
        }

        $page = 1;
        if (isset($parameters['.page']) === true) {
            $page = (int) $parameters['.page'];
        }

        return ['limit' => $limit, 'page' => $page];
    }//end extractPaginationParams()

    /**
     * Perform a local search using Elasticsearch if configured.
     *
     * @param array $parameters    Search parameters.
     * @param array $elasticConfig Elasticsearch configuration.
     * @param int   $totalResults  Reference to total results count.
     *
     * @return array{results: array, facets: array} Local search results.
     *
     * @psalm-suppress UndefinedThisPropertyFetch
     * @psalm-suppress MixedMethodCall Dynamically injected service.
     */
    private function performLocalSearch(array $parameters, array $elasticConfig, int &$totalResults): array
    {
        $localResults = [
            'results' => [],
            'facets'  => [],
        ];

        if ($elasticConfig['location'] !== '' && isset($this->elasticService) === true) {
            // @var array{results: array, facets: array} $localResults
            $localResults = $this->elasticService->searchObject(
                filters: $parameters,
                config: $elasticConfig,
                totalResults: $totalResults,
            );
        }

        return $localResults;
    }//end performLocalSearch()

    /**
     * Build search endpoint map from directory entries.
     *
     * @param array $directory  Directory entries.
     * @param array $parameters Search parameters including .catalogi filter.
     *
     * @return array<string, array<string>> Map of endpoint URLs to catalogue IDs.
     */
    private function buildSearchEndpoints(array $directory, array $parameters): array
    {
        $searchEndpoints = [];

        foreach ($directory as $instance) {
            // @var array{search?: string, default?: bool, catalogId?: string} $instance
            $instance['search'] = $this->urlGenerator->getAbsoluteURL(
                $this->urlGenerator->linkToRoute(routeName: "opencatalogi.directory.index")
            );

            if (($instance['default'] ?? false) === false
                || (isset($parameters['.catalogi']) === true
                && in_array($instance['catalogId'] ?? '', (array) $parameters['.catalogi']) === false)
            ) {
                continue;
            }

            $searchEndpoints[$instance['search']][] = $instance['catalogId'] ?? '';
        }

        return $searchEndpoints;
    }//end buildSearchEndpoints()

    /**
     * Send async search requests to remote endpoints and collect responses.
     *
     * @param array<string, array<string>> $searchEndpoints Map of endpoint URLs to catalogue IDs.
     * @param array                        $parameters      Search parameters.
     *
     * @return array The settled promise responses.
     *
     * @psalm-suppress MixedAssignment Search endpoints from directory.
     * @psalm-suppress MixedMethodCall GuzzleHttp returns mixed from settle().
     */
    private function fetchRemoteResults(array $searchEndpoints, array $parameters): array
    {
        unset($parameters['.catalogi']);

        $promises = [];
        // @psalm-suppress MixedAssignment Search endpoints from directory
        foreach ($searchEndpoints as $searchEndpoint => $catalogi) {
            $parameters['_catalogi'] = $catalogi;

            $promises[] = $this->client->getAsync(
                uri: $searchEndpoint,
                options: ['query' => $parameters]
            );
        }

        // @psalm-suppress MixedAssignment GuzzleHttp promise results.
        // @psalm-suppress MixedMethodCall GuzzleHttp returns mixed from settle().
        $responses = Utils::settle($promises)->wait();

        return $responses;
    }//end fetchRemoteResults()

    /**
     * Process remote search responses and merge into results.
     *
     * @param array $responses    The settled promise responses.
     * @param array $results      Existing results to merge into.
     * @param array $aggregations Existing aggregations to merge into.
     *
     * @return array{results: array, aggregations: array} Merged results and aggregations.
     *
     * @psalm-suppress MixedAssignment GuzzleHttp response entries.
     * @psalm-suppress MixedArrayAccess GuzzleHttp response structure is dynamic.
     * @psalm-suppress MixedMethodCall GuzzleHttp response methods are dynamic.
     * @psalm-suppress MixedArgument GuzzleHttp response values are dynamic.
     * @psalm-suppress MixedArgumentTypeCoercion GuzzleHttp response values are dynamic.
     */
    private function processRemoteResponses(array $responses, array $results, array $aggregations): array
    {
        foreach ($responses as $response) {
            // @var array{state: string, value?: \Psr\Http\Message\ResponseInterface} $response
            if ($response['state'] !== 'fulfilled' || isset($response['value']) === false) {
                continue;
            }

            // @var array<string, mixed> $responseData
            $responseData = json_decode(
                json: $response['value']->getBody()->getContents(),
                associative: true
            );

            // @var array $remoteResults
            $remoteResults = $responseData['results'] ?? [];
            $results       = array_merge($results, $remoteResults);

            usort($results, [$this, 'sortResultArray']);

            // @var array|null $remoteFacets
            $remoteFacets = $responseData['facets'] ?? null;
            $aggregations = $this->mergeAggregations(
                existingAggregations: $aggregations,
                newAggregations: $remoteFacets
            );
        }//end foreach

        return [
            'results'      => $results,
            'aggregations' => $aggregations,
        ];
    }//end processRemoteResponses()

    /**
     * Build the final paginated response array.
     *
     * @param array $results      The search results.
     * @param array $aggregations The facet aggregations.
     * @param int   $totalResults Total result count.
     * @param int   $limit        Results per page.
     * @param int   $page         Current page number.
     *
     * @return array The formatted response.
     */
    private function buildPaginatedResponse(
        array $results,
        array $aggregations,
        int $totalResults,
        int $limit,
        int $page
    ): array {
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
    }//end buildPaginatedResponse()

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
     * @psalm-suppress MixedInferredReturnType, UndefinedThisPropertyFetch Dynamic services injected at runtime.
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function search(array $parameters, array $elasticConfig, array $dbConfig, array $catalogi=[]): array
    {
        $pagination   = $this->extractPaginationParams(parameters: $parameters);
        $totalResults = 0;
        $localResults = $this->performLocalSearch(
            parameters: $parameters,
            elasticConfig: $elasticConfig,
            totalResults: $totalResults
        );

        // @psalm-suppress UndefinedThisPropertyFetch
        // @psalm-suppress MixedMethodCall Dynamically injected service.
        // @var array<int, array<string, mixed>> $directory
        $directory = [];
        if (isset($this->directoryService) === true) {
            $directory = $this->directoryService->listDirectory(limit: 1000);
        }

        if (count($directory) === 0) {
            return $this->buildPaginatedResponse(
                results: $localResults['results'],
                aggregations: $localResults['facets'],
                totalResults: $totalResults,
                limit: $pagination['limit'],
                page: $pagination['page']
            );
        }

        $searchEndpoints = $this->buildSearchEndpoints(directory: $directory, parameters: $parameters);
        $responses       = $this->fetchRemoteResults(
            searchEndpoints: $searchEndpoints,
            parameters: $parameters
        );
        $merged          = $this->processRemoteResponses(
            responses: $responses,
            results: $localResults['results'],
            aggregations: $localResults['facets']
        );

        return $this->buildPaginatedResponse(
            results: $merged['results'],
            aggregations: $merged['aggregations'],
            totalResults: $totalResults,
            limit: $pagination['limit'],
            page: $pagination['page']
        );
    }//end search()
}//end class
