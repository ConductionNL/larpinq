<?php

namespace OCA\LarpingApp\Db;

use OCA\LarpingApp\Db\Skill;
use OCP\AppFramework\Db\Entity;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

class SkillMapper extends QBMapper
{
	public function __construct(IDBConnection $db)
	{
		parent::__construct($db, 'larpingapp_skills');
	}

	public function find(int $id): Skill
	{
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from('larpingapp_skills')
			->where(
				$qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT))
			);

		return $this->findEntity(query: $qb);
	}

	public function findAll(?int $limit = null, ?int $offset = null, ?array $filters = [], ?array $searchConditions = [], ?array $searchParams = []): array
	{
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from('larpingapp_skills')
			->setMaxResults($limit)
			->setFirstResult($offset);

        foreach($filters as $filter => $value) {
			if ($value === 'IS NOT NULL') {
				$qb->andWhere($qb->expr()->isNotNull($filter));
			} elseif ($value === 'IS NULL') {
				$qb->andWhere($qb->expr()->isNull($filter));
			} else {
				$qb->andWhere($qb->expr()->eq($filter, $qb->createNamedParameter($value)));
			}
        }

        if (!empty($searchConditions)) {
            $qb->andWhere('(' . implode(' OR ', $searchConditions) . ')');
            foreach ($searchParams as $param => $value) {
                $qb->setParameter($param, $value);
            }
        }

		return $this->findEntities(query: $qb);
	}

	public function createFromArray(array $object): Skill
	{
		$skill = new Skill();
		$skill->hydrate(object: $object);
		return $this->insert(entity: $skill);
	}

	public function updateFromArray(int $id, array $object): Skill
	{
		$skill = $this->find($id);
		$skill->hydrate($object);

		return $this->update($skill);
	}
}
