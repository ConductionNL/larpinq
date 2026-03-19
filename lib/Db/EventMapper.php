<?php

/**
 * Event mapper implementation
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
 *
 * @template-extends QBMapper<Event>
 */
class EventMapper extends QBMapper
{
    /**
     * Constructor for EventMapper
     *
     * @param IDBConnection $dbConn Database connection
     *
     * @psalm-suppress PossiblyUnusedMethod Instantiated via Nextcloud dependency injection.
     */
    public function __construct(IDBConnection $dbConn)
    {
        parent::__construct(db: $dbConn, tableName: 'larpingapp_events', entityClass: Event::class);
    }//end __construct()

    /**
     * Find an event by ID
     *
     * @param int $eventId The event ID
     *
     * @return Event
     *
     * @throws \OCP\AppFramework\Db\DoesNotExistException
     * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException
     *
     * @SuppressWarnings(PHPMD.ShortVariable)
     */
    public function find(int $eventId): Event
    {
        $queryBuilder = $this->db->getQueryBuilder();
        $queryBuilder->select('*')
            ->from($this->getTableName())
            ->where($queryBuilder->expr()->eq('id', $queryBuilder->createNamedParameter($eventId)));

        // @var Event
        return $this->findEntity(query: $queryBuilder);
    }//end find()

    /**
     * Find all events matching the given criteria
     *
     * @param int|null                  $limit            Maximum number of results
     * @param int|null                  $offset           Result offset
     * @param array<string,mixed>|null  $filters          Additional filters
     * @param array<int,string>|null    $searchConditions Search conditions
     * @param array<string,string>|null $searchParams     Search parameters
     *
     * @return Event[]
     *
     * @psalm-suppress PossiblyUnusedMethod Called dynamically via ObjectService::getMapper().
     *
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
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
            ->from($this->getTableName());

        if ($limit !== null) {
            $queryBuilder->setMaxResults($limit);
        }

        if ($offset !== null) {
            $queryBuilder->setFirstResult($offset);
        }

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

        if ($searchConditions !== null && empty($searchConditions) === false) {
            $queryBuilder->andWhere('('.implode(' OR ', $searchConditions).')');
            if ($searchParams !== null) {
                // @psalm-suppress MixedAssignment Search params from request
                foreach ($searchParams as $param => $value) {
                    $queryBuilder->setParameter($param, $value);
                }
            }
        }

        // @var Event[]
        return $this->findEntities(query: $queryBuilder);
    }//end findAll()

    /**
     * Create a new event from array data
     *
     * @param array<string,mixed> $data The event data
     *
     * @return Event
     *
     * @psalm-suppress PossiblyUnusedMethod Called dynamically via ObjectService::saveObject().
     */
    public function createFromArray(array $data): Event
    {
        $event = new Event();
        // @psalm-suppress MixedAssignment Dynamic entity property
        foreach ($data as $key => $value) {
            $event->$key = $value;
        }

        // @var Event
        return $this->insert(entity: $event);
    }//end createFromArray()

    /**
     * Update an event from array data
     *
     * @param int                 $eventId The event ID
     * @param array<string,mixed> $data    The updated event data
     *
     * @return Event
     *
     * @psalm-suppress PossiblyUnusedMethod Called dynamically via ObjectService::saveObject().
     */
    public function updateFromArray(int $eventId, array $data): Event
    {
        $event = $this->find(eventId: $eventId);
        // @psalm-suppress MixedAssignment Dynamic entity property
        foreach ($data as $key => $value) {
            $event->$key = $value;
        }

        // @var Event
        return $this->update(entity: $event);
    }//end updateFromArray()
}//end class
