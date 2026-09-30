<?php

/**
 * A portal visitor's own player profile (players-self-signup, REQ-PSS-001..003)
 * and the owner of a character created through the portal.
 *
 * @category Test
 * @package  OCA\Larpinq\Tests\Unit\Listener
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/changes/players-self-signup/specs/portal-contribution/spec.md
 */

declare(strict_types=1);

namespace OCA\Larpinq\Tests\Unit\Listener;

use OCA\Larpinq\Listener\PortalProfileListener;
use OCA\Larpinq\Service\ConfigFileLoaderService;
use OCA\Larpinq\Service\RegisterObjectFetcher;
use OCA\OpenRegister\Db\ObjectEntity;
use OCA\OpenRegister\Event\ObjectCreatedEvent;
use OCA\OpenRegister\Event\ObjectCreatingEvent;
use OCA\Portaliq\Event\PortalAccountClaimRequestedEvent;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IAppConfig;
use OCP\IL10N;
use Opis\JsonSchema\Validator;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use ReflectionClass;

/**
 * An ObjectEntity with a schema, a uuid and a payload.
 */
class PortalObjectEntity extends ObjectEntity {
	/**
	 * Build an entity.
	 *
	 * @param string $schema The schema id.
	 * @param array<string,mixed> $data The object payload.
	 * @param string|null $uuid The uuid, once saved.
	 */
	public function __construct(string $schema, array $data, ?string $uuid = null) {
		$this->schema = $schema;
		$this->object = $data;
		$this->uuid = $uuid;
	}
}

/**
 * The listener over the real OpenRegister events and the real portaliq claim event.
 */
class PortalProfileListenerTest extends TestCase {

	private const PLAYER_SCHEMA = 'player-schema-id';

	private const CHARACTER_SCHEMA = 'character-schema-id';

	/**
	 * A portal subject reference as portaliq issues it: not a uuid.
	 */
	private const SUBJECT = '99930md1c2b7e4f0a';

	private const PLAYER_UUID = '0f6a1c52-5d0e-4d8f-9b3a-6b2d1f4e7a10';

	/**
	 * Players the fetcher finds for a portal subject.
	 *
	 * @var array<int, array<string, mixed>>
	 */
	private array $existing = [];

	/**
	 * The filters the fetcher was asked for.
	 *
	 * @var array<int, array<string, string>>
	 */
	private array $asked = [];

	/**
	 * Events handed to the dispatcher.
	 *
	 * @var array<int, Event>
	 */
	private array $dispatched = [];

	/**
	 * What portaliq answers to a claim.
	 */
	private string $answer = 'ok';

	/**
	 * Warnings logged.
	 *
	 * @var array<int, string>
	 */
	private array $warnings = [];

	/**
	 * The listener with the configured schemas.
	 *
	 * @return PortalProfileListener The listener.
	 */
	private function listener(): PortalProfileListener {
		$config = $this->createMock(IAppConfig::class);
		$config->method('getValueString')->willReturnCallback(
			static fn (string $app, string $key, string $default = ''): string => match ($key) {
				'player_schema' => self::PLAYER_SCHEMA,
				'character_schema' => self::CHARACTER_SCHEMA,
				default => $default,
			}
		);
		$fetcher = $this->createMock(RegisterObjectFetcher::class);
		$fetcher->method('getObjectsWithAppAuthority')->willReturnCallback(
			function (string $type, array $filters, ?int $limit = null): array {
				$this->assertSame('player', $type);
				$this->assertSame(1, $limit, 'A bounded query');
				$this->asked[] = $filters;
				return $this->existing;
			}
		);
		$dispatcher = $this->createMock(IEventDispatcher::class);
		$dispatcher->method('dispatchTyped')->willReturnCallback(
			function (Event $event): void {
				$this->dispatched[] = $event;
				if ($event instanceof PortalAccountClaimRequestedEvent) {
					$event->answer($this->answer);
				}
			}
		);
		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnCallback(static fn (string $text): string => $text);
		$logger = $this->createMock(LoggerInterface::class);
		$logger->method('warning')->willReturnCallback(
			function (string $message): void {
				$this->warnings[] = $message;
			}
		);

		return new PortalProfileListener($config, $fetcher, $dispatcher, $l10n, $logger);
	}//end listener()

	/**
	 * Scenario "Lotte joins the campaign": her profile is marked self-registered
	 * and awaiting review, and what other listeners changed is kept.
	 *
	 * @return void
	 */
	public function testAPortalProfileIsMarkedSelfRegisteredAndAwaitingReview(): void {
		$event = new ObjectCreatingEvent(new PortalObjectEntity(self::PLAYER_SCHEMA, ['name' => 'Lotte Bakker', 'portalSubjectRef' => self::SUBJECT]));
		$event->setModifiedData(['description' => 'kept']);

		$this->listener()->handle($event);

		$this->assertFalse($event->isPropagationStopped());
		$this->assertSame(['portalSubjectRef' => self::SUBJECT], $this->asked[0]);
		$this->assertSame(['description' => 'kept', 'selfRegistered' => true, 'awaitingReview' => true], $event->getModifiedData());
	}//end testAPortalProfileIsMarkedSelfRegisteredAndAwaitingReview()

	/**
	 * Scenario "A double click": a second profile for the same portal account
	 * is refused with a message.
	 *
	 * @return void
	 */
	public function testASecondProfileForTheSameAccountIsRefused(): void {
		$this->existing = [['id' => self::PLAYER_UUID, 'name' => 'Lotte Bakker', 'portalSubjectRef' => self::SUBJECT]];
		$event = new ObjectCreatingEvent(new PortalObjectEntity(self::PLAYER_SCHEMA, ['name' => 'Lotte B.', 'portalSubjectRef' => self::SUBJECT]));

		$this->listener()->handle($event);

		$this->assertTrue($event->isPropagationStopped());
		$this->assertSame('This portal account already has a player profile.', $event->getErrors()['message']);
		$this->assertSame([], $event->getModifiedData());
	}//end testASecondProfileForTheSameAccountIsRefused()

	/**
	 * A player a game master creates is not touched and costs no query.
	 *
	 * @return void
	 */
	public function testAPlayerWithoutAPortalSubjectIsLeftAlone(): void {
		$event = new ObjectCreatingEvent(new PortalObjectEntity(self::PLAYER_SCHEMA, ['name' => 'Joris']));

		$this->listener()->handle($event);

		$this->assertSame([], $this->asked);
		$this->assertSame([], $event->getModifiedData());
		$this->assertFalse($event->isPropagationStopped());
	}//end testAPlayerWithoutAPortalSubjectIsLeftAlone()

	/**
	 * Scenario "Lotte creates her first character": the character the portal
	 * stamps with her player uuid is played by her.
	 *
	 * @return void
	 */
	public function testAPortalCharacterIsPlayedByItsOwner(): void {
		$event = new ObjectCreatingEvent(new PortalObjectEntity(self::CHARACTER_SCHEMA, ['name' => 'Wren', 'ownerRef' => self::PLAYER_UUID]));

		$this->listener()->handle($event);

		$this->assertSame(['ocName' => self::PLAYER_UUID], $event->getModifiedData());
	}//end testAPortalCharacterIsPlayedByItsOwner()

	/**
	 * A character with a player already chosen, or without an owner, keeps it.
	 *
	 * @return void
	 */
	public function testACharacterWithAPlayerOrWithoutOwnerIsLeftAlone(): void {
		$chosen = new ObjectCreatingEvent(new PortalObjectEntity(self::CHARACTER_SCHEMA, ['name' => 'Wren', 'ownerRef' => self::PLAYER_UUID, 'ocName' => 'another-player']));
		$plain = new ObjectCreatingEvent(new PortalObjectEntity(self::CHARACTER_SCHEMA, ['name' => 'Ash']));

		$this->listener()->handle($chosen);
		$this->listener()->handle($plain);

		$this->assertSame([], $chosen->getModifiedData());
		$this->assertSame([], $plain->getModifiedData());
	}//end testACharacterWithAPlayerOrWithoutOwnerIsLeftAlone()

	/**
	 * REQ-PSS-002: once the profile exists, larpinq asks portaliq to record the
	 * player's uuid as the account's `ownerRef` claim.
	 *
	 * @return void
	 */
	public function testACreatedProfileAsksPortaliqForTheClaim(): void {
		$this->listener()->handle(new ObjectCreatedEvent(new PortalObjectEntity(self::PLAYER_SCHEMA, ['name' => 'Lotte Bakker', 'portalSubjectRef' => self::SUBJECT], self::PLAYER_UUID)));

		$this->assertCount(1, $this->dispatched);
		$claim = $this->dispatched[0];
		$this->assertInstanceOf(PortalAccountClaimRequestedEvent::class, $claim);
		$this->assertSame('larpinq', $claim->getAppId());
		$this->assertSame(self::SUBJECT, $claim->getSubjectRef());
		$this->assertSame('ownerRef', $claim->getClaimName(), 'The claim the createCharacter action and myCharacters read');
		$this->assertSame(self::PLAYER_UUID, $claim->getValue());
		$this->assertSame([], $this->warnings);
	}//end testACreatedProfileAsksPortaliqForTheClaim()

	/**
	 * Risk 2: a refused or unanswered claim leaves the profile unlinked and is logged.
	 *
	 * @return void
	 */
	public function testARefusedClaimIsLogged(): void {
		$this->answer = 'refused';
		$this->listener()->handle(new ObjectCreatedEvent(new PortalObjectEntity(self::PLAYER_SCHEMA, ['portalSubjectRef' => self::SUBJECT], self::PLAYER_UUID)));

		$this->assertCount(1, $this->warnings);
	}//end testARefusedClaimIsLogged()

	/**
	 * Other created objects, and players a game master made, send no claim.
	 *
	 * @return void
	 */
	public function testOtherCreatedObjectsSendNoClaim(): void {
		$this->listener()->handle(new ObjectCreatedEvent(new PortalObjectEntity(self::PLAYER_SCHEMA, ['name' => 'Joris'], self::PLAYER_UUID)));
		$this->listener()->handle(new ObjectCreatedEvent(new PortalObjectEntity(self::CHARACTER_SCHEMA, ['portalSubjectRef' => self::SUBJECT], self::PLAYER_UUID)));

		$this->assertSame([], $this->dispatched);
	}//end testOtherCreatedObjectsSendNoClaim()

	/**
	 * The exact payloads the portal writes, after this listener, validate
	 * against the merged register schemas: the subject reference is not a uuid,
	 * and the character's owner and player are the player's uuid.
	 *
	 * @return void
	 */
	public function testThePortalPayloadsValidateAgainstTheRealSchemas(): void {
		$profile = new ObjectCreatingEvent(new PortalObjectEntity(self::PLAYER_SCHEMA, ['name' => 'Lotte Bakker', 'description' => 'Plays healers', 'portalSubjectRef' => self::SUBJECT]));
		$character = new ObjectCreatingEvent(new PortalObjectEntity(self::CHARACTER_SCHEMA, ['name' => 'Wren', 'background' => 'A ferry hand', 'ownerRef' => self::PLAYER_UUID]));
		$this->listener()->handle($profile);
		$this->listener()->handle($character);

		$schemas = $this->schemas();
		foreach (['player' => $profile, 'character' => $character] as $key => $event) {
			$payload = array_merge($event->getObject()->getObject(), $event->getModifiedData());
			$result = (new Validator())->validate(
				json_decode((string)json_encode($payload)),
				json_decode((string)json_encode($this->forOpis(node: $schemas[$key])))
			);
			$this->assertTrue($result->isValid(), $key . ' payload must validate: ' . json_encode($result->error()?->args()));
		}
	}//end testThePortalPayloadsValidateAgainstTheRealSchemas()

	/**
	 * The defect this change closes: without the claim, the portal stamped the
	 * subject reference into `ownerRef`, which the character schema refuses.
	 *
	 * @return void
	 */
	public function testASubjectReferenceIsNotAValidOwner(): void {
		$payload = ['name' => 'Wren', 'ownerRef' => self::SUBJECT];
		$result = (new Validator())->validate(
			json_decode((string)json_encode($payload)),
			json_decode((string)json_encode($this->forOpis(node: $this->schemas()['character'])))
		);

		$this->assertFalse($result->isValid());
	}//end testASubjectReferenceIsNotAValidOwner()

	/**
	 * The merged schemas, as the import sees them.
	 *
	 * @return array<string, mixed> The schemas by key.
	 */
	private function schemas(): array {
		$appPath = dirname(__DIR__, 3);
		$monolith = json_decode((string)file_get_contents($appPath . '/lib/Settings/larpinq_register.json'), true);
		$reflection = new ReflectionClass(ConfigFileLoaderService::class);
		$merge = $reflection->getMethod('mergeRegisterFragments');
		$merge->setAccessible(true);

		return $merge->invoke($reflection->newInstanceWithoutConstructor(), $monolith, $appPath)['components']['schemas'];
	}//end schemas()

	/**
	 * The schema as OpenRegister hands it to Opis: a `$ref` relation accepts a
	 * UUID string; rules, calculations and configuration are not JSON Schema.
	 *
	 * @param array<string, mixed> $node A schema node.
	 *
	 * @return array<string, mixed> The node without them, at every depth.
	 */
	private function forOpis(array $node): array {
		unset($node['$ref'], $node['authorization'], $node['calculation'], $node['configuration']);
		foreach ($node as $key => $value) {
			if (is_array($value) === true) {
				$node[$key] = $this->forOpis(node: $value);
			}
		}

		return $node;
	}//end forOpis()
}//end class
