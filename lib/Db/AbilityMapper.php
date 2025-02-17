<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2024 Ruben Linde <ruben@larpingapp.com>
 * @author    Ruben Linde <ruben@larpingapp.com>
 * @license   AGPL-3.0-or-later
 */

namespace OCA\LarpingApp\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @template-extends QBMapper<Ability>
 * @package          OCA\LarpingApp\Db
 */
class AbilityMapper extends QBMapper
{
    /**
     * @param IDBConnection $db Database connection
     */
    public function __construct(IDBConnection $db)
    {
        parent::__construct($db, 'larpingapp_abilities', Ability::class);
    }

    /**
     * Find an ability by ID
     *
     * @param  int $id The ability ID
     * @return Ability
     * @throws \OCP\AppFramework\Db\DoesNotExistException
     * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException
     */
    public function find(int $id): Ability
    {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('id', $qb->createNamedParameter($id)));
        return $this->findEntity($qb);
    }

    /**
     * Find all abilities for a user
     *
     * @param  string $userId The user ID
     * @return Ability[]
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
     * Create a new ability from array data
     *
     * @param  array<string,mixed> $data The ability data
     * @return Ability
     */
    public function createFromArray(array $data): Ability
    {
        $ability = new Ability();
        foreach ($data as $key => $value) {
            $ability->$key = $value;
        }
        return $this->insert($ability);
    }

    /**
     * Update an ability from array data
     *
     * @param  int                 $id   The ability ID
     * @param  array<string,mixed> $data The updated ability data
     * @return Ability
     */
    public function updateFromArray(int $id, array $data): Ability
    {
        $ability = $this->find($id);
        foreach ($data as $key => $value) {
            $ability->$key = $value;
        }
        return $this->update($ability);
    }
}
