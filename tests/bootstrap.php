<?php

/**
 * Bootstrap file for PHPUnit unit tests.
 *
 * @category Test
 * @package  OCA\LarpingApp\Tests
 *
 * @author    Ruben Linde <ruben@larpingapp.com>
 * @copyright 2024 Ruben Linde
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @version GIT: <git-id>
 *
 * @link https://larpingapp.com
 */

declare(strict_types=1);

// Define that we're running PHPUnit.
define('PHPUNIT_RUN', 1);

// Include Composer's autoloader.
require_once __DIR__ . '/../vendor/autoload.php';

// Include the Nextcloud 3rdparty autoloader so that Symfony and other NC
// runtime dependencies (e.g. Symfony\Component\HttpFoundation\HeaderUtils)
// are available during unit tests executed inside the container.
// This file only exists in a full Nextcloud deployment; it is silently skipped
// in the bare php:8.3-cli CI environment.
$nc3rdpartyAutoload = __DIR__ . '/../../../3rdparty/autoload.php';
if (file_exists($nc3rdpartyAutoload) === true) {
    require_once $nc3rdpartyAutoload;
}

// Register OCP/NCU classes from nextcloud/ocp package.
// nextcloud/ocp has no autoload section in its composer.json, so we register it manually.
//
// In CI (bare php:8.3-cli, no Nextcloud installed) the vendor/nextcloud/ocp/OCP
// directory is a broken symlink pointing to /var/www/html/lib/public.  Fall back
// to the OCP.bak copy that ships alongside the symlink in the package so that OCP
// stubs are always available regardless of environment.
spl_autoload_register(function (string $class): void {
    $ocpBase = __DIR__ . '/../vendor/nextcloud/ocp';

    // Prefer the real symlink target when Nextcloud is mounted; fall back to the
    // bundled OCP.bak stubs when the symlink is broken (bare CI container).
    $ocpDir = is_dir($ocpBase . '/OCP') ? $ocpBase . '/OCP' : $ocpBase . '/OCP.bak';
    $ncuDir = $ocpBase . '/NCU';

    $prefixMap = [
        'OCP\\' => $ocpDir . '/',
        'NCU\\' => $ncuDir . '/',
    ];

    foreach ($prefixMap as $prefix => $dir) {
        if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
            continue;
        }

        $relative = str_replace(search: '\\', replace: '/', subject: substr($class, strlen($prefix)));
        $file     = $dir . $relative . '.php';
        if (file_exists($file) === true) {
            require_once $file;
        }

        break;
    }//end foreach

});
