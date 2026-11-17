<?php

/**
 * FormsFeedbackAnswers: who submitted a form, from Forms' submissions.
 *
 * @category Test
 * @package  OCA\Larpinq\Tests\Unit\Service
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/changes/events-post-event-survey/specs/event-feedback/spec.md#requirement-game-masters-see-who-answered-req-eps-004
 */

declare(strict_types=1);

namespace OCA\Larpinq\Tests\Unit\Service;

use OCA\Larpinq\Service\FormsFeedbackAnswers;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * REQ-EPS-003 and REQ-EPS-004 of events-post-event-survey, against a fake Forms reader.
 */
#[CoversClass(FormsFeedbackAnswers::class)]
class FormsFeedbackAnswersTest extends TestCase {

	/**
	 * A submission as Forms' mapper returns it.
	 *
	 * @param string $uid The submitting user.
	 *
	 * @return object
	 */
	private static function submission(string $uid): object {
		return new class($uid) {
			/**
			 * Constructor.
			 *
			 * @param string $uid The user.
			 */
			public function __construct(private readonly string $uid) {
			}

			/**
			 * The user.
			 *
			 * @return string
			 */
			public function getUserId(): string {
				return $this->uid;
			}
		};
	}//end submission()

	/**
	 * The reader over a container holding this mapper, or none.
	 *
	 * @param object|null $mapper The fake SubmissionMapper; null when Forms is absent.
	 * @param LoggerInterface|null $logger The logger.
	 *
	 * @return FormsFeedbackAnswers
	 */
	private function reader(?object $mapper, ?LoggerInterface $logger = null): FormsFeedbackAnswers {
		$container = $this->createMock(ContainerInterface::class);
		$container->method('has')->with(FormsFeedbackAnswers::SUBMISSION_MAPPER)->willReturn($mapper !== null);
		$container->method('get')->with(FormsFeedbackAnswers::SUBMISSION_MAPPER)->willReturn($mapper);

		return new FormsFeedbackAnswers(container: $container, logger: $logger ?? $this->createMock(LoggerInterface::class));
	}//end reader()

	/**
	 * Anna answered twice and Sanne not at all: Anna is listed once; anonymous answers name nobody.
	 *
	 * @return void
	 */
	public function testTheUsersWhoSubmittedEachOnce(): void {
		$mapper = new class([self::submission('anna'), self::submission('anna'), self::submission('anon-user-4f2a'), self::submission(''), self::submission('pieter')]) {
			/**
			 * Constructor.
			 *
			 * @param list<object> $rows The submissions.
			 */
			public function __construct(private readonly array $rows) {
			}

			/** @var list<int> */
			public array $asked = [];

			/**
			 * The submissions of a form.
			 *
			 * @param int $formId The form.
			 *
			 * @return list<object>
			 */
			public function findByForm(int $formId): array {
				$this->asked[] = $formId;
				return $this->rows;
			}
		};

		$this->assertSame(expected: ['anna', 'pieter'], actual: $this->reader(mapper: $mapper)->answeredBy(formId: 12));
		$this->assertSame(expected: [12], actual: $mapper->asked);
	}//end testTheUsersWhoSubmittedEachOnce()

	/**
	 * Without Forms, or without a form, nobody is known to have answered: null, not an empty list.
	 *
	 * @return void
	 */
	public function testWithoutFormsTheAnswerIsUnknown(): void {
		$this->assertNull(actual: $this->reader(mapper: null)->answeredBy(formId: 12));
		$this->assertNull(actual: $this->reader(mapper: new \stdClass())->answeredBy(formId: 0));
	}//end testWithoutFormsTheAnswerIsUnknown()

	/**
	 * A Forms that fails to read is logged and unknown.
	 *
	 * @return void
	 */
	public function testAFailingReadIsUnknown(): void {
		$mapper = new class {
			/**
			 * The submissions of a form.
			 *
			 * @param int $formId The form.
			 *
			 * @return never
			 */
			public function findByForm(int $formId): array {
				throw new RuntimeException('Forms is broken ' . $formId);
			}
		};
		$logger = $this->createMock(LoggerInterface::class);
		$logger->expects($this->once())->method('warning');

		$this->assertNull(actual: $this->reader(mapper: $mapper, logger: $logger)->answeredBy(formId: 12));
	}//end testAFailingReadIsUnknown()
}//end class
