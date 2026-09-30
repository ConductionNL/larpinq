<?php

/**
 * A player's own stat sheet counts the XP awards a game master granted to
 * their character (DECISIONS row 30, event-xp-awards).
 *
 * xpAward is read by game masters and the record's owner only, and the owner
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
		$this->openRegister = $this->openRegister($this->permissionHandler($uid, ['larpers']), $uid, $blocks);

		$this->openRegister->seed('ability', 'gerrit', ['id' => 'ab-xp', 'name' => 'XP', 'base' => 0]);
		$this->openRegister->seed('character', 'anna', ['id' => '00000000-0000-4000-8000-00000000a001', 'name' => 'Brynja', 'ownerUid' => 'anna']);
		$this->openRegister->seed('character', 'bert', ['id' => '00000000-0000-4000-8000-00000000b002', 'name' => 'Oswin', 'ownerUid' => 'bert']);
		// The game master gerrit granted both awards, so he owns them.
		$this->openRegister->seed('xpaward', 'gerrit', ['id' => 'aw-1', 'character' => '00000000-0000-4000-8000-00000000a001', 'event' => 'ev-1', 'amount' => 20, 'reason' => 'attendance']);
		$this->openRegister->seed('xpaward', 'gerrit', ['id' => 'aw-2', 'character' => '00000000-0000-4000-8000-00000000a001', 'event' => 'ev-2', 'amount' => 5, 'reason' => 'plot']);
		$this->openRegister->seed('xpaward', 'gerrit', ['id' => 'aw-3', 'character' => '00000000-0000-4000-8000-00000000b002', 'event' => 'ev-1', 'amount' => 30, 'reason' => 'attendance']);

		$fetcher = $this->fetcherOver($this->openRegister);
		$session = $this->sessionOf($uid);

		return new CharacterService($fetcher, new NullLogger(), new EffectApplier(), new CharacterConnectionGuard($fetcher), $session);
	}//end engine()

	/**
	 * Control: OpenRegister's evaluator itself refuses the player the award,
	 * so a green result below comes from larpinq's own read, not a lax fake.
	 *
	 * @return void
	 */
	public function testTheEvaluatorHidesTheGameMastersAwardFromThePlayer(): void {
		$this->requireOpenRegister();
		$this->engine('anna');
		$this->assertFalse($this->openRegister->readable('xpaward', $this->openRegister->objects['xpaward']['aw-1'], true));
		$this->assertTrue($this->openRegister->readable('character', $this->openRegister->objects['character']['00000000-0000-4000-8000-00000000a001'], true));
	}//end testTheEvaluatorHidesTheGameMastersAwardFromThePlayer()

	/**
	 * The owner's stat sheet counts both awards on her character, with their audit.
	 *
	 * @return void
	 */
	public function testThePlayerCountsTheAwardsOnHerOwnCharacter(): void {
		$this->requireOpenRegister();
		$engine = $this->engine('anna');

		$stats = $engine->calculateCharacter(['id' => '00000000-0000-4000-8000-00000000a001', 'name' => 'Brynja', 'ownerUid' => 'anna'])['stats'];

		$this->assertSame(25, $stats['ab-xp']['value']);
		$this->assertSame(['aw-1', 'aw-2'], array_map(static fn (array $entry): string => $entry['award']['id'], $stats['ab-xp']['audit']));
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
		$engine = $this->engine('anna');

		$stats = $engine->calculateCharacter(['id' => '00000000-0000-4000-8000-00000000b002', 'name' => 'Oswin', 'ownerUid' => 'anna'])['stats'];

		$this->assertSame(0, $stats['ab-xp']['value']);
		$this->assertSame([], array_values(array_filter($this->openRegister->reads, static fn (array $read): bool => $read['rbac'] === false)));
	}//end testAnotherPlayersAwardsStayHidden()
}//end class
