<?php

/**
 * Drift check for the OpenRegister test stubs.
 *
 * The directory tests/stubs/openregister/ carries verbatim copies of OpenRegister's pre-write
 * events and an ObjectEntity of the real shape. A stub that drifts from the
 * real class lets a listener test pass over code that fails on an instance
 * (learniq#984), so this test compares each stub with the real file whenever
 * the openregister source is available: beside this app (apps-extra layout) or
 * named by OPENREGISTER_SOURCE. Without the source it is skipped and says so.
 *
 * @category Test
 * @package  OCA\Larpinq\Tests\Unit\Support
 * @author   Ruben Linde <ruben@larpingapp.com>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://larpingapp.com
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\Larpinq\Tests\Unit\Support;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Stub-versus-source comparison for the OpenRegister stubs.
 */
class OpenRegisterStubDriftTest extends TestCase {

	/**
	 * One case per stub file.
	 *
	 * @return array<string, array{0: string, 1: bool}> Stub path and whether it must match exactly.
	 */
	public static function stubProvider(): array {
		$cases = [];
		foreach ((array)glob(__DIR__ . '/../../stubs/openregister/Event/*.php') as $file) {
			$cases['Event/' . basename((string)$file, '.php')] = [(string)$file, true];
		}

		$cases['Service/Integration/IntegrationProvider'] = [__DIR__ . '/../../stubs/openregister/Service/Integration/IntegrationProvider.php', true];
		$cases['Exception/NotAuthorizedException'] = [__DIR__ . '/../../stubs/openregister/Exception/NotAuthorizedException.php', true];
		$cases['Db/ObjectEntity'] = [__DIR__ . '/../../stubs/openregister/Db/ObjectEntity.php', false];

		return $cases;
	}//end stubProvider()

	/**
	 * An event stub declares exactly the real public methods; the entity stub a subset of them.
	 *
	 * @param string $stubFile The stub file.
	 * @param bool $exact Whether the method sets must be equal (else the stub's must be a subset).
	 *
	 * @return void
	 */
	#[DataProvider('stubProvider')]
	public function testStubMatchesTheRealClass(string $stubFile, bool $exact): void {
		$root = $this->openRegisterRoot();
		if ($root === null) {
			$this->markTestSkipped(
				message: 'openregister source not found beside larpinq and OPENREGISTER_SOURCE is unset: stub drift is UNVERIFIED by this run.'
			);
		}

		$relative = substr($stubFile, strpos($stubFile, '/stubs/openregister/') + strlen('/stubs/openregister/'));
		$realFile = $root . '/lib/' . $relative;
		self::assertFileExists(filename: $realFile, message: 'The stub mirrors a class OpenRegister no longer ships: ' . $relative);

		$real = $this->publicMethods(file: $realFile);
		$stub = $this->publicMethods(file: $stubFile);
		if ($exact === true) {
			self::assertSame(expected: $real, actual: $stub, message: $relative . ' drifted from ' . $realFile);
			return;
		}

		self::assertSame(expected: [], actual: array_values(array_diff($stub, $real)), message: $relative . ' declares methods the real class lacks');
	}//end testStubMatchesTheRealClass()

	/**
	 * The openregister source root, when available.
	 *
	 * @return string|null The root, or null.
	 */
	private function openRegisterRoot(): ?string {
		foreach ([(string)getenv('OPENREGISTER_SOURCE'), dirname(__DIR__, 4) . '/openregister'] as $candidate) {
			if ($candidate !== '' && is_dir($candidate . '/lib/Event') === true) {
				return $candidate;
			}
		}

		return null;
	}//end openRegisterRoot()

	/**
	 * Sorted public method names a PHP file's class offers.
	 *
	 * Counts the methods the class declares and those it takes from a parent
	 * class or a trait in the same directory: OpenRegister moved the event
	 * accessors into `AbstractObjectEntityEvent` and the `WriteVetoState`
	 * trait, so the leaf file alone declares none of its public API.
	 *
	 * @param string $file The file.
	 *
	 * @return array<int,string> Method names.
	 */
	private function publicMethods(string $file): array {
		$names = $this->declaredMethods(file: $file, seen: []);
		$names = array_values(array_unique($names));
		sort($names);
		return $names;
	}//end publicMethods()

	/**
	 * Public method names of a file's class, its same-directory parent and traits.
	 *
	 * @param string            $file The file.
	 * @param array<int,string> $seen Files already read, against a cycle.
	 *
	 * @return array<int,string> Method names, unsorted, possibly repeated.
	 */
	private function declaredMethods(string $file, array $seen): array {
		if (in_array($file, $seen, true) === true || is_file($file) === false) {
			return [];
		}

		$seen[] = $file;
		$source = (string)file_get_contents($file);
		preg_match_all('/public\s+(?:static\s+)?function\s+(\w+)\s*\(/', $source, $matches);
		$names = $matches[1];

		$related = [];
		if (preg_match('/\b(?:class|trait)\s+\w+\s+extends\s+(\w+)/', $source, $parent) === 1) {
			$related[] = $parent[1];
		}

		$body = substr($source, (int)strpos($source, '{'));
		preg_match_all('/^\s*use\s+(\w+(?:\s*,\s*\w+)*)\s*;/m', $body, $traits);
		foreach ($traits[1] as $list) {
			$related = array_merge($related, preg_split('/\s*,\s*/', $list));
		}

		foreach ($related as $name) {
			$names = array_merge($names, $this->declaredMethods(file: dirname($file) . '/' . $name . '.php', seen: $seen));
		}

		return $names;
	}//end declaredMethods()
}//end class
