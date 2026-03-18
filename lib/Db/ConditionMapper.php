<?php

/**
 * Condition mapper for LarpingApp
 *
 * @category  Database
 * @package   OCA\LarpingApp\Db
 * @author    Ruben Linde <ruben@larpingapp.com>
 * @copyright 2024 Ruben Linde
 * @license   AGPL-3.0-or-later https://www.gnu.org/licenses/agpl-3.0.en.html
 * @link      https://larpingapp.com
 */

declare(strict_types=1);

namespace OCA\LarpingApp\Db;

use OCA\LarpingApp\Db\Condition;
use OCP\AppFramework\Db\Entity;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * Data mapper for Condition entities.
 *
 * @template-extends QBMapper<Condition>
 * @package          OCA\LarpingApp\Db
 */
class ConditionMapper extends QBMapper
{
    /**
     * Constructor for ConditionMapper.
     *
     * @param IDBConnection $db Database connection
     *
     * @psalm-suppress PossiblyUnusedMethod Instantiated via Nextcloud dependency injection.
     */
    public function __construct(IDBConnection $db)
    {
        parent::__construct(db: $db, tableName: 'larpingapp_conditions', entityClass: Condition::class);
    }//end __construct()

    /**
     * Find a condition by ID
     *
     * @param int $id The condition ID
     *
     * @return Condition
     *
     * @throws \OCP\AppFramework\Db\DoesNotExistException
     * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException
     */
    public function find(int $id): Condition
    {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('id', $qb->createNamedParameter($id)));
        return $this->findEntity(query: $qb);
    }//end find()

    /**
     * Find all conditions for a user
     *
     * @param string $userId The user ID
     *
     * @return Condition[]
     *
     * @psalm-suppress PossiblyUnusedMethod Called dynamically via ObjectService::getMapper().
     */
    public function findAll(string $userId): array
    {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)));

        return $this->findEntities(query: $qb);
    }//end findAll()

    /**
     * Create a new condition from array data
     *
     * @param array<string,mixed> $data The condition data
     *
     * @return Condition
     *
     * @psalm-suppress PossiblyUnusedMethod Called dynamically via ObjectService::saveObject().
     */
    public function createFromArray(array $data): Condition
    {
        $condition = new Condition();
        /** @psalm-suppress MixedAssignment Dynamic entity property */
        foreach ($data as $key => $value) {
            $condition->$key = $value;
        }

        return $this->insert(entity: $condition);
    }//end createFromArray()

    /**
     * Update a condition from array data
     *
     * @param int                 $id   The condition ID
     * @param array<string,mixed> $data The updated condition data
     *
     * @return Condition
     *
     * @psalm-suppress PossiblyUnusedMethod Called dynamically via ObjectService::saveObject().
     */
    public function updateFromArray(int $id, array $data): Condition
    {
        $condition = $this->find(id: $id);
        /** @psalm-suppress MixedAssignment Dynamic entity property */
        foreach ($data as $key => $value) {
            $condition->$key = $value;
        }

        return $this->update(entity: $condition);
    }//end updateFromArray()
}//end class
