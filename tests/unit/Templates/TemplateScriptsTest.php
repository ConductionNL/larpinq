<?php

/**
 * The scripts the page templates load are bundles the build emits.
 *
 * @category Test
 * @package  OCA\Larpinq\Tests\Unit\Templates
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec exclude build wiring between the templates and webpack.config.js, no spec requirement
 */

declare(strict_types=1);

namespace OCA\Larpinq\Tests\Unit\Templates;

use PHPUnit\Framework\TestCase;

/**
 * Every `Util::addScript()` in a template names an entry of webpack.config.js.
 * A script with no bundle behind it logs "Could not find resource" on every
 * page view.
 */
class TemplateScriptsTest extends TestCase {

	/**
	 * The bundle names webpack.config.js emits, without the app id and `.js`.
	 *
	 * @return array<int, string>
	 */
	private function bundles(): array {
		$config = (string)file_get_contents(dirname(__DIR__, 3) . '/webpack.config.js');
		preg_match_all("/filename:\\s*appId\\s*\\+\\s*'-([a-z0-9-]+)\\.js'/", $config, $matches);

		return $matches[1];
	}//end bundles()

	/**
	 * Each template's scripts are emitted bundles.
	 *
	 * @return void
	 */
	public function testEveryTemplateScriptIsABuiltBundle(): void {
		$bundles = $this->bundles();
		$this->assertContains('main', $bundles);

		$root = dirname(__DIR__, 3) . '/templates';
		foreach (['index.php', 'settings/admin.php'] as $template) {
			$source = (string)file_get_contents($root . '/' . $template);
			preg_match_all("/Util::addScript\\(\\\$appId,\\s*\\\$appId\\s*\\.\\s*'-([a-z0-9-]+)'\\)/", $source, $matches);
			$this->assertNotEmpty($matches[1], $template . ' loads no script');
			foreach ($matches[1] as $script) {
				$this->assertContains($script, $bundles, $template . ' loads ' . $script . ', which the build does not emit');
			}
		}
	}//end testEveryTemplateScriptIsABuiltBundle()
}//end class
