<?php

/**
 * SignupFormBindingTest: the binding's mapping, when it changed, and how it is
 * handed to OpenRegister's form destination validator
 * (forms-registration-is-checked-at-binding).
 *
 * @category Test
 * @package  OCA\Larpinq\Tests\Unit\Service
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/changes/forms-registration-is-checked-at-binding/specs/forms-registration/spec.md
 */

declare(strict_types=1);

namespace OCA\Larpinq\Tests\Unit\Service;

use OCA\Larpinq\Service\RegisterSchemaLookup;
use OCA\Larpinq\Service\SignupFormBinding;
use OCA\OpenRegister\Db\Schema;
use OCA\OpenRegister\Service\Form\FormDestination;
use OCA\OpenRegister\Service\Form\FormDestinationValidatorInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use RuntimeException;

/**
 * The binding check, over a recording validator.
 */
#[CoversClass(SignupFormBinding::class)]
class SignupFormBindingTest extends TestCase {

	private const REGISTRATION_SCHEMA = '42';

	/** @var list<array{mapping: array<string, mixed>, destination: FormDestination}> What the validator was asked. */
	private array $asked = [];

	/** @var list<array{0: string|int, 1: bool, 2: bool}> What the schema mapper was asked. */
	private array $found = [];

	/**
	 * A binding over a validator answering with the given findings.
	 *
	 * @param array<int, array<string, mixed>> $findings The validator's answer.
	 * @param string                           $schemaId The registration schema id the lookup gives.
	 *
	 * @return array{0: SignupFormBinding, 1: Schema} The binding and the schema the mapper returns.
	 */
	private function binding(array $findings = [], string $schemaId = self::REGISTRATION_SCHEMA): array {
		$schema = new Schema();
		$test = $this;
		$validator = new class($findings, $test) implements FormDestinationValidatorInterface {
			/**
			 * @param array<int, array<string, mixed>> $findings The answer.
			 * @param SignupFormBindingTest            $test     Records the calls.
			 */
			public function __construct(private array $findings, private SignupFormBindingTest $test) {
			}

			public function validate(array $mapping, FormDestination $destination): array {
				$this->test->recordValidate(mapping: $mapping, destination: $destination);

				return $this->findings;
			}
		};
		$mapper = new class($schema, $test) {
			public function __construct(private Schema $schema, private SignupFormBindingTest $test) {
			}

			public function find(string|int $id, ?array $_extend = [], bool $_rbac = true, bool $_multitenancy = true): Schema {
				$this->test->recordFind(id: $id, rbac: $_rbac, multitenancy: $_multitenancy);

				return $this->schema;
			}
		};
		$container = $this->createMock(originalClassName: ContainerInterface::class);
		$container->method('get')->willReturnMap([
			[FormDestinationValidatorInterface::class, $validator],
			['OCA\OpenRegister\Db\SchemaMapper', $mapper],
		]);
		$lookup = $this->createMock(originalClassName: RegisterSchemaLookup::class);
		$lookup->method('resolve')->with('registration')->willReturn(['7', $schemaId]);

		return [new SignupFormBinding(container: $container, schemas: $lookup), $schema];
	}//end binding()

	/**
	 * Record a validate() call.
	 *
	 * @param array<string, mixed> $mapping     The mapping.
	 * @param FormDestination      $destination The destination.
	 *
	 * @return void
	 */
	public function recordValidate(array $mapping, FormDestination $destination): void {
		$this->asked[] = ['mapping' => $mapping, 'destination' => $destination];
	}//end recordValidate()

	/**
	 * Record a find() call.
	 *
	 * @param string|int $id           The schema id.
	 * @param bool       $rbac         Whether RBAC was asked.
	 * @param bool       $multitenancy Whether multitenancy was asked.
	 *
	 * @return void
	 */
	public function recordFind(string|int $id, bool $rbac, bool $multitenancy): void {
		$this->found[] = [$id, $rbac, $multitenancy];
	}//end recordFind()

	public function testTheMappingCarriesTheQuestionsAndTheListenerValues(): void {
		$mapping = SignupFormBinding::mapping(event: [
			'signupForm' => 12,
			'signupFormMapping' => [
				['field' => '31', 'type' => 'short', 'property' => 'playerName'],
				['field' => 32, 'property' => 'character'],
				'not a row',
			],
		]);

		$this->assertSame(
			expected: [
				['field' => '31', 'type' => 'short', 'property' => 'playerName'],
				['field' => '32', 'type' => '', 'property' => 'character'],
			],
			actual: $mapping['fields']
		);
		$this->assertSame(
			expected: ['event', 'submitterUid', 'status', 'submissionId', 'submittedAt'],
			actual: array_keys($mapping['fixed'])
		);
		$this->assertSame(expected: 'pending', actual: $mapping['fixed']['status']);
		$this->assertIsInt(actual: $mapping['fixed']['submissionId']);
	}//end testTheMappingCarriesTheQuestionsAndTheListenerValues()

	public function testAnEventWithoutMappingHasNoFields(): void {
		$this->assertSame(expected: [], actual: SignupFormBinding::mapping(event: ['signupForm' => 12])['fields']);
		$this->assertSame(expected: [], actual: SignupFormBinding::mapping(event: ['signupFormMapping' => 'broken'])['fields']);
	}//end testAnEventWithoutMappingHasNoFields()

	public function testTheBindingChangesWithTheFormOrTheMapping(): void {
		[$binding] = $this->binding();
		$bound = ['signupForm' => 12, 'signupFormMapping' => [['field' => '31', 'property' => 'playerName']]];

		$this->assertTrue(condition: $binding->bindingChanged(candidate: $bound, old: []), message: 'a new binding');
		$this->assertTrue(condition: $binding->bindingChanged(candidate: ['signupForm' => 13] + $bound, old: $bound), message: 'another form');
		$this->assertTrue(
			condition: $binding->bindingChanged(candidate: ['signupFormMapping' => []] + $bound, old: $bound),
			message: 'another mapping'
		);
		$this->assertFalse(condition: $binding->bindingChanged(candidate: ['name' => 'Renamed'] + $bound, old: $bound), message: 'an unrelated edit');
		$this->assertFalse(condition: $binding->bindingChanged(candidate: ['signupForm' => null], old: $bound), message: 'unbinding');
		$this->assertFalse(condition: $binding->bindingChanged(candidate: ['signupForm' => 0], old: []), message: 'no form');
	}//end testTheBindingChangesWithTheFormOrTheMapping()

	public function testTheValidatorJudgesTheMappingAgainstTheRegistrationSchema(): void {
		$finding = ['property' => 'playerName', 'code' => 'required-unmapped', 'message' => 'Nothing fills playerName.'];
		[$binding, $schema] = $this->binding(findings: [3 => $finding]);
		$event = ['signupForm' => 12, 'signupFormMapping' => [['field' => '31', 'type' => 'short', 'property' => 'nickname']]];

		$this->assertSame(expected: [$finding], actual: $binding->findings(event: $event));
		$this->assertCount(expectedCount: 1, haystack: $this->asked);
		$this->assertSame(expected: SignupFormBinding::mapping(event: $event), actual: $this->asked[0]['mapping']);
		$this->assertSame(expected: $schema, actual: $this->asked[0]['destination']->schema);
		$this->assertNull(actual: $this->asked[0]['destination']->audience);
		$this->assertSame(expected: [[self::REGISTRATION_SCHEMA, false, false]], actual: $this->found);
	}//end testTheValidatorJudgesTheMappingAgainstTheRegistrationSchema()

	public function testAValidBindingHasNoFindings(): void {
		[$binding] = $this->binding(findings: []);

		$this->assertSame(expected: [], actual: $binding->findings(event: ['signupForm' => 12]));
	}//end testAValidBindingHasNoFindings()

	public function testWithoutARegistrationSchemaTheBindingCannotBeJudged(): void {
		[$binding] = $this->binding(schemaId: '');

		$this->expectException(exception: RuntimeException::class);
		$this->expectExceptionMessage(message: 'Larpinq has no registration schema.');
		$binding->findings(event: ['signupForm' => 12]);
	}//end testWithoutARegistrationSchemaTheBindingCannotBeJudged()
}//end class
