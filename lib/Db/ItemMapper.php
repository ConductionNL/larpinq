<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2024 Ruben Linde <ruben@larpingapp.com>
 * @author    Ruben Linde <ruben@larpingapp.com>
 * @license   AGPL-3.0-or-later
 */

namespace OCA\LarpingApp\Db;

use OCA\LarpingApp\Db\Item;
use OCP\AppFramework\Db\Entity;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @template-extends QBMapper<Item>
 * @package          OCA\LarpingApp\Db
 */
class ItemMapper extends QBMapper
{
    /**
     * @param IDBConnection $db Database connection
     */
    public function __construct(IDBConnection $db)
    {
        parent::__construct($db, 'larpingapp_items', Item::class);
    }

    /**
     * Find an item by ID
     *
     * @param  int $id The item ID
     * @return Item
     * @throws \OCP\AppFramework\Db\DoesNotExistException
     * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException
     */
    public function find(int $id): Item
    {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('id', $qb->createNamedParameter($id)));
        return $this->findEntity($qb);
    }

    /**
     * Find all items for a user
     *
     * @param  string $userId The user ID
     * @return Item[]
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
     * Create a new item from array data
     *
     * @param  array<string,mixed> $data The item data
     * @return Item
     */
    public function createFromArray(array $data): Item
    {
        $item = new Item();
        foreach ($data as $key => $value) {
            $item->$key = $value;
        }
        return $this->insert($item);
    }

    /**
     * Update an item from array data
     *
     * @param  int                 $id   The item ID
     * @param  array<string,mixed> $data The updated item data
     * @return Item
     */
    public function updateFromArray(int $id, array $data): Item
    {
        $item = $this->find($id);
        foreach ($data as $key => $value) {
            $item->$key = $value;
        }
        return $this->update($item);
    }
}
