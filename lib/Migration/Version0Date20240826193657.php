<?php

declare(strict_types=1);

/**
 * LarpingApp database migration
 *
 * @copyright Copyright (c) 2024 Ruben Linde <ruben@larpingapp.com>
 * @author    Ruben Linde <ruben@larpingapp.com>
 * @license   AGPL-3.0-or-later
 *
 * @category Database
 * @package  OCA\LarpingApp\Migration
 * @link     https://larpingapp.com
 */

namespace OCA\LarpingApp\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Database migration for LarpingApp initial tables
 *
 * @category Database
 * @package  OCA\LarpingApp\Migration
 * @author   Ruben Linde <ruben@larpingapp.com>
 * @license  AGPL-3.0-or-later
 * @link     https://larpingapp.com
 */
class Version0Date20240826193657 extends SimpleMigrationStep
{
    /**
     * Pre-schema change operations
     *
     * @param  IOutput                   $output        Migration output
     * @param  Closure(): ISchemaWrapper $schemaClosure Schema closure
     * @param  array                     $options       Migration options
     * @return void
     */
    public function preSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void
    {
    }

    /**
     * Schema change operations
     *
     * @param  IOutput                   $output        Migration output
     * @param  Closure(): ISchemaWrapper $schemaClosure Schema closure
     * @param  array                     $options       Migration options
     * @return ISchemaWrapper|null
     */
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper
    {
        /**
 * @var ISchemaWrapper $schema 
*/
        $schema = $schemaClosure();

        // Create tables
        $this->createAbilitiesTable($schema);
        $this->createConditionsTable($schema);
        $this->createEffectsTable($schema);
        $this->createEventsTable($schema);
        $this->createItemsTable($schema);
        $this->createPlayersTable($schema);
        $this->createSettingsTable($schema);
        $this->createSkillsTable($schema);
        $this->createTemplatesTable($schema);

        return $schema;
    }

    /**
     * Post-schema change operations
     *
     * @param  IOutput                   $output        Migration output
     * @param  Closure(): ISchemaWrapper $schemaClosure Schema closure
     * @param  array                     $options       Migration options
     * @return void
     */
    public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void
    {
    }

    // ... rest of the file with table creation methods
}
