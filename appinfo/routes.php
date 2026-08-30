<?php

return [
	'routes' => [
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
	],
];
