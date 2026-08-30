<?php

/**
 * Docblock-annotation posture of the SettingsController write endpoints.
 *
 * @category Test
 * @package  OCA\Larpinq\Tests\Unit\Controller
 *
 * @author    Ruben Linde <ruben@larpingapp.com>
 * @copyright 2024 Ruben Linde
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @version GIT: <git-id>
 *
 * @link https://larpingapp.com
 */

declare(strict_types=1);

namespace OCA\Larpinq\Tests\Unit\Controller;

use OCA\Larpinq\Controller\SettingsController;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

/**
 * A SENTENCE THAT STARTS WITH AN ANNOTATION *IS* THE ANNOTATION.
 *
 * Nextcloud reads controller docblock annotations in
 * `OC\AppFramework\Utility\ControllerMethodReflector::reflect()` with exactly
 * one regular expression:
 *
 *     /^\h+\*\h+@(?P<annotation>[A-Z]\w+)((?P<parameter>.*))?$/m
 *
 * Everything after the tag name is captured as the annotation's *parameter*
 * and thrown away. The tag is then recorded as PRESENT. `SecurityMiddleware`
 * consults it through `hasAnnotationOrAttribute($m, 'NoCSRFRequired', ...)`,
 * whose annotation arm still fires on Nextcloud 32/33, and returns early from
 * `isValidCSRF()` — the CSRF token is never checked.
 *
 * So a docblock line reading
 *
 *     * @NoCSRFRequired removed to close the CSRF-forgery surface (closes #206).
 *
 * does not document a removal. It re-adds the annotation it claims to have
 * deleted, on a state-mutating admin POST, and reads to a human as the exact
 * opposite of what the server does. Both `create()` (POST /api/settings, an
 * instance-wide configuration write) and `reimport()` (POST
 * /api/settings/reimport) carried that line, so #206 was never closed in the
 * only place that decides it.
 *
 * This test asks the question the way Nextcloud asks it — the same regex over
 * the same docblock text — rather than grepping for the literal tag, because
 * grepping for `* @NoCSRFRequired$` is precisely the check that missed this.
 */
class SettingsControllerCsrfPostureTest extends TestCase {

	/**
	 * Nextcloud's annotation regex, copied verbatim from
	 * lib/private/AppFramework/Utility/ControllerMethodReflector.php.
	 *
	 * @var string
	 */
	private const NC_ANNOTATION_RX = '/^\h+\*\h+@(?P<annotation>[A-Z]\w+)((?P<parameter>.*))?$/m';

	/**
	 * The write endpoints that must be CSRF-protected. All three are
	 * state-mutating and admin-only; none may carry NoCSRFRequired in any form.
	 *
	 * @var string[]
	 */
	private const CSRF_PROTECTED_WRITES = ['update', 'create', 'reimport'];

	/**
	 * Parse one method's docblock the way Nextcloud parses it.
	 *
	 * @param string $method The controller method name.
	 *
	 * @return string[] Lower-cased annotation names Nextcloud would record.
	 */
	private function annotationsOf(string $method): array {
		$reflection = new ReflectionMethod(SettingsController::class, $method);
		$docs = $reflection->getDocComment();
		$this->assertIsString(
			$docs,
			sprintf('%s() has no docblock at all — nothing to judge.', $method)
		);

		$matches = [];
		preg_match_all(self::NC_ANNOTATION_RX, $docs, $matches);

		return array_map('strtolower', $matches['annotation']);
	}//end annotationsOf()

	/**
	 * POSITIVE CONTROL — the parser and the class under test are both real.
	 *
	 * Two ways this suite could pass while proving nothing: the regex could be
	 * wrong (then it finds no annotations anywhere and every assertion below
	 * is vacuous), and `OCA\Larpinq\…` could resolve to the app DEPLOYED at
	 * /var/www/html/custom_apps rather than this working tree (then the suite
	 * grades somebody else's file). Both are checked here first.
	 *
	 * @return void
	 */
	public function testTheParserAndTheClassUnderTestAreReal(): void {
		// The class under test must be THIS repository's copy.
		$file = (new ReflectionClass(SettingsController::class))->getFileName();
		$this->assertIsString($file);
		$this->assertSame(
			realpath(__DIR__ . '/../../../lib/Controller/SettingsController.php'),
			realpath($file),
			'SettingsController resolved outside this working tree — the Nextcloud '
			. 'autoloader hijacks OCA\\<App>\\* to the installed app, and this suite '
			. 'would then be grading the deployed copy instead of the code under test.'
		);

		// The regex must actually find a tag-position annotation. index() is
		// legitimately annotated, so a parser that works reports it here.
		$this->assertContains(
			'nocsrfrequired',
			$this->annotationsOf('index'),
			'The Nextcloud annotation regex found no @NoCSRFRequired on index(), '
			. 'which genuinely carries one — the parser is broken, so every '
			. 'assertion in this file would pass over anything.'
		);

	}//end testTheParserAndTheClassUnderTestAreReal()

	/**
	 * No settings write may disable CSRF, by attribute or by annotation —
	 * including an annotation hidden inside an explanatory sentence.
	 *
	 * @return void
	 */
	public function testSettingsWritesDoNotDisableCsrf(): void {
		foreach (self::CSRF_PROTECTED_WRITES as $method) {
			$annotations = $this->annotationsOf($method);

			$this->assertNotContains(
				'nocsrfrequired',
				$annotations,
				sprintf(
					'SettingsController::%s() is a state-mutating admin write, and '
					. 'Nextcloud reads a @NoCSRFRequired annotation out of its docblock '
					. '— SecurityMiddleware::isValidCSRF() therefore returns early and '
					. 'never checks the token. If the tag only appears inside a sentence '
					. 'saying it was removed, that sentence IS the tag: move the token '
					. 'off tag position (do not start the line with it).',
					$method
				)
			);

			$attributes = (new ReflectionMethod(SettingsController::class, $method))
				->getAttributes();
			$names = array_map(
				static function ($attribute) {
					return $attribute->getName();
				},
				$attributes
			);
			$this->assertNotContains(
				'OCP\AppFramework\Http\Attribute\NoCSRFRequired',
				$names,
				sprintf('SettingsController::%s() carries a #[NoCSRFRequired] attribute.', $method)
			);
		}//end foreach

	}//end testSettingsWritesDoNotDisableCsrf()

	/**
	 * The writes must also stay admin-only: Nextcloud's default for a method
	 * with no auth annotation is "admin session required", so NoAdminRequired
	 * must be absent in every form here too.
	 *
	 * @return void
	 */
	public function testSettingsWritesStayAdminOnly(): void {
		foreach (self::CSRF_PROTECTED_WRITES as $method) {
			$this->assertNotContains(
				'noadminrequired',
				$this->annotationsOf($method),
				sprintf(
					'SettingsController::%s() writes instance-wide configuration and '
					. 'must not be reachable by a non-admin session.',
					$method
				)
			);
		}//end foreach

	}//end testSettingsWritesStayAdminOnly()

}//end class
