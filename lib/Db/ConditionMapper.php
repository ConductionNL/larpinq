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
     * @param IDBConnection $dbConn Database connection
     *
     * @psalm-suppress PossiblyUnusedMethod Instantiated via Nextcloud dependency injection.
     */
    public function __construct(IDBConnection $dbConn)
    {
        parent::__construct(db: $dbConn, tableName: 'larpingapp_conditions', entityClass: Condition::class);
    }//end __construct()

    /**
     * Find a condition by ID
     *
     * @param int $conditionId The condition ID
     *
     * @return Condition
     *
     * @throws \OCP\AppFramework\Db\DoesNotExistException
     * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException
     *
     * @SuppressWarnings(PHPMD.ShortVariable)
     */
    public function find(int $conditionId): Condition
    {
        $queryBuilder = $this->db->getQueryBuilder();
        $queryBuilder->select('*')
            ->from($this->getTableName())
            ->where($queryBuilder->expr()->eq('id', $queryBuilder->createNamedParameter($conditionId)));
        return $this->findEntity(query: $queryBuilder);
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
        $queryBuilder = $this->db->getQueryBuilder();
        $queryBuilder->select('*')
            ->from($this->getTableName())
            ->where($queryBuilder->expr()->eq('user_id', $queryBuilder->createNamedParameter($userId)));

        return $this->findEntities(query: $queryBuilder);
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
     * @param int                 $conditionId The condition ID
     * @param array<string,mixed> $data        The updated condition data
     *
     * @return Condition
     *
     * @psalm-suppress PossiblyUnusedMethod Called dynamically via ObjectService::saveObject().
     */
    public function updateFromArray(int $conditionId, array $data): Condition
    {
        $condition = $this->find($conditionId);
        /** @psalm-suppress MixedAssignment Dynamic entity property */
        foreach ($data as $key => $value) {
            $condition->$key = $value;
        }

        return $this->update(entity: $condition);
    }//end updateFromArray()
}//end class
