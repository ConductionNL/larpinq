<?php

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

declare(strict_types=1);

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
 *
 * @template-extends QBMapper<Item>
 *
 * @psalm-suppress MoreSpecificReturnType, LessSpecificReturnStatement QBMapper returns Entity but we know the concrete type.
 */
class ItemMapper extends QBMapper
{
    /**
     * Constructor
     *
     * @param IDBConnection $dbConn Database connection
     *
     * @psalm-suppress PossiblyUnusedMethod Instantiated via Nextcloud dependency injection.
     */
    public function __construct(IDBConnection $dbConn)
    {
        parent::__construct(db: $dbConn, tableName: 'larpingapp_items', entityClass: Item::class);
    }//end __construct()

    /**
     * Find an item by ID
     *
     * @param int $itemId The item ID
     *
     * @throws \OCP\AppFramework\Db\DoesNotExistException
     * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException
     *
     * @return Item The found item
     *
     * @SuppressWarnings(PHPMD.ShortVariable)
     */
    public function find(int $itemId): Item
    {
        $queryBuilder = $this->db->getQueryBuilder();
        $queryBuilder->select('*')
            ->from($this->getTableName())
            ->where($queryBuilder->expr()->eq('id', $queryBuilder->createNamedParameter($itemId)));
        return $this->findEntity(query: $queryBuilder);
    }//end find()

    /**
     * Find all items
     *
     * @return Item[] Array of items
     *
     * @psalm-suppress PossiblyUnusedMethod Called dynamically via ObjectService::getMapper().
     */
    public function findAll(): array
    {
        $queryBuilder = $this->db->getQueryBuilder();
        $queryBuilder->select('*')
            ->from($this->getTableName());

        return $this->findEntities(query: $queryBuilder);
    }//end findAll()

    /**
     * Create a new item from array data
     *
     * @param array<string,mixed> $data The item data
     *
     * @return Item
     *
     * @psalm-suppress PossiblyUnusedMethod Called dynamically via ObjectService::saveObject().
     */
    public function createFromArray(array $data): Item
    {
        $item = new Item();
        /** @psalm-suppress MixedAssignment Dynamic entity property */
        foreach ($data as $key => $value) {
            $item->$key = $value;
        }

        return $this->insert(entity: $item);
    }//end createFromArray()

    /**
     * Update an item from array data
     *
     * @param int                 $itemId The item ID
     * @param array<string,mixed> $data   The updated item data
     *
     * @return Item
     *
     * @psalm-suppress PossiblyUnusedMethod Called dynamically via ObjectService::saveObject().
     */
    public function updateFromArray(int $itemId, array $data): Item
    {
        $item = $this->find($itemId);
        /** @psalm-suppress MixedAssignment Dynamic entity property */
        foreach ($data as $key => $value) {
            $item->$key = $value;
        }

        return $this->update(entity: $item);
    }//end updateFromArray()
}//end class
