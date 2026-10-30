<?php

/**
 * Unit tests for CharacterRequirementListener.
 *
 * Uses the real OpenRegister pre-write event classes (tests/stubs/openregister)
 * so the listener is exercised without the OpenRegister app installed.
 *
 * @category Test
 * @package  OCA\Larpinq\Tests\Unit\Listener
 * @author   Ruben Linde <ruben@larpingapp.com>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://larpingapp.com
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
 * An OpenRegister object entity with a schema and a payload.
 *
 * Extends the ObjectEntity stub (tests/stubs/openregister/Db/ObjectEntity.php),
 * which carries the real class's properties and accessors, so the listener
 * meets the same `getSchema()` / `getObject()` it meets in production and the
 * events it receives are the real OpenRegister event classes. The earlier
 * hand-made fake declared its own event classes and entity shape; a fake shaped
 * to what the caller calls, rather than to what the collaborator is, cannot fail
 * for the reason the suite exists (larpinq#308).
 */
class FakeObjectEntity extends ObjectEntity {
	/**
	 * Build an entity for a schema id and payload.
	 *
	 * @param string $schema The schema id.
	 * @param array<string,mixed> $data The object payload.
	 */
	public function __construct(string $schema, array $data) {
		$this->schema = $schema;
		$this->object = $data;
	}
}

/**
 * Tests for the character-write veto listener.
 */
class CharacterRequirementListenerTest extends TestCase {
	private const SCHEMA_ID = 'char-schema-uuid';

	private LoggerInterface $logger;

	protected function setUp(): void {
		parent::setUp();
		$this->logger = $this->createMock(LoggerInterface::class);
	}

	private function makeListener(
		array $skills = [],
		bool $isGm = true,
		?string $uid = 'gm1',
		array $fields = [],
	): CharacterRequirementListener {
		$fetcher = $this->createMock(RegisterObjectFetcher::class);
		$fetcher->method('getObjects')->willReturnCallback(function (string $type) use ($skills, $fields): array {
			return match ($type) {
				'skill' => $skills,
				'characterfield' => $fields,
				default => [],
			};
		});
		$engine = new CharacterService($fetcher, $this->logger, new EffectApplier());
		$idList = new IdListNormaliser();
		$requirementService = new SkillRequirementService(
			$engine,
			$fetcher,
			$this->logger,
			new SkillRequirementChecker($idList),
			$idList
		);

		$config = $this->createMock(IAppConfig::class);
		$config->method('getValueString')->willReturn(self::SCHEMA_ID);

		$userSession = $this->createMock(IUserSession::class);
		if ($uid !== null) {
			$user = $this->createMock(IUser::class);
			$user->method('getUID')->willReturn($uid);
			$userSession->method('getUser')->willReturn($user);
		} else {
			$userSession->method('getUser')->willReturn(null);
		}

		$groupManager = $this->createMock(IGroupManager::class);
		$groupManager->method('isInGroup')->willReturn($isGm);

		return new CharacterRequirementListener(
			$requirementService,
			$config,
			$userSession,
			$groupManager,
			$this->logger,
			new CustomFieldGuard($fetcher, new CustomFieldValidator())
		);
	}

	/**
	 * Scenario "Text in a number field" (characters-custom-fields REQ-CCF-004):
	 * the write is refused with an error on key `scars`.
	 *
	 * @return void
	 */
	public function testRejectsTextInANumberField(): void {
		$fields = [['id' => 'f3', 'key' => 'scars', 'fieldType' => 'number', 'visibility' => 'owner']];
		$listener = $this->makeListener(fields: $fields);
		$old = new FakeObjectEntity(self::SCHEMA_ID, ['name' => 'Mirela', 'customFields' => ['scars' => 2]]);
		$new = new FakeObjectEntity(self::SCHEMA_ID, ['name' => 'Mirela', 'customFields' => ['scars' => 'many']]);
		$event = new ObjectUpdatingEvent($new, $old);

		$listener->handle($event);

		$this->assertTrue($event->isPropagationStopped());
		$this->assertSame('custom_field_invalid', $event->getErrors()['code']);
		$this->assertSame(['scars'], array_keys($event->getErrors()['fields']));
	}

	/**
	 * A valid value passes, and does not trigger the skill checks.
	 *
	 * @return void
	 */
	public function testAllowsAValidCustomField(): void {
		$fields = [['id' => 'f3', 'key' => 'scars', 'fieldType' => 'number', 'visibility' => 'owner']];
		$listener = $this->makeListener(fields: $fields);
		$old = new FakeObjectEntity(self::SCHEMA_ID, ['name' => 'Mirela', 'customFields' => ['scars' => 2]]);
		$new = new FakeObjectEntity(self::SCHEMA_ID, ['name' => 'Mirela', 'customFields' => ['scars' => 3]]);
		$event = new ObjectUpdatingEvent($new, $old);

		$listener->handle($event);

		$this->assertFalse($event->isPropagationStopped());
	}

	public function testRejectsCreateWithUnmetPrerequisite(): void {
		$skills = [
			['id' => 'basic', 'name' => 'Basic'],
			['id' => 'adv', 'name' => 'Advanced', 'requiredSkills' => ['basic']],
		];
		$listener = $this->makeListener(skills: $skills);
		$entity = new FakeObjectEntity(self::SCHEMA_ID, ['skills' => ['adv']]);
		$event = new ObjectCreatingEvent($entity);

		$listener->handle($event);

		$this->assertTrue($event->isPropagationStopped());
		$this->assertSame('requirements_not_met', $event->getErrors()['code']);
	}

	public function testAllowsCreateWhenPrerequisiteMet(): void {
		$skills = [
			['id' => 'basic', 'name' => 'Basic'],
			['id' => 'adv', 'name' => 'Advanced', 'requiredSkills' => ['basic']],
		];
		$listener = $this->makeListener(skills: $skills);
		$entity = new FakeObjectEntity(self::SCHEMA_ID, ['skills' => ['basic', 'adv']]);
		$event = new ObjectCreatingEvent($entity);

		$listener->handle($event);

		$this->assertFalse($event->isPropagationStopped());
	}

	public function testIgnoresNonCharacterSchema(): void {
		$listener = $this->makeListener(skills: []);
		$entity = new FakeObjectEntity('other-schema', ['skills' => ['adv']]);
		$event = new ObjectCreatingEvent($entity);

		$listener->handle($event);

		$this->assertFalse($event->isPropagationStopped());
	}

	public function testDiffScopedUnrelatedEditPasses(): void {
		// Pre-existing unmet state, but the write only changes the name.
		$skills = [
			['id' => 'basic', 'name' => 'Basic'],
			['id' => 'adv', 'name' => 'Advanced', 'requiredSkills' => ['basic']],
		];
		$listener = $this->makeListener(skills: $skills);
		$old = new FakeObjectEntity(self::SCHEMA_ID, ['skills' => ['adv'], 'name' => 'Old']);
		$new = new FakeObjectEntity(self::SCHEMA_ID, ['skills' => ['adv'], 'name' => 'New']);
		$event = new ObjectUpdatingEvent($new, $old);

		$listener->handle($event);

		$this->assertFalse($event->isPropagationStopped());
	}

	public function testOverrideAcceptedFromGm(): void {
		$skills = [
			['id' => 'basic', 'name' => 'Basic'],
			['id' => 'adv', 'name' => 'Advanced', 'requiredSkills' => ['basic']],
		];
		$listener = $this->makeListener(skills: $skills, isGm: true, uid: 'gm1');
		$entity = new FakeObjectEntity(self::SCHEMA_ID, [
			'skills' => ['adv'],
			'requirementOverrides' => [['skill' => 'adv', 'reason' => 'respec']],
		]);
		$event = new ObjectCreatingEvent($entity);

		$listener->handle($event);

		$this->assertFalse($event->isPropagationStopped());
	}

	public function testOverrideRejectedFromNonGm(): void {
		$skills = [['id' => 'adv', 'name' => 'Advanced']];
		$listener = $this->makeListener(skills: $skills, isGm: false, uid: 'player1');
		$entity = new FakeObjectEntity(self::SCHEMA_ID, [
			'skills' => ['adv'],
			'requirementOverrides' => [['skill' => 'adv', 'reason' => 'sneaky']],
		]);
		$event = new ObjectCreatingEvent($entity);

		$listener->handle($event);

		$this->assertTrue($event->isPropagationStopped());
		$this->assertSame('override_forbidden', $event->getErrors()['requirementOverrides'][0]['code']);
	}

	public function testEmptyReasonOverrideRejected(): void {
		$skills = [['id' => 'adv', 'name' => 'Advanced']];
		$listener = $this->makeListener(skills: $skills, isGm: true, uid: 'gm1');
		$entity = new FakeObjectEntity(self::SCHEMA_ID, [
			'skills' => ['adv'],
			'requirementOverrides' => [['skill' => 'adv', 'reason' => '']],
		]);
		$event = new ObjectCreatingEvent($entity);

		$listener->handle($event);

		$this->assertTrue($event->isPropagationStopped());
		$this->assertSame('override_reason_required', $event->getErrors()['requirementOverrides'][0]['code']);
	}
}
