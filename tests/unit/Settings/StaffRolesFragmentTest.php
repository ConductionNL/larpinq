<?php

/**
 * The staff role rights and the staff invite on the merged register
 * (admin-staff-roles-and-invites).
 *
 * Runs the REAL ConfigFileLoaderService fragment merge over the real monolith
 * and every real register.d fragment, the same path the import takes.
 *
 * @category Test
 * @package  OCA\Larpinq\Tests\Unit\Settings
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/changes/admin-staff-roles-and-invites/specs/staff-roles/spec.md
 */

declare(strict_types=1);

namespace OCA\Larpinq\Tests\Unit\Settings;

use OCA\Larpinq\AppInfo\Application;
use OCA\Larpinq\Service\ConfigFileLoaderService;
use OCA\Larpinq\Support\AppIdentity;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * REQ-ASR-002, REQ-ASR-003 and REQ-ASR-004 of admin-staff-roles-and-invites.
 */
class StaffRolesFragmentTest extends TestCase {

	/**
	 * The merged register, built once per test.
	 *
	 * @var array<string, mixed>|null
	 */
	private ?array $merged = null;

	/**
	 * The register as OpenRegister imports it: monolith plus every fragment.
	 *
	 * @return array<string, mixed> The merged register.
	 */
	private function mergedRegister(): array {
		if ($this->merged !== null) {
			return $this->merged;
		}

		$appPath = dirname(__DIR__, 3);
		$monolith = json_decode((string)file_get_contents($appPath . '/lib/Settings/larpinq_register.json'), true);
		$this->assertIsArray(actual: $monolith);

		$reflection = new ReflectionClass(ConfigFileLoaderService::class);
		$loader = $reflection->newInstanceWithoutConstructor();
		$merge = $reflection->getMethod('mergeRegisterFragments');
		$merge->setAccessible(true);

		// @var array<string, mixed> $merged
		$merged = $merge->invoke($loader, $monolith, $appPath);
		$this->merged = $merged;
		return $merged;
	}//end mergedRegister()

	/**
	 * One merged schema.
	 *
	 * @param string $key The schema key.
	 *
	 * @return array<string, mixed> The schema.
	 */
	private function schema(string $key): array {
		$schemas = $this->mergedRegister()['components']['schemas'];
		$this->assertArrayHasKey(key: $key, array: $schemas);
		return $schemas[$key];
	}//end schema()

	/**
	 * The plain group names a rule list admits, without row conditions.
	 *
	 * @param array<int, mixed> $rules A rule list.
	 *
	 * @return list<string> The groups admitted without a match.
	 */
	private static function plainGroups(array $rules): array {
		$groups = [];
		foreach ($rules as $rule) {
			if (is_string($rule) === true) {
				$groups[] = $rule;
			} else if (is_array($rule) === true && isset($rule['match']) === false && isset($rule['group']) === true) {
				$groups[] = (string)$rule['group'];
			}
		}

		return $groups;
	}//end plainGroups()

	/**
	 * Story writers read and write plots, plot parts and lore pages; rules marshals do not even read plots.
	 *
	 * @return void
	 */
	public function testWritersWriteTheStorySchemas(): void {
		foreach (['plot', 'plotPart', 'lorePage'] as $key) {
			$authorization = $this->schema(key: $key)['authorization'];
			foreach (['read', 'create', 'update'] as $action) {
				$this->assertContains(needle: Application::ROLE_WRITERS, haystack: $authorization[$action], message: "writers {$action} {$key}");
				$this->assertNotContains(needle: Application::ROLE_RULES, haystack: $authorization[$action], message: "rules marshals do not {$action} {$key}");
			}
		}

		$this->assertContains(needle: Application::ROLE_WRITERS, haystack: $this->schema(key: 'plotPart')['properties']['privateText']['authorization']['read']);
	}//end testWritersWriteTheStorySchemas()

	/**
	 * Rules marshals write the five rule schemas; writers do not.
	 *
	 * @return void
	 */
	public function testRulesMarshalsWriteTheRuleSchemas(): void {
		foreach (['ability', 'skill', 'item', 'condition', 'effect'] as $key) {
			$authorization = $this->schema(key: $key)['authorization'];
			foreach (['create', 'update'] as $action) {
				$this->assertContains(needle: Application::ROLE_RULES, haystack: $authorization[$action], message: "rules marshals {$action} {$key}");
				$this->assertNotContains(needle: Application::ROLE_WRITERS, haystack: $authorization[$action], message: "writers do not {$action} {$key}");
			}
		}
	}//end testRulesMarshalsWriteTheRuleSchemas()

	/**
	 * No staff role deletes anything; game masters keep every delete.
	 *
	 * @return void
	 */
	public function testNoStaffRoleDeletes(): void {
		foreach ($this->mergedRegister()['components']['schemas'] as $key => $schema) {
			$delete = $schema['authorization']['delete'] ?? [];
			foreach (AppIdentity::STAFF_ROLES as $role) {
				$this->assertNotContains(needle: $role, haystack: $delete, message: "{$role} must not delete {$key}");
			}
		}

		foreach (['character', 'xpAward', 'registration'] as $key) {
			$this->assertSame(expected: ['gamemasters'], actual: self::plainGroups(rules: $this->schema(key: $key)['authorization']['delete']));
		}
	}//end testNoStaffRoleDeletes()

	/**
	 * A writer changes the story fields of any character and nothing else of it.
	 *
	 * Every character property a writer may not change carries an update rule
	 * without the writers group, so the row-level update a writer holds reaches
	 * only the story fields.
	 *
	 * @return void
	 */
	public function testAWriterChangesOnlyTheStoryFieldsOfACharacter(): void {
		$character = $this->schema(key: 'character');
		$this->assertContains(needle: Application::ROLE_WRITERS, haystack: $character['authorization']['read']);
		$this->assertContains(needle: Application::ROLE_WRITERS, haystack: $character['authorization']['update']);
		$this->assertNotContains(needle: Application::ROLE_WRITERS, haystack: $character['authorization']['create']);

		$story = ['background', 'slNotesPublic', 'slNotesPrivate', 'writer', 'writingStep'];
		foreach ($character['properties'] as $name => $property) {
			$update = $property['authorization']['update'] ?? null;
			if (in_array($name, $story, true) === true) {
				$this->assertContains(needle: Application::ROLE_WRITERS, haystack: $update ?? [], message: "a writer changes {$name}");
				continue;
			}

			if ($name === 'ownerRef') {
				// Calculated from the owner, never written by anyone.
				continue;
			}

			$this->assertIsArray(actual: $update, message: "{$name} needs an update rule so a writer cannot change it");
			$this->assertNotContains(needle: Application::ROLE_WRITERS, haystack: $update, message: "a writer must not change {$name}");
		}

		// A writer reads every field.
		foreach ($character['properties'] as $name => $property) {
			if (isset($property['authorization']['read']) === true) {
				$this->assertContains(needle: Application::ROLE_WRITERS, haystack: $property['authorization']['read'], message: "a writer reads {$name}");
			}
		}
	}//end testAWriterChangesOnlyTheStoryFieldsOfACharacter()

	/**
	 * A treasurer reads registrations and changes only their payment state.
	 *
	 * @return void
	 */
	public function testATreasurerChangesOnlyThePaymentState(): void {
		$registration = $this->schema(key: 'registration');
		$this->assertContains(needle: Application::ROLE_TREASURERS, haystack: $registration['authorization']['read']);
		$this->assertContains(needle: Application::ROLE_TREASURERS, haystack: $registration['authorization']['update']);
		$this->assertNotContains(needle: Application::ROLE_TREASURERS, haystack: $registration['authorization']['create']);

		foreach ($registration['properties'] as $name => $property) {
			$update = $property['authorization']['update'] ?? null;
			$this->assertIsArray(actual: $update, message: "{$name} needs an update rule so a treasurer cannot change it");
			if ($name === 'paymentState') {
				$this->assertContains(needle: Application::ROLE_TREASURERS, haystack: $update);
				continue;
			}

			$this->assertNotContains(needle: Application::ROLE_TREASURERS, haystack: $update, message: "a treasurer must not change {$name}");
		}
	}//end testATreasurerChangesOnlyThePaymentState()

	/**
	 * A steward reads the roster's characters and the registrations, and writes attendance only.
	 *
	 * @return void
	 */
	public function testAStewardReadsTheRosterAndWritesAttendance(): void {
		$this->assertContains(needle: Application::ROLE_STEWARDS, haystack: $this->schema(key: 'character')['authorization']['read']);
		$this->assertNotContains(needle: Application::ROLE_STEWARDS, haystack: $this->schema(key: 'character')['authorization']['update']);
		$this->assertContains(needle: Application::ROLE_STEWARDS, haystack: $this->schema(key: 'registration')['authorization']['read']);
		$this->assertNotContains(needle: Application::ROLE_STEWARDS, haystack: $this->schema(key: 'registration')['authorization']['update']);

		// The private fields keep their own read rules, without the stewards.
		foreach (['background', 'slNotesPrivate', 'customFieldsPrivate'] as $name) {
			$this->assertNotContains(needle: Application::ROLE_STEWARDS, haystack: $this->schema(key: 'character')['properties'][$name]['authorization']['read']);
		}

		$this->assertContains(needle: Application::ROLE_STEWARDS, haystack: $this->schema(key: 'attendance')['authorization']['create']);
	}//end testAStewardReadsTheRosterAndWritesAttendance()

	/**
	 * The staff invite is in the register, for game masters only, and nobody reads its token hash through the API.
	 *
	 * @return void
	 */
	public function testTheStaffInviteIsForGameMastersOnly(): void {
		$this->assertContains(needle: 'larping_staff_invite', haystack: $this->mergedRegister()['components']['registers']['larpinq']['schemas']);

		$invite = $this->schema(key: 'staffInvite');
		$this->assertSame(expected: 'larping_staff_invite', actual: $invite['slug']);
		foreach (['read', 'create', 'update', 'delete'] as $action) {
			$this->assertSame(expected: ['gamemasters'], actual: $invite['authorization'][$action]);
		}

		$this->assertSame(expected: ['gamemasters', ...AppIdentity::STAFF_ROLES], actual: $invite['properties']['role']['enum']);
		$this->assertSame(expected: ['admin'], actual: $invite['properties']['tokenHash']['authorization']['read']);
		$this->assertSame(expected: ['role', 'tokenHash', 'expiresAt', 'maxUses'], actual: $invite['required']);
	}//end testTheStaffInviteIsForGameMastersOnly()
}//end class
