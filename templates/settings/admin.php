<?php
use OCP\Util;

$appId = OCA\LarpingApp\AppInfo\Application::APP_ID;
Util::addScript($appId, $appId . '-settings');
Util::addStyle($appId, 'main');

?>

<div id="settings" data-version="<?php p($_['version'] ?? ''); ?>"></div>
