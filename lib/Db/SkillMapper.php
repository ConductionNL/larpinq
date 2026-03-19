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
 *
 * @template-extends QBMapper<Skill>
 *
 * @psalm-suppress MoreSpecificReturnType, LessSpecificReturnStatement QBMapper returns Entity but we know the concrete type.
 */
class SkillMapper extends QBMapper
{
    /**
     * Constructor for SkillMapper
     *
     * @param IDBConnection $dbConn Database connection
     *
     * @psalm-suppress PossiblyUnusedMethod Instantiated via Nextcloud dependency injection.
     */
    public function __construct(IDBConnection $dbConn)
    {
        parent::__construct(db: $dbConn, tableName: 'larpingapp_skills', entityClass: Skill::class);
    }//end __construct()

    /**
     * Find a skill by ID
     *
     * @param int $skillId The skill ID
     *
     * @return Skill
     *
     * @throws \OCP\AppFramework\Db\DoesNotExistException
     * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException
     *
     * @SuppressWarnings(PHPMD.ShortVariable)
     */
    public function find(int $skillId): Skill
    {
        $queryBuilder = $this->db->getQueryBuilder();

        $queryBuilder->select('*')
            ->from('larpingapp_skills')
            ->where(
                $queryBuilder->expr()->eq('id', $queryBuilder->createNamedParameter($skillId, IQueryBuilder::PARAM_INT))
            );

        return $this->findEntity(query: $queryBuilder);
    }//end find()

    /**
     * Find all skills matching the given criteria
     *
     * @param int|null                  $limit            Maximum number of results
     * @param int|null                  $offset           Result offset
     * @param array<string,mixed>|null  $filters          Additional filters
     * @param array<int,string>|null    $searchConditions Search conditions
     * @param array<string,string>|null $searchParams     Search parameters
     *
     * @return array
     *
     * @psalm-suppress PossiblyUnusedMethod Called dynamically via ObjectService::getMapper().
     * @psalm-suppress PossiblyNullArgument Offset null is handled by the query builder.
     * @psalm-suppress PossiblyNullIterator Filters/searchParams default to empty arrays.
     * @psalm-suppress RiskyTruthyFalsyComparison Search conditions checked for empty.
     *
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    public function findAll(
        ?int $limit=null,
        ?int $offset=null,
        ?array $filters=[],
        ?array $searchConditions=[],
        ?array $searchParams=[]
    ): array {
        $queryBuilder = $this->db->getQueryBuilder();

        $queryBuilder->select('*')
            ->from('larpingapp_skills')
            ->setMaxResults($limit)
            ->setFirstResult($offset ?? 0);

        if ($filters !== null) {
            // @psalm-suppress MixedAssignment Filter values from request params
            foreach ($filters as $filter => $value) {
                if ($value === 'IS NOT NULL') {
                    $queryBuilder->andWhere($queryBuilder->expr()->isNotNull($filter));
                } else if ($value === 'IS NULL') {
                    $queryBuilder->andWhere($queryBuilder->expr()->isNull($filter));
                }

                if ($value !== 'IS NOT NULL' && $value !== 'IS NULL') {
                    $queryBuilder->andWhere($queryBuilder->expr()->eq($filter, $queryBuilder->createNamedParameter($value)));
                }
            }
        }

        if ($searchConditions !== null && count($searchConditions) > 0) {
            $queryBuilder->andWhere('('.implode(' OR ', $searchConditions).')');
            if ($searchParams !== null) {
                // @psalm-suppress MixedAssignment Search params from request
                foreach ($searchParams as $param => $value) {
                    $queryBuilder->setParameter($param, $value);
                }
            }
        }

        return $this->findEntities(query: $queryBuilder);
    }//end findAll()

    /**
     * Create a new skill from array data
     *
     * @param array<string,mixed> $object The skill data
     *
     * @return Skill
     *
     * @psalm-suppress PossiblyUnusedMethod Called dynamically via ObjectService::saveObject().
     */
    public function createFromArray(array $object): Skill
    {
        $skill = new Skill();
        $skill->hydrate($object);
        return $this->insert(entity: $skill);
    }//end createFromArray()

    /**
     * Update a skill from array data
     *
     * @param int                 $skillId The skill ID
     * @param array<string,mixed> $object  The updated skill data
     *
     * @return Skill
     *
     * @psalm-suppress PossiblyUnusedMethod Called dynamically via ObjectService::saveObject().
     */
    public function updateFromArray(int $skillId, array $object): Skill
    {
        $skill = $this->find(skillId: $skillId);
        $skill->hydrate($object);

        return $this->update(entity: $skill);
    }//end updateFromArray()
}//end class
