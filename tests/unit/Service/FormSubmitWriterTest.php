<?php

/**
 * Tests for the form submit writer: one larpinq object through OpenRegister's
 * submit service, as the account, with the app's authority and an idempotency key.
 *
 * @category Test
 * @package  OCA\Larpinq\Tests\Unit\Service
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/changes/forms-registration-is-checked-at-binding/specs/forms-registration/spec.md#requirement-a-forms-submit-must-create-the-registration-through-the-submit-service
 */

declare(strict_types=1);

namespace OCA\Larpinq\Tests\Unit\Service;

use OCA\Larpinq\Service\FormSubmitWriter;
use OCA\Larpinq\Service\RegisterSchemaLookup;
use OCA\OpenRegister\Service\Form\FormSubmission;
use OCA\OpenRegister\Service\Form\FormSubmitServiceInterface;
use OCP\IUser;
use OCP\IUserManager;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use RuntimeException;

/**
 * What the writer hands the submit service, and when it refuses.
 */
#[CoversClass(FormSubmitWriter::class)]
class FormSubmitWriterTest extends TestCase {

	/** @var list<array{submission: FormSubmission, key: string|null}> What the submit service was asked. */
	private array $submits = [];

	private IUser $anna;

	protected function setUp(): void {
		$this->anna = $this->createMock(originalClassName: IUser::class);
		$this->anna->method('getUID')->willReturn('anna');
	}//end setUp()

	/**
	 * A writer over a container that does or does not bind the submit service.
	 *
	 * @param bool $bound Whether the submit service is bound.
	 *
	 * @return FormSubmitWriter The writer.
	 */
	private function writer(bool $bound = true): FormSubmitWriter {
		$submits = &$this->submits;
		$service = new class($submits) {
			/** @param array<int, mixed> $submits */
			public function __construct(private array &$submits) {
			}

			/** @return array<string, mixed> */
			public function submit(FormSubmission $submission, ?string $idempotencyKey = null): array {
				$this->submits[] = ['submission' => $submission, 'key' => $idempotencyKey];

				return ['id' => 'reg-1', 'reference' => 'R-1'];
			}
		};
		$container = $this->createMock(originalClassName: ContainerInterface::class);
		$container->method('has')->willReturnCallback(static fn (string $id): bool => $bound && $id === FormSubmitServiceInterface::class);
		$container->method('get')->willReturnCallback(
			static function (string $id) use ($service, $bound): object {
				if ($bound === false || $id !== FormSubmitServiceInterface::class) {
					throw new RuntimeException('not bound: ' . $id);
				}

				return $service;
			}
		);
		$schemas = $this->createMock(originalClassName: RegisterSchemaLookup::class);
		$schemas->method('resolve')->willReturnCallback(static fn (string $objectTypeLower): array => ['3', $objectTypeLower . '-schema']);
		$users = $this->createMock(originalClassName: IUserManager::class);
		$users->method('get')->willReturnCallback(fn (string $uid): ?IUser => $uid === 'anna' ? $this->anna : null);

		return new FormSubmitWriter(container: $container, schemas: $schemas, users: $users);
	}//end writer()

	public function testItSubmitsAsTheAccountWithTheAppsAuthorityAndTheKey(): void {
		$answer = $this->writer()->submit(objectType: 'registration', data: ['event' => 'e-1'], uid: 'anna', scope: 'forms:12', key: 'forms-submission:501');

		$this->assertSame(expected: ['id' => 'reg-1', 'reference' => 'R-1'], actual: $answer);
		$this->assertCount(expectedCount: 1, haystack: $this->submits);
		$submission = $this->submits[0]['submission'];
		$this->assertSame(expected: 'forms-submission:501', actual: $this->submits[0]['key']);
		$this->assertSame(expected: [['register' => '3', 'schema' => 'registration-schema', 'mapping' => null]], actual: $submission->writes);
		$this->assertSame(expected: ['event' => 'e-1'], actual: $submission->payload);
		$this->assertSame(expected: $this->anna, actual: $submission->submitter->subject);
		$this->assertSame(expected: 'forms:12', actual: $submission->submitter->scope);
		$this->assertNull(actual: $submission->submitter->draftId, message: 'a fresh submit, never a draft');
		$this->assertTrue(condition: $submission->submitter->appAuthority, message: 'players may not create registrations; the app vouches');
	}//end testItSubmitsAsTheAccountWithTheAppsAuthorityAndTheKey()

	public function testItIsAvailableOnlyWhenTheSubmitServiceIsBound(): void {
		$this->assertTrue(condition: $this->writer()->available());
		$this->assertFalse(condition: $this->writer(bound: false)->available());
	}//end testItIsAvailableOnlyWhenTheSubmitServiceIsBound()

	public function testItRefusesWhenUnavailable(): void {
		$this->expectException(exception: RuntimeException::class);
		$this->expectExceptionMessage(message: 'OpenRegister has no form submit with an app\'s authority.');

		try {
			$this->writer(bound: false)->submit(objectType: 'registration', data: [], uid: 'anna', scope: 'forms:12', key: 'k');
		} finally {
			$this->assertSame(expected: [], actual: $this->submits);
		}
	}//end testItRefusesWhenUnavailable()

	public function testItRefusesAnUnknownAccount(): void {
		$this->expectException(exception: RuntimeException::class);
		$this->expectExceptionMessage(message: 'The submitting account does not exist.');

		try {
			$this->writer()->submit(objectType: 'registration', data: [], uid: 'ghost', scope: 'forms:12', key: 'k');
		} finally {
			$this->assertSame(expected: [], actual: $this->submits);
		}
	}//end testItRefusesAnUnknownAccount()
}//end class
