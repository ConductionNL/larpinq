<?php

/**
 * ObjectQueryService for LarpingApp
 *
 * Handles request parameter parsing, entity extension and result building,
 * extracted from ObjectService to reduce class complexity.
 *
 * @category  Service
 * @package   OCA\LarpingApp\Service
 * @author    Ruben Linde <ruben@larpingapp.com>
 * @copyright 2024 Ruben Linde
 * @license   AGPL-3.0-or-later https://www.gnu.org/licenses/agpl-3.0.en.html
 * @link      https://larpingapp.com
 */

declare(strict_types=1);

namespace OCA\LarpingApp\Service;

use Exception;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use InvalidArgumentException;

/**
 * Service for extending entities and building result arrays.
 *
 * @category Service
 * @package  OCA\LarpingApp\Service
 * @author   Ruben Linde <ruben@larpingapp.com>
 * @license  https://www.gnu.org/licenses/agpl-3.0.html GNU AGPL v3 or later
 * @link     https://larpingapp.com
 *
 * @psalm-suppress UnusedClass Injected via Nextcloud DI container.
 *
 * @SuppressWarnings(PHPMD.ShortVariable)
 */
class ObjectQueryService
{
    /**
     * Constructor for ObjectQueryService.
     *
     * @param ObjectService $objectService The object service.
     *
     * @psalm-suppress PossiblyUnusedMethod Instantiated via Nextcloud dependency injection.
     */
    public function __construct(
        private ObjectService $objectService
    ) {
    }//end __construct()

    /**
     * Get a parameter value from request params, checking both prefixed and unprefixed keys.
     *
     * @param array  $params  The request parameters.
     * @param string $key     The parameter key.
     * @param mixed  $default Default value if not found.
     *
     * @return mixed The parameter value.
     */
    private function getParam(array $params, string $key, mixed $default=null): mixed
    {
        // @var mixed $result
        $result = $params[$key] ?? $params['_'.$key] ?? $default;
        return $result;
    }//end getParam()

    /**
     * Ensure a value is an array by splitting comma-separated strings.
     *
     * @param mixed $value The value to normalize.
     *
     * @return array|null The normalized array or null.
     */
    private function toArrayParam(mixed $value): ?array
    {
        if (is_string($value) === true) {
            return array_map('trim', explode(',', $value));
        }

        if (is_array($value) === true) {
            return $value;
        }

        return null;
    }//end toArrayParam()

    /**
     * Parse pagination and sorting parameters from request params.
     *
     * @param array $requestParams The request parameters.
     *
     * @return array{limit: int|null, offset: int|null, order: array, extend: array|null, search: string|null}
     */
    private function parseRequestParams(array $requestParams): array
    {
        // @var int|null $limit.
        $limit = $this->getParam(params: $requestParams, key: 'limit');
        // @var int|null $offset.
        $offset = $this->getParam(params: $requestParams, key: 'offset');
        $order  = $this->toArrayParam(value: $this->getParam(params: $requestParams, key: 'order', default: [])) ?? [];
        $extend = $this->toArrayParam(value: $this->getParam(params: $requestParams, key: 'extend'));
        // @var int|null $page.
        $page = $this->getParam(params: $requestParams, key: 'page');
        // @var string|null $search.
        $search = $this->getParam(params: $requestParams, key: 'search');

        if ($page !== null && isset($limit) === true) {
            $offset = $limit * ($page - 1);
        }

        return [
            'limit'  => $limit,
            'offset' => $offset,
            'order'  => $order,
            'extend' => $extend,
            'search' => $search,
        ];
    }//end parseRequestParams()

    /**
     * Remove internal routing and pagination params from filter array.
     *
     * @param array $requestParams The request parameters.
     *
     * @return array Cleaned filter array.
     */
    private function extractFilters(array $requestParams): array
    {
        $filters = $requestParams;
        unset($filters['_route']);
        unset(
            $filters['_extend'],
            $filters['_limit'],
            $filters['_offset'],
            $filters['_order'],
            $filters['_page'],
            $filters['_search']
        );
        unset($filters['extend'], $filters['limit'], $filters['offset'], $filters['order'], $filters['page']);
        unset($filters['search']);

        return $filters;
    }//end extractFilters()

    /**
     * Get a result array for a request based on the request and the object type.
     *
     * @param string $objectType    The type of object to retrieve.
     * @param array  $requestParams The request parameters.
     *
     * @return array The result array containing objects and total count.
     *
     * @throws ContainerExceptionInterface|DoesNotExistException|MultipleObjectsReturnedException|NotFoundExceptionInterface
     * @throws InvalidArgumentException If extend is requested for non-OpenRegister objects.
     */
    public function getResultArrayForRequest(string $objectType, array $requestParams): array
    {
        $parsed  = $this->parseRequestParams(requestParams: $requestParams);
        $filters = $this->extractFilters(requestParams: $requestParams);

        $objects = $this->objectService->getObjects(
            objectType: $objectType,
            limit: $parsed['limit'],
            offset: $parsed['offset'],
            filters: $filters,
            sort: $parsed['order'],
            extend: $parsed['extend'],
            search: $parsed['search']
        );

        $facets = $this->objectService->getFacets(objectType: $objectType, filters: $filters);

        return [
            'results' => $objects,
            'facets'  => $facets,
            'total'   => $this->objectService->getCount(objectType: $objectType, filters: $filters),
        ];
    }//end getResultArrayForRequest()

    /**
     * Extends an entity with related objects based on the extend array.
     *
     * @param mixed $entity The entity to extend.
     * @param array $extend An array of properties to extend.
     *
     * @return array The extended entity as an array.
     *
     * @throws Exception If a property is not present on the entity.
     *
     * @psalm-suppress PossiblyUnusedMethod Public API for extending entities with related objects.
     * @psalm-suppress MixedMethodCall Mapper/entity resolved dynamically.
     * @psalm-suppress MixedReturnStatement Entity may return mixed from jsonSerialize().
     * @psalm-suppress MixedArrayOffset Entity properties are dynamic string keys.
     */
    public function extendEntity(mixed $entity, array $extend): array
    {
        $suppressErrors = false;
        if (is_array($entity) === true) {
            $result = $entity;
        } else {
            // @var \JsonSerializable $entityObject.
            $entityObject = $entity;
            // @var array<string,mixed> $result.
            $result = $entityObject->jsonSerialize();
        }

        if (in_array(needle: 'all', haystack: $extend) === true) {
            // @var array<string,mixed> $entityArray
            $entityArray    = $entity;
            $extend         = array_keys($entityArray);
            $suppressErrors = true;
        }

        // @psalm-suppress MixedAssignment Extend array values are strings.
        foreach ($extend as $property) {
            $singularProperty = rtrim((string) $property, 's');
            // @var string $property.
            $value = $this->getPropertyValue(result: $result, property: $property, singular: $singularProperty);
            if ($value === null) {
                continue;
            }

            $propertyObject = $this->resolveMapperType(
                property: $property,
                singularProperty: $singularProperty,
                suppressErrors: $suppressErrors
            );
            if ($propertyObject === null) {
                continue;
            }

            $result[$property] = $this->resolveExtendedValue(
                propertyObject: $propertyObject,
                value: $value
            );
        }//end foreach

        return $result;
    }//end extendEntity()

    /**
     * Get the value of a property from the result array.
     *
     * @param array  $result   The entity result array.
     * @param string $property The property name.
     * @param string $singular The singular form of the property name.
     *
     * @return mixed|null The property value, or null if empty.
     *
     * @throws Exception If the property is not found.
     */
    private function getPropertyValue(array $result, string $property, string $singular): mixed
    {
        if (array_key_exists($property, $result) === true) {
            // @var mixed $value
            $value = $result[$property];
            if (empty($value) === true) {
                return null;
            }

            return $value;
        }

        if (array_key_exists($singular, $result) === true) {
            return $result[$singular];
        }

        throw new Exception("Property '$property' or '$singular' is not present in the entity.");
    }//end getPropertyValue()

    /**
     * Resolve which property name and mapper type to use for extending.
     *
     * @param string $property         The property name.
     * @param string $singularProperty The singular form of the property name.
     * @param bool   $suppressErrors   Whether to suppress mapper errors.
     *
     * @return string|null The resolved property object type, or null if no mapper found.
     *
     * @throws Exception If no mapper available and errors not suppressed.
     */
    private function resolveMapperType(string $property, string $singularProperty, bool $suppressErrors): ?string
    {
        try {
            $this->objectService->getMapper(objectType: $property);
            return $singularProperty;
        } catch (Exception $e) {
            // First attempt failed, try singular.
        }

        try {
            $this->objectService->getMapper(objectType: $singularProperty);
            return $singularProperty;
        } catch (Exception $e) {
            if ($suppressErrors === true) {
                return null;
            }

            throw new Exception("No mapper available for property '$property'.");
        }
    }//end resolveMapperType()

    /**
     * Resolve the extended value for a property.
     *
     * @param string $propertyObject The object type to use.
     * @param mixed  $value          The property value (ID or array of IDs).
     *
     * @return mixed The resolved extended value.
     *
     * @psalm-suppress MixedAssignment Value from entity array.
     */
    private function resolveExtendedValue(string $propertyObject, mixed $value): mixed
    {
        if (is_array($value) === true) {
            return $this->objectService->getMultipleObjects(objectType: $propertyObject, ids: $value);
        }

        // @var mixed $objectId
        $objectId = $value;
        if (is_object($value) === true) {
            // @var \OCP\AppFramework\Db\Entity $value.
            $objectId = (string) $value->getId();
        }

        return $this->objectService->getObject(objectType: $propertyObject, id: (string) $objectId);
    }//end resolveExtendedValue()
}//end class
