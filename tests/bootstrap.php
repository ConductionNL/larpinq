<?php

/**
 * Bootstrap file for PHPUnit unit tests.
 *
 * @category Test
 * @package  OCA\Larpinq\Tests
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
$composerAutoloader = require __DIR__ . '/../vendor/autoload.php';

// Doctrine placeholders, loaded BEFORE anything can mock an OCP DB interface.
// IQueryBuilder evaluates class constants referencing Doctrine\DBAL\ParameterType
// at parse time, and IDBConnection::getQueryBuilder() returns IQueryBuilder — so
// without these, createMock(IDBConnection::class) dies with
// `Class "Doctrine\DBAL\ParameterType" not found`, raised from inside
// createMock(), which reads as a broken test rather than a missing dependency.
// Every declaration is class_exists()-guarded, so a real runtime still wins.
require_once __DIR__ . '/stubs/DoctrineStubs.php';

// Include the Nextcloud 3rdparty autoloader so that Symfony and other NC
// runtime dependencies (e.g. Symfony\Component\HttpFoundation\HeaderUtils)
// are available during unit tests. NEXTCLOUD_3RDPARTY names it explicitly;
// otherwise the first `3rdparty/autoload.php` in a parent directory of the
// app is used, which finds server/3rdparty from apps/, apps-extra/ and a
// container mount alike. Without one (the bare php:8.3-cli CI image) it is
// skipped.
$nc3rdpartyAutoload = (string)getenv('NEXTCLOUD_3RDPARTY');
if ($nc3rdpartyAutoload !== '') {
	$nc3rdpartyAutoload = rtrim($nc3rdpartyAutoload, '/') . '/autoload.php';
}

for ($dir = dirname(__DIR__); $nc3rdpartyAutoload === '' && dirname($dir) !== $dir; $dir = dirname($dir)) {
	if (is_file(dirname($dir) . '/3rdparty/autoload.php') === true) {
		$nc3rdpartyAutoload = dirname($dir) . '/3rdparty/autoload.php';
	}
}

if ($nc3rdpartyAutoload !== '' && is_file($nc3rdpartyAutoload) === true) {
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
	$ocpDir = $ocpBase . '/OCP.bak';
	if (is_dir($ocpBase . '/OCP') === true) {
		$ocpDir = $ocpBase . '/OCP';
	}
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
		$file = $dir . $relative . '.php';
		if (file_exists($file) === true) {
			require_once $file;
		}

		break;
	}//end foreach
});

// OpenRegister is a sibling Nextcloud app, not a composer dependency, so its
// classes are absent from a bare unit-test process. tests/stubs/openregister/
// carries the pre-write events verbatim and an ObjectEntity of the real shape,
// so listener tests construct the REAL event classes rather than hand-made
// doubles (learniq#984: a double with a method the real event lacks hid a 500
// on every update). The prefix is APPENDED to this process's loader only, so a
// real OpenRegister on the path always wins, and it is never in composer.json.
if ($composerAutoloader instanceof \Composer\Autoload\ClassLoader) {
	$composerAutoloader->addPsr4('OCA\\OpenRegister\\', __DIR__ . '/stubs/openregister/');
	// Portaliq's claim event, verbatim, for the self-signup claim listener
	// (players-self-signup). Same rule: appended, so a real portaliq wins.
	$composerAutoloader->addPsr4('OCA\\Portaliq\\', __DIR__ . '/stubs/portaliq/');
	// Nextcloud Forms' submit event in its real shape, for the sign-up listener
	// (registration-intake-and-capacity). Same rule: appended, so a real forms wins.
	$composerAutoloader->addPsr4('OCA\\Forms\\', __DIR__ . '/stubs/forms/');
}
