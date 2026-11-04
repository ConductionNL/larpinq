<?php

/**
 * Unit tests for MigrateAppConfigKeys.
 *
 * These tests exist because the thing they guard fails SILENTLY. The repair
 * step carries this app's `oc_appconfig` rows across the
 * `larpingapp` -> `larpinq` rename; if it does not, the app comes up looking
 * freshly installed and pointed at nothing, with every existing OpenRegister
 * object still there and invisible. Nothing throws.
 *
 * Two of the cases below are shaped by defects that shipped green in earlier
 * renames in this programme, and both are noted at the test that catches them:
 * the reserved-key fixture that returned the SAME value for both namespaces
 * (so the never-overwrite guard suppressed the write and the test passed with
 * the reserved list emptied), and the read-outside-the-try that aborted an
 * install.
 *
 * @category Test
 * @package  OCA\Larpinq\Tests\Unit\Repair
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://conduction.nl
 */

declare(strict_types=1);

namespace OCA\Larpinq\Tests\Unit\Repair;

use OCA\Larpinq\AppInfo\Application;
use OCA\Larpinq\Repair\MigrateAppConfigKeys;
use OCP\IAppConfig;
use OCP\Migration\IOutput;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * Tests for the larpingapp -> larpinq app-config migration.
 */
class MigrateAppConfigKeysTest extends TestCase {
	private const OLD_APP_ID = 'larpingapp';

	/** @var IAppConfig&MockObject The app config. */
	private IAppConfig&MockObject $appConfig;

	/** @var LoggerInterface&MockObject The logger. */
	private LoggerInterface&MockObject $logger;

	/** @var IOutput&MockObject The output. */
	private IOutput&MockObject $output;

	/** @var MigrateAppConfigKeys The step. */
	private MigrateAppConfigKeys $step;

	/**
	 * Build the doubles and the subject every test starts from.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->appConfig = $this->createMock(originalClassName: IAppConfig::class);
		$this->logger = $this->createMock(originalClassName: LoggerInterface::class);
		$this->output = $this->createMock(originalClassName: IOutput::class);

		$this->step = new MigrateAppConfigKeys(appConfig: $this->appConfig, logger: $this->logger);
	}//end setUp()

	/**
	 * Get name mentions both namespaces.
	 *
	 * @return void
	 */
	public function testGetNameMentionsBothNamespaces(): void {
		self::assertStringContainsString(needle: self::OLD_APP_ID, haystack: $this->step->getName());
		self::assertStringContainsString(needle: Application::APP_ID, haystack: $this->step->getName());
	}//end testGetNameMentionsBothNamespaces()

	/**
	 * The happy path: a stored value under the old id lands under the new one.
	 *
	 * @return void
	 */
	public function testCopiesStoredValuesToTheNewNamespace(): void {
		$this->appConfig->method('getKeys')
			->with(self::OLD_APP_ID)
			->willReturn(['register', 'character_schema']);

		$this->appConfig->method('getValueString')->willReturnCallback(
			static function (string $app, string $key): string {
				if ($app === self::OLD_APP_ID) {
					return ['register' => '7', 'character_schema' => '19'][$key] ?? '';
				}

				// Nothing stored under the new app id yet.
				return '';
			}
		);

		$written = [];
		$this->appConfig->method('setValueString')->willReturnCallback(
			static function (string $app, string $key, string $value) use (&$written): bool {
				$written[$app][$key] = $value;
				return true;
			}
		);

		$this->step->run($this->output);

		self::assertSame(
			expected: ['register' => '7', 'character_schema' => '19'],
			actual: $written[Application::APP_ID] ?? []
		);
	}//end testCopiesStoredValuesToTheNewNamespace()

	/**
	 * Never clobber an admin edit made after the rename. Also what makes a
	 * second run a no-op.
	 *
	 * @return void
	 */
	public function testDoesNotOverwriteAValueAlreadySetUnderTheNewAppId(): void {
		$this->appConfig->method('getKeys')->willReturn(['register']);

		$this->appConfig->method('getValueString')->willReturnCallback(
			static fn (string $app): string => match ($app) {
				self::OLD_APP_ID => '7',
				default => '42',
			}
		);

		$this->appConfig->expects(self::never())->method('setValueString');

		$this->step->run($this->output);
	}//end testDoesNotOverwriteAValueAlreadySetUnderTheNewAppId()

	/**
	 * Nextcloud's own keys must not be copied.
	 *
	 * ⚠️ THE FIXTURE MUST RETURN A DIFFERENT VALUE FOR EACH NAMESPACE. An
	 * earlier app in this programme wrote this test with the same value on
	 * both sides, so the never-overwrite guard — not the reserved list — was
	 * what suppressed the write. That test passed with RESERVED_KEYS emptied,
	 * which is precisely the bug it was meant to catch: copying `enabled`
	 * stores type STRING over Nextcloud's MIXED and the next `app:enable`
	 * fails permanently with an AppConfigTypeConflictException.
	 *
	 * Here the new namespace is EMPTY, so the only thing that can stop the
	 * write is the reserved list.
	 *
	 * @return void
	 */
	public function testSkipsNextcloudReservedKeys(): void {
		$this->appConfig->method('getKeys')
			->willReturn(['enabled', 'installed_version', 'types', 'register']);

		$this->appConfig->method('getValueString')->willReturnCallback(
			static function (string $app, string $key): string {
				if ($app === self::OLD_APP_ID) {
					return [
						'enabled' => 'yes',
						'installed_version' => '0.1.20',
						'types' => 'filesystem',
						'register' => '7',
					][$key] ?? '';
				}

				// Deliberately EMPTY: the never-overwrite guard must not be
				// able to explain a skip in this test.
				return '';
			}
		);

		$written = [];
		$this->appConfig->method('setValueString')->willReturnCallback(
			static function (string $app, string $key, string $value) use (&$written): bool {
				$written[$key] = $value;
				return true;
			}
		);

		$this->step->run($this->output);

		self::assertSame(expected: ['register' => '7'], actual: $written);
	}//end testSkipsNextcloudReservedKeys()

	/**
	 * A READ that throws must not escape run().
	 *
	 * ⚠️ This is the case two apps in this programme got wrong by leaving the
	 * reads OUTSIDE the try. It matters more than it looks: this step is
	 * registered under <install>, so a throw here does not merely fail an
	 * upgrade — the app never enables and every route goes with it.
	 *
	 * The assertion is deliberately two-part: no exception escapes, AND the
	 * loop carries on to migrate the following key. A `try` wrapped around the
	 * whole foreach would satisfy the first half and fail the second.
	 *
	 * @return void
	 */
	public function testAReadThatThrowsIsLoggedAndTheLoopContinues(): void {
		$this->appConfig->method('getKeys')->willReturn(['poisoned', 'register']);

		$this->appConfig->method('getValueString')->willReturnCallback(
			static function (string $app, string $key): string {
				if ($key === 'poisoned') {
					throw new RuntimeException('unreadable value');
				}

				if ($app === self::OLD_APP_ID) {
					return '7';
				}

				return '';
			}
		);

		$written = [];
		$this->appConfig->method('setValueString')->willReturnCallback(
			static function (string $app, string $key, string $value) use (&$written): bool {
				$written[$key] = $value;
				return true;
			}
		);

		$this->logger->expects(self::atLeastOnce())->method('warning');

		$this->step->run($this->output);

		self::assertSame(expected: ['register' => '7'], actual: $written, message: 'the loop must continue past a bad key');
	}//end testAReadThatThrowsIsLoggedAndTheLoopContinues()

	/**
	 * An unreadable key list must not abort the install either.
	 *
	 * @return void
	 */
	public function testAnUnreadableKeyListIsSurvivable(): void {
		$this->appConfig->method('getKeys')
			->willThrowException(new RuntimeException('appconfig unavailable'));

		$this->appConfig->expects(self::never())->method('setValueString');
		$this->logger->expects(self::atLeastOnce())->method('warning');

		$this->step->run($this->output);
	}//end testAnUnreadableKeyListIsSurvivable()

	/**
	 * An empty old value is not copied.
	 *
	 * @return void
	 */
	public function testAnEmptyOldValueIsNotCopied(): void {
		$this->appConfig->method('getKeys')->willReturn(['register']);
		$this->appConfig->method('getValueString')->willReturn('');

		$this->appConfig->expects(self::never())->method('setValueString');

		$this->step->run($this->output);
	}//end testAnEmptyOldValueIsNotCopied()

	/**
	 * Nothing stored under the old id is a no op.
	 *
	 * @return void
	 */
	public function testNothingStoredUnderTheOldIdIsANoOp(): void {
		$this->appConfig->method('getKeys')->willReturn([]);

		$this->appConfig->expects(self::never())->method('setValueString');

		$this->step->run($this->output);
	}//end testNothingStoredUnderTheOldIdIsANoOp()

	/**
	 * The summary counts each outcome once: migrated, already present, empty,
	 * reserved and a read that failed.
	 *
	 * @return void
	 */
	public function testTheSummaryCountsEachOutcome(): void {
		$this->appConfig->method('getKeys')->willReturn(['enabled', 'blank', 'kept', 'register', 'poisoned']);
		$this->appConfig->method('getValueString')->willReturnCallback(
			static function (string $app, string $key): string {
				if ($key === 'poisoned') {
					throw new \RuntimeException('unreadable');
				}

				$stored = ['larpinq' => ['kept' => 'new'], 'larpingapp' => ['enabled' => 'old', 'kept' => 'old', 'register' => 'old']];
				return $stored[$app][$key] ?? '';
			}
		);
		$this->appConfig->method('setValueString')->willReturn(true);
		$this->output->expects($this->once())->method('info')->with(
			'MigrateAppConfigKeys: 1 key(s) migrated, 1 already present, 1 had no value to migrate, 1 skipped as Nextcloud-reserved.'
		);

		$this->step->run($this->output);

	}//end testTheSummaryCountsEachOutcome()
}//end class
