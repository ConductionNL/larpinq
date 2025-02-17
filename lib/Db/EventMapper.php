<?php

declare(strict_types=1);

/**
 * Event mapper implementation
 *
 * @category  Database
 * @package   OCA\LarpingApp\Db
 * @author    Ruben Linde <ruben@larpingapp.com>
 * @copyright 2024 Ruben Linde
 * @license   https://www.gnu.org/licenses/agpl-3.0.html GNU AGPL v3 or later
 * @link      https://larpingapp.com
 *
 * @phpversion 8.2
 */

namespace OCA\LarpingApp\Db;

use OCA\LarpingApp\Db\Event;
use OCP\AppFramework\Db\Entity;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * Mapper class for Event entities
 *
 * @category Database
 * @package  OCA\LarpingApp\Db
 * @author   Ruben Linde <ruben@larpingapp.com>
 * @license  https://www.gnu.org/licenses/agpl-3.0.html GNU AGPL v3 or later
 * @link     https://larpingapp.com
 */
class EventMapper extends QBMapper
{
    /**
     * Constructor for EventMapper
     *
     * @param IDBConnection $db Database connection
     */
    public function __construct(IDBConnection $db)
    {
        parent::__construct($db, 'larpingapp_events', Event::class);
    }

    /**
     * Find an event by ID
     *
     * @param  int $id The event ID
     * @return Event
     * @throws \OCP\AppFramework\Db\DoesNotExistException
     * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException
     */
    public function find(int $id): Event
    {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('id', $qb->createNamedParameter($id)));
        
        return $this->findEntity($qb);
    }

    /**
     * Find all events matching the given criteria
     *
     * @param  int|null   $limit            Maximum number of results
     * @param  int|null   $offset           Result offset
     * @param  array|null $filters          Additional filters
     * @param  array|null $searchConditions Search conditions
     * @param  array|null $searchParams     Search parameters
     * @return Event[]
     */
    public function findAll(
        ?int $limit = null,
        ?int $offset = null,
        ?array $filters = [],
        ?array $searchConditions = [],
        ?array $searchParams = []
    ): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName());

        if ($limit !== null) {
            $qb->setMaxResults($limit);
        }
        if ($offset !== null) {
            $qb->setFirstResult($offset);
        }

        foreach ($filters as $filter => $value) {
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

        return $this->findEntities($qb);
    }

    /**
     * Create a new event from array data
     *
     * @param  array<string,mixed> $data The event data
     * @return Event
     */
    public function createFromArray(array $data): Event
    {
        $event = new Event();
        foreach ($data as $key => $value) {
            $event->$key = $value;
        }
        return $this->insert($event);
    }

    /**
     * Update an event from array data
     *
     * @param  int                 $id   The event ID
     * @param  array<string,mixed> $data The updated event data
     * @return Event
     */
    public function updateFromArray(int $id, array $data): Event
    {
        $event = $this->find($id);
        foreach ($data as $key => $value) {
            $event->$key = $value;
        }
        return $this->update($event);
    }
}
