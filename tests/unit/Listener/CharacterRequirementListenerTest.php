<?php

/**
 * Unit tests for CharacterRequirementListener.
 *
 * Defines lightweight fakes for the optional OpenRegister event/entity classes
 * so the listener can be exercised without the OpenRegister app installed.
 *
 * @category Test
 * @package  OCA\LarpingApp\Tests\Unit\Listener
 * @author   Ruben Linde <ruben@larpingapp.com>
 * @license  AGPL-3.0-or-later https://www.gnu.org/licenses/agpl-3.0.en.html
 * @link     https://larpingapp.com
 */

declare(strict_types=1);

namespace OCA\OpenRegister\Event;

use OCP\EventDispatcher\Event;

if (class_exists('OCA\OpenRegister\Event\ObjectCreatingEvent') === false) {
    /**
     * Test double for OpenRegister's ObjectCreatingEvent.
     */
    class ObjectCreatingEvent extends Event
    {
        private array $errors = [];
        private bool $stopped = false;

        public function __construct(private object $object)
        {
        }

        public function getObject(): object
        {
            return $this->object;
        }

        public function stopPropagation(): void
        {
            $this->stopped = true;
        }

        public function isPropagationStopped(): bool
        {
            return $this->stopped;
        }

        public function setErrors(array $errors): void
        {
            $this->errors = $errors;
        }

        public function getErrors(): array
        {
            return $this->errors;
        }
    }

    /**
     * Test double for OpenRegister's ObjectUpdatingEvent.
     */
    class ObjectUpdatingEvent extends Event
    {
        private array $errors = [];
        private bool $stopped = false;

        public function __construct(private object $newObject, private ?object $oldObject = null)
        {
        }

        public function getNewObject(): object
        {
            return $this->newObject;
        }

        public function getOldObject(): ?object
        {
            return $this->oldObject;
        }

        public function stopPropagation(): void
        {
            $this->stopped = true;
        }

        public function isPropagationStopped(): bool
        {
            return $this->stopped;
        }

        public function setErrors(array $errors): void
        {
            $this->errors = $errors;
        }

        public function getErrors(): array
        {
            return $this->errors;
        }
    }
}//end if

namespace OCA\LarpingApp\Tests\Unit\Listener;

use OCA\LarpingApp\Listener\CharacterRequirementListener;
use OCA\LarpingApp\Service\CharacterService;
use OCA\LarpingApp\Service\EffectApplier;
use OCA\LarpingApp\Service\IdListNormaliser;
use OCA\LarpingApp\Service\RegisterObjectFetcher;
use OCA\LarpingApp\Service\SkillRequirementChecker;
use OCA\LarpingApp\Service\SkillRequirementService;
use OCA\OpenRegister\Event\ObjectCreatingEvent;
use OCA\OpenRegister\Event\ObjectUpdatingEvent;
use OCP\IAppConfig;
use OCP\IGroupManager;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Minimal object-entity fake matching the subset of the OR ObjectEntity API
 * the listener uses (getSchema + getObject).
 */
class FakeObjectEntity
{
    public function __construct(private string $schema, private array $data)
    {
    }

    public function getSchema(): string
    {
        return $this->schema;
    }

    public function getObject(): array
    {
        return $this->data;
    }
}

/**
 * Tests for the character-write veto listener.
 */
class CharacterRequirementListenerTest extends TestCase
{
    private const SCHEMA_ID = 'char-schema-uuid';

    private LoggerInterface $logger;

    protected function setUp(): void
    {
        parent::setUp();
        $this->logger = $this->createMock(LoggerInterface::class);
    }

    private function makeListener(
        array $skills = [],
        bool $isGm = true,
        ?string $uid = 'gm1'
    ): CharacterRequirementListener {
        $fetcher = $this->createMock(RegisterObjectFetcher::class);
        $fetcher->method('getObjects')->willReturnCallback(function (string $type) use ($skills): array {
            return match ($type) {
                'skill' => $skills,
                default => [],
            };
        });
        $engine             = new CharacterService($fetcher, $this->logger, new EffectApplier());
        $idList             = new IdListNormaliser();
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
            $this->logger
        );
    }

    public function testRejectsCreateWithUnmetPrerequisite(): void
    {
        $skills = [
            ['id' => 'basic', 'name' => 'Basic'],
            ['id' => 'adv', 'name' => 'Advanced', 'requiredSkills' => ['basic']],
        ];
        $listener = $this->makeListener(skills: $skills);
        $entity   = new FakeObjectEntity(self::SCHEMA_ID, ['skills' => ['adv']]);
        $event    = new ObjectCreatingEvent($entity);

        $listener->handle($event);

        $this->assertTrue($event->isPropagationStopped());
        $this->assertSame('requirements_not_met', $event->getErrors()['code']);
    }

    public function testAllowsCreateWhenPrerequisiteMet(): void
    {
        $skills = [
            ['id' => 'basic', 'name' => 'Basic'],
            ['id' => 'adv', 'name' => 'Advanced', 'requiredSkills' => ['basic']],
        ];
        $listener = $this->makeListener(skills: $skills);
        $entity   = new FakeObjectEntity(self::SCHEMA_ID, ['skills' => ['basic', 'adv']]);
        $event    = new ObjectCreatingEvent($entity);

        $listener->handle($event);

        $this->assertFalse($event->isPropagationStopped());
    }

    public function testIgnoresNonCharacterSchema(): void
    {
        $listener = $this->makeListener(skills: []);
        $entity   = new FakeObjectEntity('other-schema', ['skills' => ['adv']]);
        $event    = new ObjectCreatingEvent($entity);

        $listener->handle($event);

        $this->assertFalse($event->isPropagationStopped());
    }

    public function testDiffScopedUnrelatedEditPasses(): void
    {
        // Pre-existing unmet state, but the write only changes the name.
        $skills = [
            ['id' => 'basic', 'name' => 'Basic'],
            ['id' => 'adv', 'name' => 'Advanced', 'requiredSkills' => ['basic']],
        ];
        $listener = $this->makeListener(skills: $skills);
        $old      = new FakeObjectEntity(self::SCHEMA_ID, ['skills' => ['adv'], 'name' => 'Old']);
        $new      = new FakeObjectEntity(self::SCHEMA_ID, ['skills' => ['adv'], 'name' => 'New']);
        $event    = new ObjectUpdatingEvent($new, $old);

        $listener->handle($event);

        $this->assertFalse($event->isPropagationStopped());
    }

    public function testOverrideAcceptedFromGm(): void
    {
        $skills = [
            ['id' => 'basic', 'name' => 'Basic'],
            ['id' => 'adv', 'name' => 'Advanced', 'requiredSkills' => ['basic']],
        ];
        $listener = $this->makeListener(skills: $skills, isGm: true, uid: 'gm1');
        $entity   = new FakeObjectEntity(self::SCHEMA_ID, [
            'skills' => ['adv'],
            'requirementOverrides' => [['skill' => 'adv', 'reason' => 'respec']],
        ]);
        $event = new ObjectCreatingEvent($entity);

        $listener->handle($event);

        $this->assertFalse($event->isPropagationStopped());
    }

    public function testOverrideRejectedFromNonGm(): void
    {
        $skills   = [['id' => 'adv', 'name' => 'Advanced']];
        $listener = $this->makeListener(skills: $skills, isGm: false, uid: 'player1');
        $entity   = new FakeObjectEntity(self::SCHEMA_ID, [
            'skills' => ['adv'],
            'requirementOverrides' => [['skill' => 'adv', 'reason' => 'sneaky']],
        ]);
        $event = new ObjectCreatingEvent($entity);

        $listener->handle($event);

        $this->assertTrue($event->isPropagationStopped());
        $this->assertSame('override_forbidden', $event->getErrors()['requirementOverrides'][0]['code']);
    }

    public function testEmptyReasonOverrideRejected(): void
    {
        $skills   = [['id' => 'adv', 'name' => 'Advanced']];
        $listener = $this->makeListener(skills: $skills, isGm: true, uid: 'gm1');
        $entity   = new FakeObjectEntity(self::SCHEMA_ID, [
            'skills' => ['adv'],
            'requirementOverrides' => [['skill' => 'adv', 'reason' => '']],
        ]);
        $event = new ObjectCreatingEvent($entity);

        $listener->handle($event);

        $this->assertTrue($event->isPropagationStopped());
        $this->assertSame('override_reason_required', $event->getErrors()['requirementOverrides'][0]['code']);
    }
}
