<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2024 Ruben Linde <ruben@larpingapp.com>
 * @author    Ruben Linde <ruben@larpingapp.com>
 * @license   AGPL-3.0-or-later
 */

namespace OCA\LarpingApp\Db;

use OCA\LarpingApp\Db\Condition;
use OCP\AppFramework\Db\Entity;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @template-extends QBMapper<Condition>
 * @package          OCA\LarpingApp\Db
 */
class ConditionMapper extends QBMapper
{
    /**
     * @param IDBConnection $db Database connection
     */
    public function __construct(IDBConnection $db)
    {
        parent::__construct($db, 'larpingapp_conditions', Condition::class);
    }

    /**
     * Find a condition by ID
     *
     * @param  int $id The condition ID
     * @return Condition
     * @throws \OCP\AppFramework\Db\DoesNotExistException
     * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException
     */
    public function find(int $id): Condition
    {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('id', $qb->createNamedParameter($id)));
        return $this->findEntity($qb);
    }

    /**
     * Find all conditions for a user
     *
     * @param  string $userId The user ID
     * @return Condition[]
     */
    public function findAll(string $userId): array
    {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)));
        
        return $this->findEntities($qb);
    }

    /**
     * Create a new condition from array data
     *
     * @param  array<string,mixed> $data The condition data
     * @return Condition
     */
    public function createFromArray(array $data): Condition
    {
        $condition = new Condition();
        foreach ($data as $key => $value) {
            $condition->$key = $value;
        }
        return $this->insert($condition);
    }

    /**
     * Update a condition from array data
     *
     * @param  int                 $id   The condition ID
     * @param  array<string,mixed> $data The updated condition data
     * @return Condition
     */
    public function updateFromArray(int $id, array $data): Condition
    {
        $condition = $this->find($id);
        foreach ($data as $key => $value) {
            $condition->$key = $value;
        }
        return $this->update($condition);
    }
}
