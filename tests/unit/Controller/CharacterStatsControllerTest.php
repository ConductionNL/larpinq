<?php

/**
 * CharacterStatsControllerTest.
 *
 * Drives GET /api/characters/{id}/stats through the REAL stat engine,
 * presenter and budget resolver over a fake register, on the design's seeded
 * example: strength 10 + Swordsmanship 3 + Iron shield 1 = 14, and XP 40
 * earned, 10 spent, 30 left.
 *
 * @category Test
 * @package  OCA\Larpinq\Tests\Unit\Controller
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/specs/character-management/spec.md
 */

declare(strict_types=1);

namespace OCA\Larpinq\Tests\Unit\Controller;

use OCA\Larpinq\Controller\CharacterStatsController;
use OCA\Larpinq\Service\CharacterService;
use OCA\Larpinq\Service\CharacterStatSheet;
use OCA\Larpinq\Service\CharacterStatsPresenter;
use OCA\Larpinq\Service\CharacterXpHistory;
use OCA\Larpinq\Service\EffectApplier;
use OCA\Larpinq\Service\IdListNormaliser;
use OCA\Larpinq\Service\RegisterObjectFetcher;
use OCA\Larpinq\Service\SkillRequirementChecker;
use OCA\Larpinq\Service\SkillRequirementService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\IRequest;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * REQ-CSP-001 to REQ-CSP-004.
 */
class CharacterStatsControllerTest extends TestCase {

	private const MIRELA = '11111111-1111-4111-8111-111111111111';

	/** @var array<string,SkillRequirementService> */
	private array $services = [];

	/**
	 * A controller over the seeded register.
	 *
	 * @param array<string,mixed>|null $character The character, or null when unreadable.
	 * @param array<int,mixed>|null    $logs      The character's audit trail, or null when unreadable.
	 *
	 * @return CharacterStatsController The controller.
	 */
	private function controller(?array $character, ?array $logs = []): CharacterStatsController {
		$fetcher = $this->createMock(originalClassName: RegisterObjectFetcher::class);
		$fetcher->method('getObject')->willReturnCallback(
			static function (string $objectType, string $id) use ($character): array {
				if ($objectType === 'skill') {
					return ['id' => $id, 'name' => ['sk-herb' => 'Kruidenkunde', 'sk-sword' => 'Swordsmanship'][$id] ?? ''];
				}

				if ($character === null) {
					throw new DoesNotExistException('not readable');
				}

				return $character;
			}
		);
		$fetcher->method('getObjectLogs')->willReturnCallback(
			static function () use ($logs): array {
				if ($logs === null) {
					throw new \RuntimeException('audit trail unavailable');
				}

				return $logs;
			}
		);
		$fetcher->method('getObjects')->willReturnCallback(
			static fn (string $type): array => match ($type) {
				'ability' => [
					['id' => 'str', 'name' => 'Strength', 'base' => 10],
					['id' => 'xp', 'name' => 'XP', 'base' => 0],
					['id' => 'dex', 'name' => 'Dexterity', 'base' => 8],
				],
				'effect' => [
					['id' => 'e-blade', 'name' => 'Blade training', 'modifier' => 3, 'modification' => 'positive', 'abilities' => ['str']],
					['id' => 'e-cost', 'name' => 'Skill cost', 'modifier' => 10, 'modification' => 'negative', 'abilities' => ['xp']],
					['id' => 'e-shield', 'name' => 'Shield weight', 'modifier' => 1, 'modification' => 'positive', 'abilities' => ['str']],
					['id' => 'e-herb-cost', 'name' => 'Herb lore cost', 'modifier' => 6, 'modification' => 'negative', 'abilities' => ['xp']],
				],
				'skill' => [
					['id' => 'sk-sword', 'name' => 'Swordsmanship', 'effects' => ['e-blade', 'e-cost']],
					['id' => 'sk-herb', 'name' => 'Kruidenkunde', 'effects' => ['e-herb-cost']],
				],
				'item' => [['id' => 'it-shield', 'name' => 'Iron shield', 'effects' => ['e-shield']]],
				'xpAward' => [
					['id' => 'aw-1', 'character' => self::MIRELA, 'amount' => 20, 'reason' => 'Summer Siege'],
					['id' => 'aw-2', 'character' => self::MIRELA, 'amount' => 20, 'reason' => 'Winter Moot'],
				],
				default => [],
			}
		);
		$logger = $this->createMock(originalClassName: LoggerInterface::class);
		$engine = new CharacterService(objectFetcher: $fetcher, logger: $logger, effectApplier: new EffectApplier());
		$requirements = new SkillRequirementService(
			characterService: $engine,
			objectFetcher: $fetcher,
			logger: $logger,
			checker: new SkillRequirementChecker(idList: new IdListNormaliser()),
			idList: new IdListNormaliser()
		);
		$this->services['requirements'] = $requirements;

		return new CharacterStatsController(
			appName: 'larpinq',
			request: $this->createMock(originalClassName: IRequest::class),
			objectFetcher: $fetcher,
			statSheet: new CharacterStatSheet(
				characterService: $engine,
				requirementService: $requirements,
				presenter: new CharacterStatsPresenter(),
				xpHistory: new CharacterXpHistory(objectFetcher: $fetcher, characterService: $engine, requirementService: $requirements, logger: $logger)
			)
		);
	}//end controller()

	/**
	 * The seeded character.
	 *
	 * @return array<string,mixed> Mirela.
	 */
	private function mirela(): array {
		return ['id' => self::MIRELA, 'name' => 'Mirela the Wanderer', 'skills' => ['sk-sword'], 'items' => ['it-shield']];
	}//end mirela()

	/**
	 * A game master sees why strength is 14.
	 *
	 * @return void
	 */
	public function testStrengthIsFourteenWithBothSources(): void {
		$sheet = $this->controller(character: $this->mirela())->show(id: self::MIRELA)->getData();
		$strength = $sheet['abilities'][0];

		$this->assertSame(expected: ['Strength', 10, 14], actual: [$strength['name'], $strength['base'], $strength['final']]);
		$this->assertSame(
			expected: [['skill', 'Swordsmanship', 'Blade training', 3], ['item', 'Iron shield', 'Shield weight', 1]],
			actual: array_map(static fn (array $m): array => [$m['source'], $m['sourceName'], $m['effectName'], $m['change']], $strength['modifiers'])
		);
	}//end testStrengthIsFourteenWithBothSources()

	/**
	 * An untouched ability has no modifiers.
	 *
	 * @return void
	 */
	public function testAnUntouchedAbilityHasNoModifiers(): void {
		$dex = $this->controller(character: $this->mirela())->show(id: self::MIRELA)->getData()['abilities'][2];

		$this->assertSame(expected: ['Dexterity', 8, 8, []], actual: [$dex['name'], $dex['base'], $dex['final'], $dex['modifiers']]);
	}//end testAnUntouchedAbilityHasNoModifiers()

	/**
	 * XP 40 earned, 10 spent, 30 left, and left is what the budget check reads.
	 *
	 * @return void
	 */
	public function testXpMatchesTheBudgetCheck(): void {
		$controller = $this->controller(character: $this->mirela());
		$xp = $controller->show(id: self::MIRELA)->getData()['xp'];
		$budget = $this->services['requirements']->validate(candidate: $this->mirela(), oldCharacter: $this->mirela())['budget'];

		$this->assertSame(expected: ['ability' => 'xp', 'earned' => 40, 'spent' => 10, 'left' => 30, 'history' => []], actual: $xp);
		$this->assertSame(expected: $budget['value'], actual: $xp['left']);
		$this->assertSame(expected: $budget['ability'], actual: $xp['ability']);
	}//end testXpMatchesTheBudgetCheck()

	/**
	 * Someone without access gets 404, not the sheet.
	 *
	 * @return void
	 */
	public function testAnUnreadableCharacterIsNotFound(): void {
		$response = $this->controller(character: null)->show(id: self::MIRELA);

		$this->assertSame(expected: 404, actual: $response->getStatus());
		$this->assertArrayNotHasKey(key: 'abilities', array: $response->getData());
	}//end testAnUnreadableCharacterIsNotFound()

	/**
	 * The audit row of a game master removing herb lore from Mirela.
	 *
	 * @return array<string,mixed> The row.
	 */
	private function herbLoreRemoved(): array {
		return [
			'action' => 'update',
			'changed' => ['skills' => ['old' => ['sk-sword', 'sk-herb'], 'new' => ['sk-sword']]],
			'user' => 'anna',
			'userName' => 'Anna',
			'created' => '2026-11-14T09:00:00+00:00',
		];
	}//end herbLoreRemoved()

	/**
	 * REQ-CSP-006: a removal yields one refund line with the skill, its XP, the date and who removed it.
	 *
	 * @return void
	 */
	public function testARemovedSkillShowsOneRefundLine(): void {
		$logs = [
			['action' => 'create', 'changed' => ['skills' => ['sk-sword', 'sk-herb']], 'userName' => 'Anna', 'created' => '2026-11-01T09:00:00+00:00'],
			['action' => 'update', 'changed' => ['name' => ['old' => 'Mirela', 'new' => 'Mirela the Wanderer']], 'userName' => 'Anna', 'created' => '2026-11-02T09:00:00+00:00'],
			$this->herbLoreRemoved(),
		];

		$history = $this->controller(character: $this->mirela(), logs: $logs)->show(id: self::MIRELA)->getData()['xp']['history'];

		$this->assertSame(
			expected: [[
				'type' => 'refund',
				'skillId' => 'sk-herb',
				'skillName' => 'Kruidenkunde',
				'xp' => 6,
				'date' => '2026-11-14T09:00:00+00:00',
				'user' => 'Anna',
			]],
			actual: $history
		);
	}//end testARemovedSkillShowsOneRefundLine()

	/**
	 * REQ-CSP-005 and REQ-CSP-006: reading the history adds no XP; XP left is the engine's figure.
	 *
	 * @return void
	 */
	public function testReadingTheHistoryAddsNoXp(): void {
		$without = $this->controller(character: $this->mirela(), logs: [])->show(id: self::MIRELA)->getData()['xp'];
		$with = $this->controller(character: $this->mirela(), logs: [$this->herbLoreRemoved(), $this->herbLoreRemoved()])->show(id: self::MIRELA)->getData()['xp'];

		$this->assertSame(expected: [30, 10, 40], actual: [$with['left'], $with['spent'], $with['earned']]);
		$this->assertSame(expected: [$without['left'], $without['spent']], actual: [$with['left'], $with['spent']]);
		$this->assertCount(expectedCount: 2, haystack: $with['history']);
	}//end testReadingTheHistoryAddsNoXp()

	/**
	 * REQ-CSP-005: the refund is the skill's XP cost, the difference it makes to XP left.
	 *
	 * @return void
	 */
	public function testTheRefundIsWhatTheSkillCosts(): void {
		$logs = [['action' => 'update', 'changed' => ['skills' => ['old' => ['sk-sword'], 'new' => []]], 'userName' => 'Anna', 'created' => '2026-11-14T10:00:00+00:00']];
		$character = ['id' => self::MIRELA, 'name' => 'Mirela the Wanderer', 'skills' => [], 'items' => ['it-shield']];

		$sheet = $this->controller(character: $character, logs: $logs)->show(id: self::MIRELA)->getData();

		$this->assertSame(expected: 40, actual: $sheet['xp']['left']);
		$this->assertSame(expected: [['sk-sword', 'Swordsmanship', 10]], actual: array_map(static fn (array $l): array => [$l['skillId'], $l['skillName'], $l['xp']], $sheet['xp']['history']));
	}//end testTheRefundIsWhatTheSkillCosts()

	/**
	 * An unreadable audit trail leaves the history empty and the sheet intact.
	 *
	 * @return void
	 */
	public function testAnUnreadableHistoryIsEmptyNotAnError(): void {
		$response = $this->controller(character: $this->mirela(), logs: null)->show(id: self::MIRELA);

		$this->assertSame(expected: 200, actual: $response->getStatus());
		$this->assertSame(expected: [], actual: $response->getData()['xp']['history']);
		$this->assertSame(expected: 30, actual: $response->getData()['xp']['left']);
	}//end testAnUnreadableHistoryIsEmptyNotAnError()
}//end class
