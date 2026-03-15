<?php

return [
	'routes' => [
		// Page route — serves the SPA for the root URL
		['name' => 'dashboard#page', 'url' => '/', 'verb' => 'GET'],
		['name' => 'characters#downloadPdf', 'url' => '/characters/{id}/download/{template}', 'verb' => 'GET'],
		['name' => 'settings#index', 'url' => 'api/settings', 'verb' => 'GET'],
		['name' => 'settings#create', 'url' => 'api/settings', 'verb' => 'POST'],
		// Object API routes
		['name' => 'objects#index', 'url' => 'api/objects/{objectType}', 'verb' => 'GET'],
		['name' => 'objects#create', 'url' => 'api/objects/{objectType}', 'verb' => 'POST'],
		['name' => 'objects#show', 'url' => 'api/objects/{objectType}/{id}', 'verb' => 'GET'],
		['name' => 'objects#update', 'url' => 'api/objects/{objectType}/{id}', 'verb' => 'PUT'],
		['name' => 'objects#destroy', 'url' => 'api/objects/{objectType}/{id}', 'verb' => 'DELETE'],
		['name' => 'objects#lock', 'url' => 'api/objects/{objectType}/{id}/lock', 'verb' => 'POST'],
		['name' => 'objects#unlock', 'url' => 'api/objects/{objectType}/{id}/unlock', 'verb' => 'POST'],
		['name' => 'objects#revert', 'url' => 'api/objects/{objectType}/{id}/revert', 'verb' => 'POST'],
		['name' => 'objects#getAuditTrail', 'url' => 'api/objects/{objectType}/{id}/audit', 'verb' => 'GET'],
		['name' => 'objects#getRelations', 'url' => 'api/objects/{objectType}/{id}/relations', 'verb' => 'GET'],
		['name' => 'objects#getUses', 'url' => 'api/objects/{objectType}/{id}/uses', 'verb' => 'GET'],
		['name' => 'objects#getFiles', 'url' => 'api/objects/{objectType}/{id}/files', 'verb' => 'GET'],
		// SPA catch-all — serves the Vue app for any frontend route (history mode routing)
		['name' => 'dashboard#page', 'url' => '/{path}', 'verb' => 'GET', 'requirements' => ['path' => '.+'], 'defaults' => ['path' => '']],
	],
];
