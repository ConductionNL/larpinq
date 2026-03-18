<?php
/**
 * LarpingApp database migration (legacy — no-op).
 *
 * LarpingApp is a thin-client app that stores all data in OpenRegister.
 * This migration previously altered local database tables that are no
 * longer needed. It is kept as a no-op to avoid migration version conflicts.
 *
 * @category  Database
 * @package   OCA\LarpingApp\Migration
 * @author    Ruben Linde <ruben@larpingapp.com>
 * @copyright 2024 Ruben Linde
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link      https://larpingapp.com
 */

declare(strict_types=1);

namespace OCA\LarpingApp\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Legacy migration step — no-op.
 *
 * All data is now stored in OpenRegister. Local tables are no longer altered.
 *
 * @psalm-suppress UnusedClass Loaded by the Nextcloud migration framework.
 */
class Version0Date20241015141612 extends SimpleMigrationStep
{
    /**
     * No schema changes needed.
     *
     * @param IOutput                   $output        Migration output
     * @param Closure(): ISchemaWrapper $schemaClosure Schema closure
     * @param array                     $options       Migration options
     *
     * @return ISchemaWrapper|null
     */
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper
    {
        return null;
    }//end changeSchema()
}//end class
