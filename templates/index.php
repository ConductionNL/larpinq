<?php

use OCP\Util;

$appId = OCA\LarpingApp\AppInfo\Application::APP_ID;
Util::addScript($appId, $appId . '-main');
Util::addStyle($appId, 'main');
?>


<div id="larpingapp"></div>