<?php

/**
 * Effect mapper for LarpingApp
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

use OCA\LarpingApp\Db\Effect;
use OCP\AppFramework\Db\Entity;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * Data mapper for Effect entities.
 *
 * @template-extends QBMapper<Effect>
 * @package          OCA\LarpingApp\Db
 */
class EffectMapper extends QBMapper
{
    /**
     * Constructor for EffectMapper.
     *
     * @param IDBConnection $db Database connection
     */
    public function __construct(IDBConnection $db)
    {
        parent::__construct($db, 'larpingapp_effects', Effect::class);
    }//end __construct()

    /**
     * Find an effect by ID
     *
     * @param int $id The effect ID
     *
     * @return Effect
     *
     * @throws \OCP\AppFramework\Db\DoesNotExistException
     * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException
     */
    public function find(int $id): Effect
    {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('id', $qb->createNamedParameter($id)));
        return $this->findEntity($qb);
    }//end find()

    /**
     * Find all effects for a user
     *
     * @param string $userId The user ID
     *
     * @return Effect[]
     */
    public function findAll(string $userId): array
    {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)));

        return $this->findEntities($qb);
    }//end findAll()

    /**
     * Create a new effect from array data
     *
     * @param array<string,mixed> $data The effect data
     *
     * @return Effect
     */
    public function createFromArray(array $data): Effect
    {
        $effect = new Effect();
        foreach ($data as $key => $value) {
            $effect->$key = $value;
        }

        return $this->insert($effect);
    }//end createFromArray()

    /**
     * Update an effect from array data
     *
     * @param int                 $id   The effect ID
     * @param array<string,mixed> $data The updated effect data
     *
     * @return Effect
     */
    public function updateFromArray(int $id, array $data): Effect
    {
        $effect = $this->find($id);
        foreach ($data as $key => $value) {
            $effect->$key = $value;
        }

        return $this->update($effect);
    }//end updateFromArray()
}//end class
