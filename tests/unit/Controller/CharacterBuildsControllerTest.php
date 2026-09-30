<?php

/**
 * CharacterBuildsControllerTest.
 *
 * Drives GET /api/builds/{id}/report through the REAL stat engine,
 * requirement service and presenter over a fake register, on the design's
 * seed: Mirela has 40 XP from two awards and Swordsmanship (10 XP). Build
 * "Alchemist path" (Herbalism and Potion brewing, 10 XP each) passes; build
 * "Winter campaign" (Swordsmanship and Riding, 10 + 35 XP) is 5 XP short.
 *
 * @category Test
 * @package  OCA\Larpinq\Tests\Unit\Controller
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/specs/character-builds/spec.md
 */

declare(strict_types=1);

namespace OCA\Larpinq\Tests\Unit\Controller;

use OCA\Larpinq\Controller\CharacterBuildsController;
use OCA\Larpinq\Service\CharacterService;
use OCA\Larpinq\Service\CharacterStatsPresenter;
use OCA\Larpinq\Service\EffectApplier;
use OCA\Larpinq\Service\IdListNormaliser;
use OCA\Larpinq\Service\RegisterObjectFetcher;
use OCA\Larpinq\Service\SkillRequirementChecker;
use OCA\Larpinq\Service\SkillRequirementService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * REQ-CMB-002 and REQ-CMB-004.
 */
class CharacterBuildsControllerTest extends TestCase {

	private const MIRELA = '11111111-1111-4111-8111-111111111111';
	private const ALCHEMIST = '22222222-2222-4222-8222-222222222222';
	private const WINTER = '33333333-3333-4333-8333-333333333333';

	/**
	 * The objects the calling user may read, by id.
	 *
	 * @var array<string, array<string, mixed>>
	 */
	private array $readable = [];

	/**
	 * The fake register the engine reads from.
	 *
	 * @var RegisterObjectFetcher&\PHPUnit\Framework\MockObject\MockObject
	 */
	private RegisterObjectFetcher $fetcher;

	/**
	 * The requirement service the controller runs, for comparing reports.
	 *
	 * @var SkillRequirementService
	 */
	private SkillRequirementService $requirements;

	/**
	 * Mirela and her two builds, all readable.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->readable = [
			self::MIRELA => $this->mirela(),
			self::ALCHEMIST => ['id' => self::ALCHEMIST, 'character' => self::MIRELA, 'name' => 'Alchemist path', 'purpose' => 'plan', 'skills' => ['sk-herb', 'sk-potion'], 'items' => [], 'conditions' => []],
			self::WINTER => ['id' => self::WINTER, 'character' => ['id' => self::MIRELA], 'name' => 'Winter campaign', 'purpose' => 'other-game', 'skills' => ['sk-sword', 'sk-ride'], 'items' => ['it-shield']],
		];
	}//end setUp()

	/**
	 * The seeded character.
	 *
	 * @return array<string, mixed> Mirela.
	 */
	private function mirela(): array {
		return ['id' => self::MIRELA, 'name' => 'Mirela the Wanderer', 'skills' => ['sk-sword'], 'items' => [], 'conditions' => []];
	}//end mirela()

	/**
	 * A controller over the seeded register, acting as a user.
	 *
	 * @param bool $gameMaster Whether the caller is a game master.
	 *
	 * @return CharacterBuildsController The controller.
	 */
	private function controller(bool $gameMaster = false): CharacterBuildsController {
		$this->fetcher = $this->createMock(RegisterObjectFetcher::class);
		$this->fetcher->method('getObject')->willReturnCallback(
			function (string $type, string $id): array {
				if (isset($this->readable[$id]) === false) {
					throw new DoesNotExistException('not readable');
				}

				return $this->readable[$id];
			}
		);
		$this->fetcher->method('getObjects')->willReturnCallback(
			static fn (string $type): array => match ($type) {
				'ability' => [['id' => 'str', 'name' => 'Strength', 'base' => 10], ['id' => 'xp', 'name' => 'XP', 'base' => 0]],
				'effect' => [
					['id' => 'e-cost10', 'name' => 'Skill cost', 'modifier' => 10, 'modification' => 'negative', 'abilities' => ['xp']],
					['id' => 'e-brew', 'name' => 'Brewing cost', 'modifier' => 10, 'modification' => 'negative', 'abilities' => ['xp']],
					['id' => 'e-cost35', 'name' => 'Riding cost', 'modifier' => 35, 'modification' => 'negative', 'abilities' => ['xp']],
					['id' => 'e-shield', 'name' => 'Shield weight', 'modifier' => 1, 'modification' => 'positive', 'abilities' => ['str']],
				],
				'skill' => [
					['id' => 'sk-sword', 'name' => 'Swordsmanship', 'effects' => ['e-cost10']],
					['id' => 'sk-herb', 'name' => 'Herbalism', 'effects' => ['e-cost10']],
					['id' => 'sk-potion', 'name' => 'Potion brewing', 'effects' => ['e-brew']],
					['id' => 'sk-ride', 'name' => 'Riding', 'effects' => ['e-cost35']],
				],
				'item' => [['id' => 'it-shield', 'name' => 'Iron shield', 'effects' => ['e-shield']]],
				'xpAward' => [
					['id' => 'aw-1', 'character' => self::MIRELA, 'amount' => 20, 'reason' => 'Summer Siege'],
					['id' => 'aw-2', 'character' => self::MIRELA, 'amount' => 20, 'reason' => 'Winter Moot'],
				],
				default => [],
			}
		);
		// REQ-CMB-002: the check MUST NOT write anything.
		$this->fetcher->expects($this->never())->method('saveObject');
		$this->fetcher->expects($this->never())->method('deleteObject');

		$logger = $this->createMock(LoggerInterface::class);
		$engine = new CharacterService($this->fetcher, $logger, new EffectApplier());
		$this->requirements = new SkillRequirementService($engine, $this->fetcher, $logger, new SkillRequirementChecker(new IdListNormaliser()), new IdListNormaliser());

		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('anna');
		$session = $this->createMock(IUserSession::class);
		$session->method('getUser')->willReturn($user);
		$groups = $this->createMock(IGroupManager::class);
		$groups->method('isInGroup')->willReturnCallback(static fn (string $uid, string $group): bool => $gameMaster === true && $group === 'gamemasters');
		$groups->method('isAdmin')->willReturn(false);

		return new CharacterBuildsController('larpinq', $this->createMock(IRequest::class), $this->fetcher, $engine, $this->requirements, new CharacterStatsPresenter(), $session, $groups);
	}//end controller()

	/**
	 * A build within the budget passes, and the report is the requirement
	 * check of the character with the build's lists.
	 *
	 * @return void
	 */
	public function testTheAlchemistPathPasses(): void {
		$data = $this->controller()->report(id: self::ALCHEMIST)->getData();

		$candidate = array_merge($this->mirela(), ['skills' => ['sk-herb', 'sk-potion'], 'items' => [], 'conditions' => []]);
		$this->assertSame($this->requirements->validate(candidate: $candidate, oldCharacter: $this->mirela()), $data['report']);
		$this->assertTrue($data['report']['valid']);
		$this->assertSame(['ability' => 'xp', 'earned' => 40, 'spent' => 20, 'left' => 20], $data['stats']['xp']);
		$this->assertSame(['id' => self::ALCHEMIST, 'name' => 'Alchemist path'], $data['build']);
		$this->assertSame(['id' => self::MIRELA, 'name' => 'Mirela the Wanderer'], $data['character']);
	}//end testTheAlchemistPathPasses()

	/**
	 * Scenario "A build that costs too much": short by 5 XP, and the character
	 * is unchanged.
	 *
	 * @return void
	 */
	public function testTheWinterCampaignIsFiveXpShort(): void {
		$data = $this->controller()->report(id: self::WINTER)->getData();

		$this->assertFalse($data['report']['valid']);
		$this->assertSame(['ability' => 'xp', 'value' => -5, 'shortfall' => 5, 'ok' => false], $data['report']['budget']);
		// The build's item counts on the sheet: strength 10 + 1.
		$this->assertSame(11, $data['stats']['abilities'][0]['final']);
		$this->assertSame($this->mirela(), $this->readable[self::MIRELA]);
	}//end testTheWinterCampaignIsFiveXpShort()

	/**
	 * Scenario "Another player looks": a build the caller cannot read is 404,
	 * and so is a build whose character the caller cannot read.
	 *
	 * @return void
	 */
	public function testAnUnreadableBuildIsNotFound(): void {
		unset($this->readable[self::ALCHEMIST]);
		$response = $this->controller()->report(id: self::ALCHEMIST);
		$this->assertSame(404, $response->getStatus());
		$this->assertArrayNotHasKey('report', $response->getData());

		unset($this->readable[self::MIRELA]);
		$this->assertSame(404, $this->controller()->report(id: self::WINTER)->getStatus());
	}//end testAnUnreadableBuildIsNotFound()

	/**
	 * Only game masters get the Apply action.
	 *
	 * @return void
	 */
	public function testOnlyGameMastersApply(): void {
		$this->assertSame(['allowed' => true], $this->controller(gameMaster: true)->access()->getData());
		$this->assertSame(['allowed' => false], $this->controller()->access()->getData());
	}//end testOnlyGameMastersApply()
}//end class
