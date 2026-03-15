<?php
/**
 * LarpingApp database migration for abilities table update
 *
 * @category  Database
 * @package   OCA\LarpingApp\Migration
 * @author    Ruben Linde <ruben@larpingapp.com>
 * @copyright 2024 Ruben Linde
 * @license   https://www.gnu.org/licenses/agpl-3.0.html GNU AGPL v3 or later
 * @link      https://larpingapp.com
 */

declare(strict_types=1);

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
     * @param IOutput                   $output        Migration output
     * @param Closure(): ISchemaWrapper $schemaClosure Schema closure
     * @param array                     $options       Migration options
     *
     * @return void
     */
    public function preSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void
    {
    }//end preSchemaChange()

    /**
     * Schema change operations
     *
     * @param IOutput                   $output        Migration output
     * @param Closure(): ISchemaWrapper $schemaClosure Schema closure
     * @param array                     $options       Migration options
     *
     * @return ISchemaWrapper|null
     */
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper
    {
        // No-op: LarpingApp now uses OpenRegister for all data storage.
        return null;
    }//end changeSchema()

    /**
     * Post-schema change operations
     *
     * @param IOutput                   $output        Migration output
     * @param Closure(): ISchemaWrapper $schemaClosure Schema closure
     * @param array                     $options       Migration options
     *
     * @return void
     */
    public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void
    {
    }//end postSchemaChange()
}//end class
