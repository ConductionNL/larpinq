<?php

declare(strict_types=1);

/*
 * Larpinq route table.
 *
 * Built through \OCA\OpenRegister\AppHost\Routes::standard(), which appends
 * the SPA catch-all (`dashboard#catchAll` on `/{path}`) after every route
 * below. Without that catch-all the server has no handler for
 * `/apps/larpinq/<route>`, so deep links and reloads 404 before the SPA loads
 * — measured 2026-09-01, larpinq was the ONLY one of the fleet's seven
 * hash-routed apps whose sub-paths returned 404 rather than the app shell,
 * which is what blocked it from moving to history routing.
 *
 * Routes listed here are passed as `$extra`; `standard()` lets an `$extra`
 * route override a canonical one of the same name, so the existing
 * `dashboard#page`, `settings#*` and `preferences#*` entries below keep their
 * exact URLs and verbs. Domain routes are inserted BEFORE the catch-all, so
 * they keep priority over the `/{path}` fallback.
 *
 * This file references no OCA\OpenRegister symbol other than the pure array
 * builder Routes::standard(), so it is safe to require even when OpenRegister
 * is disabled.
 */

$extra = [
	// Page routes
	['name' => 'dashboard#page', 'url' => '/', 'verb' => 'GET'],
	['name' => 'characters#downloadPdf', 'url' => '/characters/{id}/download/{template}', 'verb' => 'GET'],
	['name' => 'events#downloadRunsheet', 'url' => '/events/{id}/runsheet/{template}', 'verb' => 'GET'],
	['name' => 'events#roster', 'url' => '/api/events/{id}/roster', 'verb' => 'GET'],
	['name' => 'events#recordAttendance', 'url' => '/api/events/{id}/attendance', 'verb' => 'POST'],
	['name' => 'characters#requirementReport', 'url' => '/api/characters/{id}/requirement-report', 'verb' => 'GET'],
	['name' => 'settings#index', 'url' => 'api/settings', 'verb' => 'GET'],
	['name' => 'settings#create', 'url' => 'api/settings', 'verb' => 'POST'],
	// Canonical AppHost settings write (OpenRegister\AppHost\Routes::standard()).
	// `settings#create` above stays as the legacy POST alias; both reach the
	// same SettingsController::update(). URL spelled without a leading slash
	// to match its two siblings — RouteParser ltrims it either way.
	['name' => 'settings#update', 'url' => 'api/settings', 'verb' => 'PUT'],
	['name' => 'settings#reimport', 'url' => 'api/settings/reimport', 'verb' => 'POST'],
	// First-time setup wizard (ADR-042).
	['name' => 'setup#status', 'url' => '/api/setup/status', 'verb' => 'GET'],
	['name' => 'setup#saveConfig', 'url' => '/api/setup/config', 'verb' => 'POST'],
	['name' => 'setup#runAction', 'url' => '/api/setup/action/{actionId}', 'verb' => 'POST'],
	// Generic per-user preferences (used by shared nextcloud-vue widgets, e.g. CnSupportDialog).
	['name' => 'preferences#getPreference', 'url' => '/api/preferences/{key}', 'verb' => 'GET'],
	['name' => 'preferences#setPreference', 'url' => '/api/preferences/{key}', 'verb' => 'PUT'],
];

// ⚠️ The AppHost builder is invoked through a `class_exists()` guard.
//
// Nextcloud `include`s this file for EVERY larpinq request, and PHPUnit
// includes it without booting sibling apps at all. An unguarded static call to
// a class owned by another app therefore fatals — measured: four PHPUnit
// errors reading `Class "OCA\OpenRegister\AppHost\Routes" not found` the
// moment this file started calling it. In production the same shape makes
// every route in the app 500 when openregister is absent, not just the AppHost
// ones, and larpinq does not declare `<app>openregister</app>`, so an admin can
// create exactly that configuration.
//
// `class_exists()` autoloads without fatalling when the class is unavailable.
// The fallback below reproduces `Routes::standard()`'s output locally, so
// larpinq still routes — catch-all included — without openregister.
if (class_exists('OCA\OpenRegister\AppHost\Routes') === true) {
	return \OCA\OpenRegister\AppHost\Routes::standard($extra);
}

$canonicalRoutes = [
	['name' => 'dashboard#page', 'url' => '/', 'verb' => 'GET'],
	['name' => 'settings#index', 'url' => '/api/settings', 'verb' => 'GET'],
	['name' => 'settings#create', 'url' => '/api/settings', 'verb' => 'POST'],
	['name' => 'settings#update', 'url' => '/api/settings', 'verb' => 'PUT'],
	['name' => 'settings#load', 'url' => '/api/settings/load', 'verb' => 'POST'],
	['name' => 'preferences#getPreference', 'url' => '/api/preferences/{key}', 'verb' => 'GET'],
	['name' => 'preferences#setPreference', 'url' => '/api/preferences/{key}', 'verb' => 'PUT'],
	['name' => 'metrics#index', 'url' => '/api/metrics', 'verb' => 'GET'],
	['name' => 'health#index', 'url' => '/api/health', 'verb' => 'GET'],
];

$catchAllRoute = [
	'name' => 'dashboard#catchAll',
	'url' => '/{path}',
	'verb' => 'GET',
	'requirements' => ['path' => '.+'],
	'defaults' => ['path' => ''],
];

$extraNames = [];
foreach ($extra as $extraRoute) {
	if (isset($extraRoute['name']) === true) {
		$extraNames[(string) $extraRoute['name']] = true;
	}
}

$mergedRoutes = [];
foreach ($canonicalRoutes as $canonicalRoute) {
	if (isset($extraNames[$canonicalRoute['name']]) === true) {
		continue;
	}

	$mergedRoutes[] = $canonicalRoute;
}

$mergedRoutes = array_merge($mergedRoutes, $extra);
$mergedRoutes[] = $catchAllRoute;

return ['routes' => $mergedRoutes];
