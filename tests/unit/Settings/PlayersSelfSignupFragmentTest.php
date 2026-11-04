<?php

/**
 * The player fields behind self-signup (players-self-signup, REQ-PSS-001 and
 * REQ-PSS-004), as the import sees them.
 *
 * @category Test
 * @package  OCA\Larpinq\Tests\Unit\Settings
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/specs/portal-contribution/spec.md
 */

declare(strict_types=1);

namespace OCA\Larpinq\Tests\Unit\Settings;

use OCA\Larpinq\Service\ConfigFileLoaderService;
use Opis\JsonSchema\Validator;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * REQ-PSS-001 and REQ-PSS-004.
 */
class PlayersSelfSignupFragmentTest extends TestCase {

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
	 * The portal reference is a plain string (portaliq's subject reference is
	 * not a uuid), hidden and set once; the registration and review fields exist.
	 *
	 * @return void
	 */
	public function testAPlayerCarriesThePortalReferenceAndTheReviewFields(): void {
		$properties = $this->schemas()['player']['properties'];

		$this->assertSame(expected: 'string', actual: $properties['portalSubjectRef']['type']);
		$this->assertArrayNotHasKey(key: 'format', array: $properties['portalSubjectRef'], message: 'A portal subject reference is not a uuid');
		$this->assertFalse(condition: $properties['portalSubjectRef']['visible']);
		$this->assertTrue(condition: $properties['portalSubjectRef']['readOnly']);
		$this->assertTrue(condition: $properties['portalSubjectRef']['facetable'], message: 'The one-profile check filters on it');

		$this->assertSame(expected: 'boolean', actual: $properties['selfRegistered']['type']);
		$this->assertSame(expected: 'boolean', actual: $properties['awaitingReview']['type']);
		$this->assertTrue(condition: $properties['awaitingReview']['facetable'], message: 'The New players list filters on it');
		$this->assertSame(expected: 'date-time', actual: $properties['reviewedAt']['format']);
		$this->assertSame(expected: 'string', actual: $properties['reviewedBy']['type']);
		$this->assertArrayNotHasKey(key: 'format', array: $properties['reviewedBy'], message: 'OpenRegister has no user format');
	}//end testAPlayerCarriesThePortalReferenceAndTheReviewFields()

	/**
	 * The demo self-registered player validates and is awaiting review.
	 *
	 * @return void
	 */
	public function testTheSeedSelfRegisteredPlayerValidates(): void {
		$appPath = dirname(__DIR__, 3);
		$mock = json_decode((string)file_get_contents($appPath . '/lib/Settings/larpinq_mock_register.json'), true);
		$schema = $this->schemas()['player'];
		unset($schema['authorization'], $schema['configuration']);
		$found = null;
		foreach ($mock['components']['objects'] as $object) {
			if (($object['@self']['schema'] ?? '') === 'player' && ($object['selfRegistered'] ?? false) === true) {
				$found = $object;
			}
		}

		$this->assertNotNull(actual: $found, message: 'A self-registered demo player ships');
		$this->assertSame(expected: 'Lotte Bakker', actual: $found['name']);
		$this->assertTrue(condition: $found['awaitingReview']);
		unset($found['@self']);
		$result = (new Validator())->validate(json_decode((string)json_encode($found)), json_decode((string)json_encode($schema)));
		$this->assertTrue(condition: $result->isValid(), message: 'The seed must validate');
	}//end testTheSeedSelfRegisteredPlayerValidates()
}//end class
