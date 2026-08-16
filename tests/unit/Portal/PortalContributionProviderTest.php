<?php

/**
 * Unit tests for the portaliq portal contribution provider.
 *
 * Pins larpingapp's ADR-046 contract-v2.1 contribution: the dependency-free
 * duck-typed shape (inert without portaliq), the v2 getAudiences() + v1
 * getAudience() pair, the single `player`-audience manifest (collections,
 * scoping map, field whitelists), the conservative create-character whitelist
 * and the fail-closed null for any other audience. Also pins the scoping key
 * and the projection whitelists against the register JSONs at HEAD (base
 * `larpingapp_register.json` deep-merged with the `portal-identity` fragment)
 * so a schema drift — a renamed `ownerRef`, a dropped whitelist column, or a
 * game-master-only column leaking into a whitelist — fails here instead of
 * silently scoping portal reads to nothing or exposing staff data.
 *
 * Subjects use nil-pattern UUIDs per the change design.md Seed Data section —
 * self-evidently fake, never colliding with live data.
 *
 * @category Test
 * @package  OCA\LarpingApp\Tests\Unit\Portal
 *
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @version GIT: <git-id>
 *
 * @link https://larpingapp.com
 *
 * @spec openspec/changes/portal-contribution/specs/portal-contribution/spec.md
 */

declare(strict_types=1);

namespace OCA\LarpingApp\Tests\Unit\Portal;

use OCA\LarpingApp\Portal\PortalContributionProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Pin the declarative portal contribution manifest.
 *
 * @spec openspec/changes/portal-contribution/specs/portal-contribution/spec.md
 */
final class PortalContributionProviderTest extends TestCase {
	/**
	 * Server-derived subject fixture for the player audience (nil UUIDs).
	 *
	 * @var array<string, mixed>
	 */
	private const PLAYER_SUBJECT = [
		'subjectRef' => '00000000-0000-0000-0000-000000000001',
		'audience' => 'player',
		'organisation' => '00000000-0000-0000-0000-000000000002',
		'trust' => 'low',
	];

	/**
	 * Game-master-only / internal columns that MUST never reach a player.
	 *
	 * @var array<int, string>
	 */
	private const FORBIDDEN_CHARACTER_FIELDS = [
		'approved',
		'slNotesPrivate',
		'notice',
		'requirementOverrides',
		'ownerUid',
		'ownerRef',
	];

	/**
	 * The provider under test (direct construction — no container).
	 *
	 * @var PortalContributionProvider
	 */
	private PortalContributionProvider $provider;

	/**
	 * Construct the provider directly, as portaliq's registry would.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->provider = new PortalContributionProvider();

	}//end setUp()

	/**
	 * Scenario: Provider is discoverable and inert without portaliq.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/portal-contribution/specs/portal-contribution/spec.md
	 */
	public function testProviderIsPlainAndDependencyFree(): void {
		$reflection = new ReflectionClass(PortalContributionProvider::class);

		$this->assertSame(
			'OCA\\LarpingApp\\Portal\\PortalContributionProvider',
			$reflection->getName(),
			'Provider must live at the convention FQCN portaliq probes for'
		);
		$this->assertSame([], $reflection->getInterfaceNames(), 'Duck-typed: no implements clause allowed');
		$this->assertFalse($reflection->getParentClass(), 'Provider must not extend anything');
		$this->assertNull($reflection->getConstructor(), 'Provider must have no constructor dependencies');

		$source = (string)file_get_contents((string)$reflection->getFileName());
		$stripped = (string)preg_replace('/\/\*.*?\*\/|\/\/[^\n]*/s', '', $source);
		$this->assertStringNotContainsStringIgnoringCase(
			'portaliq',
			$stripped,
			'Provider code must reference no portaliq symbol (comments excluded)'
		);

	}//end testProviderIsPlainAndDependencyFree()

	/**
	 * Scenario: Audiences advertised on both contract versions.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/portal-contribution/specs/portal-contribution/spec.md
	 */
	public function testAudiencesAdvertisedOnBothContractVersions(): void {
		$this->assertSame(['player'], $this->provider->getAudiences());
		$this->assertSame('player', $this->provider->getAudience());

	}//end testAudiencesAdvertisedOnBothContractVersions()

	/**
	 * Scenario: Player sees own characters plus public event and catalog lists.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/portal-contribution/specs/portal-contribution/spec.md
	 */
	public function testPlayerContributionCollectionShape(): void {
		$manifest = $this->provider->getContribution(self::PLAYER_SUBJECT);

		$this->assertIsArray($manifest);
		$collections = $this->indexById($manifest['collections']);

		$this->assertSame(
			['myCharacters', 'events', 'skillCatalog', 'itemCatalog', 'conditionCatalog'],
			array_keys($collections),
			'Exactly the declared collections, in order'
		);

		// Owner-scoped characters: scoped EXCLUSIVELY by ownerRef, never ownerUid.
		$characters = $collections['myCharacters'];
		$this->assertSame('larpingapp', $characters['register']);
		$this->assertSame('character', $characters['schema']);
		$this->assertSame('ownerRef', $characters['scopeField'], 'A4 rule: scope by the uuid domain ref, not ownerUid');
		$this->assertSame('ownerRef', $characters['scopeClaim']);
		$this->assertTrue($characters['listable']);

		// Public lists carry an explicit empty scopeField (the controller
		// otherwise defaults to subjectRef and would match nothing).
		foreach (['events', 'skillCatalog', 'itemCatalog', 'conditionCatalog'] as $publicId) {
			$this->assertSame('', $collections[$publicId]['scopeField'], $publicId . ' must be an unscoped public list');
			$this->assertArrayNotHasKey('scopeClaim', $collections[$publicId], $publicId . ' is not subject-scoped');
		}

	}//end testPlayerContributionCollectionShape()

	/**
	 * Scenario: the character projection drops every game-master-only column.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/portal-contribution/specs/portal-contribution/spec.md
	 */
	public function testCharacterProjectionExcludesStaffOnlyFields(): void {
		$manifest = (array)$this->provider->getContribution(self::PLAYER_SUBJECT);
		$characters = $this->indexById($manifest['collections'])['myCharacters'];
		$fields = $characters['fields'];

		$this->assertNotEmpty($fields, 'The character collection must be field-projected (default-deny)');

		foreach (self::FORBIDDEN_CHARACTER_FIELDS as $forbidden) {
			$this->assertNotContains(
				$forbidden,
				$fields,
				'Game-master-only / internal column "' . $forbidden . '" must never be projected to a player'
			);
		}

		// The player still sees their own game-relevant, non-secret columns.
		foreach (['name', 'ocName', 'description', 'slNotesPublic'] as $expected) {
			$this->assertContains($expected, $fields);
		}

	}//end testCharacterProjectionExcludesStaffOnlyFields()

	/**
	 * Scenario: public item/condition catalogs drop the ownership roster.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/portal-contribution/specs/portal-contribution/spec.md
	 */
	public function testPublicCatalogsDropOwnershipArray(): void {
		$collections = $this->indexById(((array)$this->provider->getContribution(self::PLAYER_SUBJECT))['collections']);

		foreach (['itemCatalog', 'conditionCatalog'] as $catalogId) {
			$this->assertNotContains(
				'characters',
				$collections[$catalogId]['fields'],
				$catalogId . ' must not leak which characters own the object'
			);
		}

	}//end testPublicCatalogsDropOwnershipArray()

	/**
	 * Scenario: the only action is a conservative create-character whitelist.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/portal-contribution/specs/portal-contribution/spec.md
	 */
	public function testCreateCharacterActionIsConservative(): void {
		$manifest = (array)$this->provider->getContribution(self::PLAYER_SUBJECT);
		$actions = $manifest['actions'];

		$this->assertCount(1, $actions, 'Event signup is delegated to NC Forms; the only action is createCharacter');

		$action = $actions[0];
		$this->assertSame('createCharacter', $action['id']);
		$this->assertSame('create', $action['type']);
		$this->assertSame('character', $action['schema']);
		$this->assertSame('ownerRef', $action['scopeField'], 'The writer stamps ownerRef = subjectRef so the record is player-owned');
		$this->assertSame(['name', 'ocName', 'background'], $action['fields'], 'No approved / slNotesPrivate / economy / lifecycle fields');

		// No inbox surface (larpingapp has no per-player message collection).
		$this->assertSame([], $manifest['notifications']);
		foreach ($manifest['collections'] as $collection) {
			$this->assertNotSame('inbox', ($collection['kind'] ?? null), 'No inbox collection ships in this wave');
		}

	}//end testCreateCharacterActionIsConservative()

	/**
	 * Scenario: Wave declares create actions only — no endpoint actions.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/portal-contribution/specs/portal-contribution/spec.md
	 */
	public function testNoEndpointActions(): void {
		$manifest = (array)$this->provider->getContribution(self::PLAYER_SUBJECT);

		foreach ($manifest['actions'] as $action) {
			$this->assertSame('create', $action['type'], 'Receiver-side assertion verification does not exist yet');
		}

	}//end testNoEndpointActions()

	/**
	 * Scenario: Unknown / missing audience yields null (fail-closed).
	 *
	 * @return void
	 *
	 * @spec openspec/changes/portal-contribution/specs/portal-contribution/spec.md
	 */
	public function testFailsClosedForUnknownAudience(): void {
		$this->assertNull($this->provider->getContribution(['audience' => 'client']));
		$this->assertNull($this->provider->getContribution(['audience' => 'supplier']));
		$this->assertNull($this->provider->getContribution(['audience' => '']));
		$this->assertNull($this->provider->getContribution([]));

	}//end testFailsClosedForUnknownAudience()

	/**
	 * Register-drift pin: the scoping key and every whitelist column exist on
	 * their schema at HEAD, and the forbidden columns exist but are excluded.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/portal-contribution/specs/portal-contribution/spec.md
	 */
	public function testScopingMapMatchesRegisterAtHead(): void {
		$characterProps = $this->mergedCharacterProperties();

		// The sibling portal-identity config change added ownerRef as a uuid.
		$this->assertArrayHasKey('ownerRef', $characterProps, 'portal-identity must add character.ownerRef');
		$this->assertSame('string', $characterProps['ownerRef']['type']);
		$this->assertSame('uuid', $characterProps['ownerRef']['format']);

		// ownerUid is kept (internal notifications depend on it).
		$this->assertArrayHasKey('ownerUid', $characterProps, 'ownerUid must be retained alongside ownerRef');

		// Every forbidden column is a REAL character property — so excluding it
		// from the whitelist is meaningful, not a typo that silently no-ops.
		foreach (['approved', 'slNotesPrivate', 'notice', 'requirementOverrides'] as $realStaffField) {
			$this->assertArrayHasKey($realStaffField, $characterProps, $realStaffField . ' should still exist to be excluded');
		}

		$schemas = $this->registerSchemas();
		$manifest = (array)$this->provider->getContribution(self::PLAYER_SUBJECT);

		foreach ($manifest['collections'] as $collection) {
			$schemaName = $collection['schema'];
			$this->assertArrayHasKey($schemaName, $schemas, 'Collection schema "' . $schemaName . '" must exist in the register');

			$schemaProps = ($schemaName === 'character') ? $characterProps : ($schemas[$schemaName]['properties'] ?? []);
			foreach (($collection['fields'] ?? []) as $field) {
				$this->assertArrayHasKey(
					$field,
					$schemaProps,
					'Whitelisted field "' . $field . '" is not a property of schema "' . $schemaName . '" (drift)'
				);
			}
		}

	}//end testScopingMapMatchesRegisterAtHead()

	/**
	 * Index a list of manifest entries by their `id`, preserving order.
	 *
	 * @param array<int, array<string, mixed>> $entries The manifest entries.
	 *
	 * @return array<string, array<string, mixed>> The entries keyed by id.
	 */
	private function indexById(array $entries): array {
		$indexed = [];
		foreach ($entries as $entry) {
			$indexed[$entry['id']] = $entry;
		}

		return $indexed;
	}//end indexById()

	/**
	 * Load the register's schema map from the monolithic register JSON at HEAD.
	 *
	 * @return array<string, array<string, mixed>> The schemas by name.
	 */
	private function registerSchemas(): array {
		$register = (array)json_decode(
			(string)file_get_contents(__DIR__ . '/../../../lib/Settings/larpingapp_register.json'),
			true
		);

		return (array)($register['components']['schemas'] ?? []);
	}//end registerSchemas()

	/**
	 * Deep-merge the base `character` properties with the portal-identity
	 * fragment (mirrors ConfigFileLoaderService union-by-key), so the pin sees
	 * the same character schema OpenRegister imports.
	 *
	 * @return array<string, array<string, mixed>> The merged character properties.
	 */
	private function mergedCharacterProperties(): array {
		$base = (array)($this->registerSchemas()['character']['properties'] ?? []);
		$fragment = (array)json_decode(
			(string)file_get_contents(__DIR__ . '/../../../lib/Settings/register.d/portal-identity.json'),
			true
		);

		$fragmentProps = (array)($fragment['components']['schemas']['character']['properties'] ?? []);

		return array_merge($base, $fragmentProps);
	}//end mergedCharacterProperties()
}//end class
