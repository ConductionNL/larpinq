<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2024 Ruben Linde <ruben@larpingapp.com>
 * @author    Ruben Linde <ruben@larpingapp.com>
 * @license   AGPL-3.0-or-later
 */

namespace OCA\LarpingApp\Db;

use OCA\LarpingApp\Db\Player;
use OCP\AppFramework\Db\Entity;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @template-extends QBMapper<Player>
 * @package          OCA\LarpingApp\Db
 */
class PlayerMapper extends QBMapper
{
    /**
     * @param IDBConnection $db Database connection
     */
    public function __construct(IDBConnection $db)
    {
        parent::__construct($db, 'larpingapp_players', Player::class);
    }

    /**
     * Find a player by ID
     *
     * @param  int $id The player ID
     * @return Player
     * @throws \OCP\AppFramework\Db\DoesNotExistException
     * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException
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
     * Find all players for a user
     *
     * @param  string $userId The user ID
     * @return Player[]
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
