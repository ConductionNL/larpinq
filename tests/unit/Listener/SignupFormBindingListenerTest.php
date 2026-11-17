<?php

/**
 * SignupFormBindingListenerTest: an event write that binds a sign-up form is
 * refused when the binding is not valid against the registration schema
 * (forms-registration-is-checked-at-binding).
 *
 * @category Test
 * @package  OCA\Larpinq\Tests\Unit\Listener
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/changes/forms-registration-is-checked-at-binding/specs/forms-registration/spec.md
 */

declare(strict_types=1);

namespace OCA\Larpinq\Tests\Unit\Listener;

use OCA\Larpinq\Listener\ConfiguredSchema;
use OCA\Larpinq\Listener\PreWriteEvent;
use OCA\Larpinq\Listener\SignupFormBindingListener;
use OCA\Larpinq\Service\SignupFormBinding;
use OCA\OpenRegister\Db\ObjectEntity;
use OCA\OpenRegister\Event\ObjectCreatingEvent;
use OCA\OpenRegister\Event\ObjectUpdatingEvent;
use OCP\EventDispatcher\Event;
use OCP\IAppConfig;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * The real OpenRegister entity, with a schema id and a payload.
 */
class BindingObjectEntity extends ObjectEntity {
	/**
	 * Build an entity for a schema id and payload.
	 *
	 * @param string              $schema The schema id.
	 * @param array<string,mixed> $data   The object payload.
	 */
	public function __construct(string $schema, array $data) {
		$this->schema = $schema;
		$this->object = $data;
	}
}

/**
 * The binding guard on event writes.
 */
#[CoversClass(SignupFormBindingListener::class)]
#[UsesClass(ConfiguredSchema::class)]
#[UsesClass(PreWriteEvent::class)]
class SignupFormBindingListenerTest extends TestCase {

	private const EVENT_SCHEMA = 'event-schema-uuid';

	private const BOUND = ['name' => 'Winter Court', 'signupForm' => 12, 'signupFormMapping' => [['field' => '31', 'type' => 'short', 'property' => 'nickname']]];

	/** @var list<string> Errors logged. */
	private array $errors = [];

	/**
	 * The listener over a binding double.
	 *
	 * @param SignupFormBinding $binding The binding check.
	 *
	 * @return SignupFormBindingListener The listener.
	 */
	private function listener(SignupFormBinding $binding): SignupFormBindingListener {
		$config = $this->createMock(originalClassName: IAppConfig::class);
		$config->method('getValueString')->willReturnCallback(
			static fn (string $app, string $key, string $default = ''): string => $key === 'event_schema' ? self::EVENT_SCHEMA : $default
		);
		$logger = $this->createMock(originalClassName: LoggerInterface::class);
		$logger->method('error')->willReturnCallback(function (string $message): void {
			$this->errors[] = $message;
		});

		return new SignupFormBindingListener(binding: $binding, config: $config, logger: $logger);
	}//end listener()

	/**
	 * A binding double that changed and answers with the given findings.
	 *
	 * @param array<int, array<string, mixed>>|\Throwable $answer   The findings, or what findings() throws.
	 * @param bool                                        $changed  What bindingChanged() answers.
	 *
	 * @return SignupFormBinding The double.
	 */
	private function binding(array|\Throwable $answer, bool $changed = true): SignupFormBinding {
		$binding = $this->createMock(originalClassName: SignupFormBinding::class);
		$binding->method('bindingChanged')->willReturn($changed);
		if ($answer instanceof \Throwable) {
			$binding->method('findings')->willThrowException($answer);
		} else {
			$binding->method('findings')->willReturn($answer);
		}

		return $binding;
	}//end binding()

	/**
	 * Matches an object payload as getObject() gives it: the stored data, with the uuid as `id`.
	 *
	 * @param array<string, mixed> $expected The stored data.
	 *
	 * @return \PHPUnit\Framework\Constraint\Callback<array<string, mixed>>
	 */
	private static function payload(array $expected): \PHPUnit\Framework\Constraint\Callback {
		return self::callback(static function (array $actual) use ($expected): bool {
			unset($actual['id']);

			return $actual === $expected;
		});
	}//end payload()

	public function testAFormWithoutThePlayersNameCannotBeBound(): void {
		$finding = ['property' => 'playerName', 'code' => 'required-unmapped', 'message' => 'Nothing fills playerName.'];
		$binding = $this->binding(answer: [$finding]);
		$binding->expects(self::once())->method('findings')->with(self::payload(expected: self::BOUND));
		$event = new ObjectCreatingEvent(new BindingObjectEntity(self::EVENT_SCHEMA, self::BOUND));

		$this->listener(binding: $binding)->handle($event);

		$this->assertTrue(condition: $event->isPropagationStopped());
		$this->assertSame(expected: ['signupFormMapping' => [$finding]], actual: $event->getErrors());
	}//end testAFormWithoutThePlayersNameCannotBeBound()

	public function testAValidBindingSaves(): void {
		$event = new ObjectUpdatingEvent(
			new BindingObjectEntity(self::EVENT_SCHEMA, self::BOUND),
			new BindingObjectEntity(self::EVENT_SCHEMA, ['name' => 'Winter Court'])
		);

		$this->listener(binding: $this->binding(answer: []))->handle($event);

		$this->assertFalse(condition: $event->isPropagationStopped());
		$this->assertSame(expected: [], actual: $event->getErrors());
	}//end testAValidBindingSaves()

	public function testTheChangeIsJudgedOnTheStoredAndTheNewEvent(): void {
		$old = ['name' => 'Winter Court', 'signupForm' => 11];
		$binding = $this->binding(answer: [], changed: false);
		$binding->expects(self::once())->method('bindingChanged')->with(self::payload(expected: self::BOUND), self::payload(expected: $old))->willReturn(false);
		$binding->expects(self::never())->method('findings');
		$event = new ObjectUpdatingEvent(new BindingObjectEntity(self::EVENT_SCHEMA, self::BOUND), new BindingObjectEntity(self::EVENT_SCHEMA, $old));

		$this->listener(binding: $binding)->handle($event);

		$this->assertFalse(condition: $event->isPropagationStopped(), message: 'an unchanged binding is not judged again');
		$this->assertSame(expected: [], actual: $event->getErrors());
	}//end testTheChangeIsJudgedOnTheStoredAndTheNewEvent()

	public function testABindingThatCannotBeJudgedIsRefused(): void {
		$event = new ObjectCreatingEvent(new BindingObjectEntity(self::EVENT_SCHEMA, self::BOUND));

		$this->listener(binding: $this->binding(answer: new \RuntimeException('OpenRegister has no form destination validator.')))->handle($event);

		$this->assertTrue(condition: $event->isPropagationStopped());
		$this->assertSame(expected: 'binding_unverifiable', actual: $event->getErrors()['signupFormMapping'][0]['code']);
		$this->assertSame(expected: ['Larpinq: the sign-up form binding could not be checked; it is refused.'], actual: $this->errors);
	}//end testABindingThatCannotBeJudgedIsRefused()

	public function testOtherSchemasAndEventsAreIgnored(): void {
		$binding = $this->binding(answer: [['code' => 'x']]);
		$binding->expects(self::never())->method('bindingChanged');
		$other = new ObjectCreatingEvent(new BindingObjectEntity('player-schema-uuid', self::BOUND));

		$this->listener(binding: $binding)->handle($other);
		$this->listener(binding: $binding)->handle(new Event());

		$this->assertFalse(condition: $other->isPropagationStopped());
	}//end testOtherSchemasAndEventsAreIgnored()
}//end class
