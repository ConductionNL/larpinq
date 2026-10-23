<?php

/**
 * Character values checked against the field definitions of their world
 * (characters-custom-fields REQ-CCF-004).
 *
 * The guard runs on the REAL RegisterObjectFetcher over the in-memory
 * ObjectService stand-in, and the validator is the real one.
 *
 * @category Test
 * @package  OCA\Larpinq\Tests\Unit\Service
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/specs/character-custom-fields/spec.md
 */

declare(strict_types=1);

namespace OCA\Larpinq\Tests\Unit\Service;

require_once __DIR__ . '/InMemoryObjectService.php';

use OCA\Larpinq\Service\CustomFieldGuard;
use OCA\Larpinq\Service\CustomFieldValidator;
use OCA\Larpinq\Service\RegisterObjectFetcher;
use OCP\App\IAppManager;
use OCP\IAppConfig;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

/**
 * REQ-CCF-004: a value must match its definition.
 */
class CustomFieldGuardTest extends TestCase {

	private const ALDMOOR = '11111111-1111-4111-8111-111111111111';

	private const ELSEWHERE = '22222222-2222-4222-8222-222222222222';

	private InMemoryObjectService $store;

	/**
	 * The four Aldmoor definitions of design.md, a global one, and one of
	 * another world.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->store = new InMemoryObjectService();
		$a = self::ALDMOOR;
		$this->store->seed('characterfield', ['id' => 'f1', 'label' => 'Bloodline', 'key' => 'bloodline', 'fieldType' => 'choice', 'choices' => ['human', 'elven', 'dwarven'], 'visibility' => 'owner', 'setting' => $a]);
		$this->store->seed('characterfield', ['id' => 'f2', 'label' => 'Patron god', 'key' => 'patron-god', 'fieldType' => 'text', 'visibility' => 'owner', 'setting' => $a]);
		$this->store->seed('characterfield', ['id' => 'f3', 'label' => 'Scars', 'key' => 'scars', 'fieldType' => 'number', 'visibility' => 'owner', 'setting' => $a]);
		$this->store->seed('characterfield', ['id' => 'f4', 'label' => 'True allegiance', 'key' => 'true-allegiance', 'fieldType' => 'choice', 'choices' => ['crown', 'rebels', 'none'], 'visibility' => 'gamemasters', 'setting' => $a]);
		$this->store->seed('characterfield', ['id' => 'f5', 'label' => 'Vegetarian', 'key' => 'vegetarian', 'fieldType' => 'yes-no', 'visibility' => 'owner']);
		$this->store->seed('characterfield', ['id' => 'f6', 'label' => 'Clan', 'key' => 'clan', 'fieldType' => 'text', 'visibility' => 'owner', 'setting' => self::ELSEWHERE]);
	}//end setUp()

	/**
	 * The guard on the real fetcher.
	 *
	 * @return CustomFieldGuard The guard.
	 */
	private function guard(): CustomFieldGuard {
		$container = $this->createMock(ContainerInterface::class);
		$container->method('get')->willReturn($this->store);
		$apps = $this->createMock(IAppManager::class);
		$apps->method('getInstalledApps')->willReturn(['openregister']);
		$config = $this->createMock(IAppConfig::class);
		$config->method('getValueString')->willReturnCallback(
			static function (string $app, string $key, string $default = ''): string {
				[$type, $kind] = explode('_', $key, 2) + [1 => ''];
				return match ($kind) {
					'register' => 'larpinq',
					'schema' => $type,
					default => $default,
				};
			}
		);
		$fetcher = new RegisterObjectFetcher(
			container: $container,
			appManager: $apps,
			config: $config,
			logger: $this->createMock(LoggerInterface::class),
		);

		return new CustomFieldGuard(objectFetcher: $fetcher, validator: new CustomFieldValidator());
	}//end guard()

	/**
	 * Mirela, as a character of Aldmoor.
	 *
	 * @param array<string, mixed> $public The values for the player.
	 * @param array<string, mixed> $private The values for game masters.
	 *
	 * @return array<string, mixed> The character.
	 */
	private function mirela(array $public = [], array $private = []): array {
		return ['name' => 'Mirela the Wanderer', 'setting' => self::ALDMOOR, 'customFields' => $public, 'customFieldsPrivate' => $private];
	}//end mirela()

	/**
	 * Valid values of every type pass.
	 *
	 * @return void
	 */
	public function testValidValuesPass(): void {
		$candidate = $this->mirela(
			public: ['bloodline' => 'elven', 'patron-god' => 'The Grey Lady', 'scars' => 2, 'vegetarian' => true],
			private: ['true-allegiance' => 'rebels']
		);

		$this->assertNull($this->guard()->check(candidate: $candidate, old: []));
	}//end testValidValuesPass()

	/**
	 * Scenario "Text in a number field": the error names the key.
	 *
	 * @return void
	 */
	public function testTextInANumberFieldIsRefused(): void {
		$errors = $this->guard()->check(candidate: $this->mirela(public: ['scars' => 'many']), old: $this->mirela());

		$this->assertNotNull($errors);
		$this->assertSame('custom_field_invalid', $errors['code']);
		$this->assertSame(['scars'], array_keys($errors['fields']));
	}//end testTextInANumberFieldIsRefused()

	/**
	 * A choice outside the list, a yes-no that is not a boolean, and a key of
	 * another world are each refused by key.
	 *
	 * @return void
	 */
	public function testEachWrongValueIsNamed(): void {
		$errors = $this->guard()->check(
			candidate: $this->mirela(public: ['bloodline' => 'orcish', 'vegetarian' => 'yes', 'clan' => 'Wolves', 'nothing' => 'x']),
			old: []
		);

		$this->assertSame(['bloodline', 'clan', 'nothing', 'vegetarian'], $this->sortedKeys(errors: $errors));
	}//end testEachWrongValueIsNamed()

	/**
	 * A value in the property its visibility does not name is refused both ways.
	 *
	 * @return void
	 */
	public function testAValueSitsInThePropertyOfItsVisibility(): void {
		$errors = $this->guard()->check(
			candidate: $this->mirela(public: ['true-allegiance' => 'rebels'], private: ['scars' => 1]),
			old: []
		);

		$this->assertSame(['scars', 'true-allegiance'], $this->sortedKeys(errors: $errors));
	}//end testAValueSitsInThePropertyOfItsVisibility()

	/**
	 * Only changed keys are checked: a value left behind by a deleted
	 * definition does not block the next edit, and a write that does not touch
	 * the values is not checked at all.
	 *
	 * @return void
	 */
	public function testOnlyChangedValuesAreChecked(): void {
		$old = $this->mirela(public: ['retired-field' => 'kept', 'scars' => 1]);
		$candidate = $this->mirela(public: ['retired-field' => 'kept', 'scars' => 3]);
		$this->assertNull($this->guard()->check(candidate: $candidate, old: $old));

		$unchanged = $old;
		$unchanged['name'] = 'Mirela';
		$this->store->objects = [];
		$this->assertNull($this->guard()->check(candidate: $unchanged, old: $old));
	}//end testOnlyChangedValuesAreChecked()

	/**
	 * Clearing a value (null) is always allowed.
	 *
	 * @return void
	 */
	public function testClearingAValueIsAllowed(): void {
		$this->assertNull(
			$this->guard()->check(candidate: $this->mirela(public: ['scars' => null]), old: $this->mirela(public: ['scars' => 2]))
		);
	}//end testClearingAValueIsAllowed()

	/**
	 * The keys of an error payload, sorted.
	 *
	 * @param array<string, mixed>|null $errors The payload.
	 *
	 * @return list<string> The keys.
	 */
	private function sortedKeys(?array $errors): array {
		$this->assertNotNull($errors);
		$keys = array_keys($errors['fields']);
		sort($keys);
		return $keys;
	}//end sortedKeys()
}//end class
