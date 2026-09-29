<?php

/**
 * Test-only stub of OpenRegister's ObjectEntity.
 *
 * The real class (openregister/lib/Db/ObjectEntity.php, 2,300 lines) cannot load
 * in a bare unit-test process. This stub keeps the SHAPE that matters to
 * larpinq's listeners: it extends OCP\AppFramework\Db\Entity, carries `uuid`,
 * `register`, `schema` and `object` as protected properties, and declares the
 * four accessors the real class declares for them, with the real bodies (read
 * at openregister development c7f3bad7f8, lib/Db/ObjectEntity.php:1133-1192).
 * Note that the real `getObject()` puts the uuid first as `id`.
 *
 * @category Test
 * @package  OCA\OpenRegister\Db
 * @author    Ruben Linde <ruben@larpingapp.com>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link      https://larpingapp.com
 */

declare(strict_types=1);

namespace OCA\OpenRegister\Db;

use JsonSerializable;
use OCP\AppFramework\Db\Entity;

/**
 * Test-only stub of the OpenRegister object entity.
 */
class ObjectEntity extends Entity implements JsonSerializable {

	/**
	 * The object's UUID.
	 *
	 * @var string|null
	 */
	protected ?string $uuid = null;

	/**
	 * The register id.
	 *
	 * @var string|null
	 */
	protected ?string $register = null;

	/**
	 * The schema id.
	 *
	 * @var string|null
	 */
	protected ?string $schema = null;

	/**
	 * The decoded object payload.
	 *
	 * @var array<string,mixed>|null
	 */
	protected ?array $object = [];

	/**
	 * The object payload with the uuid first as `id` (real body).
	 *
	 * @return array<string,mixed> The object data.
	 */
	public function getObject(): array {
		$objectData = $this->object ?? [];

		return array_merge(['id' => $this->uuid], $objectData);
	}//end getObject()

	/**
	 * The object's UUID.
	 *
	 * @return string|null The UUID.
	 */
	public function getUuid(): ?string {
		return $this->uuid;
	}//end getUuid()

	/**
	 * The register id.
	 *
	 * @return string|null The register id.
	 */
	public function getRegister(): ?string {
		return $this->register;
	}//end getRegister()

	/**
	 * The schema id.
	 *
	 * @return string|null The schema id.
	 */
	public function getSchema(): ?string {
		return $this->schema;
	}//end getSchema()

	/**
	 * Serialise the entity.
	 *
	 * @return array<string,mixed> The serialised entity.
	 */
	public function jsonSerialize(): array {
		return $this->getObject();
	}//end jsonSerialize()
}//end class
