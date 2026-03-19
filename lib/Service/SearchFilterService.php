<?php
/**
 * Search filter service for LarpingApp
 *
 * Utility methods for building search filters and sorting, extracted
 * from SearchService to reduce class complexity.
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

/**
 * Service for creating search filters and sort arrays.
 *
 * @category  Service
 * @package   OCA\LarpingApp\Service
 * @author    Ruben Linde <ruben@larpingapp.com>
 * @copyright 2024 Ruben Linde
 * @license   https://www.gnu.org/licenses/agpl-3.0.html GNU AGPL v3 or later
 * @link      https://larpingapp.com
 *
 * @psalm-suppress UnusedClass Injected via Nextcloud DI container.
 */
class SearchFilterService
{
    /**
     * Constructor for SearchFilterService.
     *
     * @psalm-suppress PossiblyUnusedMethod Instantiated via Nextcloud dependency injection.
     */
    public function __construct()
    {
    }//end __construct()

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

        // @psalm-suppress MixedAssignment Filter values from request
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
            // @psalm-suppress MixedAssignment Field names from array
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
        foreach (array_keys($filters) as $key) {
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
     * This function creates an sort array based on given order param from request for MySQL.
     *
     * @param array $filters Query parameters from request.
     *
     * @return array $sort
     *
     */
    public function createSortForMySQL(array $filters): array
    {
        $sort = [];
        if (isset($filters['_order']) === true && is_array($filters['_order']) === true) {
            // @psalm-suppress MixedAssignment Order values from request
            foreach ($filters['_order'] as $field => $direction) {
                $direction = 'ASC';
                if (strtoupper((string) $direction) === 'DESC') {
                    $direction = 'DESC';
                }

                $sort[$field] = $direction;
            }
        }

        return $sort;

    }//end createSortForMySQL()

    /**
     * This function creates an sort array based on given order param from request for MongoDB.
     *
     * @param array $filters Query parameters from request.
     *
     * @return array $sort
     *
     * @todo Not functional yet. Needs to be fixed (see PublicationsController->index).
     *
     */
    public function createSortForMongoDB(array $filters): array
    {
        $sort = [];
        if (isset($filters['_order']) === true && is_array($filters['_order']) === true) {
            // @psalm-suppress MixedAssignment Order values from request
            foreach ($filters['_order'] as $field => $direction) {
                $sort[$field] = 1;
                if (strtoupper((string) $direction) === 'DESC') {
                    $sort[$field] = -1;
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
            $nameKey    = $key;
            if ($bracketPos !== false) {
                $nameKey = substr($key, 0, $bracketPos);
            }

            $this->recursiveRequestQueryKey(
                vars: $vars,
                name: $key,
                nameKey: $nameKey,
                value: $value
            );
        }//end foreach

        return $vars;
    }//end parseQueryString()

    /**
     * Recursively parses bracket-notation query parameters.
     *
     * @param array  $vars    The vars array we are going to store the query parameter in
     * @param string $name    The full $name of the query param
     * @param string $nameKey The base key of the query param
     * @param string $value   The value of the query param
     *
     * @return void
     *
     */
    private function recursiveRequestQueryKey(array &$vars, string $name, string $nameKey, string $value): void
    {
        $matches      = [];
        $matchesCount = preg_match(pattern: '/(\[[^[\]]*])/', subject: $name, matches: $matches);
        if ($matchesCount <= 0) {
            $vars[$nameKey] = $value;
            return;
        }

        $key  = $matches[0];
        $name = str_replace(search: $key, replace: '', subject: $name);
        $key  = trim(string: $key, characters: '[]');

        if (isset($vars[$nameKey]) === false || is_array($vars[$nameKey]) === false) {
            $vars[$nameKey] = [];
        }

        if (empty($key) === false) {
            // @var array $subVars
            $subVars = &$vars[$nameKey];
            $this->recursiveRequestQueryKey(
                vars: $subVars,
                name: $name,
                nameKey: $key,
                value: $value
            );
            return;
        }

        $vars[$nameKey][] = $value;

    }//end recursiveRequestQueryKey()
}//end class
