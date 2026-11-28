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
use PHPUnit\Framework\Attributes\DataProvider;
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
	 * A throwing config refuses a write that changes overrides, and the refusal is logged.
	 *
	 * @return void
	 */
	public function testAThrowingConfigRefusesAnOverrideWrite(): void {
		$event = $this->sendWithThrowingConfig(new: self::WITH_OVERRIDE);

		self::assertTrue(condition: $event->isPropagationStopped());
		self::assertSame(expected: 'override_unverifiable', actual: $event->getErrors()['requirementOverrides'][0]['code'] ?? null);
		self::assertSame(expected: ['Larpinq: requirement override check errored; refusing the write.'], actual: $this->logged);
	}//end testAThrowingConfigRefusesAnOverrideWrite()

	/**
	 * A throwing config lets a write that leaves the overrides alone through, as for any other schema.
	 *
	 * @return void
	 */
	public function testAThrowingConfigLetsAWriteWithoutOverridesThrough(): void {
		$event = $this->sendWithThrowingConfig(new: self::BASE + ['items' => ['i1']]);

		self::assertFalse(condition: $event->isPropagationStopped());
		self::assertSame(expected: [], actual: $event->getErrors());
	}//end testAThrowingConfigLetsAWriteWithoutOverridesThrough()

	/**
	 * A throwing config lets a write through that keeps the stored overrides unchanged.
	 *
	 * @return void
	 */
	public function testAThrowingConfigLetsUnchangedOverridesThrough(): void {
		$event = $this->sendWithThrowingConfig(new: self::WITH_OVERRIDE + ['items' => ['i1']], old: self::WITH_OVERRIDE);

		self::assertFalse(condition: $event->isPropagationStopped());
		self::assertSame(expected: [], actual: $event->getErrors());
	}//end testAThrowingConfigLetsUnchangedOverridesThrough()

	/**
	 * Stored and candidate overrides that differ only in a way a loose compare misses, or where the candidate drops them.
	 *
	 * @return array<string,array{0:mixed,1:mixed}>
	 */
	public static function changedOverrides(): array {
		$story = [['skill' => 's2', 'reason' => 'story']];

		return [
			'keys of an override reordered' => [$story, [['reason' => 'story', 'skill' => 's2']]],
			'reason changed from string to int' => [[['skill' => 's2', 'reason' => '1']], [['skill' => 's2', 'reason' => 1]]],
			'override removed' => [$story, []],
			'overrides set to null' => [$story, null],
		];
	}//end changedOverrides()

	/**
	 * Every change of the stored overrides counts as a change, so a config failure refuses it.
	 *
	 * @param mixed $stored    The stored requirementOverrides.
	 * @param mixed $candidate The candidate requirementOverrides.
	 *
	 * @return void
	 */
	#[DataProvider('changedOverrides')]
	public function testEveryOverrideChangeIsRefusedOnAConfigFailure(mixed $stored, mixed $candidate): void {
		$event = $this->sendWithThrowingConfig(
			new: self::BASE + ['requirementOverrides' => $candidate],
			old: self::BASE + ['requirementOverrides' => $stored]
		);

		self::assertTrue(condition: $event->isPropagationStopped());
		self::assertSame(expected: 'override_unverifiable', actual: $event->getErrors()['requirementOverrides'][0]['code'] ?? null);
	}//end testEveryOverrideChangeIsRefusedOnAConfigFailure()

	/**
	 * Schema ids that only look like the configured one are not character writes.
	 *
	 * @return array<string,array{0: string, 1: string}> The configured id and the written id.
	 */
	public static function lookalikeSchemas(): array {
		return [
			'upper case' => ['char-schema', 'CHAR-SCHEMA'],
			'mixed case' => ['char-schema', 'Char-Schema'],
			'trailing space' => ['char-schema', 'char-schema '],
			'leading space' => ['char-schema', ' char-schema'],
			'configured id as prefix' => ['char-schema', 'char-schema-2'],
			'written id as prefix' => ['char-schema', 'char'],
			'numeric with leading zero' => ['17', '017'],
			'numeric as float' => ['17', '17.0'],
			'no schema configured, empty id' => ['', ''],
		];
	}//end lookalikeSchemas()

	/**
	 * A player's override on a lookalike schema id is not checked as a character write.
	 *
	 * @param string $configured The configured character schema id.
	 * @param string $written    The schema id of the written object.
	 *
	 * @return void
	 */
	#[DataProvider('lookalikeSchemas')]
	public function testALookalikeSchemaIdIsNotACharacter(string $configured, string $written): void {
		$config = $this->createMock(originalClassName: IAppConfig::class);
		$config->method('getValueString')->willReturnMap([['larpinq', 'character_schema', '', $configured]]);
		$event = new ObjectUpdatingEvent(
			new OverrideCharacterEntity($written, self::WITH_OVERRIDE),
			new OverrideCharacterEntity($written, self::BASE)
		);
		$this->listener(config: $config, groups: $this->createMock(originalClassName: IGroupManager::class))->handle(event: $event);

		self::assertFalse(condition: $event->isPropagationStopped());
		self::assertSame(expected: [], actual: $event->getErrors());
	}//end testALookalikeSchemaIdIsNotACharacter()

	/**
	 * The exact configured schema id is a character write, so a player's override is refused.
	 *
	 * @return void
	 */
	public function testTheExactNumericSchemaIdIsACharacter(): void {
		$config = $this->createMock(originalClassName: IAppConfig::class);
		$config->method('getValueString')->willReturnMap([['larpinq', 'character_schema', '', '17']]);
		$event = new ObjectUpdatingEvent(
			new OverrideCharacterEntity('17', self::WITH_OVERRIDE),
			new OverrideCharacterEntity('17', self::BASE)
		);
		$this->listener(config: $config, groups: $this->createMock(originalClassName: IGroupManager::class))->handle(event: $event);

		self::assertTrue(condition: $event->isPropagationStopped());
		self::assertSame(expected: 'override_forbidden', actual: $event->getErrors()['requirementOverrides'][0]['code'] ?? null);
	}//end testTheExactNumericSchemaIdIsACharacter()

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
	 * Send one player update of a character through a listener whose config throws.
	 *
	 * @param array<string, mixed> $new The candidate character.
	 * @param array<string, mixed> $old The stored character.
	 *
	 * @return ObjectUpdatingEvent The event after the listener ran.
	 */
	private function sendWithThrowingConfig(array $new, array $old = self::BASE): ObjectUpdatingEvent {
		$config = $this->createMock(originalClassName: IAppConfig::class);
		$config->method('getValueString')->willThrowException(new \RuntimeException('config store down'));
		$event = new ObjectUpdatingEvent(
			new OverrideCharacterEntity('char-schema', $new),
			new OverrideCharacterEntity('char-schema', $old)
		);
		$this->listener(config: $config, groups: $this->createMock(originalClassName: IGroupManager::class))->handle(event: $event);

		return $event;
	}//end sendWithThrowingConfig()

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
