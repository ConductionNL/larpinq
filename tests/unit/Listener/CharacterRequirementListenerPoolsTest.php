<?php

/**
 * The character-write veto reports every overspent pool (REQ-RRS-002).
 *
 * Uses the real OpenRegister pre-write event classes (tests/stubs/openregister)
 * and the real requirement engine; only the object reads, the app config, the
 * session and the group manager are doubles.
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @category Test
 * @package  OCA\Larpinq\Tests\Unit\Listener
 *
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @link https://larpingapp.com
 *
 * @spec openspec/changes/rules-rulebook-and-spell-lists/specs/rulebook/spec.md#requirement-each-list-draws-on-its-own-pool-req-rrs-002
 */

declare(strict_types=1);

namespace OCA\Larpinq\Tests\Unit\Listener;

use OCA\Larpinq\Listener\CharacterRequirementListener;
use OCA\Larpinq\Service\CharacterService;
use OCA\Larpinq\Service\CustomFieldGuard;
use OCA\Larpinq\Service\CustomFieldValidator;
use OCA\Larpinq\Service\EffectApplier;
use OCA\Larpinq\Service\IdListNormaliser;
use OCA\Larpinq\Service\RegisterObjectFetcher;
use OCA\Larpinq\Service\SkillRequirementChecker;
use OCA\Larpinq\Service\SkillRequirementService;
use OCA\OpenRegister\Db\ObjectEntity;
use OCA\OpenRegister\Event\ObjectCreatingEvent;
use OCA\OpenRegister\Event\ObjectUpdatingEvent;
use OCP\IAppConfig;
use OCP\IGroupManager;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Sela has 30 XP and 2 mana. Firebolt costs 3 mana, Ward 2 mana, Feat 40 XP.
 */
class CharacterRequirementListenerPoolsTest extends TestCase {
	private const SCHEMA_ID = 'char-schema-uuid';

	/**
	 * The listener over Sela's world, for a game master.
	 *
	 * @return CharacterRequirementListener
	 */
	private function listener(): CharacterRequirementListener {
		$objects = [
			'ability' => [['id' => 'xp', 'name' => 'XP', 'base' => 30], ['id' => 'mana', 'name' => 'Mana', 'base' => 2]],
			'effect' => [
				['id' => 'mana-3', 'modifier' => 3, 'modification' => 'negative', 'abilities' => ['mana']],
				['id' => 'mana-2', 'modifier' => 2, 'modification' => 'negative', 'abilities' => ['mana']],
				['id' => 'xp-40', 'modifier' => 40, 'modification' => 'negative', 'abilities' => ['xp']],
			],
			'skill' => [
				['id' => 'firebolt', 'name' => 'Firebolt', 'list' => 'arcane', 'effects' => ['mana-3']],
				['id' => 'ward', 'name' => 'Ward', 'list' => 'arcane', 'effects' => ['mana-2']],
				['id' => 'feat', 'name' => 'Feat', 'effects' => ['xp-40']],
			],
			'skillList' => [['id' => 'arcane', 'name' => 'Arcane spells', 'costAbility' => 'mana']],
		];
		$logger = $this->createMock(LoggerInterface::class);
		$fetcher = $this->createMock(RegisterObjectFetcher::class);
		$fetcher->method('getObjects')->willReturnCallback(static fn (string $type): array => ($objects[$type] ?? []));
		$idList = new IdListNormaliser();

		$config = $this->createMock(IAppConfig::class);
		$config->method('getValueString')->willReturn(self::SCHEMA_ID);
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('gm1');
		$session = $this->createMock(IUserSession::class);
		$session->method('getUser')->willReturn($user);
		$groups = $this->createMock(IGroupManager::class);
		$groups->method('isInGroup')->willReturn(true);

		return new CharacterRequirementListener(
			requirementService: new SkillRequirementService(
				characterService: new CharacterService(objectFetcher: $fetcher, logger: $logger, effectApplier: new EffectApplier()),
				objectFetcher: $fetcher,
				logger: $logger,
				checker: new SkillRequirementChecker(idList: $idList),
				idList: $idList
			),
			config: $config,
			userSession: $session,
			groupManager: $groups,
			logger: $logger,
			customFields: new CustomFieldGuard(objectFetcher: $fetcher, validator: new CustomFieldValidator())
		);
	}

	/**
	 * A character entity with these skills.
	 *
	 * @param list<string> $skills The skill ids.
	 *
	 * @return ObjectEntity
	 */
	private static function character(array $skills): ObjectEntity {
		return new class(self::SCHEMA_ID, ['skills' => $skills]) extends ObjectEntity {
			/**
			 * @param string               $schema The schema id.
			 * @param array<string, mixed> $data   The payload.
			 */
			public function __construct(string $schema, array $data) {
				$this->schema = $schema;
				$this->object = $data;
			}
		};
	}

	/**
	 * Out of mana only: refused with the Mana pool and the pool message, no XP block.
	 *
	 * @return void
	 */
	public function testOutOfManaIsRefusedWithThePool(): void {
		$event = new ObjectCreatingEvent(self::character(skills: ['firebolt']));

		$this->listener()->handle($event);

		$errors = $event->getErrors();
		$this->assertTrue($event->isPropagationStopped());
		$this->assertSame('requirements_not_met', $errors['code']);
		$this->assertSame('Not enough points in a pool and/or unmet skill requirements.', $errors['message']);
		$this->assertSame([['ability' => 'mana', 'value' => -1, 'shortfall' => 1, 'ok' => false]], $errors['budgets']);
		$this->assertArrayNotHasKey('budget', $errors);
		$this->assertSame([], $errors['requirements']);
	}

	/**
	 * Out of XP only: the XP block and its message, and no pool list on top of it.
	 *
	 * @return void
	 */
	public function testOutOfXpOnlyKeepsTheXpMessage(): void {
		$event = new ObjectCreatingEvent(self::character(skills: ['feat']));

		$this->listener()->handle($event);

		$errors = $event->getErrors();
		$this->assertTrue($event->isPropagationStopped());
		$this->assertSame('Insufficient XP and/or unmet skill requirements.', $errors['message']);
		$this->assertSame(['ability' => 'xp', 'value' => -10, 'shortfall' => 10, 'ok' => false], $errors['budget']);
		$this->assertArrayNotHasKey('budgets', $errors);
	}

	/**
	 * Out of both: the XP block and both pools, with the pool message.
	 *
	 * @return void
	 */
	public function testOutOfXpAndManaReportsBoth(): void {
		$event = new ObjectCreatingEvent(self::character(skills: ['feat', 'firebolt']));

		$this->listener()->handle($event);

		$errors = $event->getErrors();
		$this->assertSame('Not enough points in a pool and/or unmet skill requirements.', $errors['message']);
		$this->assertSame(['ability' => 'xp', 'value' => -10, 'shortfall' => 10, 'ok' => false], $errors['budget']);
		$this->assertSame(['xp', 'mana'], array_column($errors['budgets'], 'ability'));
		$this->assertSame([10, 1], array_column($errors['budgets'], 'shortfall'));
	}

	/**
	 * An affordable spell passes the veto.
	 *
	 * @return void
	 */
	public function testAnAffordableSpellPasses(): void {
		$event = new ObjectCreatingEvent(self::character(skills: ['ward']));

		$this->listener()->handle($event);

		$this->assertFalse($event->isPropagationStopped());
	}

	/**
	 * An update that adds an unaffordable spell is refused on the pool.
	 *
	 * @return void
	 */
	public function testAnUpdateAddingAnUnaffordableSpellIsRefused(): void {
		$event = new ObjectUpdatingEvent(self::character(skills: ['ward', 'firebolt']), self::character(skills: ['ward']));

		$this->listener()->handle($event);

		$errors = $event->getErrors();
		$this->assertTrue($event->isPropagationStopped());
		$this->assertSame([['ability' => 'mana', 'value' => -3, 'shortfall' => 3, 'ok' => false]], $errors['budgets']);
	}
}//end class
