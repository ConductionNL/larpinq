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
			'OCA\\Larpinq\\Portal\\PortalContributionProvider',
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
			['myCharacters', 'events', 'skillCatalog', 'itemCatalog', 'conditionCatalog', 'myProfile', 'myRegistrations', 'rulebookChapters'],
			array_keys($collections),
			'Exactly the declared collections, in order'
		);

		// Owner-scoped characters: scoped EXCLUSIVELY by ownerRef, never ownerUid.
		$characters = $collections['myCharacters'];
		$this->assertSame('larpinq', $characters['register']);
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

		$this->assertCount(2, $actions, 'Event signup is delegated to NC Forms; the actions are createCharacter and createPlayerProfile');

		$action = $actions[0];
		$this->assertSame('createCharacter', $action['id']);
		$this->assertSame('create', $action['type']);
		$this->assertSame('character', $action['schema']);
		$this->assertSame('ownerRef', $action['scopeField'], 'The writer stamps ownerRef so the record is player-owned');
		$this->assertSame(
			'ownerRef',
			($action['scopeClaim'] ?? null),
			'ownerRef is a player uuid: the stamp must be the linked player (the claim), never the portal subject reference'
		);
		$this->assertSame(['name', 'background'], $action['fields'], 'No ocName (derived from ownerRef), no approved / slNotesPrivate / economy / lifecycle fields');

		// No inbox surface (larpinq has no per-player message collection).
		$this->assertSame([], $manifest['notifications']);
		foreach ($manifest['collections'] as $collection) {
			$this->assertNotSame('inbox', ($collection['kind'] ?? null), 'No inbox collection ships in this wave');
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
		$actions = $this->indexById($manifest['actions']);

		$this->assertArrayHasKey('createPlayerProfile', $actions);
		$action = $actions['createPlayerProfile'];
		$this->assertSame('create', $action['type']);
		$this->assertSame('larpinq', $action['register']);
		$this->assertSame('player', $action['schema']);
		$this->assertSame('portalSubjectRef', $action['scopeField']);
		$this->assertArrayNotHasKey('scopeClaim', $action, 'The profile is stamped with the subject itself: no claim exists before it');
		$this->assertSame(['name', 'description'], $action['fields'], 'No userUid, no review or registration flags');
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
		$profile = $this->indexById($manifest['collections'])['myProfile'];

		$this->assertSame('player', $profile['schema']);
		$this->assertSame('portalSubjectRef', $profile['scopeField']);
		$this->assertArrayNotHasKey('scopeClaim', $profile);
		$this->assertSame(['name', 'description'], $profile['fields'], 'No userUid, portal reference or review stamps');
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

		foreach (array_merge($manifest['collections'], $manifest['actions']) as $collection) {
			$schemaName = $this->schemaKeyOf(schemas: $schemas, name: $collection['schema']);
			$this->assertArrayHasKey($schemaName, $schemas, 'Collection schema "' . $collection['schema'] . '" must exist in the register');

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
	 * Scenario: the player's own page lists their characters and their
	 * registrations (board LrPortaal).
	 *
	 * @return void
	 *
	 * @spec openspec/specs/portal-contribution/spec.md#requirement-the-player-has-a-mijn-larp-page-in-the-portal
	 */
	public function testMyLarpPageListsCharactersAndRegistrations(): void {
		$manifest = (array)$this->provider->getContribution(self::PLAYER_SUBJECT);
		$pages    = $this->indexById($manifest['pages']);

		$this->assertSame(['myLarp', 'rulebook'], array_keys($pages));
		$this->assertSame('Mijn larp', $pages['myLarp']['label']);
		$this->assertSame('Verenigingen', $pages['myLarp']['group']);
		$this->assertSame(
			['richText', 'collection:myCharacters', 'detail:myCharacters', 'action:createCharacter', 'collection:myRegistrations', 'detail:myRegistrations'],
			$this->blockNames($pages['myLarp']['blocks'])
		);

		$registrations = $this->indexById($manifest['collections'])['myRegistrations'];
		$this->assertSame('larping_registration', $registrations['schema']);
		$this->assertSame('player', $registrations['scopeField'], 'A registration is the player\'s by its player reference');
		$this->assertSame('ownerRef', $registrations['scopeClaim'], 'The same claim that scopes the characters');
		foreach (['decidedBy', 'decidedAt', 'playerUid', 'submitterUid', 'bookedByUid', 'settlement'] as $staffField) {
			$this->assertNotContains($staffField, $registrations['fields'], $staffField . ' is game-master bookkeeping');
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
		$pages    = $this->indexById($manifest['pages']);

		$this->assertSame(
			['richText', 'collection:rulebookChapters', 'detail:rulebookChapters', 'collection:skillCatalog'],
			$this->blockNames($pages['rulebook']['blocks'])
		);

		$chapters = $this->indexById($manifest['collections'])['rulebookChapters'];
		$this->assertSame('larping_lore_page', $chapters['schema']);
		$this->assertSame('', $chapters['scopeField'], 'The rulebook is the same for every player');
		$this->assertSame(
			['category' => 'rules', 'visibility' => 'players', 'revealFrom' => 'IS NULL'],
			$chapters['filter'],
			'Only rules chapters players may read, and none held back until a reveal moment'
		);
		$this->assertSame(['field' => 'order', 'direction' => 'asc'], $chapters['defaultSort']);

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
		$collections = array_keys($this->indexById($manifest['collections']));
		$actions     = array_keys($this->indexById($manifest['actions']));

		foreach ($manifest['pages'] as $page) {
			foreach ($page['blocks'] as $block) {
				if (isset($block['collection']) === true) {
					$this->assertContains($block['collection'], $collections, $page['id'] . ' names an unknown collection');
				}

				if (isset($block['action']) === true) {
					$this->assertContains($block['action'], $actions, $page['id'] . ' names an unknown action');
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
