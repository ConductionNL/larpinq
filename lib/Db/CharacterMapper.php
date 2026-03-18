<?php

/**
 * Character mapper for LarpingApp
 *
 * @category  Database
 * @package   OCA\LarpingApp\Db
 * @author    Ruben Linde <ruben@larpingapp.com>
 * @copyright 2024 Ruben Linde
 * @license   AGPL-3.0-or-later https://www.gnu.org/licenses/agpl-3.0.en.html
 * @link      https://larpingapp.com
 */

declare(strict_types=1);

namespace OCA\LarpingApp\Db;

use OCA\LarpingApp\Db\Character;
use OCP\AppFramework\Db\Entity;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * Data mapper for Character entities.
 *
 * @template-extends QBMapper<Character>
 * @package          OCA\LarpingApp\Db
 */
class CharacterMapper extends QBMapper
{
    /**
     * Constructor for CharacterMapper.
     *
     * @param IDBConnection $dbConn Database connection
     *
     * @psalm-suppress PossiblyUnusedMethod Instantiated via Nextcloud dependency injection.
     */
    public function __construct(IDBConnection $dbConn)
    {
        parent::__construct(db: $dbConn, tableName: 'larpingapp_characters', entityClass: Character::class);
    }//end __construct()

    /**
     * Find a character by ID
     *
     * @param int $id The character ID
     *
     * @return Character
     *
     * @throws \OCP\AppFramework\Db\DoesNotExistException
     * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException
     */
    public function find(int $id): Character
    {
        $queryBuilder = $this->db->getQueryBuilder();
        $queryBuilder->select('*')
            ->from($this->getTableName())
            ->where($queryBuilder->expr()->eq('id', $queryBuilder->createNamedParameter($id)));
        return $this->findEntity(query: $queryBuilder);
    }//end find()

    /**
     * Find all characters for a user
     *
     * @param string $userId The user ID
     *
     * @return Character[]
     *
     * @psalm-suppress PossiblyUnusedMethod Called dynamically via ObjectService::getMapper().
     */
    public function findAll(string $userId): array
    {
        $queryBuilder = $this->db->getQueryBuilder();
        $queryBuilder->select('*')
            ->from($this->getTableName())
            ->where($queryBuilder->expr()->eq('user_id', $queryBuilder->createNamedParameter($userId)));

        return $this->findEntities(query: $queryBuilder);
    }//end findAll()

    /**
     * Create a new character from array data
     *
     * @param array<string,mixed> $data The character data
     *
     * @return Character
     *
     * @psalm-suppress PossiblyUnusedMethod Called dynamically via ObjectService::saveObject().
     */
    public function createFromArray(array $data): Character
    {
        $character = new Character();
        /** @psalm-suppress MixedAssignment Dynamic entity property */
        foreach ($data as $key => $value) {
            $character->$key = $value;
        }

        return $this->insert(entity: $character);
    }//end createFromArray()

    /**
     * Update a character from array data
     *
     * @param int                 $id   The character ID
     * @param array<string,mixed> $data The updated character data
     *
     * @return Character
     *
     * @psalm-suppress PossiblyUnusedMethod Called dynamically via ObjectService::saveObject().
     */
    public function updateFromArray(int $id, array $data): Character
    {
        $character = $this->find($id);
        /** @psalm-suppress MixedAssignment Dynamic entity property */
        foreach ($data as $key => $value) {
            $character->$key = $value;
        }

        return $this->update(entity: $character);
    }//end updateFromArray()
}//end class
