<?php

/**
 * FleetAppId — resolver behaviour and the cross-app binding rot guard.
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\Larpinq\Tests\Unit\Support;

use OCA\Larpinq\Support\FleetAppId;
use PHPUnit\Framework\TestCase;

/**
 * Guards the cross-app identity resolver.
 *
 * The test that matters here is {@see self::testNoStaleCrossAppNamespaceIsBoundDirectly}.
 * In August 2026 filinq renamed `OCA\DocuDesk` to `OCA\Filinq`, and thirteen
 * bindings across five apps kept naming the old namespace. Every one of them is
 * wrapped in `class_exists`, `instanceof`, `$container->get()` inside a
 * try/catch, or `has()` — so all thirteen degraded to "the app is not
 * installed" and nothing was logged, nothing turned red, and no test failed.
 * A grep is the only instrument that could have caught it, so it is a test.
 *
 * phpcs:disable CustomSniffs.Functions.NamedParameters
 */
final class FleetAppIdTest extends TestCase {

	/**
	 * Retired namespace => the canonical app name it belongs to.
	 *
	 * @var array<string, string>
	 */
	private const RETIRED_NAMESPACES = [
		'OpenConnector'   => 'integriq',
		'DocuDesk'        => 'filinq',
		'NLDesign'        => 'thematiq',
		'SoftwareCatalog' => 'stackiq',
		'LarpingApp'      => 'larpinq',
		'Procest'         => 'dossiq',
		'Scholiq'         => 'learniq',
		'Decidesk'        => 'decidiq',
		'OpenBuilt'       => 'buildiq',
		'Doriath'         => 'keepiq',
	];

	/**
	 * Retired namespace => the namespace that replaced it.
	 *
	 * A file naming BOTH is binding defensively on purpose and is correct; only
	 * a file naming the retired one ALONE is dark. That distinction is the
	 * whole point of the scan, so it is encoded rather than assumed.
	 *
	 * @var array<string, string>
	 */
	private const REPLACED_BY = [
		'OpenConnector'   => 'Integriq',
		'DocuDesk'        => 'Filinq',
		'NLDesign'        => 'Thematiq',
		'SoftwareCatalog' => 'Stackiq',
		'LarpingApp'      => 'Larpinq',
		'Procest'         => 'Dossiq',
		'Scholiq'         => 'Learniq',
		'Decidesk'        => 'Decidiq',
		'OpenBuilt'       => 'Buildiq',
		'Doriath'         => 'Keepiq',
	];

	/**
	 * The one file allowed to name a retired namespace: the map itself.
	 *
	 * @var string
	 */
	private const RESOLVER_PATH = 'lib/Support/FleetAppId.php';

	/**
	 * The newest namespace is offered first, with the retired one kept as fallback.
	 *
	 * Order is the whole contract: a fully migrated instance must resolve to the
	 * new name, and an instance still on an older release must still resolve.
	 *
	 * @return void
	 */
	public function testCandidatesAreNewestFirstAndKeepTheOldName(): void {
		$candidates = FleetAppId::classCandidates('filinq', 'Service\PdfService');

		$this->assertSame(
			['OCA\Filinq\Service\PdfService', 'OCA\DocuDesk\Service\PdfService'],
			$candidates,
			'filinq must resolve to OCA\Filinq first and still fall back to OCA\DocuDesk.'
		);

	}//end testCandidatesAreNewestFirstAndKeepTheOldName()

	/**
	 * A leading separator on the relative name does not produce a doubled one.
	 *
	 * @return void
	 */
	public function testLeadingSeparatorIsNormalised(): void {
		$this->assertSame(
			FleetAppId::classCandidates('decidiq', 'Event\DecisionConcludedEvent'),
			FleetAppId::classCandidates('decidiq', '\Event\DecisionConcludedEvent'),
			'A leading backslash on the relative class name must not change the result.'
		);

	}//end testLeadingSeparatorIsNormalised()

	/**
	 * Every renamed app carries both a new and a retired namespace.
	 *
	 * Dropping the retired entry is the change that silently re-breaks an
	 * instance running an older release, so it is asserted rather than trusted.
	 *
	 * @return void
	 */
	public function testEveryRenamedAppKeepsBothNames(): void {
		foreach (self::RETIRED_NAMESPACES as $retired => $canonical) {
			$candidates = FleetAppId::classCandidates($canonical, 'Service\Probe');

			$this->assertCount(
				2,
				$candidates,
				sprintf('%s must offer both its current and its retired namespace.', $canonical)
			);
			$this->assertStringContainsString(
				'OCA\\'.$retired.'\\',
				$candidates[1],
				sprintf('%s must keep OCA\%s as its fallback namespace.', $canonical, $retired)
			);
		}

	}//end testEveryRenamedAppKeepsBothNames()

	/**
	 * An unknown app yields no candidates rather than a guessed namespace.
	 *
	 * @return void
	 */
	public function testUnknownAppYieldsNoCandidates(): void {
		$this->assertSame([], FleetAppId::classCandidates('notafleetapp', 'Service\Thing'));

	}//end testUnknownAppYieldsNoCandidates()

	/**
	 * resolveClass returns null when no candidate namespace is autoloadable.
	 *
	 * @return void
	 */
	public function testResolveClassReturnsNullWhenAbsent(): void {
		$this->assertNull(FleetAppId::resolveClass('filinq', 'Service\NoSuchServiceHere'));

	}//end testResolveClassReturnsNullWhenAbsent()

	/**
	 * isInstanceOf matches a class living under any candidate namespace.
	 *
	 * The fixture is declared under the RETIRED namespace on purpose: an
	 * instance still running the old release is exactly the case a hard swap to
	 * the new name would break, and this test fails if someone makes that swap.
	 *
	 * @return void
	 */
	public function testIsInstanceOfMatchesTheRetiredNamespaceToo(): void {
		if (class_exists('OCA\DocuDesk\Event\SigningConcludedEvent') === false) {
			eval('namespace OCA\DocuDesk\Event; class SigningConcludedEvent {}');
		}

		$legacyEvent = new \OCA\DocuDesk\Event\SigningConcludedEvent();

		$this->assertTrue(
			FleetAppId::isInstanceOf($legacyEvent, 'filinq', 'Event\SigningConcludedEvent'),
			'An event dispatched by a pre-rename filinq must still be recognised.'
		);
		$this->assertFalse(
			FleetAppId::isInstanceOf(new \stdClass(), 'filinq', 'Event\SigningConcludedEvent'),
			'An unrelated object must not match.'
		);

	}//end testIsInstanceOfMatchesTheRetiredNamespaceToo()

	/**
	 * No file under lib/ binds a retired cross-app namespace in executable code.
	 *
	 * This is the regression guard for the August 2026 outage. Every stale
	 * binding fails CLOSED and SILENTLY — `class_exists` returns false,
	 * `instanceof` returns false, `$container->get()` throws into a catch that
	 * degrades gracefully — so the integration stops working while every check
	 * stays green. Nothing but a scan of the source can see it.
	 *
	 * Comments may still name a retired namespace: they explain the history and
	 * cost nothing at runtime. Only code is checked.
	 *
	 * A file that names the retired namespace AND its replacement is binding
	 * defensively on purpose — a dual-spelling candidate list is the correct
	 * pattern, not a defect — so it passes. Only a SOLE binding to a retired
	 * name is reported.
	 *
	 * @return void
	 */
	public function testNoStaleCrossAppNamespaceIsBoundDirectly(): void {
		$root = dirname(__DIR__, 3);
		$offences = [];

		$files = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator($root.'/lib', \FilesystemIterator::SKIP_DOTS)
		);

		foreach ($files as $file) {
			if ($file->isFile() === false || $file->getExtension() !== 'php') {
				continue;
			}

			$relative = ltrim(str_replace($root, '', $file->getPathname()), '/');
			if ($relative === self::RESOLVER_PATH) {
				// The candidate map is the one place these names belong.
				continue;
			}

			$contents = (string)file_get_contents($file->getPathname());

			foreach (explode("\n", $contents) as $number => $line) {
				$trimmed = ltrim($line);
				if ($trimmed === '' || str_starts_with($trimmed, '*') === true
					|| str_starts_with($trimmed, '//') === true
					|| str_starts_with($trimmed, '/*') === true
				) {
					continue;
				}

				foreach (array_keys(self::RETIRED_NAMESPACES) as $retired) {
					// Matches both the single-backslash source form and the
					// escaped form used inside double-quoted PHP strings.
					if (preg_match('/OCA\\\\{1,2}'.$retired.'\\\\/', $line) !== 1) {
						continue;
					}

					// A file that also names the replacement is covering both
					// spellings deliberately — that is the fix, not the defect.
					$replacement = self::REPLACED_BY[$retired];
					if (preg_match('/OCA\\\\{1,2}'.$replacement.'\\\\/', $contents) === 1) {
						continue;
					}

					$offences[] = sprintf('%s:%d %s', $relative, ($number + 1), trim($line));
				}
			}
		}

		$this->assertSame(
			[],
			$offences,
			"Retired cross-app namespaces are bound directly. Every one of these fails\n"
			."silently at runtime — resolve through FleetAppId::classCandidates(),\n"
			."resolveClass(), getService() or isInstanceOf() instead:\n"
			.implode("\n", $offences)
		);

	}//end testNoStaleCrossAppNamespaceIsBoundDirectly()

}//end class
