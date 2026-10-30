<?php

/**
 * Drift check for the OpenRegister test stubs.
 *
 * tests/stubs/openregister/ carries verbatim copies of OpenRegister's pre-write
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
			$this->markTestSkipped('openregister source not found beside larpinq and OPENREGISTER_SOURCE is unset: stub drift is UNVERIFIED by this run.');
		}

		$relative = substr($stubFile, strpos($stubFile, '/stubs/openregister/') + strlen('/stubs/openregister/'));
		$realFile = $root . '/lib/' . $relative;
		self::assertFileExists($realFile, 'The stub mirrors a class OpenRegister no longer ships: ' . $relative);

		$real = $this->publicMethods(file: $realFile);
		$stub = $this->publicMethods(file: $stubFile);
		if ($exact === true) {
			self::assertSame($real, $stub, $relative . ' drifted from ' . $realFile);
			return;
		}

		self::assertSame([], array_values(array_diff($stub, $real)), $relative . ' declares methods the real class lacks');
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
	 * Sorted public method names declared in a PHP file.
	 *
	 * @param string $file The file.
	 *
	 * @return array<int,string> Method names.
	 */
	private function publicMethods(string $file): array {
		preg_match_all('/public\s+(?:static\s+)?function\s+(\w+)\s*\(/', (string)file_get_contents($file), $matches);
		$names = array_values(array_unique($matches[1]));
		sort($names);

		return $names;
	}//end publicMethods()
}//end class
