<?php

/**
 * The game master check on requirement overrides does not fail open.
 *
 * A game-rule check that throws (the custom field guard, the requirement
 * validator) lets the write through: a broken rule must not block every
 * character write. The check that only game masters author overrides is not
 * a game rule. It runs on its own, before the game rules, and when it cannot
 * decide it refuses the write.
 *
 * @category Test
 * @package  OCA\Larpinq\Tests\Unit\Listener
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/specs/skill-requirement-enforcement/spec.md
 */

declare(strict_types=1);

namespace OCA\Larpinq\Tests\Unit\Listener;

use OCA\Larpinq\Listener\CharacterRequirementListener;
use OCA\Larpinq\Service\CustomFieldGuard;
use OCA\Larpinq\Service\SkillRequirementService;
use OCA\OpenRegister\Db\ObjectEntity;
use OCA\OpenRegister\Event\ObjectUpdatingEvent;
use OCP\IAppConfig;
use OCP\IGroupManager;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * An object entity with a schema id and a payload.
 */
class OverrideCharacterEntity extends ObjectEntity {
	/**
	 * Build an entity for a schema id and payload.
	 *
	 * @param string              $schema The schema id.
	 * @param array<string,mixed> $data   The object payload.
	 */
	public function __construct(string $schema, array $data) {
		$this->schema = $schema;
		$this->object = $data;
	}//end __construct()
}//end class

/**
 * A character entity whose payload cannot be read.
 */
class UnreadableCharacterEntity extends ObjectEntity {
	/**
	 * Build an entity of a schema.
	 *
	 * @param string $schema The schema id.
	 */
	public function __construct(string $schema) {
		$this->schema = $schema;
	}//end __construct()

	/**
	 * The payload; it cannot be read.
	 *
	 * @return array<string,mixed> Never returns.
	 *
	 * @throws \RuntimeException Always.
	 */
	public function getObject(): array {
		throw new \RuntimeException('payload unreadable');
	}//end getObject()
}//end class

/**
 * Overrides stay game-master-only when a check throws.
 *
 * @covers \OCA\Larpinq\Listener\CharacterRequirementListener
 * @uses   \OCA\Larpinq\Tests\Unit\Listener\OverrideCharacterEntity
 * @uses   \OCA\Larpinq\Tests\Unit\Listener\UnreadableCharacterEntity
 */
class CharacterOverrideFailsClosedTest extends TestCase {

	private const BASE = ['name' => 'Mirela', 'skills' => ['s1']];

	/**
	 * The error lines the listener logged.
	 *
	 * @var string[]
	 */
	private array $logged = [];

	private const WITH_OVERRIDE = [
		'name' => 'Mirela',
		'skills' => ['s1'],
		'requirementOverrides' => [['skill' => 's2', 'reason' => 'story']],
	];

	/**
	 * A player's new override is refused even when the custom field guard throws.
	 *
	 * @return void
	 */
	public function testAPlayerOverrideIsRefusedWhenTheFieldGuardThrows(): void {
		$event = $this->send(uid: 'player1', fieldsThrow: true, validatorThrows: false, groupsThrow: false);

		self::assertTrue(condition: $event->isPropagationStopped());
		self::assertSame(expected: 'override_forbidden', actual: $event->getErrors()['requirementOverrides'][0]['code'] ?? null);
	}//end testAPlayerOverrideIsRefusedWhenTheFieldGuardThrows()

	/**
	 * A player's new override is refused when the requirement validator throws.
	 *
	 * @return void
	 */
	public function testAPlayerOverrideIsRefusedWhenTheValidatorThrows(): void {
		$event = $this->send(uid: 'player1', fieldsThrow: false, validatorThrows: true, groupsThrow: false);

		self::assertTrue(condition: $event->isPropagationStopped());
		self::assertSame(expected: 'override_forbidden', actual: $event->getErrors()['requirementOverrides'][0]['code'] ?? null);
	}//end testAPlayerOverrideIsRefusedWhenTheValidatorThrows()

	/**
	 * A signed-out writer's new override is refused when the field guard throws.
	 *
	 * @return void
	 */
	public function testASignedOutOverrideIsRefusedWhenTheFieldGuardThrows(): void {
		$event = $this->send(uid: null, fieldsThrow: true, validatorThrows: false, groupsThrow: false);

		self::assertTrue(condition: $event->isPropagationStopped());
		self::assertSame(expected: 'override_forbidden', actual: $event->getErrors()['requirementOverrides'][0]['code'] ?? null);
	}//end testASignedOutOverrideIsRefusedWhenTheFieldGuardThrows()

	/**
	 * When the game master check itself throws, the override is refused.
	 *
	 * @return void
	 */
	public function testAnOverrideIsRefusedWhenTheGameMasterCheckThrows(): void {
		$event = $this->send(uid: 'gm1', fieldsThrow: false, validatorThrows: false, groupsThrow: true);

		self::assertTrue(condition: $event->isPropagationStopped());
		self::assertSame(expected: 'override_unverifiable', actual: $event->getErrors()['requirementOverrides'][0]['code'] ?? null);
	}//end testAnOverrideIsRefusedWhenTheGameMasterCheckThrows()

	/**
	 * A game master's override still goes through when a game rule throws.
	 *
	 * @return void
	 */
	public function testAGameMasterOverrideStillPassesWhenTheFieldGuardThrows(): void {
		$event = $this->send(uid: 'gm1', fieldsThrow: true, validatorThrows: false, groupsThrow: false);

		self::assertFalse(condition: $event->isPropagationStopped());
		self::assertSame(expected: [], actual: $event->getErrors());
	}//end testAGameMasterOverrideStillPassesWhenTheFieldGuardThrows()

	/**
	 * A player's write that leaves the overrides alone still passes when a game rule throws.
	 *
	 * @return void
	 */
	public function testAPlayerWriteWithoutOverrideChangesStillPassesWhenTheFieldGuardThrows(): void {
		$event = $this->send(
			uid: 'player1',
			fieldsThrow: true,
			validatorThrows: false,
			groupsThrow: false,
			new: self::BASE + ['items' => ['i1']]
		);

		self::assertFalse(condition: $event->isPropagationStopped());
		self::assertSame(expected: [], actual: $event->getErrors());
	}//end testAPlayerWriteWithoutOverrideChangesStillPassesWhenTheFieldGuardThrows()

	/**
	 * A write on another schema is not checked for overrides.
	 *
	 * @return void
	 */
	public function testAWriteOnAnotherSchemaIsNotChecked(): void {
		$event = $this->send(uid: 'player1', fieldsThrow: false, validatorThrows: false, groupsThrow: false, schema: 'other-schema');

		self::assertFalse(condition: $event->isPropagationStopped());
		self::assertSame(expected: [], actual: $event->getErrors());
	}//end testAWriteOnAnotherSchemaIsNotChecked()

	/**
	 * A config that throws does not refuse writes: the fail-closed check covers characters only.
	 *
	 * @return void
	 */
	public function testAThrowingConfigDoesNotRefuseTheWrite(): void {
		$config = $this->createMock(originalClassName: IAppConfig::class);
		$config->method('getValueString')->willThrowException(new \RuntimeException('config store down'));
		$event = new ObjectUpdatingEvent(
			new OverrideCharacterEntity('other-app-schema', self::WITH_OVERRIDE),
			new OverrideCharacterEntity('other-app-schema', self::BASE)
		);
		$this->listener(config: $config, groups: $this->createMock(originalClassName: IGroupManager::class))->handle(event: $event);

		self::assertFalse(condition: $event->isPropagationStopped());
		self::assertSame(expected: [], actual: $event->getErrors());
	}//end testAThrowingConfigDoesNotRefuseTheWrite()

	/**
	 * A character whose payload cannot be read is refused, and the refusal is logged.
	 *
	 * @return void
	 */
	public function testAnUnreadableCharacterIsRefusedAndLogged(): void {
		$event = new ObjectUpdatingEvent(new UnreadableCharacterEntity('char-schema'), new OverrideCharacterEntity('char-schema', self::BASE));
		$this->listener(config: $this->characterConfig(), groups: $this->createMock(originalClassName: IGroupManager::class))->handle(event: $event);

		self::assertTrue(condition: $event->isPropagationStopped());
		self::assertSame(expected: 'override_unverifiable', actual: $event->getErrors()['requirementOverrides'][0]['code'] ?? null);
		self::assertSame(expected: ['Larpinq: requirement override check errored; refusing the write.'], actual: $this->logged);
	}//end testAnUnreadableCharacterIsRefusedAndLogged()

	/**
	 * A game master check that fails with an \Error refuses the override, and the refusal is logged.
	 *
	 * @return void
	 */
	public function testAnErrorInTheGameMasterCheckRefusesAndIsLogged(): void {
		$session = $this->createMock(originalClassName: IUserSession::class);
		$user = $this->createMock(originalClassName: IUser::class);
		$user->method('getUID')->willReturn('gm1');
		$session->method('getUser')->willReturn($user);
		$groups = $this->createMock(originalClassName: IGroupManager::class);
		$groups->method('isInGroup')->willThrowException(new \TypeError('group backend returned garbage'));
		$event = new ObjectUpdatingEvent(
			new OverrideCharacterEntity('char-schema', self::WITH_OVERRIDE),
			new OverrideCharacterEntity('char-schema', self::BASE)
		);
		$this->listener(config: $this->characterConfig(), groups: $groups, session: $session)->handle(event: $event);

		self::assertTrue(condition: $event->isPropagationStopped());
		self::assertSame(expected: 'override_unverifiable', actual: $event->getErrors()['requirementOverrides'][0]['code'] ?? null);
		self::assertSame(expected: ['Larpinq: requirement override check errored; refusing the write.'], actual: $this->logged);
	}//end testAnErrorInTheGameMasterCheckRefusesAndIsLogged()

	/**
	 * A config that names the character schema.
	 *
	 * @return IAppConfig The config.
	 */
	private function characterConfig(): IAppConfig {
		$config = $this->createMock(originalClassName: IAppConfig::class);
		$config->method('getValueString')->willReturnMap([['larpinq', 'character_schema', '', 'char-schema']]);

		return $config;
	}//end characterConfig()

	/**
	 * The listener over these doubles; its error log lines land in $this->logged.
	 *
	 * @param IAppConfig        $config  The config.
	 * @param IGroupManager     $groups  The group manager.
	 * @param IUserSession|null $session The session, or a signed-out one.
	 *
	 * @return CharacterRequirementListener The listener.
	 */
	private function listener(IAppConfig $config, IGroupManager $groups, ?IUserSession $session = null): CharacterRequirementListener {
		$logger = $this->createMock(originalClassName: LoggerInterface::class);
		$logger->method('error')->willReturnCallback(
			function (string $message): void {
				$this->logged[] = $message;
			}
		);
		$requirements = $this->createMock(originalClassName: SkillRequirementService::class);
		$requirements->method('validate')->willReturn(['valid' => true]);

		return new CharacterRequirementListener(
			requirementService: $requirements,
			config: $config,
			userSession: ($session ?? $this->createMock(originalClassName: IUserSession::class)),
			groupManager: $groups,
			logger: $logger,
			customFields: $this->createMock(originalClassName: CustomFieldGuard::class)
		);
	}//end listener()

	/**
	 * Send one update of a character through the listener.
	 *
	 * @param string|null          $uid             The signed-in user, or null.
	 * @param bool                 $fieldsThrow     Whether the custom field guard throws.
	 * @param bool                 $validatorThrows Whether the requirement validator throws.
	 * @param bool                 $groupsThrow     Whether the group manager throws.
	 * @param array<string, mixed> $new             The candidate character.
	 * @param string               $schema          The schema id of the written object.
	 *
	 * @return ObjectUpdatingEvent The event after the listener ran.
	 */
	private function send(
		?string $uid,
		bool $fieldsThrow,
		bool $validatorThrows,
		bool $groupsThrow,
		array $new = self::WITH_OVERRIDE,
		string $schema = 'char-schema',
	): ObjectUpdatingEvent {
		$fields = $this->createMock(originalClassName: CustomFieldGuard::class);
		if ($fieldsThrow === true) {
			$fields->method('check')->willThrowException(new \RuntimeException('field definitions unreadable'));
		}

		$requirements = $this->createMock(originalClassName: SkillRequirementService::class);
		if ($validatorThrows === true) {
			$requirements->method('validate')->willThrowException(new \RuntimeException('validator broke'));
		} else {
			$requirements->method('validate')->willReturn(['valid' => true]);
		}

		$config = $this->createMock(originalClassName: IAppConfig::class);
		$config->method('getValueString')->willReturnMap([['larpinq', 'character_schema', '', 'char-schema']]);

		$session = $this->createMock(originalClassName: IUserSession::class);
		$user = null;
		if ($uid !== null) {
			$user = $this->createMock(originalClassName: IUser::class);
			$user->method('getUID')->willReturn($uid);
		}

		$session->method('getUser')->willReturn($user);

		$groups = $this->createMock(originalClassName: IGroupManager::class);
		if ($groupsThrow === true) {
			$groups->method('isInGroup')->willThrowException(new \RuntimeException('group backend down'));
		} else {
			$groups->method('isInGroup')->willReturnCallback(
				fn (string $who, string $group): bool => $who === 'gm1' && $group === 'gamemasters'
			);
		}

		$listener = new CharacterRequirementListener(
			requirementService: $requirements,
			config: $config,
			userSession: $session,
			groupManager: $groups,
			logger: $this->createMock(originalClassName: LoggerInterface::class),
			customFields: $fields
		);

		$event = new ObjectUpdatingEvent(
			new OverrideCharacterEntity($schema, $new),
			new OverrideCharacterEntity($schema, self::BASE)
		);
		$listener->handle(event: $event);

		return $event;
	}//end send()
}//end class
