<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2024 Ruben Linde <ruben@larpingapp.com>
 * @author    Ruben Linde <ruben@larpingapp.com>
 * @license   AGPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 */

namespace OCA\LarpingApp\Db;

use OCA\LarpingApp\Db\Template;
use OCP\AppFramework\Db\Entity;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @template-extends QBMapper<Template>
 * @package          OCA\LarpingApp\Db
 */
class TemplateMapper extends QBMapper
{
    /**
     * @param IDBConnection $db Database connection
     */
    public function __construct(IDBConnection $db)
    {
        parent::__construct($db, 'larpingapp_templates', Template::class);
    }

    /**
     * Find a template by ID
     *
     * @param  int $id The template ID
     * @return Template
     * @throws \OCP\AppFramework\Db\DoesNotExistException
     * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException
     */
    public function find(int $id): Template
    {
        $qb = $this->db->getQueryBuilder();

        $qb->select('*')
            ->from($this->getTableName())
            ->where(
                $qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT))
            );

        return $this->findEntity(query: $qb);
    }

    public function findAll(?int $limit = null, ?int $offset = null, ?array $filters = [], ?array $searchConditions = [], ?array $searchParams = []): array
    {
        $qb = $this->db->getQueryBuilder();

        $qb->select('*')
            ->from($this->getTableName())
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

    /**
     * Create a new template from array data
     *
     * @param  array<string,mixed> $data The template data
     * @return Template
     */
    public function createFromArray(array $data): Template
    {
        $template = new Template();
        foreach ($data as $key => $value) {
            $template->$key = $value;
        }

        //        var_dump($catalog->getTitle());

        return $this->insert($template);
    }

    /**
     * Update a template from array data
     *
     * @param  int                 $id   The template ID
     * @param  array<string,mixed> $data The updated template data
     * @return Template
     */
    public function updateFromArray(int $id, array $data): Template
    {
        $template = $this->find($id);
        foreach ($data as $key => $value) {
            $template->$key = $value;
        }

        return $this->update($template);
    }
}
