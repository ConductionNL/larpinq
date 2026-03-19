<?php

/**
 * ObjectExtensionService for LarpingApp
 *
 * Handles entity extension and request result building,
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

/**
 * Service for extending entities and building result arrays.
 *
 * @category Service
 * @package  OCA\LarpingApp\Service
 * @author   Ruben Linde <ruben@larpingapp.com>
 * @license  https://www.gnu.org/licenses/agpl-3.0.html GNU AGPL v3 or later
 * @link     https://larpingapp.com
 *
 * @psalm-suppress PossiblyUnusedMethod Instantiated via Nextcloud dependency injection.
 *
 * @SuppressWarnings(PHPMD.ShortVariable)
 */
class ObjectExtensionService
{

    /**
     * Constructor for ObjectExtensionService.
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
     * Parse pagination and sorting parameters from request params.
     *
     * @param array $requestParams The request parameters.
     *
     * @return array{limit: int|null, offset: int|null, order: array, extend: array|null, search: string|null}
     */
    private function parseRequestParams(array $requestParams): array
    {
        // @var int|null $limit.
        $limit = $requestParams['limit'] ?? $requestParams['_limit'] ?? null;

        // @var int|null $offset
        $offset = $requestParams['offset'] ?? $requestParams['_offset'] ?? null;

        // @var array|string $order
        $order = $requestParams['order'] ?? $requestParams['_order'] ?? [];

        // @var array|string|null $extend
        $extend = $requestParams['extend'] ?? $requestParams['_extend'] ?? null;

        // @var int|null $page
        $page = $requestParams['page'] ?? $requestParams['_page'] ?? null;

        // @var string|null $search
        $search = $requestParams['search'] ?? $requestParams['_search'] ?? null;

        // If page is set, calculate the offset.
        if ($page !== null && isset($limit) === true) {
            $offset = $limit * ($page - 1);
        }

        // Ensure order and extend are arrays.
        if (is_string($order) === true) {
            $order = array_map('trim', explode(',', $order));
        }

        if (is_string($extend) === true) {
            $extend = array_map('trim', explode(',', $extend));
        }

        $extendArray = null;
        if (is_array($extend) === true) {
            $extendArray = $extend;
        }

        return [
            'limit'  => $limit,
            'offset' => $offset,
            'order'  => $order,
            'extend' => $extendArray,
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
     * @psalm-suppress PossiblyUnusedMethod Public API method called from ObjectsController.
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
     * Extend a single property value with related objects.
     *
     * @param array  $result         The entity array being extended.
     * @param string $property       The property name.
     * @param string $propertyObject The resolved object type.
     * @param mixed  $value          The property value (ID or array of IDs).
     *
     * @return mixed The extended value.
     *
     * @psalm-suppress MixedAssignment Value from entity array.
     */
    private function extendPropertyValue(array $result, string $property, string $propertyObject, mixed $value): mixed
    {
        if (is_array($value) === true) {
            return $this->objectService->getMultipleObjects(
                objectType: $propertyObject,
                ids: $value
            );
        }

        // @var mixed $objectId
        $objectId = $value;
        if (is_object($value) === true) {
            // @var \OCP\AppFramework\Db\Entity $value.
            $objectId = (string) $value->getId();
        }

        return $this->objectService->getObject(
            objectType: $propertyObject,
            id: (string) $objectId
        );
    }//end extendPropertyValue()

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
        // Convert the entity to an array if it's not already one.
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

        // Iterate through each property to be extended.
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

            $result[$property] = $this->extendPropertyValue(
                result: $result,
                property: $property,
                propertyObject: $propertyObject,
                value: $value
            );
        }

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
}//end class
