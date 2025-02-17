<?php

declare(strict_types=1);

/**
 * Data mapper for Item entities
 *
 * @category  Apps
 * @package   LarpingApp
 * @author    Ruben Linde <ruben@nextcloud.com>
 * @copyright 2024 Ruben Linde
 * @license   AGPL-3.0-or-later https://www.gnu.org/licenses/agpl-3.0.en.html
 *
 * @php-version 8.2
 */

namespace OCA\LarpingApp\Db;

use OCA\LarpingApp\Db\Item;
use OCP\AppFramework\Db\Entity;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * Data mapper for Item entities
 *
 * @category  Apps
 * @package   LarpingApp
 * @author    Ruben Linde <ruben@nextcloud.com>
 * @copyright 2024 Ruben Linde
 * @license   AGPL-3.0-or-later https://www.gnu.org/licenses/agpl-3.0.en.html
 * @link      https://larpingapp.com
 */
class ItemMapper extends QBMapper
{
    /**
     * Constructor
     *
     * @param IDBConnection $db Database connection
     */
    public function __construct(IDBConnection $db)
    {
        parent::__construct($db, 'larpingapp_items', Item::class);
    }

    /**
     * Find an item by ID
     *
     * @param int $id The item ID
     * 
     * @throws \OCP\AppFramework\Db\DoesNotExistException
     * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException
     * 
     * @return Item The found item
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
     * Find all items
     * 
     * @return Item[] Array of items
     */
    public function findAll(): array
    {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName());
        
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
