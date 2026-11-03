<?php

/**
 * Unit tests for the portaliq portal contribution provider.
 *
 * Pins larpinq's ADR-046 contract-v2.1 contribution: the dependency-free
 * duck-typed shape (inert without portaliq), the v2 getAudiences() + v1
 * getAudience() pair, the single `player`-audience manifest (collections,
 * scoping map, field whitelists), the conservative create-character whitelist
 * and the fail-closed null for any other audience. Also pins the scoping key
 * and the projection whitelists against the register JSONs at HEAD (base
 * `larpinq_register.json` deep-merged with the `portal-identity` fragment)
 * so a schema drift — a renamed `ownerRef`, a dropped whitelist column, or a
 * game-master-only column leaking into a whitelist — fails here instead of
 * silently scoping portal reads to nothing or exposing staff data.
 *
 * Subjects use nil-pattern UUIDs per the change design.md Seed Data section —
 * self-evidently fake, never colliding with live data.
 *
 * @category Test
 * @package  OCA\Larpinq\Tests\Unit\Portal
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

namespace OCA\Larpinq\Tests\Unit\Portal;

use OCA\Larpinq\Portal\PortalContributionProvider;
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
			expected: 'OCA\\Larpinq\\Portal\\PortalContributionProvider',
			actual: $reflection->getName(),
			message: 'Provider must live at the convention FQCN portaliq probes for'
		);
		$this->assertSame(expected: [], actual: $reflection->getInterfaceNames(), message: 'Duck-typed: no implements clause allowed');
		$this->assertFalse(condition: $reflection->getParentClass(), message: 'Provider must not extend anything');
		$this->assertNull(actual: $reflection->getConstructor(), message: 'Provider must have no constructor dependencies');

		$source = (string)file_get_contents((string)$reflection->getFileName());
		$stripped = (string)preg_replace('/\/\*.*?\*\/|\/\/[^\n]*/s', '', $source);
		$this->assertStringNotContainsStringIgnoringCase(
			needle: 'portaliq',
			haystack: $stripped,
			message: 'Provider code must reference no portaliq symbol (comments excluded)'
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
		$this->assertSame(expected: ['player'], actual: $this->provider->getAudiences());
		$this->assertSame(expected: 'player', actual: $this->provider->getAudience());

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

		$this->assertIsArray(actual: $manifest);
		$collections = $this->indexById(entries: $manifest['collections']);

		$this->assertSame(
			expected: ['myCharacters', 'events', 'skillCatalog', 'itemCatalog', 'conditionCatalog', 'myProfile', 'myRegistrations', 'rulebookChapters'],
			actual: array_keys($collections),
			message: 'Exactly the declared collections, in order'
		);

		// Owner-scoped characters: scoped EXCLUSIVELY by ownerRef, never ownerUid.
		$characters = $collections['myCharacters'];
		$this->assertSame(expected: 'larpinq', actual: $characters['register']);
		$this->assertSame(expected: 'character', actual: $characters['schema']);
		$this->assertSame(expected: 'ownerRef', actual: $characters['scopeField'], message: 'A4 rule: scope by the uuid domain ref, not ownerUid');
		$this->assertSame(expected: 'ownerRef', actual: $characters['scopeClaim']);
		$this->assertTrue(condition: $characters['listable']);

		// Public lists carry an explicit empty scopeField (the controller
		// otherwise defaults to subjectRef and would match nothing).
		foreach (['events', 'skillCatalog', 'itemCatalog', 'conditionCatalog'] as $publicId) {
			$this->assertSame(expected: '', actual: $collections[$publicId]['scopeField'], message: $publicId . ' must be an unscoped public list');
			$this->assertArrayNotHasKey(key: 'scopeClaim', array: $collections[$publicId], message: $publicId . ' is not subject-scoped');
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
		$characters = $this->indexById(entries: $manifest['collections'])['myCharacters'];
		$fields = $characters['fields'];

		$this->assertNotEmpty(actual: $fields, message: 'The character collection must be field-projected (default-deny)');

		foreach (self::FORBIDDEN_CHARACTER_FIELDS as $forbidden) {
			$this->assertNotContains(
				needle: $forbidden,
				haystack: $fields,
				message: 'Game-master-only / internal column "' . $forbidden . '" must never be projected to a player'
			);
		}

		// The player still sees their own game-relevant, non-secret columns.
		foreach (['name', 'ocName', 'description', 'slNotesPublic'] as $expected) {
			$this->assertContains(needle: $expected, haystack: $fields);
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
		$collections = $this->indexById(entries: ((array)$this->provider->getContribution(self::PLAYER_SUBJECT))['collections']);

		foreach (['itemCatalog', 'conditionCatalog'] as $catalogId) {
			$this->assertNotContains(
				needle: 'characters',
				haystack: $collections[$catalogId]['fields'],
				message: $catalogId . ' must not leak which characters own the object'
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

		$this->assertCount(expectedCount: 2, haystack: $actions, message: 'Event signup is delegated to NC Forms; the actions are createCharacter and createPlayerProfile');

		$action = $actions[0];
		$this->assertSame(expected: 'createCharacter', actual: $action['id']);
		$this->assertSame(expected: 'create', actual: $action['type']);
		$this->assertSame(expected: 'character', actual: $action['schema']);
		$this->assertSame(expected: 'ownerRef', actual: $action['scopeField'], message: 'The writer stamps ownerRef so the record is player-owned');
		$this->assertSame(
			expected: 'ownerRef',
			actual: ($action['scopeClaim'] ?? null),
			message: 'ownerRef is a player uuid: the stamp must be the linked player (the claim), never the portal subject reference'
		);
		$this->assertSame(expected: ['name', 'background'], actual: $action['fields'], message: 'No ocName (derived from ownerRef), no approved / slNotesPrivate / economy / lifecycle fields');

		// No inbox surface (larpinq has no per-player message collection).
		$this->assertSame(expected: [], actual: $manifest['notifications']);
		foreach ($manifest['collections'] as $collection) {
			$this->assertNotSame(expected: 'inbox', actual: ($collection['kind'] ?? null), message: 'No inbox collection ships in this wave');
		}

	}//end testCreateCharacterActionIsConservative()

	/**
	 * REQ-PSS-001: a portal visitor creates their own player profile, stamped
	 * with their portal subject, with a name and a description only.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/portal-contribution/spec.md
	 */
	public function testCreatePlayerProfileIsStampedWithThePortalSubject(): void {
		$manifest = (array)$this->provider->getContribution(self::PLAYER_SUBJECT);
		$actions = $this->indexById(entries: $manifest['actions']);

		$this->assertArrayHasKey(key: 'createPlayerProfile', array: $actions);
		$action = $actions['createPlayerProfile'];
		$this->assertSame(expected: 'create', actual: $action['type']);
		$this->assertSame(expected: 'larpinq', actual: $action['register']);
		$this->assertSame(expected: 'player', actual: $action['schema']);
		$this->assertSame(expected: 'portalSubjectRef', actual: $action['scopeField']);
		$this->assertArrayNotHasKey(key: 'scopeClaim', array: $action, message: 'The profile is stamped with the subject itself: no claim exists before it');
		$this->assertSame(expected: ['name', 'description'], actual: $action['fields'], message: 'No userUid, no review or registration flags');
	}//end testCreatePlayerProfileIsStampedWithThePortalSubject()

	/**
	 * REQ-PSS-001: "My profile" shows only the subject's own player.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/portal-contribution/spec.md
	 */
	public function testMyProfileIsScopedToTheSubject(): void {
		$manifest = (array)$this->provider->getContribution(self::PLAYER_SUBJECT);
		$profile = $this->indexById(entries: $manifest['collections'])['myProfile'];

		$this->assertSame(expected: 'player', actual: $profile['schema']);
		$this->assertSame(expected: 'portalSubjectRef', actual: $profile['scopeField']);
		$this->assertArrayNotHasKey(key: 'scopeClaim', array: $profile);
		$this->assertSame(expected: ['name', 'description'], actual: $profile['fields'], message: 'No userUid, portal reference or review stamps');
	}//end testMyProfileIsScopedToTheSubject()

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
			$this->assertSame(expected: 'create', actual: $action['type'], message: 'Receiver-side assertion verification does not exist yet');
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
		$this->assertNull(actual: $this->provider->getContribution(['audience' => 'client']));
		$this->assertNull(actual: $this->provider->getContribution(['audience' => 'supplier']));
		$this->assertNull(actual: $this->provider->getContribution(['audience' => '']));
		$this->assertNull(actual: $this->provider->getContribution([]));

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
		$this->assertArrayHasKey(key: 'ownerRef', array: $characterProps, message: 'portal-identity must add character.ownerRef');
		$this->assertSame(expected: 'string', actual: $characterProps['ownerRef']['type']);
		$this->assertSame(expected: 'uuid', actual: $characterProps['ownerRef']['format']);

		// ownerUid is kept (internal notifications depend on it).
		$this->assertArrayHasKey(key: 'ownerUid', array: $characterProps, message: 'ownerUid must be retained alongside ownerRef');

		// Every forbidden column is a REAL character property — so excluding it
		// from the whitelist is meaningful, not a typo that silently no-ops.
		foreach (['approved', 'slNotesPrivate', 'notice', 'requirementOverrides'] as $realStaffField) {
			$this->assertArrayHasKey(key: $realStaffField, array: $characterProps, message: $realStaffField . ' should still exist to be excluded');
		}

		$schemas = $this->registerSchemas();
		$manifest = (array)$this->provider->getContribution(self::PLAYER_SUBJECT);

		foreach (array_merge($manifest['collections'], $manifest['actions']) as $collection) {
			$schemaName = $this->schemaKeyOf(schemas: $schemas, name: $collection['schema']);
			$this->assertArrayHasKey(key: $schemaName, array: $schemas, message: 'Collection schema "' . $collection['schema'] . '" must exist in the register');

			$schemaProps = ($schemaName === 'character') ? $characterProps : ($schemas[$schemaName]['properties'] ?? []);
			foreach (($collection['fields'] ?? []) as $field) {
				$this->assertArrayHasKey(
					key: $field,
					array: $schemaProps,
					message: 'Whitelisted field "' . $field . '" is not a property of schema "' . $schemaName . '" (drift)'
				);
			}
		}

	}//end testScopingMapMatchesRegisterAtHead()

	/**
	 * Scenario: the player's own page lists their characters and their
	 * registrations (board LrPortaal).
	 *
	 * @return void
	 *
	 * @spec openspec/specs/portal-contribution/spec.md#requirement-the-player-has-a-mijn-larp-page-in-the-portal
	 */
	public function testMyLarpPageListsCharactersAndRegistrations(): void {
		$manifest = (array)$this->provider->getContribution(self::PLAYER_SUBJECT);
		$pages    = $this->indexById(entries: $manifest['pages']);

		$this->assertSame(expected: ['myLarp', 'rulebook'], actual: array_keys($pages));
		$this->assertSame(expected: 'Mijn larp', actual: $pages['myLarp']['label']);
		$this->assertSame(expected: 'Verenigingen', actual: $pages['myLarp']['group']);
		$this->assertSame(
			expected: ['richText', 'collection:myCharacters', 'detail:myCharacters', 'action:createCharacter', 'collection:myRegistrations', 'detail:myRegistrations'],
			actual: $this->blockNames(blocks: $pages['myLarp']['blocks'])
		);

		$registrations = $this->indexById(entries: $manifest['collections'])['myRegistrations'];
		$this->assertSame(expected: 'larping_registration', actual: $registrations['schema']);
		$this->assertSame(expected: 'player', actual: $registrations['scopeField'], message: 'A registration is the player\'s by its player reference');
		$this->assertSame(expected: 'ownerRef', actual: $registrations['scopeClaim'], message: 'The same claim that scopes the characters');
		foreach (['decidedBy', 'decidedAt', 'playerUid', 'submitterUid', 'bookedByUid', 'settlement'] as $staffField) {
			$this->assertNotContains(needle: $staffField, haystack: $registrations['fields'], message: $staffField . ' is game-master bookkeeping');
		}

	}//end testMyLarpPageListsCharactersAndRegistrations()

	/**
	 * Scenario: the rulebook page shows the rules chapters players may read,
	 * then the skills (board LrPortaalRegelboek).
	 *
	 * @return void
	 *
	 * @spec openspec/specs/portal-contribution/spec.md#requirement-the-player-reads-the-rulebook-in-the-portal
	 */
	public function testRulebookPageShowsReadableRulesChapters(): void {
		$manifest = (array)$this->provider->getContribution(self::PLAYER_SUBJECT);
		$pages    = $this->indexById(entries: $manifest['pages']);

		$this->assertSame(
			expected: ['richText', 'collection:rulebookChapters', 'detail:rulebookChapters', 'collection:skillCatalog'],
			actual: $this->blockNames(blocks: $pages['rulebook']['blocks'])
		);

		$chapters = $this->indexById(entries: $manifest['collections'])['rulebookChapters'];
		$this->assertSame(expected: 'larping_lore_page', actual: $chapters['schema']);
		$this->assertSame(expected: '', actual: $chapters['scopeField'], message: 'The rulebook is the same for every player');
		$this->assertSame(
			expected: ['category' => 'rules', 'visibility' => 'players', 'revealFrom' => 'IS NULL'],
			actual: $chapters['filter'],
			message: 'Only rules chapters players may read, and none held back until a reveal moment'
		);
		$this->assertSame(expected: ['field' => 'order', 'direction' => 'asc'], actual: $chapters['defaultSort']);

	}//end testRulebookPageShowsReadableRulesChapters()

	/**
	 * Every page block names a collection or an action of this contribution.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/portal-contribution/spec.md#requirement-the-player-has-a-mijn-larp-page-in-the-portal
	 */
	public function testPageBlocksResolveWithinTheContribution(): void {
		$manifest    = (array)$this->provider->getContribution(self::PLAYER_SUBJECT);
		$collections = array_keys($this->indexById(entries: $manifest['collections']));
		$actions     = array_keys($this->indexById(entries: $manifest['actions']));

		foreach ($manifest['pages'] as $page) {
			foreach ($page['blocks'] as $block) {
				if (isset($block['collection']) === true) {
					$this->assertContains(needle: $block['collection'], haystack: $collections, message: $page['id'] . ' names an unknown collection');
				}

				if (isset($block['action']) === true) {
					$this->assertContains(needle: $block['action'], haystack: $actions, message: $page['id'] . ' names an unknown action');
				}
			}
		}

	}//end testPageBlocksResolveWithinTheContribution()

	/**
	 * The blocks of a page as `type` or `type:target` names.
	 *
	 * @param array<int, array<string, mixed>> $blocks The page blocks.
	 *
	 * @return array<int, string> The names.
	 */
	private function blockNames(array $blocks): array {
		$names = [];
		foreach ($blocks as $block) {
			$target  = ($block['collection'] ?? ($block['action'] ?? ''));
			$names[] = ($target === '') ? $block['type'] : $block['type'] . ':' . $target;
		}

		return $names;
	}//end blockNames()

	/**
	 * The register key of a schema named by its key or by its slug.
	 *
	 * @param array<string, array<string, mixed>> $schemas The register schemas by key.
	 * @param string                              $name    A schema key or slug.
	 *
	 * @return string The key, or the name itself when nothing matches.
	 */
	private function schemaKeyOf(array $schemas, string $name): string {
		foreach ($schemas as $key => $schema) {
			if ($key === $name || ($schema['slug'] ?? '') === $name) {
				return $key;
			}
		}

		return $name;
	}//end schemaKeyOf()

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
		$appPath = dirname(__DIR__, 3);
		$register = (array)json_decode((string)file_get_contents($appPath . '/lib/Settings/larpinq_register.json'), true);
		$reflection = new ReflectionClass(\OCA\Larpinq\Service\ConfigFileLoaderService::class);
		$merge = $reflection->getMethod('mergeRegisterFragments');
		$merge->setAccessible(true);
		$merged = (array)$merge->invoke($reflection->newInstanceWithoutConstructor(), $register, $appPath);

		return (array)($merged['components']['schemas'] ?? []);
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
