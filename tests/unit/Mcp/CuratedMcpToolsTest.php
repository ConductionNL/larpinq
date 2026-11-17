<?php

/**
 * Unit tests for Larpinq's two curated MCP tools and their opt-in list.
 *
 * @category Test
 * @package  OCA\Larpinq\Tests\Unit\Mcp
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://larpingapp.com
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/changes/hermiq-ai-tooling/specs/hermiq-ai-tooling/spec.md
 */

declare(strict_types=1);

namespace OCA\Larpinq\Tests\Unit\Mcp;

use InvalidArgumentException;
use OCA\Larpinq\Mcp\LarpinqScannableServices;
use OCA\Larpinq\Service\CharacterService;
use OCA\Larpinq\Service\EffectApplier;
use OCA\Larpinq\Service\EventRosterService;
use OCA\Larpinq\Service\RegisterObjectFetcher;
use OCA\OpenRegister\Mcp\Attribute\McpTool;
use OCA\OpenRegister\Mcp\IMcpScannableServices;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use ReflectionClass;
use RuntimeException;

/**
 * REQ-001, REQ-003, REQ-004 and REQ-005 of hermiq-ai-tooling.
 */
class CuratedMcpToolsTest extends TestCase {

	/** @var RegisterObjectFetcher&MockObject */
	private $fetcher;

	/** @var list<array<string, mixed>> The awards written. */
	private array $saved = [];

	/**
	 * Set up the fetcher double.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->fetcher = $this->createMock(RegisterObjectFetcher::class);
		$this->saved = [];
	}//end setUp()

	/**
	 * The roster service with a signed-in game master.
	 *
	 * @param list<array<string, mixed>> $attendance The event's attendance rows.
	 * @param string|null                $failFor    A character whose award write throws.
	 *
	 * @return EventRosterService The service.
	 */
	private function roster(array $attendance, ?string $failFor = null): EventRosterService {
		$this->fetcher->method('getObject')->willReturnCallback(
			static function (string $objectType, string $id): array {
				if ($objectType === 'event' && $id === 'ev-sat') {
					return ['id' => 'ev-sat', 'name' => 'Saturday'];
				}
				throw new RuntimeException('not found');
			}
		);
		$this->fetcher->method('getObjects')->willReturnCallback(
			static fn (string $objectType): array => $objectType === 'attendance' ? $attendance : []
		);
		$this->fetcher->method('saveObject')->willReturnCallback(
			function (string $objectType, array $data) use ($failFor): array {
				if ($data['character'] === $failFor) {
					throw new RuntimeException('Forbidden');
				}
				$this->saved[] = ['type' => $objectType] + $data;
				return ['id' => 'award-' . $data['character']] + $data;
			}
		);

		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('gm-ann');
		$session = $this->createMock(IUserSession::class);
		$session->method('getUser')->willReturn($user);

		return new EventRosterService($this->fetcher, $session);
	}//end roster()

	/**
	 * Scenario "Award 5 XP to everyone who attended Saturday's event".
	 *
	 * @return void
	 */
	public function testOnlyCheckedInCharactersAreAwarded(): void {
		$result = $this->roster([
			['event' => 'ev-sat', 'character' => 'c-1', 'status' => 'checked-in'],
			['event' => 'ev-sat', 'character' => 'c-2', 'status' => 'checked-in'],
			['event' => 'ev-sat', 'character' => 'c-3', 'status' => 'no-show'],
			['event' => 'ev-sat', 'character' => 'c-4', 'status' => 'registered'],
			['event' => 'ev-sat', 'character' => 'c-5', 'status' => 'checked-in'],
		])->awardXpToAttendees('ev-sat', 5, 'Saturday session');

		$this->assertSame(['c-1', 'c-2', 'c-5'], array_column($this->saved, 'character'));
		foreach ($this->saved as $award) {
			$this->assertSame('xpAward', $award['type']);
			$this->assertSame('ev-sat', $award['event']);
			$this->assertSame(5, $award['amount']);
			$this->assertSame('Saturday session', $award['reason']);
			$this->assertSame('gm-ann', $award['awardedBy'], 'the invoking principal, never the tool input');
			$this->assertSame('mcp', $award['awardedVia']);
			$this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T/', $award['awardedAt']);
		}
		$this->assertSame(
			[
				['character' => 'c-1', 'status' => 'created', 'award' => 'award-c-1'],
				['character' => 'c-2', 'status' => 'created', 'award' => 'award-c-2'],
				['character' => 'c-5', 'status' => 'created', 'award' => 'award-c-5'],
			],
			$result['results']
		);
	}//end testOnlyCheckedInCharactersAreAwarded()

	/**
	 * A refused write is reported per character and the others are still created.
	 *
	 * @return void
	 */
	public function testAPartialFailureIsReportedPerCharacter(): void {
		$result = $this->roster(
			[
				['character' => 'c-1', 'status' => 'checked-in'],
				['character' => 'c-2', 'status' => 'checked-in'],
			],
			'c-1'
		)->awardXpToAttendees('ev-sat', 3, 'Plot');

		$this->assertSame(['c-2'], array_column($this->saved, 'character'));
		$this->assertSame(
			[
				['character' => 'c-1', 'status' => 'failed', 'error' => 'Forbidden'],
				['character' => 'c-2', 'status' => 'created', 'award' => 'award-c-2'],
			],
			$result['results']
		);
	}//end testAPartialFailureIsReportedPerCharacter()

	/**
	 * Scenario "Empty roster refuses instead of silently succeeding".
	 *
	 * @return void
	 */
	public function testAnEventWithoutCheckInsIsRefusedAndNothingIsWritten(): void {
		$service = $this->roster([['character' => 'c-3', 'status' => 'no-show']]);

		try {
			$service->awardXpToAttendees('ev-sat', 5, 'x');
			$this->fail('An empty roster must be refused.');
		} catch (InvalidArgumentException $e) {
			$this->assertStringContainsString('Nobody is checked in', $e->getMessage());
		}
		$this->assertSame([], $this->saved);
	}//end testAnEventWithoutCheckInsIsRefusedAndNothingIsWritten()

	/**
	 * A non-positive amount and an unreadable event are refused before any read of the roster.
	 *
	 * @return void
	 */
	public function testABadAmountOrAnUnknownEventIsRefused(): void {
		$service = $this->roster([['character' => 'c-1', 'status' => 'checked-in']]);

		foreach ([['ev-sat', 0, 'at least 1'], ['ev-gone', 5, 'Event not found']] as [$event, $amount, $message]) {
			try {
				$service->awardXpToAttendees($event, $amount, 'x');
				$this->fail('Expected a refusal for ' . $event);
			} catch (InvalidArgumentException $e) {
				$this->assertStringContainsString($message, $e->getMessage());
			}
		}
		$this->assertSame([], $this->saved);
	}//end testABadAmountOrAnUnknownEventIsRefused()

	/**
	 * The character service over the fetcher double.
	 *
	 * @return CharacterService The service.
	 */
	private function characters(): CharacterService {
		return new CharacterService(
			objectFetcher: $this->fetcher,
			logger: $this->createMock(LoggerInterface::class),
			effectApplier: new EffectApplier()
		);
	}//end characters()

	/**
	 * Scenario "What's Alice's current sheet?": the calculated sheet without the GM's private notes, and no write.
	 *
	 * @return void
	 */
	public function testTheSheetIsTheCalculatedCharacterWithoutPrivateNotes(): void {
		$alice = ['id' => 'alice', 'name' => 'Alice', 'slNotesPrivate' => 'secret plot', 'notes' => 'public'];
		$this->fetcher->method('getObject')->with('character', 'alice')->willReturn($alice);
		$this->fetcher->method('getObjects')->willReturn([]);
		$this->fetcher->expects($this->never())->method('saveObject');

		$service = $this->characters();
		$sheet = $service->getCharacterSheet('alice');

		$expected = $service->calculateCharacter($alice);
		unset($expected['slNotesPrivate']);
		$this->assertSame($expected, $sheet);
		$this->assertArrayNotHasKey('slNotesPrivate', $sheet);
		$this->assertArrayHasKey('stats', $sheet);
	}//end testTheSheetIsTheCalculatedCharacterWithoutPrivateNotes()

	/**
	 * An unreadable character answers exactly like a missing one.
	 *
	 * @return void
	 */
	public function testAnUnreadableCharacterLooksLikeAMissingOne(): void {
		$this->fetcher->method('getObject')->willReturnCallback(
			static function (string $type, string $id): array {
				if ($id === 'hidden') {
					throw new RuntimeException('Forbidden');
				}
				return [];
			}
		);

		$service = $this->characters();

		$this->assertSame(['error' => 'not-found', 'character' => 'hidden'], $service->getCharacterSheet('hidden'));
		$this->assertSame(['error' => 'not-found', 'character' => 'nope'], $service->getCharacterSheet('nope'));
	}//end testAnUnreadableCharacterLooksLikeAMissingOne()

	/**
	 * The opt-in list names exactly the two classes, and they carry exactly the two tools with honest hints.
	 *
	 * @return void
	 */
	public function testExactlyTheTwoToolsAreExposedWithHonestHints(): void {
		$services = new LarpinqScannableServices();
		$this->assertInstanceOf(IMcpScannableServices::class, $services);
		$this->assertSame([EventRosterService::class, CharacterService::class], $services->getScannableServiceClasses());

		$tools = [];
		foreach ($services->getScannableServiceClasses() as $class) {
			foreach ((new ReflectionClass($class))->getMethods() as $method) {
				foreach ($method->getAttributes(McpTool::class) as $attribute) {
					$tools[$attribute->newInstance()->name] = $attribute->newInstance();
				}
			}
		}

		$this->assertSame(['awardXpToAttendees', 'getCharacterSheet'], array_keys($tools));

		$award = $tools['awardXpToAttendees'];
		$this->assertSame('create', $award->scope);
		$this->assertFalse($award->readOnlyHint);
		$this->assertFalse($award->destructiveHint);
		$this->assertFalse($award->idempotentHint, 'a retry creates awards again, so it must not claim idempotency');

		$sheet = $tools['getCharacterSheet'];
		$this->assertSame('read', $sheet->scope);
		$this->assertTrue($sheet->readOnlyHint);
	}//end testExactlyTheTwoToolsAreExposedWithHonestHints()
}//end class
