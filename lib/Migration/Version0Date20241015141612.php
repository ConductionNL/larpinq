<?php

declare(strict_types=1);

/**
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
 * Database migration for LarpingApp abilities table
 *
 * @category Database
 * @package  OCA\LarpingApp\Migration
 * @author   Ruben Linde <ruben@larpingapp.com>
 * @license  AGPL-3.0-or-later
 * @link     https://larpingapp.com
 */
class Version0Date20241015141612 extends SimpleMigrationStep
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

        // Update the abilities table to add the base column
        $table = $schema->getTable('larpingapp_abilities');
        $table->addColumn(
            'base', Types::INTEGER, [
            'notnull' => true,
            'default' => 0
            ]
        );    
        $table->addColumn(
            'allowed_negative', Types::BOOLEAN, [
            'notnull' => true,
            'default' => false
            ]
        );

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
}
