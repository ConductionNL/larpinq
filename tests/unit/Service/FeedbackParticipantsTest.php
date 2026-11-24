<?php

/**
 * FeedbackParticipants: the checked-in participants of an event and their characters.
 *
 * @category Test
 * @package  OCA\Larpinq\Tests\Unit\Service
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/specs/event-feedback/spec.md
 */

declare(strict_types=1);

namespace OCA\Larpinq\Tests\Unit\Service;

use OCA\Larpinq\Service\FeedbackParticipants;
use OCA\Larpinq\Service\RegisterObjectFetcher;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * REQ-EPS-002 and REQ-EPS-004 participant reads, by the extracted collaborator.
 */
#[CoversClass(FeedbackParticipants::class)]
class FeedbackParticipantsTest extends TestCase {

	/**
	 * The reader over these attendance rows and characters.
	 *
	 * @param list<array<string, mixed>>          $rows       The attendance rows the store answers.
	 * @param array<string, array<string, mixed>> $characters Characters by id.
	 *
	 * @return FeedbackParticipants
	 */
	private function participants(array $rows, array $characters): FeedbackParticipants {
		$fetcher = $this->createMock(originalClassName: RegisterObjectFetcher::class);
		$fetcher->method('getObjectsWithAppAuthority')->willReturnCallback(
			static function (string $objectType, array $filters) use ($rows): array {
				self::assertSame(expected: 'attendance', actual: $objectType);
				self::assertSame(expected: ['event' => 'e1', 'status' => 'checked-in'], actual: $filters);
				return $rows;
			}
		);
		$fetcher->method('getObject')->willReturnCallback(
			static function (string $objectType, string $id) use ($characters): array {
				if (isset($characters[$id]) === false) {
					throw new RuntimeException('gone');
				}

				return $characters[$id];
			}
		);
		return new FeedbackParticipants(fetcher: $fetcher);
	}//end participants()

	/**
	 * A no-show the store lets through is dropped.
	 *
	 * @return void
	 */
	public function testOnlyCheckedInRowsCount(): void {
		$rows = [['id' => 'a1', 'status' => 'checked-in'], ['id' => 'a2', 'status' => 'no-show']];

		$checkedIn = $this->participants(rows: $rows, characters: [])->checkedIn(eventId: 'e1');

		$this->assertSame(expected: [['id' => 'a1', 'status' => 'checked-in']], actual: $checkedIn);
	}//end testOnlyCheckedInRowsCount()

	/**
	 * The owner is read from the character by id or by embedded object; a missing one is ''.
	 *
	 * @return void
	 */
	public function testOwnerAndName(): void {
		$reader = $this->participants(rows: [], characters: ['c1' => ['ownerUid' => 'anna', 'name' => 'Aldric']]);

		$this->assertSame(expected: 'anna', actual: $reader->ownerOf(attendance: ['character' => 'c1']));
		$this->assertSame(expected: 'anna', actual: $reader->ownerOf(attendance: ['character' => ['id' => 'c1']]));
		$this->assertSame(expected: '', actual: $reader->ownerOf(attendance: ['character' => 'gone']));
		$this->assertSame(expected: '', actual: $reader->ownerOf(attendance: []));
		$this->assertSame(expected: 'Aldric', actual: $reader->characterName(attendance: ['character' => 'c1']));
		$this->assertSame(expected: 'Embedded', actual: $reader->characterName(attendance: ['character' => ['id' => 'c1', 'name' => 'Embedded']]));
	}//end testOwnerAndName()

	/**
	 * Invitees are the invited participants with an account, by uid with the character name.
	 *
	 * @return void
	 */
	public function testInvitees(): void {
		$rows = [
			['status' => 'checked-in', 'character' => 'c1', 'feedbackInvitedAt' => '2026-09-01T10:00:00+00:00'],
			['status' => 'checked-in', 'character' => 'c2'],
			['status' => 'checked-in', 'character' => 'c3', 'feedbackInvitedAt' => '2026-09-01T10:00:00+00:00'],
		];
		$characters = [
			'c1' => ['ownerUid' => 'anna', 'name' => 'Aldric'],
			'c2' => ['ownerUid' => 'bob', 'name' => 'Brenna'],
			'c3' => ['ownerUid' => '', 'name' => 'Nobody'],
		];

		$this->assertSame(expected: ['anna' => 'Aldric'], actual: $this->participants(rows: $rows, characters: $characters)->invitees(eventId: 'e1'));
	}//end testInvitees()
}//end class
