<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2024 Ruben Linde <ruben@larpingapp.com>
 * @author    Ruben Linde <ruben@larpingapp.com>
 * @license   AGPL-3.0-or-later
 */

namespace OCA\LarpingApp\Db;

use OCA\LarpingApp\Db\Character;
use OCP\AppFramework\Db\Entity;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @template-extends QBMapper<Character>
 * @package          OCA\LarpingApp\Db
 */
class CharacterMapper extends QBMapper
{
    /**
     * @param IDBConnection $db Database connection
     */
    public function __construct(IDBConnection $db)
    {
        parent::__construct($db, 'larpingapp_characters', Character::class);
    }

    /**
     * Find a character by ID
     *
     * @param  int $id The character ID
     * @return Character
     * @throws \OCP\AppFramework\Db\DoesNotExistException
     * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException
     */
    public function find(int $id): Character
    {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('id', $qb->createNamedParameter($id)));
        return $this->findEntity($qb);
    }

    /**
     * Find all characters for a user
     *
     * @param  string $userId The user ID
     * @return Character[]
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
     * Create a new character from array data
     *
     * @param  array<string,mixed> $data The character data
     * @return Character
     */
    public function createFromArray(array $data): Character
    {
        $character = new Character();
        foreach ($data as $key => $value) {
            $character->$key = $value;
        }
        return $this->insert($character);
    }

    /**
     * Update a character from array data
     *
     * @param  int                 $id   The character ID
     * @param  array<string,mixed> $data The updated character data
     * @return Character
     */
    public function updateFromArray(int $id, array $data): Character
    {
        $character = $this->find($id);
        foreach ($data as $key => $value) {
            $character->$key = $value;
        }
        return $this->update($character);
    }
}
