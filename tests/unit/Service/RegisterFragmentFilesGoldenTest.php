<?php

/**
 * Golden test for how ConfigFileLoaderService reads lib/Settings/register.d.
 *
 * Each case builds a throwaway app directory with a register file and some
 * fragment files, loads the configuration and records the merged data, the
 * fragment signature, or the error. Compared with
 * fixtures/register-fragment-files.golden.json. Set RECORD_GOLDEN=1 to
 * rewrite it.
 *
 * @category Test
 * @package  OCA\Larpinq\Tests\Unit\Service
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-33
 */

declare(strict_types=1);

namespace OCA\Larpinq\Tests\Unit\Service;

use OCA\Larpinq\Service\ConfigFileLoaderService;
use OCP\App\IAppManager;
use PHPUnit\Framework\TestCase;

/**
 * The register fragments, case by case.
 *
 * @covers \OCA\Larpinq\Service\ConfigFileLoaderService
 */
class RegisterFragmentFilesGoldenTest extends TestCase {

	private const FIXTURE = __DIR__.'/fixtures/register-fragment-files.golden.json';

	/**
	 * The cases: fragment file name to content, or null for no register.d directory.
	 *
	 * @return array<string,array<string,string>|null> The cases.
	 */
	private static function cases(): array {
		return [
			'no fragment directory' => null,
			'empty fragment directory' => [],
			'fragments merge in name order' => [
				'b-second.json' => '{"components":{"schemas":{"Event":{"title":"Event B"}}},"x":"b"}',
				'a-first.json' => '{"components":{"schemas":{"Event":{"title":"Event A","properties":{"days":{"type":"integer"}}}}},"x":"a"}',
			],
			'a non-json file is ignored' => [
				'a.json' => '{"y":1}',
				'notes.txt' => 'not json',
			],
			'invalid json is refused' => [
				'a.json' => '{"y":1}',
				'b.json' => '{broken',
			],
			'a json scalar is refused' => [
				'a.json' => '42',
			],
		];
	}//end cases()

	/**
	 * Every case matches the recorded outcome.
	 *
	 * @return void
	 */
	public function testMatchesTheRecordedOutcomes(): void {
		$actual = [];
		foreach (self::cases() as $name => $fragments) {
			$actual[$name] = json_encode(value: $this->loadWith(fragments: $fragments), flags: JSON_PRETTY_PRINT);
		}

		if (getenv('RECORD_GOLDEN') === '1') {
			file_put_contents(filename: self::FIXTURE, data: json_encode(value: $actual, flags: JSON_PRETTY_PRINT)."\n");
		}

		$expected = json_decode(json: (string)file_get_contents(filename: self::FIXTURE), associative: true);
		foreach ($expected as $name => $outcome) {
			self::assertSame(expected: $outcome, actual: $actual[$name] ?? null, message: $name);
		}

		self::assertSame(expected: array_keys($expected), actual: array_keys($actual));
	}//end testMatchesTheRecordedOutcomes()

	/**
	 * Load the configuration of a throwaway app with these fragments.
	 *
	 * @param array<string,string>|null $fragments Fragment file name to content, or null for no directory.
	 *
	 * @return array<string,mixed> The merged data and signature, or the error.
	 */
	private function loadWith(?array $fragments): array {
		$app = sys_get_temp_dir().'/larpinq-fragments-'.bin2hex(random_bytes(6));
		mkdir(directory: $app.'/lib/Settings/register.d', recursive: true);
		file_put_contents(
			filename: $app.'/lib/Settings/larpinq_register.json',
			data: '{"components":{"schemas":{"Character":{"title":"Character"}}},"x":"base"}'
		);
		if ($fragments === null) {
			rmdir(directory: $app.'/lib/Settings/register.d');
		}

		foreach ($fragments ?? [] as $file => $content) {
			file_put_contents(filename: $app.'/lib/Settings/register.d/'.$file, data: $content);
		}

		$appManager = $this->createMock(originalClassName: IAppManager::class);
		$appManager->method('getAppPath')->willReturn($app);
		$service = new ConfigFileLoaderService(appManager: $appManager);
		try {
			$outcome = ['data' => $service->loadConfigurationFile(), 'signature' => $service->getFragmentSignature()];
		} catch (\RuntimeException $e) {
			$outcome = ['error' => $e->getMessage()];
		} finally {
			exec('rm -rf '.escapeshellarg($app));
		}

		return $outcome;
	}//end loadWith()
}//end class
