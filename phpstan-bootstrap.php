<?php

/**
 * PHPStan bootstrap file - registers OCP autoloader for static analysis.
 *
 * vendor/nextcloud/ocp/OCP is a symlink pointing to /var/www/html/lib/public
 * which only resolves inside the Nextcloud container.  In bare CI environments
 * or on a local host where /var/www/html does not exist, fall back to the
 * bundled OCP.bak copy that ships alongside the symlink.
 */

$autoloader = require __DIR__ . '/vendor/autoload.php';

$ocpBase = __DIR__ . '/vendor/nextcloud/ocp';
$ocpDir  = is_dir($ocpBase . '/OCP') ? $ocpBase . '/OCP' : $ocpBase . '/OCP.bak';

$autoloader->addPsr4('OCP\\', $ocpDir . '/');
$autoloader->addPsr4('NCU\\', $ocpBase . '/NCU/');
