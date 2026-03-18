<?php

/**
 * Setting mapper for LarpingApp
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

use OCA\LarpingApp\Db\Setting;
use OCP\AppFramework\Db\Entity;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * Data mapper for Setting entities.
 *
 * @template-extends QBMapper<Setting>
 * @package          OCA\LarpingApp\Db
 */
class SettingMapper extends QBMapper
{
    /**
     * Constructor for SettingMapper.
     *
     * @param IDBConnection $dbConn Database connection
     *
     * @psalm-suppress PossiblyUnusedMethod Instantiated via Nextcloud dependency injection.
     */
    public function __construct(IDBConnection $dbConn)
    {
        parent::__construct(db: $dbConn, tableName: 'larpingapp_settings', entityClass: Setting::class);
    }//end __construct()

    /**
     * Find a setting by ID
     *
     * @param int $settingId The setting ID
     *
     * @return Setting
     *
     * @throws \OCP\AppFramework\Db\DoesNotExistException
     * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException
     *
     * @SuppressWarnings(PHPMD.ShortVariable)
     */
    public function find(int $settingId): Setting
    {
        $queryBuilder = $this->db->getQueryBuilder();
        $queryBuilder->select('*')
            ->from($this->getTableName())
            ->where($queryBuilder->expr()->eq('id', $queryBuilder->createNamedParameter($settingId)));
        return $this->findEntity(query: $queryBuilder);
    }//end find()

    /**
     * Find all settings for a user
     *
     * @param string $userId The user ID
     *
     * @return Setting[]
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
     * Create a new setting from array data
     *
     * @param array<string,mixed> $data The setting data
     *
     * @return Setting
     *
     * @psalm-suppress PossiblyUnusedMethod Called dynamically via ObjectService::saveObject().
     */
    public function createFromArray(array $data): Setting
    {
        $setting = new Setting();
        /** @psalm-suppress MixedAssignment Dynamic entity property */
        foreach ($data as $key => $value) {
            $setting->$key = $value;
        }

        return $this->insert(entity: $setting);
    }//end createFromArray()

    /**
     * Update a setting from array data
     *
     * @param int                 $settingId The setting ID
     * @param array<string,mixed> $data      The updated setting data
     *
     * @return Setting
     *
     * @psalm-suppress PossiblyUnusedMethod Called dynamically via ObjectService::saveObject().
     */
    public function updateFromArray(int $settingId, array $data): Setting
    {
        $setting = $this->find($settingId);
        /** @psalm-suppress MixedAssignment Dynamic entity property */
        foreach ($data as $key => $value) {
            $setting->$key = $value;
        }

        return $this->update(entity: $setting);
    }//end updateFromArray()
}//end class
