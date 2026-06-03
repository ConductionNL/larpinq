<?php
use OCP\Util;

$appId = OCA\LarpingApp\AppInfo\Application::APP_ID;
// Shared split-chunks must be registered before the entry-point so webpack's
// chunk-loading runtime finds them as initial (synchronous) dependencies.
Util::addScript($appId, $appId . '-shared-vendor');
Util::addScript($appId, $appId . '-shared-nc-vue');
Util::addScript($appId, $appId . '-settings');
Util::addStyle($appId, 'main');

?>

<div id="settings"></div>
