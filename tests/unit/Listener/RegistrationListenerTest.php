<?php

/**
 * RegistrationListener: a registration is checked and decided before it is
 * stored, and the participants follow it after.
 *
 * @category Test
 * @package  OCA\Larpinq\Tests\Unit\Listener
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/changes/larpinq-manifest-tier-4/tasks.md#phase-4--verification
 */

declare(strict_types=1);

namespace OCA\Larpinq\Tests\Unit\Listener;

use OCA\Larpinq\Kernel\Contracts\RegistrationServiceInterface;
use OCA\Larpinq\Kernel\Contracts\RegistrationWriteCheckInterface;
use OCA\Larpinq\Listener\PreWriteEvent;
use OCA\Larpinq\Listener\RegistrationListener;
use OCA\OpenRegister\Db\ObjectEntity;
use OCA\OpenRegister\Event\ObjectCreatedEvent;
use OCA\OpenRegister\Event\ObjectCreatingEvent;
use OCA\OpenRegister\Event\ObjectUpdatingEvent;
use OCP\IAppConfig;
use OCP\IL10N;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/**
 * A registration entity with a schema and a payload.
 */
class RegistrationObjectEntity extends ObjectEntity {
	/**
	 * Build an entity.
	 *
	 * @param string              $schema The schema id.
	 * @param array<string,mixed> $data   The object payload.
	 */
	public function __construct(string $schema, array $data) {
		$this->uuid   = 'r1';
		$this->schema = $schema;
		$this->object = $data;
	}
}

/**
 * The listener over the stub OpenRegister events.
 */
#[CoversClass(RegistrationListener::class)]
#[UsesClass(PreWriteEvent::class)]
class RegistrationListenerTest extends TestCase {

	private const SCHEMA = '42';

	/**
	 * The capacity and participants service.
	 *
	 * @var RegistrationServiceInterface&MockObject
	 */
	private RegistrationServiceInterface&MockObject $service;

	/**
	 * The character and ticket check.
	 *
	 * @var RegistrationWriteCheckInterface&MockObject
	 */
	private RegistrationWriteCheckInterface&MockObject $writeCheck;

	/**
	 * The listener with the registration schema configured and "gm" signed in.
	 *
	 * @return RegistrationListener
	 */
	private function listener(): RegistrationListener {
		$config = $this->createMock(originalClassName: IAppConfig::class);
		$config->method('getValueString')->willReturnMap([['larpinq', 'registration_schema', '', self::SCHEMA]]);
		$user = $this->createMock(originalClassName: IUser::class);
		$user->method('getUID')->willReturn('gm');
		$session = $this->createMock(originalClassName: IUserSession::class);
		$session->method('getUser')->willReturn($user);
		$l10n = $this->createMock(originalClassName: IL10N::class);
		$l10n->method('t')->willReturnCallback(static fn (string $text): string => 'NL:' . $text);
		$this->service = $this->createMock(originalClassName: RegistrationServiceInterface::class);
		$this->writeCheck = $this->createMock(originalClassName: RegistrationWriteCheckInterface::class);

		return new RegistrationListener(
			config: $config,
			service: $this->service,
			writeCheck: $this->writeCheck,
			session: $session,
			l10n: $l10n,
			logger: new NullLogger(),
		);
	}//end listener()

	/**
	 * A create gets the checked changes and the decided status as modified data.
	 *
	 * @return void
	 */
	public function testACreateIsCheckedAndDecided(): void {
		$listener = $this->listener();
		$this->writeCheck->expects($this->once())->method('check')->with(['id' => 'r1', 'event' => 'e1'], null)
			->willReturn(['refusal' => null, 'changes' => ['price' => 50]]);
		$this->service->expects($this->once())->method('beforeCreate')->with(['id' => 'r1', 'event' => 'e1'])->willReturn(['status' => 'accepted']);
		$this->service->expects($this->never())->method('beforeUpdate');

		$event = new ObjectCreatingEvent(new RegistrationObjectEntity(self::SCHEMA, ['event' => 'e1']));
		$listener->handle($event);

		$this->assertSame(expected: ['price' => 50, 'status' => 'accepted'], actual: $event->getModifiedData());
		$this->assertFalse(condition: $event->isPropagationStopped());
	}//end testACreateIsCheckedAndDecided()

	/**
	 * An update is decided from the stored registration and the acting user.
	 *
	 * @return void
	 */
	public function testAnUpdateIsDecidedFromTheStoredOne(): void {
		$listener = $this->listener();
		$this->writeCheck->method('check')->willReturn(['refusal' => null, 'changes' => []]);
		$this->service->expects($this->once())->method('beforeUpdate')
			->with(['id' => 'r1', 'status' => 'cancelled'], ['id' => 'r1', 'status' => 'accepted'], 'gm')->willReturn([]);

		$event = new ObjectUpdatingEvent(
			new RegistrationObjectEntity(self::SCHEMA, ['status' => 'cancelled']),
			new RegistrationObjectEntity(self::SCHEMA, ['status' => 'accepted'])
		);
		$listener->handle($event);

		$this->assertSame(expected: [], actual: $event->getModifiedData());
	}//end testAnUpdateIsDecidedFromTheStoredOne()

	/**
	 * A refused write is stopped with the translated reason and nothing decided.
	 *
	 * @return void
	 */
	public function testARefusalStopsTheWrite(): void {
		$listener = $this->listener();
		$this->writeCheck->method('check')->willReturn(['refusal' => 'No places left', 'changes' => []]);
		$this->service->expects($this->never())->method('beforeCreate');

		$event = new ObjectCreatingEvent(new RegistrationObjectEntity(self::SCHEMA, []));
		$listener->handle($event);

		$this->assertTrue(condition: $event->isPropagationStopped());
		$this->assertSame(expected: ['message' => 'NL:No places left'], actual: $event->getErrors());
	}//end testARefusalStopsTheWrite()

	/**
	 * Another schema's write is left alone; a stored registration moves the participants.
	 *
	 * @return void
	 */
	public function testOtherSchemasAreIgnoredAndAfterWriteRuns(): void {
		$listener = $this->listener();
		$this->writeCheck->expects($this->never())->method('check');
		$listener->handle(new ObjectCreatingEvent(new RegistrationObjectEntity('7', ['event' => 'e1'])));

		$this->service->expects($this->once())->method('afterWrite')->with(['id' => 'r1', 'event' => 'e1'], null);
		$listener->handle(new ObjectCreatedEvent(new RegistrationObjectEntity(self::SCHEMA, ['event' => 'e1'])));
	}//end testOtherSchemasAreIgnoredAndAfterWriteRuns()
}//end class
