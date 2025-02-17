<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2024 Ruben Linde <ruben@larpingapp.com>
 * @author    Ruben Linde <ruben@larpingapp.com>
 * @license   AGPL-3.0-or-later
 */

namespace OCA\LarpingApp\Db;

use OCA\LarpingApp\Db\Effect;
use OCP\AppFramework\Db\Entity;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @template-extends QBMapper<Effect>
 * @package          OCA\LarpingApp\Db
 */
class EffectMapper extends QBMapper
{
    /**
     * @param IDBConnection $db Database connection
     */
    public function __construct(IDBConnection $db)
    {
        parent::__construct($db, 'larpingapp_effects', Effect::class);
    }

    /**
     * Find an effect by ID
     *
     * @param  int $id The effect ID
     * @return Effect
     * @throws \OCP\AppFramework\Db\DoesNotExistException
     * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException
     */
    public function find(int $id): Effect
    {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('id', $qb->createNamedParameter($id)));
        return $this->findEntity($qb);
    }

    /**
     * Find all effects for a user
     *
     * @param  string $userId The user ID
     * @return Effect[]
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
     * Create a new effect from array data
     *
     * @param  array<string,mixed> $data The effect data
     * @return Effect
     */
    public function createFromArray(array $data): Effect
    {
        $effect = new Effect();
        foreach ($data as $key => $value) {
            $effect->$key = $value;
        }
        return $this->insert($effect);
    }

    /**
     * Update an effect from array data
     *
     * @param  int                 $id   The effect ID
     * @param  array<string,mixed> $data The updated effect data
     * @return Effect
     */
    public function updateFromArray(int $id, array $data): Effect
    {
        $effect = $this->find($id);
        foreach ($data as $key => $value) {
            $effect->$key = $value;
        }
        return $this->update($effect);
    }
}
