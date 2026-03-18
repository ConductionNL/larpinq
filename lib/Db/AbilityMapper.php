<?php

/**
 * Ability mapper for LarpingApp
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

use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * Data mapper for Ability entities.
 *
 * @template-extends QBMapper<Ability>
 * @package          OCA\LarpingApp\Db
 */
class AbilityMapper extends QBMapper
{
    /**
     * Constructor for AbilityMapper.
     *
     * @param IDBConnection $dbConn Database connection
     *
     * @psalm-suppress PossiblyUnusedMethod Instantiated via Nextcloud dependency injection.
     */
    public function __construct(IDBConnection $dbConn)
    {
        parent::__construct(db: $dbConn, tableName: 'larpingapp_abilities', entityClass: Ability::class);
    }//end __construct()

    /**
     * Find an ability by ID
     *
     * @param int $abilityId The ability ID
     *
     * @return Ability
     *
     * @throws \OCP\AppFramework\Db\DoesNotExistException
     * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException
     *
     * @SuppressWarnings(PHPMD.ShortVariable)
     */
    public function find(int $abilityId): Ability
    {
        $queryBuilder = $this->db->getQueryBuilder();
        $queryBuilder->select('*')
            ->from($this->getTableName())
            ->where($queryBuilder->expr()->eq('id', $queryBuilder->createNamedParameter($abilityId)));
        return $this->findEntity(query: $queryBuilder);
    }//end find()

    /**
     * Find all abilities for a user
     *
     * @param string $userId The user ID
     *
     * @return Ability[]
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
     * Create a new ability from array data
     *
     * @param array<string,mixed> $data The ability data
     *
     * @return Ability
     *
     * @psalm-suppress PossiblyUnusedMethod Called dynamically via ObjectService::saveObject().
     */
    public function createFromArray(array $data): Ability
    {
        $ability = new Ability();
        /** @psalm-suppress MixedAssignment Dynamic entity property */
        foreach ($data as $key => $value) {
            $ability->$key = $value;
        }

        return $this->insert(entity: $ability);
    }//end createFromArray()

    /**
     * Update an ability from array data
     *
     * @param int                 $abilityId The ability ID
     * @param array<string,mixed> $data      The updated ability data
     *
     * @return Ability
     *
     * @psalm-suppress PossiblyUnusedMethod Called dynamically via ObjectService::saveObject().
     */
    public function updateFromArray(int $abilityId, array $data): Ability
    {
        $ability = $this->find($abilityId);
        /** @psalm-suppress MixedAssignment Dynamic entity property */
        foreach ($data as $key => $value) {
            $ability->$key = $value;
        }

        return $this->update(entity: $ability);
    }//end updateFromArray()
}//end class
