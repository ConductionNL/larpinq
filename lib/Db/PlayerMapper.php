<?php

namespace OCA\LarpingApp\Db;

use OCA\LarpingApp\Db\Player;
use OCP\AppFramework\Db\Entity;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

class PlayerMapper extends QBMapper
{
    public function __construct(IDBConnection $db)
    {
        parent::__construct($db, 'larpingapp_players');
    }

    public function find(int $id): Player
    {
        $qb = $this->db->getQueryBuilder();

        $qb->select('*')
            ->from('larpingapp_players')
            ->where(
                $qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT))
            );

        return $this->findEntity(query: $qb);
    }

    public function findAll(?int $limit = null, ?int $offset = null, ?array $filters = [], ?array $searchConditions = [], ?array $searchParams = []): array
    {
        $qb = $this->db->getQueryBuilder();

        $qb->select('*')
            ->from('larpingapp_players')
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

    public function createFromArray(array $object): Player
    {
        $player = new Player();
        $player->hydrate(object: $object);
        return $this->insert(entity: $player);
    }

    public function updateFromArray(int $id, array $object): Player
    {
        $player = $this->find($id);
        $player->hydrate($object);

        return $this->update($player);
    }
}
