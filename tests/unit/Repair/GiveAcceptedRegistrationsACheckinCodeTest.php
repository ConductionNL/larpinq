<?php

/**
 * Accepted registrations stored before the check-in code get one.
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/changes/events-qr-checkin/specs/event-checkin-roster/spec.md#requirement-every-accepted-registration-has-a-check-in-code-req-eqc-001
 */

declare(strict_types=1);

namespace OCA\Larpinq\Tests\Unit\Repair;

use OCA\Larpinq\Repair\GiveAcceptedRegistrationsACheckinCode;
use OCA\Larpinq\Service\RegisterObjectFetcher;
use OCP\Migration\IOutput;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/**
 * @covers \OCA\Larpinq\Repair\GiveAcceptedRegistrationsACheckinCode
 * @uses \OCA\Larpinq\Service\CheckinCode
 */
class GiveAcceptedRegistrationsACheckinCodeTest extends TestCase {

	public function testOnlyAcceptedRegistrationsWithoutACodeGetOneAndTheCountIsLogged(): void {
		$fetcher = $this->createMock(RegisterObjectFetcher::class);
		$fetcher->expects($this->once())->method('getObjectsWithAppAuthority')
			->with('registration', ['status' => 'accepted'], GiveAcceptedRegistrationsACheckinCode::MAX_ROWS)
			->willReturn([
				['id' => 'r1', 'status' => 'accepted', 'player' => 'p1', '@self' => ['id' => 'r1']],
				['id' => 'r2', 'status' => 'accepted', 'checkinCode' => 'ABCDEFGHIJKLMNOPQRSTUVWXYZ'],
			]);
		$saved = [];
		$fetcher->method('saveObjectWithAppAuthority')->willReturnCallback(
			static function (string $objectType, array $data, ?string $uuid = null) use (&$saved): array {
				$saved[] = [$objectType, $data, $uuid];
				return $data;
			}
		);
		$lines = [];
		$output = $this->createMock(IOutput::class);
		$output->method('info')->willReturnCallback(static function (string $line) use (&$lines): void {
			$lines[] = $line;
		});

		(new GiveAcceptedRegistrationsACheckinCode(objectFetcher: $fetcher, logger: new NullLogger()))->run($output);

		$this->assertCount(1, $saved);
		[$type, $data, $uuid] = $saved[0];
		$this->assertSame(['registration', 'r1'], [$type, $uuid]);
		$this->assertArrayNotHasKey('@self', $data);
		$this->assertSame('p1', $data['player'], 'the rest of the registration is saved back unchanged');
		$this->assertMatchesRegularExpression('/^[A-Z2-7]{26}$/', $data['checkinCode']);
		$this->assertSame(['1 of 1 accepted registrations without a check-in code now have one.'], $lines);
	}//end testOnlyAcceptedRegistrationsWithoutACodeGetOneAndTheCountIsLogged()

	public function testAFailedReadIsNamedAndWritesNothing(): void {
		$fetcher = $this->createMock(RegisterObjectFetcher::class);
		$fetcher->method('getObjectsWithAppAuthority')->willThrowException(new \RuntimeException('down'));
		$fetcher->expects($this->never())->method('saveObjectWithAppAuthority');
		$output = $this->createMock(IOutput::class);
		$output->expects($this->once())->method('warning')->with($this->stringContains('none got a check-in code'));

		(new GiveAcceptedRegistrationsACheckinCode(objectFetcher: $fetcher, logger: new NullLogger()))->run($output);
	}//end testAFailedReadIsNamedAndWritesNothing()

	public function testOnlyGameMastersAndThePlayerReadTheCode(): void {
		$fragment = json_decode((string)file_get_contents(__DIR__ . '/../../../lib/Settings/register.d/events-qr-checkin.json'), true);
		$property = $fragment['components']['schemas']['registration']['properties']['checkinCode'];

		$this->assertSame(
			[['group' => 'gamemasters'], ['group' => 'larpers', 'match' => ['playerUid' => '$userId']]],
			$property['authorization']['read']
		);
		$this->assertSame(['gamemasters'], $property['authorization']['update']);
		$this->assertSame('explicit', $property['authorization']['reveal']);
	}//end testOnlyGameMastersAndThePlayerReadTheCode()
}//end class
