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

	private IAppConfig&MockObject $appConfig;

	private LoggerInterface&MockObject $logger;

	private IOutput&MockObject $output;

	private MigrateAppConfigKeys $step;

	protected function setUp(): void {
		parent::setUp();

		$this->appConfig = $this->createMock(IAppConfig::class);
		$this->logger = $this->createMock(LoggerInterface::class);
		$this->output = $this->createMock(IOutput::class);

		$this->step = new MigrateAppConfigKeys($this->appConfig, $this->logger);
	}//end setUp()

	public function testGetNameMentionsBothNamespaces(): void {
		self::assertStringContainsString(self::OLD_APP_ID, $this->step->getName());
		self::assertStringContainsString(Application::APP_ID, $this->step->getName());
	}//end testGetNameMentionsBothNamespaces()

	/**
	 * The happy path: a stored value under the old id lands under the new one.
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
			['register' => '7', 'character_schema' => '19'],
			$written[Application::APP_ID] ?? []
		);
	}//end testCopiesStoredValuesToTheNewNamespace()

	/**
	 * Never clobber an admin edit made after the rename. Also what makes a
	 * second run a no-op.
	 */
	public function testDoesNotOverwriteAValueAlreadySetUnderTheNewAppId(): void {
		$this->appConfig->method('getKeys')->willReturn(['register']);

		$this->appConfig->method('getValueString')->willReturnCallback(
			static fn (string $app): string => $app === self::OLD_APP_ID ? '7' : '42'
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

		self::assertSame(['register' => '7'], $written);
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
	 */
	public function testAReadThatThrowsIsLoggedAndTheLoopContinues(): void {
		$this->appConfig->method('getKeys')->willReturn(['poisoned', 'register']);

		$this->appConfig->method('getValueString')->willReturnCallback(
			static function (string $app, string $key): string {
				if ($key === 'poisoned') {
					throw new RuntimeException('unreadable value');
				}

				return $app === self::OLD_APP_ID ? '7' : '';
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

		self::assertSame(['register' => '7'], $written, 'the loop must continue past a bad key');
	}//end testAReadThatThrowsIsLoggedAndTheLoopContinues()

	/**
	 * An unreadable key list must not abort the install either.
	 */
	public function testAnUnreadableKeyListIsSurvivable(): void {
		$this->appConfig->method('getKeys')
			->willThrowException(new RuntimeException('appconfig unavailable'));

		$this->appConfig->expects(self::never())->method('setValueString');
		$this->logger->expects(self::atLeastOnce())->method('warning');

		$this->step->run($this->output);
	}//end testAnUnreadableKeyListIsSurvivable()

	public function testAnEmptyOldValueIsNotCopied(): void {
		$this->appConfig->method('getKeys')->willReturn(['register']);
		$this->appConfig->method('getValueString')->willReturn('');

		$this->appConfig->expects(self::never())->method('setValueString');

		$this->step->run($this->output);
	}//end testAnEmptyOldValueIsNotCopied()

	public function testNothingStoredUnderTheOldIdIsANoOp(): void {
		$this->appConfig->method('getKeys')->willReturn([]);

		$this->appConfig->expects(self::never())->method('setValueString');

		$this->step->run($this->output);
	}//end testNothingStoredUnderTheOldIdIsANoOp()
}//end class
