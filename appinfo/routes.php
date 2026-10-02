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
	['name' => 'characters#pdfTemplates', 'url' => '/api/pdf/templates', 'verb' => 'GET'],
	['name' => 'events#downloadRunsheet', 'url' => '/events/{id}/runsheet/{template}', 'verb' => 'GET'],
	['name' => 'events#roster', 'url' => '/api/events/{id}/roster', 'verb' => 'GET'],
	['name' => 'events#recordAttendance', 'url' => '/api/events/{id}/attendance', 'verb' => 'POST'],
	['name' => 'eventCheckin#checkinByCode', 'url' => '/api/events/{id}/checkin-code', 'verb' => 'POST'],
	['name' => 'xpAwards#access', 'url' => '/api/xp-awards/access', 'verb' => 'GET'],
	['name' => 'xpAwards#roster', 'url' => '/api/events/{id}/xp-award-roster', 'verb' => 'GET'],
	['name' => 'xpAwards#award', 'url' => '/api/events/{id}/xp-awards', 'verb' => 'POST'],
	['name' => 'characters#requirementReport', 'url' => '/api/characters/{id}/requirement-report', 'verb' => 'GET'],
	['name' => 'characterStats#show', 'url' => '/api/characters/{id}/stats', 'verb' => 'GET'],
	['name' => 'playerAttendance#index', 'url' => '/api/players/{id}/attendance', 'verb' => 'GET'],
	['name' => 'registrationChoices#offer', 'url' => '/api/registrations/{id}/offer', 'verb' => 'GET'],
	['name' => 'registrationChoices#counts', 'url' => '/api/events/{id}/choices', 'verb' => 'GET'],
	['name' => 'registrationPayments#access', 'url' => '/api/payments/access', 'verb' => 'GET'],
	['name' => 'registrationPayments#request', 'url' => '/api/registrations/{id}/payment-request', 'verb' => 'POST'],
	['name' => 'registrationChanges#changes', 'url' => '/api/registrations/{id}/changes', 'verb' => 'GET'],
	['name' => 'registrationChanges#cancel', 'url' => '/api/registrations/{id}/cancel', 'verb' => 'POST'],
	['name' => 'registrationChanges#addParticipant', 'url' => '/api/registrations/{id}/participants', 'verb' => 'POST'],
	['name' => 'registrationChanges#offerTransfer', 'url' => '/api/registrations/{id}/transfer', 'verb' => 'POST'],
	['name' => 'registrationChanges#acceptTransfer', 'url' => '/api/registrations/{id}/transfer/accept', 'verb' => 'POST'],
	['name' => 'registrationChanges#withdrawTransfer', 'url' => '/api/registrations/{id}/transfer/withdraw', 'verb' => 'POST'],
	['name' => 'characterBuilds#access', 'url' => '/api/builds/apply-access', 'verb' => 'GET'],
	['name' => 'characterBuilds#report', 'url' => '/api/builds/{id}/report', 'verb' => 'GET'],
	['name' => 'worlds#access', 'url' => '/api/worlds/copy-access', 'verb' => 'GET'],
	['name' => 'worlds#preview', 'url' => '/api/worlds/{id}/copy', 'verb' => 'GET'],
	['name' => 'worlds#copy', 'url' => '/api/worlds/{id}/copy', 'verb' => 'POST'],
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
	// ⚠️ The health and metrics routes are DELIBERATELY absent here, and their
	// absence is the point of this comment. Routes::standard() supplies both on
	// the branch above, where OpenRegister's AppHost aliases its generic
	// health/metrics controllers onto larpinq's conventional class names —
	// which is why /api/health and /api/metrics answer 200 on a normal instance
	// even though this repo ships neither controller.
	//
	// This fallback runs ONLY when OpenRegister is absent, and then nothing
	// aliases them: declaring those routes would advertise two endpoints whose
	// target classes do not exist, so a request to either would fatal rather
	// than 404. gate-14 (route-reachability) reported exactly that.
	//
	// ⚠️ And do NOT write their route slugs (`<controller>` + `#` + `<method>`)
	// into this comment. gate-14 reads this file statically and matches that
	// shape anywhere in it, comments included — spelling them out here made the
	// gate go on reporting both long after the routes themselves were gone,
	// with the finding pointing at controller files that do not exist.
];

$catchAllRoute = [
	'name' => 'dashboard#catchAll',
	'url' => '/{path}',
	'verb' => 'GET',
	// Mirrors Routes::standard()'s own requirement, lookahead included.
	// Nextcloud's RouteParser processes `routes` before `resources` and Symfony
	// matches in insertion order, and `.+` matches slashes — so a bare `.+`
	// catch-all swallows unmatched `api/...` paths and answers the SPA shell at
	// HTTP 200, handing JSON callers HTML with nothing erroring
	// (openregister#3270, zaakafhandelapp#619).
	'requirements' => ['path' => '(?!api/).+'],
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
