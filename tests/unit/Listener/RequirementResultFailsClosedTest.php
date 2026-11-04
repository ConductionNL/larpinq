<?php

/**
 * Only an explicit valid result lets a character write pass the requirement check.
 *
 * SkillRequirementService answers `valid: true` or `valid: false`. A result
 * without `valid`, or with any other value, is not a pass: the listener
 * refuses the write rather than reading the gap as an allow.
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
use OCP\IUserSession;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * An object entity with a schema id and a payload.
 */
class RequirementCharacterEntity extends ObjectEntity {
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
 * The requirement result decides only when it says so.
 *
 * @covers \OCA\Larpinq\Listener\CharacterRequirementListener
 * @uses   \OCA\Larpinq\Tests\Unit\Listener\RequirementCharacterEntity
 */
class RequirementResultFailsClosedTest extends TestCase {

	/**
	 * Requirement results and whether the write must be refused.
	 *
	 * @return array<string, array{0: array<string, mixed>, 1: bool}> The cases.
	 */
	public static function results(): array {
		return [
			'valid true passes' => [['valid' => true], false],
			'valid false refuses' => [['valid' => false], true],
			'no valid key refuses' => [['requirements' => []], true],
			'empty result refuses' => [[], true],
			'valid null refuses' => [['valid' => null], true],
			'valid 0 refuses' => [['valid' => 0], true],
			'valid 1 refuses' => [['valid' => 1], true],
			'valid "true" refuses' => [['valid' => 'true'], true],
			'valid "false" refuses' => [['valid' => 'false'], true],
		];
	}//end results()

	/**
	 * The write is refused unless the result is an explicit valid: true.
	 *
	 * @param array<string, mixed> $result  The requirement result.
	 * @param bool                 $refused Whether the write must be refused.
	 *
	 * @return void
	 */
	#[DataProvider('results')]
	public function testOnlyAnExplicitValidResultPasses(array $result, bool $refused): void {
		$requirements = $this->createMock(originalClassName: SkillRequirementService::class);
		$requirements->method('validate')->willReturn($result);

		$config = $this->createMock(originalClassName: IAppConfig::class);
		$config->method('getValueString')->willReturnMap([['larpinq', 'character_schema', '', 'char-schema']]);

		$listener = new CharacterRequirementListener(
			requirementService: $requirements,
			config: $config,
			userSession: $this->createMock(originalClassName: IUserSession::class),
			groupManager: $this->createMock(originalClassName: IGroupManager::class),
			logger: $this->createMock(originalClassName: LoggerInterface::class),
			customFields: $this->createMock(originalClassName: CustomFieldGuard::class)
		);

		$event = new ObjectUpdatingEvent(
			new RequirementCharacterEntity('char-schema', ['name' => 'Mirela', 'skills' => ['s1', 's2']]),
			new RequirementCharacterEntity('char-schema', ['name' => 'Mirela', 'skills' => ['s1']])
		);
		$listener->handle(event: $event);

		self::assertSame(expected: $refused, actual: $event->isPropagationStopped());
		if ($refused === true) {
			self::assertSame(expected: 'requirements_not_met', actual: ($event->getErrors()['code'] ?? null));
		}
	}//end testOnlyAnExplicitValidResultPasses()
}//end class
