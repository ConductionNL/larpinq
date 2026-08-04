/*
 * SPDX-FileCopyrightText: 2026 Larping Contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * HIGH-VALUE correctness workflow: character-stat computation.
 *
 * This is the heart of larpingapp — proving that EFFECTS carried by a
 * character's skills/items/conditions actually COMPUTE onto the character's
 * ability scores, with the exact expected numeric result. base 10 + an
 * effect of "+3 strength" must yield strength 13.
 *
 * How the assertion is driven
 * ---------------------------
 * The computation lives in CharacterService::calculateCharacter(), which
 * walks character.skills/items/conditions/events -> entity.effects ->
 * effect.modifier and applies each modifier to the matching ability's base
 * value, producing character.stats[abilityId].value.
 *
 * That service is NOT surfaced through any HTTP controller (the only
 * CharactersController method is downloadPdf) and the SPA never calls it, so
 * the computed stat cannot be observed by clicking through the running UI.
 * To still assert the REAL computation end-to-end against REAL persisted OR
 * data, the active tests:
 *
 *   1. seed ability + effect + skill + character through the OpenRegister
 *      object REST API (the authoritative store — real saveObject path), then
 *   2. invoke the genuine production CharacterService::calculateCharacter()
 *      inside the container (computeCharacterStat helper) against the seeded
 *      character read back via the real RegisterObjectFetcher, and
 *   3. assert the computed ability value equals base ± modifier exactly.
 *
 * This exercises the true code path
 *   calculateCharacter -> applyEntityEffects -> applyEffects
 *     -> calculateEffect -> applyModifierToAbility
 * with data that was actually persisted, so a regression in either the
 * persistence shape or the arithmetic is caught.
 *
 * STAT_UI_BLOCKER (why the through-the-UI variant is test.fixme)
 * -------------------------------------------------------------
 * There is no way to observe the computed stat through the deployed SPA on
 * this instance:
 *   - no controller/route exposes calculateCharacter(), and the SPA does not
 *     compute stats client-side; and
 *   - even the raw character detail page renders shell-only because the
 *     slug-addressed object fetch 500s on the 11-way duplicate "larpingapp"
 *     register (see crud-persistence.workflow.spec.ts -> DETAIL_SLUG_500_BLOCKER); and
 *   - the app's own data-loading path (RegisterObjectFetcher::getObjects ->
 *     MagicMapper::findAll) returns EMPTY in this environment, so even if a
 *     view existed it would compute against zero abilities (see
 *     FINDALL_EMPTY_BLOCKER below).
 * These are environment/OR-data defects, not larpingapp source bugs. The
 * algorithm itself is correct — proven by the active assertions here and by
 * the 20-case unit suite in tests/unit/Service/CharacterServiceTest.php.
 *
 * FINDALL_EMPTY_BLOCKER (a REAL data-loading bug observed on this instance)
 * ------------------------------------------------------------------------
 * The computeCharacterStat harness deliberately reads the character via
 * RegisterObjectFetcher::getObject() (find-by-UUID, which works) and lets
 * CharacterService::loadAllEntities() pull the ability/effect/skill
 * collections via getObjects() (findAll). On this instance that findAll
 * returns ZERO rows (the magic-mapper search path filters everything out in
 * CLI/no-org context, compounded by the duplicate-slug register), so
 * loadAllEntities() loads no abilities and calculateCharacter() yields an
 * EMPTY stats map — the +3 never applies. That makes the live end-to-end
 * computation observably BROKEN here even though the arithmetic is correct.
 * The `live data-loading` test below captures this as the bug it is:
 * it is marked test.fixme with this exact reason, and the BUG LIST in the
 * task report records it. Fix path: repair OR findAll for register 8 (dedupe
 * the duplicate "larpingapp" registers / restore org context), then this
 * test goes green unmodified.
 */

import { test, expect, type APIRequestContext } from '@playwright/test'
import {
	RUN_ID,
	newApi,
	resolveSchemaIds,
	FixtureLedger,
	createObject,
	seedStatScenario,
	computeCharacterStat,
	computeCharacterStatLive,
	cleanupLedger,
	fixtureName,
} from './fixtures'

// Documented blocker reasons; each is annotated onto its test.fixme below.
const STAT_UI_BLOCKER
	= 'STAT_UI_BLOCKER: computed character stats are not surfaced through any '
	+ 'controller or the SPA (only CharactersController::downloadPdf exists; the '
	+ 'SPA does not compute stats and its detail page is slug-500-blocked). '
	+ 'Not drivable via the UI on this instance — env/OR defect, not a source bug.'

/**
 * On a developer box the CharacterService harness needs a reachable Nextcloud
 * to bootstrap (a docker `nextcloud` container, or a server root above this
 * checkout). Where neither exists, skipping is honest — the alternative is a
 * failure that names the arithmetic for an environment problem.
 *
 * ⚠️ BUT NEVER ON CI. The shared workflow always provides a server root
 * (`$GITHUB_WORKSPACE/server`, found by fixtures.ts::findServerRoot), so a null
 * result there means the harness genuinely broke — and a skip would turn the
 * two highest-value correctness assertions in this repo into a green-looking
 * no-op. `test.skip()` reports as a PASS in every summary the pipeline prints.
 * So the escape hatch is gated off on CI: there, a null result must fail.
 *
 * @param {ComputedStat|null} computed Harness result.
 * @return {boolean} True when skipping is legitimate.
 */
function harnessUnavailable(computed: unknown): boolean {
	const onCI = process.env.GITHUB_ACTIONS === 'true' || (process.env.CI ?? '') !== ''
	return computed === null && !onCI
}

let api: APIRequestContext
const ledger = new FixtureLedger()

test.beforeAll(async () => {
	api = await newApi()
	// Resolve register/schema ids from LarpingApp's own settings API so the
	// workflow never trusts a stale hardcoded id (e.g. the old item=22 that
	// pointed at a foreign QTI schema). Instance-independent.
	await resolveSchemaIds(api)
})

test.afterAll(async () => {
	await cleanupLedger(api, ledger)
	await api.dispose()
	// eslint-disable-next-line no-console
	console.log(`[stat-computation] RUN_ID=${RUN_ID} — fixtures cleaned up via ledger.`)
})

// ===========================================================================
// CORRECTNESS — base + effect modifier computes the exact effective stat
// openspec/specs/character-management/spec.md#view-computed-stats-in-eigenschappen-tab
// ===========================================================================

test.describe('character-stat computation — correctness (real service, real data)', () => {
	test('positive effect via skill: strength 10 + "+3" effect = 13', async () => {
		const s = await seedStatScenario(api, ledger, { base: 10, modifier: 3, modification: 'positive' })
		expect(s.expected).toBe(13)

		const computed = await computeCharacterStat({
			characterId: s.characterId,
			abilityId: s.abilityId,
			effectId: s.effectId,
			skillId: s.skillId,
		})

		// Off CI only: if no Nextcloud bootstrap is reachable, skip rather than
		// report an environment problem as an arithmetic failure. On CI a null
		// result fails — see harnessUnavailable().
		test.skip(harnessUnavailable(computed), 'CharacterService harness not runnable off CI (no server root, no docker).')
		expect(computed, 'CharacterService harness must be runnable on CI').not.toBeNull()

		// Real correctness: the persisted ability (base 10) plus the persisted
		// "+3" effect carried by the persisted skill computes to exactly 13.
		expect(computed!.base, 'persisted ability base must load').toBe(10)
		expect(computed!.value, 'base 10 + effect +3 must compute to 13').toBe(13)
		// Audit trail records the contributing effect and its delta.
		expect(Array.isArray(computed!.audit) && computed!.audit.length).toBeGreaterThan(0)
	})

	test('negative effect via item: strength 10 - "2" effect = 8', async () => {
		// Re-use the scenario seeder for the ability + effect + character, but
		// route the effect through an ITEM to prove non-skill carriers apply too.
		const base = 10
		const modifier = 2
		const abilityId = ledger.track('ability', await createObject(api, 'ability', {
			name: fixtureName('neg-strength'),
			base,
		}))
		const effectId = ledger.track('effect', await createObject(api, 'effect', {
			name: fixtureName('neg-weaken'),
			modifier,
			modification: 'negative',
			cumulative: 'cumulative',
			abilities: [abilityId],
		}))
		const itemId = ledger.track('item', await createObject(api, 'item', {
			name: fixtureName('neg-cursed-ring'),
			effects: [effectId],
		}))
		const name = fixtureName('neg-hero')
		// `ocName` is the required RELATION to a `player` object
		// (`format: uuid`, `$ref: player`), not a second display name. Passing
		// `name` here is rejected with HTTP 400 "Property 'ocName' should match
		// format 'uuid'", which is what made this test red.
		const playerId = ledger.track('player', await createObject(api, 'player', {
			name: fixtureName('neg-hero-player'),
		}))
		const characterId = ledger.track('character', await createObject(api, 'character', {
			name, ocName: playerId, type: 'player', items: [itemId],
		}))

		const computed = await computeCharacterStat({ characterId, abilityId, effectId, itemId })
		test.skip(harnessUnavailable(computed), 'CharacterService harness not runnable off CI (no server root, no docker).')
		expect(computed, 'CharacterService harness must be runnable on CI').not.toBeNull()

		expect(computed!.base, 'persisted ability base must load').toBe(10)
		expect(computed!.value, 'base 10 - effect 2 (negative) must compute to 8').toBe(8)
	})

	// -----------------------------------------------------------------------
	// The REAL data-loading defect, captured as a fixme + recorded in BUG LIST.
	// -----------------------------------------------------------------------

	// @e2e openspec/specs/character-management/spec.md#view-computed-stats-in-eigenschappen-tab
	// FIXED (2026-06-10, wave-3): OR-core made findAll work in CLI/system
	// context, so CharacterService::loadAllEntities() now loads the seeded
	// abilities/effects and the live calculateCharacter() applies the +3
	// (verified: base 10 -> value 13 with a non-empty audit trail).
	test('live: calculateCharacter applies +3 onto strength through real OR findAll', async () => {
		const s = await seedStatScenario(api, ledger, { base: 10, modifier: 3, modification: 'positive' })
		// NATIVE path: lets CharacterService::loadAllEntities() pull the
		// collections via OR findAll (no reflection injection).
		const computed = await computeCharacterStatLive(s.characterId, s.abilityId)
		expect(computed).not.toBeNull()
		// On a healthy instance loadAllEntities() pulls the seeded ability and
		// effect, and this is 13. On THIS instance findAll returns empty so
		// base/value come back null — FINDALL_EMPTY_BLOCKER, hence the fixme +
		// BUG LIST entry.
		expect(computed!.base).toBe(10)
		expect(computed!.value).toBe(13)
	})

	// @e2e openspec/specs/character-management/spec.md#view-computed-stats-in-eigenschappen-tab
	// FIXME(stat-ui-blocker): computed stats are not surfaced through any
	// controller or the SPA detail page on this instance — STAT_UI_BLOCKER.
	test.fixme('UI: character detail "Eigenschappen" tab shows the computed effective stat', async ({ page }) => {
		test.info().annotations.push({ type: 'blocker', description: STAT_UI_BLOCKER })
		const s = await seedStatScenario(api, ledger, { base: 10, modifier: 3, modification: 'positive' })
		await page.goto(`/apps/larpingapp/characters/${s.characterId}`)
		// ADR-074 rule 4: `networkidle` never settles on Nextcloud.
		await page.locator('#app-content, .app-content, #content').first()
			.waitFor({ state: 'visible', timeout: 30_000 }).catch(() => {})
		// On a healthy instance the detail page renders the computed stats tab
		// and the effective strength value (13). Blocked here by the slug-500
		// detail fetch + the missing computed-stats surface.
		await expect(page.locator('.app-content').getByText('13').first()).toBeVisible({ timeout: 10_000 })
	})
})
