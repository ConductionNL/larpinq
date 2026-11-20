<?php

/**
 * The help desk's rules on their own (communication-help-desk, REQ-CHD-001,
 * REQ-CHD-002, REQ-CHD-003): who may ask as which player, who may write in a
 * thread, and which status a message moves its question to.
 *
 * @category Test
 * @package  OCA\Larpinq\Tests\Unit\Listener
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/changes/communication-help-desk/specs/help-desk/spec.md
 */

declare(strict_types=1);

namespace OCA\Larpinq\Tests\Unit\Listener;

use OCA\Larpinq\Listener\HelpMessageListener;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(HelpMessageListener::class)]
class HelpMessageRulesTest extends TestCase {

	private const OPEN = ['askerUid' => 'anna', 'status' => 'open'];

	public function testAPlayerAsksAsTheirOwnPlayer(): void {
		self::assertNull(HelpMessageListener::askRefusal(asker: ['askerUid' => 'anna', 'ownerRef' => 'p-1'], uid: 'anna'));
	}

	public function testAPlayerCannotAskAsSomeoneElsesPlayer(): void {
		$refusal = HelpMessageListener::askRefusal(asker: ['askerUid' => 'bram', 'ownerRef' => 'p-2'], uid: 'anna');

		self::assertSame(403, $refusal['status']);
		self::assertSame('larpinq.help.not_own_player', $refusal['code']);
		self::assertSame('You can only ask a question as your own player.', $refusal['message']);
	}

	public function testAPlayerWithoutAnAskerUidIsNotTheirs(): void {
		$refusal = HelpMessageListener::askRefusal(asker: ['ownerRef' => 'p-3'], uid: 'anna');

		self::assertSame('larpinq.help.not_own_player', $refusal['code']);
	}

	public function testAQuestionNamingNoReadablePlayerIsNotRefusedHere(): void {
		self::assertNull(HelpMessageListener::askRefusal(asker: [], uid: 'anna'));
	}

	public function testAMessageWithoutItsQuestionIsRefused(): void {
		$refusal = HelpMessageListener::refusal(question: null, uid: 'anna', organiser: false);

		self::assertSame(422, $refusal['status']);
		self::assertSame('larpinq.help.no_question', $refusal['code']);
		self::assertSame('A message needs the question it answers.', $refusal['message']);
	}

	public function testAClosedQuestionRefusesEvenAGameMaster(): void {
		$refusal = HelpMessageListener::refusal(question: ['askerUid' => 'anna', 'status' => 'closed'], uid: 'joris', organiser: true);

		self::assertSame(422, $refusal['status']);
		self::assertSame('larpinq.help.closed', $refusal['code']);
		self::assertSame('This question is closed and takes no new messages.', $refusal['message']);
	}

	public function testAClosedQuestionRefusesASystemWriteToo(): void {
		$refusal = HelpMessageListener::refusal(question: ['askerUid' => 'anna', 'status' => 'closed'], uid: '', organiser: false);

		self::assertSame('larpinq.help.closed', $refusal['code']);
	}

	/**
	 * Writers allowed in an open or answered thread.
	 *
	 * @return array<string, array{array<string,string>, string, bool}>
	 */
	public static function allowedWriters(): array {
		return [
			'the asker in an open thread'          => [self::OPEN, 'anna', false],
			'the asker in an answered thread'      => [['askerUid' => 'anna', 'status' => 'answered'], 'anna', false],
			'a game master in an open thread'      => [self::OPEN, 'joris', true],
			'a game master who is also the asker'  => [self::OPEN, 'anna', true],
			'a system write'                       => [self::OPEN, '', false],
			'a question without a status'          => [['askerUid' => 'anna'], 'anna', false],
		];
	}

	#[DataProvider('allowedWriters')]
	public function testTheseWritersMayWriteInTheThread(array $question, string $uid, bool $organiser): void {
		self::assertNull(HelpMessageListener::refusal(question: $question, uid: $uid, organiser: $organiser));
	}

	/**
	 * Writers refused in a thread that is not closed.
	 *
	 * @return array<string, array{array<string,string>, string}>
	 */
	public static function refusedWriters(): array {
		return [
			'another player in an open thread'     => [self::OPEN, 'bram'],
			'another player in an answered thread' => [['askerUid' => 'anna', 'status' => 'answered'], 'bram'],
			'anyone when the asker is unknown'     => [['status' => 'open'], 'anna'],
		];
	}

	#[DataProvider('refusedWriters')]
	public function testOthersAreRefusedFromTheThread(array $question, string $uid): void {
		$refusal = HelpMessageListener::refusal(question: $question, uid: $uid, organiser: false);

		self::assertSame(403, $refusal['status']);
		self::assertSame('larpinq.help.not_asker', $refusal['code']);
		self::assertSame('Only the asker and the game masters write in this thread.', $refusal['message']);
	}

	/**
	 * The status a message moves its question to, or null when it stays.
	 *
	 * @return array<string, array{string, bool, ?string}>
	 */
	public static function statuses(): array {
		return [
			'a game master answers an open question'      => ['open', true, 'answered'],
			'the asker replies to an answer'              => ['answered', false, 'open'],
			'the asker adds to an open question'          => ['open', false, null],
			'a game master adds to an answered question'  => ['answered', true, null],
			'a closed question stays closed (game master)' => ['closed', true, null],
			'a closed question stays closed (asker)'      => ['closed', false, null],
			'an unknown status is left alone'             => ['waiting', true, null],
		];
	}

	#[DataProvider('statuses')]
	public function testTheStatusAMessageMovesItsQuestionTo(string $status, bool $fromOrganiser, ?string $expected): void {
		self::assertSame($expected, HelpMessageListener::statusAfter(status: $status, fromOrganiser: $fromOrganiser));
	}
}
