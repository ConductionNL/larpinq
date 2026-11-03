<?php

/**
 * A player's own stat sheet counts the XP awards a game master granted to
 * their character (DECISIONS row 30, event-xp-awards).
 *
 * The xpAward schema is read by game masters and the record's owner only, and the owner
 * of an award is the game master who granted it. Read as the player, OpenRegister
 * hides every award, so the player's own XP, and the XP budget their skill
 * choices are checked against, would count nothing. Players see their own
 * records through the app's pages: larpinq checks that the caller owns the
 * character (CharacterConnectionGuard), then reads that character's awards
 * with the app's authority.
 *
 * The read verdicts come from OpenRegister's REAL evaluator, loaded from
 * `OPENREGISTER_LIB` or the `openregister` app beside this one; without one
 * the test is skipped and says so.
 *
 * @category Test
 * @package  OCA\Larpinq\Tests\Unit\Service
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/specs/event-xp-awards/spec.md
 */

declare(strict_types=1);

namespace OCA\Larpinq\Tests\Unit\Service;

require_once __DIR__ . '/RealEvaluatorRegister.php';

use Composer\Autoload\ClassLoader;
use OCA\Larpinq\Service\CharacterConnectionGuard;
use OCA\Larpinq\Service\CharacterService;
use OCA\Larpinq\Service\EffectApplier;
use OCA\Larpinq\Service\ConfigFileLoaderService;
use OCA\Larpinq\Service\RegisterObjectFetcher;
use OCP\App\IAppManager;
use OCP\IAppConfig;
use OCP\IGroupManager;
use OCP\IUser;
use OCP\IUserManager;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\NullLogger;
use ReflectionClass;

/**
 * The owner of a character reads that character's XP awards; nobody else does.
 */
#[\PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses]
class OwnXpAwardsReadTest extends TestCase {
	use RealEvaluatorRegister;

	/**
	 * The fake OpenRegister: objects per schema, read through the real evaluator.
	 *
	 * @var object
	 */
	private object $openRegister;

	/**
	 * The stat engine as larpinq wires it, reading as the signed-in player.
	 *
	 * @param string $uid The signed-in player.
	 *
	 * @return CharacterService The engine.
	 */
	private function engine(string $uid): CharacterService {
		$blocks = $this->authorizationBlocks();
		$this->openRegister = $this->openRegister(handler: $this->permissionHandler(uid: $uid, groups: ['larpers']), uid: $uid, blocks: $blocks);

		$this->openRegister->seed('ability', 'gerrit', ['id' => 'ab-xp', 'name' => 'XP', 'base' => 0]);
		$this->openRegister->seed('character', 'anna', ['id' => '00000000-0000-4000-8000-00000000a001', 'name' => 'Brynja', 'ownerUid' => 'anna']);
		$this->openRegister->seed('character', 'bert', ['id' => '00000000-0000-4000-8000-00000000b002', 'name' => 'Oswin', 'ownerUid' => 'bert']);
		// The game master gerrit granted both awards, so he owns them.
		$this->openRegister->seed(
			'xpaward',
			'gerrit',
			['id' => 'aw-1', 'character' => '00000000-0000-4000-8000-00000000a001', 'event' => 'ev-1', 'amount' => 20, 'reason' => 'attendance']
		);
		$this->openRegister->seed(
			'xpaward',
			'gerrit',
			['id' => 'aw-2', 'character' => '00000000-0000-4000-8000-00000000a001', 'event' => 'ev-2', 'amount' => 5, 'reason' => 'plot']
		);
		$this->openRegister->seed(
			'xpaward',
			'gerrit',
			['id' => 'aw-3', 'character' => '00000000-0000-4000-8000-00000000b002', 'event' => 'ev-1', 'amount' => 30, 'reason' => 'attendance']
		);

		$fetcher = $this->fetcherOver(openRegister: $this->openRegister);
		$session = $this->sessionOf(uid: $uid);

		return new CharacterService(
			objectFetcher: $fetcher,
			logger: new NullLogger(),
			effectApplier: new EffectApplier(),
			connectionGuard: new CharacterConnectionGuard(objectFetcher: $fetcher),
			userSession: $session
		);
	}//end engine()

	/**
	 * Control: OpenRegister's evaluator itself refuses the player the award,
	 * so a green result below comes from larpinq's own read, not a lax fake.
	 *
	 * @return void
	 */
	public function testTheEvaluatorHidesTheGameMastersAwardFromThePlayer(): void {
		$this->requireOpenRegister();
		$this->engine(uid: 'anna');
		$this->assertFalse(condition: $this->openRegister->readable('xpaward', $this->openRegister->objects['xpaward']['aw-1'], true));
		$this->assertTrue(
			condition: $this->openRegister->readable('character', $this->openRegister->objects['character']['00000000-0000-4000-8000-00000000a001'], true)
		);
	}//end testTheEvaluatorHidesTheGameMastersAwardFromThePlayer()

	/**
	 * The owner's stat sheet counts both awards on her character, with their audit.
	 *
	 * @return void
	 */
	public function testThePlayerCountsTheAwardsOnHerOwnCharacter(): void {
		$this->requireOpenRegister();
		$engine = $this->engine(uid: 'anna');

		$stats = $engine->calculateCharacter(['id' => '00000000-0000-4000-8000-00000000a001', 'name' => 'Brynja', 'ownerUid' => 'anna'])['stats'];

		$this->assertSame(expected: 25, actual: $stats['ab-xp']['value']);
		$this->assertSame(
			expected: ['aw-1', 'aw-2'],
			actual: array_map(static fn (array $entry): string => $entry['award']['id'], $stats['ab-xp']['audit'])
		);
	}//end testThePlayerCountsTheAwardsOnHerOwnCharacter()

	/**
	 * Another player's character gets none of its awards read with the app's
	 * authority: the ownership check comes from the stored character, not the
	 * payload, so claiming ownerUid in a candidate does not help.
	 *
	 * @return void
	 */
	public function testAnotherPlayersAwardsStayHidden(): void {
		$this->requireOpenRegister();
		$engine = $this->engine(uid: 'anna');

		$stats = $engine->calculateCharacter(['id' => '00000000-0000-4000-8000-00000000b002', 'name' => 'Oswin', 'ownerUid' => 'anna'])['stats'];

		$this->assertSame(expected: 0, actual: $stats['ab-xp']['value']);
		$this->assertSame(
			expected: [],
			actual: array_values(array_filter($this->openRegister->reads, static fn (array $read): bool => $read['rbac'] === false))
		);
	}//end testAnotherPlayersAwardsStayHidden()
}//end class
