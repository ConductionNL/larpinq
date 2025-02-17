<?php

declare(strict_types=1);

/**
 * Data mapper for Player entities
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

use OCA\LarpingApp\Db\Player;
use OCP\AppFramework\Db\Entity;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * Data mapper for Player entities
 *
 * @category  Apps
 * @package   LarpingApp
 * @author    Ruben Linde <ruben@nextcloud.com>
 * @copyright 2024 Ruben Linde
 * @license   AGPL-3.0-or-later https://www.gnu.org/licenses/agpl-3.0.en.html
 * @link      https://larpingapp.com
 */
class PlayerMapper extends QBMapper
{
    /**
     * Constructor
     *
     * @param IDBConnection $db Database connection
     */
    public function __construct(IDBConnection $db)
    {
        parent::__construct($db, 'larpingapp_players', Player::class);
    }

    /**
     * Find a player by ID
     *
     * @param int $id The player ID
     * 
     * @throws \OCP\AppFramework\Db\DoesNotExistException
     * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException
     * 
     * @return Player The found player
     */
    public function find(int $id): Player
    {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('id', $qb->createNamedParameter($id)));
        return $this->findEntity($qb);
    }

    /**
     * Find all players
     * 
     * @return Player[] Array of players
     */
    public function findAll(): array
    {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName());
        
        return $this->findEntities($qb);
    }

    /**
     * Create a new player from array data
     *
     * @param  array<string,mixed> $data The player data
     * @return Player
     */
    public function createFromArray(array $data): Player
    {
        $player = new Player();
        foreach ($data as $key => $value) {
            $player->$key = $value;
        }
        return $this->insert($player);
    }

    /**
     * Update a player from array data
     *
     * @param  int                 $id   The player ID
     * @param  array<string,mixed> $data The updated player data
     * @return Player
     */
    public function updateFromArray(int $id, array $data): Player
    {
        $player = $this->find($id);
        foreach ($data as $key => $value) {
            $player->$key = $value;
        }
        return $this->update($player);
    }
}
