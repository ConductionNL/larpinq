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
use OCA\Larpinq\Service\CharacterStatsPresenter;
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
	 *
	 * @return CharacterStatsController The controller.
	 */
	private function controller(?array $character): CharacterStatsController {
		$fetcher = $this->createMock(RegisterObjectFetcher::class);
		$fetcher->method('getObject')->willReturnCallback(
			static function () use ($character): array {
				if ($character === null) {
					throw new DoesNotExistException('not readable');
				}

				return $character;
			}
		);
		$fetcher->method('getObjects')->willReturnCallback(
			static fn (string $type): array => match ($type) {
				'ability' => [['id' => 'str', 'name' => 'Strength', 'base' => 10], ['id' => 'xp', 'name' => 'XP', 'base' => 0], ['id' => 'dex', 'name' => 'Dexterity', 'base' => 8]],
				'effect' => [
					['id' => 'e-blade', 'name' => 'Blade training', 'modifier' => 3, 'modification' => 'positive', 'abilities' => ['str']],
					['id' => 'e-cost', 'name' => 'Skill cost', 'modifier' => 10, 'modification' => 'negative', 'abilities' => ['xp']],
					['id' => 'e-shield', 'name' => 'Shield weight', 'modifier' => 1, 'modification' => 'positive', 'abilities' => ['str']],
				],
				'skill' => [['id' => 'sk-sword', 'name' => 'Swordsmanship', 'effects' => ['e-blade', 'e-cost']]],
				'item' => [['id' => 'it-shield', 'name' => 'Iron shield', 'effects' => ['e-shield']]],
				'xpAward' => [
					['id' => 'aw-1', 'character' => self::MIRELA, 'amount' => 20, 'reason' => 'Summer Siege'],
					['id' => 'aw-2', 'character' => self::MIRELA, 'amount' => 20, 'reason' => 'Winter Moot'],
				],
				default => [],
			}
		);
		$logger = $this->createMock(LoggerInterface::class);
		$engine = new CharacterService($fetcher, $logger, new EffectApplier());
		$requirements = new SkillRequirementService($engine, $fetcher, $logger, new SkillRequirementChecker(new IdListNormaliser()), new IdListNormaliser());
		$this->services['requirements'] = $requirements;

		return new CharacterStatsController('larpinq', $this->createMock(IRequest::class), $fetcher, $engine, $requirements, new CharacterStatsPresenter());
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

		$this->assertSame(['Strength', 10, 14], [$strength['name'], $strength['base'], $strength['final']]);
		$this->assertSame(
			[['skill', 'Swordsmanship', 'Blade training', 3], ['item', 'Iron shield', 'Shield weight', 1]],
			array_map(static fn (array $m): array => [$m['source'], $m['sourceName'], $m['effectName'], $m['change']], $strength['modifiers'])
		);
	}//end testStrengthIsFourteenWithBothSources()

	/**
	 * An untouched ability has no modifiers.
	 *
	 * @return void
	 */
	public function testAnUntouchedAbilityHasNoModifiers(): void {
		$dex = $this->controller(character: $this->mirela())->show(id: self::MIRELA)->getData()['abilities'][2];

		$this->assertSame(['Dexterity', 8, 8, []], [$dex['name'], $dex['base'], $dex['final'], $dex['modifiers']]);
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

		$this->assertSame(['ability' => 'xp', 'earned' => 40, 'spent' => 10, 'left' => 30], $xp);
		$this->assertSame($budget['value'], $xp['left']);
		$this->assertSame($budget['ability'], $xp['ability']);
	}//end testXpMatchesTheBudgetCheck()

	/**
	 * Someone without access gets 404, not the sheet.
	 *
	 * @return void
	 */
	public function testAnUnreadableCharacterIsNotFound(): void {
		$response = $this->controller(character: null)->show(id: self::MIRELA);

		$this->assertSame(404, $response->getStatus());
		$this->assertArrayNotHasKey('abilities', $response->getData());
	}//end testAnUnreadableCharacterIsNotFound()
}//end class
