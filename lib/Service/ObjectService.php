<?php

namespace OCA\LarpingApp\Service;

use Adbar\Dot;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;
use InvalidArgumentException;
use OCP\App\IAppManager;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Symfony\Component\Uid\Uuid;
use Psr\Container\ContainerInterface;
use OCP\IAppConfig;
// Import mappers 
use OCA\LarpingApp\Db\AbilityMapper;
use OCA\LarpingApp\Db\CharacterMapper;
use OCA\LarpingApp\Db\ConditionMapper;
use OCA\LarpingApp\Db\EffectMapper;
use OCA\LarpingApp\Db\EventMapper;
use OCA\LarpingApp\Db\ItemMapper;
use OCA\LarpingApp\Db\PlayerMapper;
use OCA\LarpingApp\Db\SettingMapper;
use OCA\LarpingApp\Db\SkillMapper;
use OCA\LarpingApp\Db\TemplateMapper;

/**
 * Service class for handling object-related operations
 */
class ObjectService
{
	/** @var string $appName The name of the app */
	private string $appName;

	/**
	 * Constructor for ObjectService.
	 */
	public function __construct(
		private AbilityMapper $abilityMapper,
		private CharacterMapper $characterMapper,
		private ConditionMapper $conditionMapper,
		private EffectMapper $effectMapper,
		private EventMapper $eventMapper,
		private ItemMapper $itemMapper,
		private PlayerMapper $playerMapper,
		private SettingMapper $settingMapper,
		private SkillMapper $skillMapper,
		private TemplateMapper $templateMapper,
		private ContainerInterface $container,
		private readonly IAppManager $appManager,
		private readonly IAppConfig $config,
	) {
		$this->appName = 'larpingapp';
	}

	/**
	 * Gets the appropriate mapper based on the object type.
	 *
	 * @param string $objectType The type of object to retrieve the mapper for.
	 *
	 * @return mixed The appropriate mapper.
	 * @throws InvalidArgumentException If an unknown object type is provided.
	 * @throws NotFoundExceptionInterface|ContainerExceptionInterface If OpenRegister service is not available or if register/schema is not configured.
	 * @throws Exception
	 */
	private function getMapper(string $objectType): mixed
	{
		$objectTypeLower = strtolower($objectType);

		// Get the source for the object type from the configuration
		$source = $this->config->getValueString($this->appName, $objectTypeLower . '_source', 'internal');

		// If the source is 'open_registers', use the OpenRegister service
		if ($source === 'openregister') {
			$openRegister = $this->getOpenRegisters();
			if ($openRegister === null) {
				throw new Exception("OpenRegister service not available");
			}
			$register = $this->config->getValueString($this->appName, $objectTypeLower . '_register', '');
			if (empty($register)) {
				throw new Exception("Register not configured for $objectType");
			}
			$schema = $this->config->getValueString($this->appName, $objectTypeLower . '_schema', '');
			if (empty($schema)) {
				throw new Exception("Schema not configured for $objectType");
			}
			return $openRegister->getMapper(register: $register, schema: $schema);
		}

		// If the source is internal, return the appropriate mapper based on the object type
		return match ($objectType) {
			'ability' => $this->abilityMapper,
			'character' => $this->characterMapper,
			'condition' => $this->conditionMapper,
			'effect' => $this->effectMapper,
			'event' => $this->eventMapper,
			'item' => $this->itemMapper,
			'player' => $this->playerMapper,
			'setting' => $this->settingMapper,
			'skill' => $this->skillMapper,
			'template' => $this->templateMapper,
			default => throw new InvalidArgumentException("Unknown object type: $objectType"),
		};
	}

	/**
	 * Gets an object based on the object type and id.
	 *
	 * @param string $objectType The type of object to retrieve.
	 * @param string $id The id of the object to retrieve.
	 *
	 * @return mixed The retrieved object.
	 * @throws ContainerExceptionInterface|DoesNotExistException|MultipleObjectsReturnedException|NotFoundExceptionInterface
	 */
	public function getObject(string $objectType, string $id, array $extend = []): mixed
	{
		// Clean up the id if it's a URI by getting only the last path part
		if (filter_var($id, FILTER_VALIDATE_URL)) {
			$parts = explode('/', rtrim($id, '/'));
			$id = end($parts);
		}

		// Get the appropriate mapper for the object type
		$mapper = $this->getMapper($objectType);
		// Use the mapper to find and return the object
		$object = $mapper->find($id);

		// Convert the object to an array if it is not already an array
		if (is_object($object) && method_exists($object, 'jsonSerialize')) {
			$object = $object->jsonSerialize();
		} elseif (is_array($object) === false) {
			$object = (array)$object;
		}

		$object = $this->extendEntity(entity: $object, extend: $extend);

		return $object;
	}

	/**
	 * Gets objects based on the object type, filters, search conditions, and other parameters.
	 *
	 * @param string $objectType The type of objects to retrieve.
	 * @param int|null $limit The maximum number of objects to retrieve.
	 * @param int|null $offset The offset from which to start retrieving objects.
	 * @param array|null $filters Filters to apply to the query.
	 * @param array|null $searchConditions Search conditions to apply to the query.
	 * @param array|null $searchParams Search parameters for the query.
	 * @param array|null $sort Sorting parameters for the query.
	 * @param array|null $extend Additional parameters for extending the query.
	 *
	 * @return array The retrieved objects as arrays.
	 * @throws ContainerExceptionInterface|DoesNotExistException|MultipleObjectsReturnedException|NotFoundExceptionInterface
	 */
	public function getObjects(
		string $objectType,
		?int $limit = null,
		?int $offset = null,
		?array $filters = [],
		?array $searchConditions = [],
		?array $searchParams = [],
		?array $sort = [],
		?array $extend = []
	): array
	{

		// Get the appropriate mapper for the object type
		$mapper = $this->getMapper($objectType);
		// Use the mapper to find and return the objects based on the provided parameters
		$objects = $mapper->findAll($limit, $offset, $filters, sort: $sort);

		// Convert entity objects to arrays using jsonSerialize
		$objects = array_map(function($object) {
			return $object->jsonSerialize();
		}, $objects);

		// Extend the objects if the extend array is not empty
		if (empty($extend) === false) {
			$objects = array_map(function($object) use ($extend) {
				return $this->extendEntity($object, $extend);
			}, $objects);
		}

		return $objects;
	}

	/**
	 * Gets objects based on the object type, filters, search conditions, and other parameters.
	 *
	 * @param string $objectType The type of objects to retrieve.
	 * @param int|null $limit The maximum number of objects to retrieve.
	 * @param int|null $offset The offset from which to start retrieving objects.
	 * @param array|null $filters Filters to apply to the query.
	 * @param array|null $searchConditions Search conditions to apply to the query.
	 * @param array|null $searchParams Search parameters for the query.
	 * @param array|null $sort Sorting parameters for the query.
	 * @param array|null $extend Additional parameters for extending the query.
	 *
	 * @return array The retrieved objects as arrays.
	 * @throws ContainerExceptionInterface|DoesNotExistException|MultipleObjectsReturnedException|NotFoundExceptionInterface
	 */
	public function getFacets(
		string $objectType,
		array $filters = [],
	): array
	{
		// Get the appropriate mapper for the object type
		$mapper = $this->getMapper($objectType);

		// Use the mapper to find and return the objects based on the provided parameters
		if ($mapper instanceof \OCA\OpenRegister\Service\ObjectService === true) {
			return $mapper->getAggregations($filters);
		}

		return [];
	}

	/**
	 * Gets multiple objects based on the object type and ids.
	 *
	 * @param string $objectType The type of objects to retrieve.
	 * @param array $ids The ids of the objects to retrieve.
	 *
	 * @return array The retrieved objects.
	 * @throws ContainerExceptionInterface|NotFoundExceptionInterface If an unknown object type is provided.
	 */
	public function getMultipleObjects(string $objectType, array $ids): array
	{
		// Process the ids
		$processedIds = array_map(function($id) {
			if (is_object($id) && method_exists($id, 'getId')) {
				return $id->getId();
			} elseif (is_array($id) && isset($id['id'])) {
				return $id['id'];
			} else {
				return $id;
			}
		}, $ids);

		// Clean up the ids if they are URIs
		$cleanedIds = array_map(function($id) {
			// If the id is a URI, get only the last part of the path
			if (filter_var($id, FILTER_VALIDATE_URL)) {
				$parts = explode('/', rtrim($id, '/'));
				return end($parts);
			}
			return $id;
		}, $processedIds);

		// Get the appropriate mapper for the object type
		$mapper = $this->getMapper($objectType);

		// Use the mapper to find and return multiple objects based on the provided cleaned ids
		return $mapper->findMultiple($cleanedIds);
	}

	/**
	 * Gets all objects of a specific type.
	 *
	 * @param string $objectType The type of objects to retrieve.
	 * @param int|null $limit The maximum number of objects to retrieve.
	 * @param int|null $offset The offset from which to start retrieving objects.
	 *
	 * @return array The retrieved objects.
	 * @throws ContainerExceptionInterface|NotFoundExceptionInterface If an unknown object type is provided.
	 */
	public function getAllObjects(string $objectType, ?int $limit = null, ?int $offset = null): array
	{
		// Get the appropriate mapper for the object type
		$mapper = $this->getMapper($objectType);

		// Use the mapper to find and return all objects of the specified type
		return $mapper->findAll($limit, $offset);
	}

	/**
	 * Creates a new object or updates an existing one from an array of data.
	 *
	 * @param string $objectType The type of object to create or update.
	 * @param array $object The data to create or update the object from.
	 * @param bool $updateVersion If we should update the version or not, default = true.
	 *
	 * @return mixed The created or updated object.
	 * @throws ContainerExceptionInterface|DoesNotExistException|MultipleObjectsReturnedException|NotFoundExceptionInterface
	 */
	public function saveObject(string $objectType, array $object, bool $updateVersion = true): mixed
	{
		// Get the appropriate mapper for the object type
		$mapper = $this->getMapper($objectType);
		// If the object has an id, update it; otherwise, create a new object
		if (isset($object['id']) === true) {
			return $mapper->updateFromArray($object['id'], $object, $updateVersion);
		}
		else {
			return $mapper->createFromArray($object);
		}
	}

	/**
	 * Deletes an object based on the object type and id.
	 *
	 * @param string $objectType The type of object to delete.
	 * @param string|int $id The id of the object to delete.
	 *
	 * @return bool True if the object was successfully deleted, false otherwise.
	 * @throws ContainerExceptionInterface|NotFoundExceptionInterface|\OCP\DB\Exception If an unknown object type is provided.
	 */
	public function deleteObject(string $objectType, string|int $id): bool
	{
		// Get the appropriate mapper for the object type
		$mapper = $this->getMapper($objectType);

		// Use the mapper to get and delete the object
		try {
			$object = $mapper->find($id);
			$mapper->delete($object);
		} catch (Exception $e) {
			return false;
		}

		return true;
	}

	/**
	 * Attempts to retrieve the OpenRegister service from the container.
	 *
	 * @return mixed|null The OpenRegister service if available, null otherwise.
	 * @throws ContainerExceptionInterface|NotFoundExceptionInterface
	 */
	public function getOpenRegisters(): ?\OCA\OpenRegister\Service\ObjectService
	{
		if (in_array(needle: 'openregister', haystack: $this->appManager->getInstalledApps()) === true) {
			try {
				// Attempt to get the OpenRegister service from the container
				return $this->container->get('OCA\OpenRegister\Service\ObjectService');
			} catch (Exception $e) {
				// If the service is not available, return null
				return null;
			}
		}

		return null;
	}

	private function getCount(string $objectType, array $filters = []): int
	{
		$mapper = $this->getMapper($objectType);
		if($mapper instanceof \OCA\OpenRegister\Service\ObjectService === true) {
			return $mapper->count(filters: $filters);
		}

		return 0;
	}

	/**
	 * Get a result array for a request based on the request and the object type.
	 *
	 * @param string $objectType The type of object to retrieve
	 * @param array $requestParams The request parameters
	 *
	 * @return array The result array containing objects and total count
	 * @throws ContainerExceptionInterface|DoesNotExistException|MultipleObjectsReturnedException|NotFoundExceptionInterface
	 */
	public function getResultArrayForRequest(string $objectType, array $requestParams): array
	{
		// Extract specific parameters
		$limit = $requestParams['limit'] ?? $requestParams['_limit'] ?? null;
		$offset = $requestParams['offset'] ?? $requestParams['_offset'] ?? null;
		$order = $requestParams['order'] ?? $requestParams['_order'] ?? [];
		$extend = $requestParams['extend'] ?? $requestParams['_extend'] ?? null;
		$page = $requestParams['page'] ?? $requestParams['_page'] ?? null;

		if ($page !== null && isset($limit)) {
			$offset = $limit * ($page - 1);
		}


		// Ensure order and extend are arrays
		if (is_string($order)) {
			$order = array_map('trim', explode(',', $order));
		}
		if (is_string($extend)) {
			$extend = array_map('trim', explode(',', $extend));
		}

		// Remove unnecessary parameters from filters
		$filters = $requestParams;
		unset($filters['_route']); // Nextcloud automatically adds this
		unset($filters['_extend'], $filters['_limit'], $filters['_offset'], $filters['_order'], $filters['_page']);
		unset($filters['extend'], $filters['limit'], $filters['offset'], $filters['order'], $filters['page']);

		// Fetch objects based on filters and order
		$objects = $this->getObjects(
			objectType: $objectType,
			limit: $limit,
			offset: $offset,
			filters: $filters,
			sort: $order,
			extend: $extend
		);

		$facets  = $this->getFacets($objectType, $filters);

		// Prepare response data
		return [
			'results' => $objects,
			'facets' => $facets,
			'total' => $this->getCount(objectType: $objectType, filters: $filters),
		];
	}

	/**
	 * Extends an entity with related objects based on the extend array.
	 *
	 * @param mixed $entity The entity to extend
	 * @param array $extend An array of properties to extend
	 *
	 * @return array The extended entity as an array
	 * @throws ContainerExceptionInterface|DoesNotExistException|MultipleObjectsReturnedException|NotFoundExceptionInterface If a property is not present on the entity
	 */
	public function extendEntity(mixed $entity, array $extend): array
	{
		$surpressMapperError = false;
		// Convert the entity to an array if it's not already one
		$result = is_array($entity) ? $entity : $entity->jsonSerialize();

		if (in_array(needle: 'all', haystack: $extend) === true) {
			$extend = array_keys($entity);
			$surpressMapperError = true;
		}

		// Iterate through each property to be extended
		foreach ($extend as $property) {
			// Create a singular property name
			$singularProperty = rtrim($property, 's');

			// Check if property or singular property are keys in the array
			if (array_key_exists($property, $result)) {
				$value = $result[$property];
				if (empty($value)) {
					continue;
				}
			} elseif (array_key_exists($singularProperty, $result)) {
				$value = $result[$singularProperty];
			} else {
				throw new Exception("Property '$property' or '$singularProperty' is not present in the entity.");
			}

			// Get a mapper for the property
			$propertyObject = $property;
			try {
				$mapper = $this->getMapper($property);
				$propertyObject = $singularProperty;
			} catch (Exception $e) {
				try {
					$mapper = $this->getMapper($singularProperty);
					$propertyObject = $singularProperty;
				} catch (Exception $e) {
					// If still no mapper, throw a no mapper available error
					if ($surpressMapperError === true) {
						continue;
					}
					throw new Exception("No mapper available for property '$property'.");
				}
			}

			// Update the values
			if (is_array($value)) {
				// If the value is an array, get multiple related objects
				$result[$property] = $this->getMultipleObjects($propertyObject, $value);
			} else {
				// If the value is not an array, get a single related object
				$objectId = is_object($value) ? $value->getId() : $value;
				$result[$property] = $this->getObject($propertyObject, $objectId);
			}
		}

		// Return the extended entity as an array
		return $result;
	}

	/**
	 * Get all relations for a specific object
	 *
	 * @param string $objectType The type of object to get relations for
	 * @param string $id The id of the object to get relations for
	 * 
	 * @return array The relations for the object
	 * @throws Exception If OpenRegister service is not available
	 */
	public function getRelations(string $objectType, string $id): array
	{
		// Get the mapper first
		$mapper = $this->getMapper($objectType);

		// Get audit trails from OpenRegister
		$auditTrails = $mapper->getRelations($id);

		return $auditTrails;
	}


	/**
	 * Get all audit trails for a specific object
	 *
	 * @param string $objectType The type of object to get audit trails for
	 * @param string $id The id of the object to get audit trails for
	 * 
	 * @return array The audit trails for the object
	 */
	public function getAuditTrail(string $objectType, string $id): array
	{
		// Get the mapper first
		$mapper = $this->getMapper($objectType);

		// Get audit trails from OpenRegister
		$auditTrails = $mapper->getAuditTrail($id);

		return $auditTrails;
	}
}
