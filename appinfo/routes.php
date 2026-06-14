<?php

return [
	'routes' => [
		// Page routes
		['name' => 'dashboard#page', 'url' => '/', 'verb' => 'GET'],
		['name' => 'characters#downloadPdf', 'url' => '/characters/{id}/download/{template}', 'verb' => 'GET'],
		['name' => 'characters#requirementReport', 'url' => '/api/characters/{id}/requirement-report', 'verb' => 'GET'],
		['name' => 'settings#index', 'url' => 'api/settings', 'verb' => 'GET'],
		['name' => 'settings#create', 'url' => 'api/settings', 'verb' => 'POST'],
		['name' => 'settings#reimport', 'url' => 'api/settings/reimport', 'verb' => 'POST'],
		// Generic per-user preferences (used by shared nextcloud-vue widgets, e.g. CnSupportDialog).
		['name' => 'preferences#getPreference', 'url' => '/api/preferences/{key}', 'verb' => 'GET'],
		['name' => 'preferences#setPreference', 'url' => '/api/preferences/{key}', 'verb' => 'PUT'],
	],
];
