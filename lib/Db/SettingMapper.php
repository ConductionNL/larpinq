<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2024 Ruben Linde <ruben@larpingapp.com>
 * @author    Ruben Linde <ruben@larpingapp.com>
 * @license   AGPL-3.0-or-later
 */

namespace OCA\LarpingApp\Db;

use OCA\LarpingApp\Db\Setting;
use OCP\AppFramework\Db\Entity;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @template-extends QBMapper<Setting>
 * @package          OCA\LarpingApp\Db
 */
class SettingMapper extends QBMapper
{
    /**
     * @param IDBConnection $db Database connection
     */
    public function __construct(IDBConnection $db)
    {
        parent::__construct($db, 'larpingapp_settings', Setting::class);
    }

    /**
     * Find a setting by ID
     *
     * @param  int $id The setting ID
     * @return Setting
     * @throws \OCP\AppFramework\Db\DoesNotExistException
     * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException
     */
    public function find(int $id): Setting
    {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('id', $qb->createNamedParameter($id)));
        return $this->findEntity($qb);
    }

    /**
     * Find all settings for a user
     *
     * @param  string $userId The user ID
     * @return Setting[]
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
     * Create a new setting from array data
     *
     * @param  array<string,mixed> $data The setting data
     * @return Setting
     */
    public function createFromArray(array $data): Setting
    {
        $setting = new Setting();
        foreach ($data as $key => $value) {
            $setting->$key = $value;
        }
        return $this->insert($setting);
    }

    /**
     * Update a setting from array data
     *
     * @param  int                 $id   The setting ID
     * @param  array<string,mixed> $data The updated setting data
     * @return Setting
     */
    public function updateFromArray(int $id, array $data): Setting
    {
        $setting = $this->find($id);
        foreach ($data as $key => $value) {
            $setting->$key = $value;
        }
        return $this->update($setting);
    }
}
