<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\LarpingApp\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;


/**
 * Migration step for creating the larpingapp_abilities table
 */
class Version0Date20241015141612 extends SimpleMigrationStep
{

    /**
     * @param IOutput                   $output
     * @param Closure(): ISchemaWrapper $schemaClosure
     * @param array                     $options
     */
    public function preSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void
    {
    }

    /**
     * @param  IOutput                   $output
     * @param  Closure(): ISchemaWrapper $schemaClosure
     * @param  array                     $options
     * @return null|ISchemaWrapper
     */
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper
    {
        /**
         * @var ISchemaWrapper $schema
         */
        $schema = $schemaClosure();

        // Update the abilities table to add the base column
        $table = $schema->getTable('larpingapp_abilities');
        $table->addColumn('base', Types::INTEGER, ['notnull' => true, 'default' => 0]);    
        $table->addColumn('allowed_negative', Types::BOOLEAN, ['notnull' => true, 'default' => false]);

        return $schema;
    }

    /**
     * @param IOutput                   $output
     * @param Closure(): ISchemaWrapper $schemaClosure
     * @param array                     $options
     */
    public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void
    {
    }
}
