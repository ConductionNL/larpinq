<?php

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

declare(strict_types=1);

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
 *
 * @template-extends QBMapper<Player>
 *
 * @psalm-suppress MoreSpecificReturnType, LessSpecificReturnStatement QBMapper returns Entity but we know the concrete type.
 */
class PlayerMapper extends QBMapper
{
    /**
     * Constructor
     *
     * @param IDBConnection $db Database connection
     *
     * @psalm-suppress PossiblyUnusedMethod Instantiated via Nextcloud dependency injection.
     */
    public function __construct(IDBConnection $db)
    {
        parent::__construct(db: $db, tableName: 'larpingapp_players', entityClass: Player::class);
    }//end __construct()

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
        return $this->findEntity(query: $qb);
    }//end find()

    /**
     * Find all players
     *
     * @return Player[] Array of players
     *
     * @psalm-suppress PossiblyUnusedMethod Called dynamically via ObjectService::getMapper().
     */
    public function findAll(): array
    {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName());

        return $this->findEntities(query: $qb);
    }//end findAll()

    /**
     * Create a new player from array data
     *
     * @param array<string,mixed> $data The player data
     *
     * @return Player
     *
     * @psalm-suppress PossiblyUnusedMethod Called dynamically via ObjectService::saveObject().
     */
    public function createFromArray(array $data): Player
    {
        $player = new Player();
        foreach ($data as $key => $value) {
            /** @psalm-suppress MixedAssignment Dynamic entity property */
            $player->$key = $value;
        }

        return $this->insert(entity: $player);
    }//end createFromArray()

    /**
     * Update a player from array data
     *
     * @param int                 $id   The player ID
     * @param array<string,mixed> $data The updated player data
     *
     * @return Player
     *
     * @psalm-suppress PossiblyUnusedMethod Called dynamically via ObjectService::saveObject().
     */
    public function updateFromArray(int $id, array $data): Player
    {
        $player = $this->find(id: $id);
        foreach ($data as $key => $value) {
            /** @psalm-suppress MixedAssignment Dynamic entity property */
            $player->$key = $value;
        }

        return $this->update(entity: $player);
    }//end updateFromArray()
}//end class
