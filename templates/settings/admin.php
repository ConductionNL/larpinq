<?php

/**
 * SPDX-FileCopyrightText: 2024 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

use OCP\Util;

$appId = OCA\Larpinq\AppInfo\Application::APP_ID;
Util::addStyle($appId, 'nextcloud-vue');
Util::addScript($appId, $appId . '-settings');
Util::addStyle($appId, 'main');

?>

<div id="settings"></div>
