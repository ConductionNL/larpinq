<?php

/**
 * Skill mapper implementation
 *
 * @category  Database
 * @package   OCA\LarpingApp\Db
 * @author    Ruben Linde <ruben@larpingapp.com>
 * @copyright 2024 Ruben Linde
 * @license   https://www.gnu.org/licenses/agpl-3.0.html GNU AGPL v3 or later
 * @link      https://larpingapp.com
 */

declare(strict_types=1);

namespace OCA\LarpingApp\Db;

use OCA\LarpingApp\Db\Skill;
use OCP\AppFramework\Db\Entity;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * Mapper class for Skill entities
 *
 * @category Database
 * @package  OCA\LarpingApp\Db
 * @author   Ruben Linde <ruben@larpingapp.com>
 * @license  https://www.gnu.org/licenses/agpl-3.0.html GNU AGPL v3 or later
 * @link     https://larpingapp.com
 */
class SkillMapper extends QBMapper
{
    /**
     * Constructor for SkillMapper
     *
     * @param IDBConnection $db Database connection
     */
    public function __construct(IDBConnection $db)
    {
        parent::__construct(db: $db, tableName: 'larpingapp_skills', entityClass: Skill::class);
    }//end __construct()

    /**
     * Find a skill by ID
     *
     * @param int $id The skill ID
     *
     * @return Skill
     *
     * @throws \OCP\AppFramework\Db\DoesNotExistException
     * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException
     */
    public function find(int $id): Skill
    {
        $qb = $this->db->getQueryBuilder();

        $qb->select('*')
            ->from('larpingapp_skills')
            ->where(
                $qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT))
            );

        return $this->findEntity(query: $qb);
    }//end find()

    /**
     * Find all skills matching the given criteria
     *
     * @param int|null   $limit            Maximum number of results
     * @param int|null   $offset           Result offset
     * @param array|null $filters          Additional filters
     * @param array|null $searchConditions Search conditions
     * @param array|null $searchParams     Search parameters
     *
     * @return array
     */
    public function findAll(
        ?int $limit=null,
        ?int $offset=null,
        ?array $filters=[],
        ?array $searchConditions=[],
        ?array $searchParams=[]
    ): array {
        $qb = $this->db->getQueryBuilder();

        $qb->select('*')
            ->from('larpingapp_skills')
            ->setMaxResults($limit)
            ->setFirstResult($offset);

        foreach ($filters as $filter => $value) {
            if ($value === 'IS NOT NULL') {
                $qb->andWhere($qb->expr()->isNotNull($filter));
            } else if ($value === 'IS NULL') {
                $qb->andWhere($qb->expr()->isNull($filter));
            } else {
                $qb->andWhere($qb->expr()->eq($filter, $qb->createNamedParameter($value)));
            }
        }

        if (empty($searchConditions) === false) {
            $qb->andWhere('('.implode(' OR ', $searchConditions).')');
            foreach ($searchParams as $param => $value) {
                $qb->setParameter($param, $value);
            }
        }

        return $this->findEntities(query: $qb);
    }//end findAll()

    /**
     * Create a new skill from array data
     *
     * @param array $object The skill data
     *
     * @return Skill
     */
    public function createFromArray(array $object): Skill
    {
        $skill = new Skill();
        $skill->hydrate(object: $object);
        return $this->insert(entity: $skill);
    }//end createFromArray()

    /**
     * Update a skill from array data
     *
     * @param int   $id     The skill ID
     * @param array $object The updated skill data
     *
     * @return Skill
     */
    public function updateFromArray(int $id, array $object): Skill
    {
        $skill = $this->find(id: $id);
        $skill->hydrate($object);

        return $this->update(entity: $skill);
    }//end updateFromArray()
}//end class
