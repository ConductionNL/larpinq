/*
 * SPDX-FileCopyrightText: 2026 Larping Contributors
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The `game-mechanics` derivation contract, driven end to end.
 *
 * WHAT THESE TESTS ARE, AND WHY THEY ARE WORTH ANCHORING
 * ------------------------------------------------------
 * Every assertion below is a NUMBER computed in advance from the seeded data
 * and then compared against what the real `CharacterService` derives from rows
 * that were really persisted through OpenRegister's object REST API. Nothing
 * here asserts that a write was merely *accepted*.
 *
 * That distinction is the whole reason this file exists. The previous attempt
 * at closing a gate-19 cluster in this app wrote thirteen tests for
 * `skill-requirement-enforcement`, of which the three that passed asserted only
 * that a write returned 2xx — and would have kept passing with the enforcement
 * layer entirely absent (they did: it WAS absent, see larpingapp#308). An
 * acceptance-shaped assertion cannot detect a missing layer.
 *
 * The derivation scenarios have no such failure mode: base 20 with +5, +3, -2
 * and +1 reachable from four different carriers is 27 or it is not, and an
 * untouched ability has an empty audit trail or it does not. Each test states
 * its expected value before the call that produces it.
 *
 * HOW THE DERIVATION IS REACHED
 * -----------------------------
 * `CharacterService::calculateCharacter()` is not surfaced by any controller
 * and the SPA does not compute stats client-side (the only
 * `CharactersController` method is `downloadPdf`), so there is no UI to click.
 * The helpers in `./fixtures` therefore run the genuine production service
 * inside the instance under test, through its own
 * `loadAllEntities()`/findAll loader — no reflection injection, no stubbed
 * collections. The path exercised is:
 *
 *   calculateCharacter -> initializeAbilityScores
 *                      -> applyEntityEffects (skills, items, conditions, events)
 *                      -> applyEffects -> calculateEffect -> applyModifierToAbility
 *
 * so a regression in the persistence shape, the loader, the ordering, the
 * cumulative rule or the arithmetic all show up here.
 *
 * ISOLATION
 * ---------
 * `initializeAbilityScores()` walks EVERY ability in the register, so a
 * character's stats block contains entries this test did not create. Every
 * assertion is therefore keyed on the UUID of an ability this test seeded under
 * its own `RUN_ID` prefix, and never on the size of the stats block.
 */

import { test, expect, type APIRequestContext } from '@playwright/test'
import {
	RUN_ID,
	newApi,
	resolveSchemaIds,
	FixtureLedger,
	createObject,
	computeStatsLive,
	computeRosterLive,
	cleanupLedger,
	fixtureName,
	type DerivedStats,
} from './fixtures'

let api: APIRequestContext
const ledger = new FixtureLedger()

test.beforeAll(async () => {
	api = await newApi()
	await resolveSchemaIds(api)
})

test.afterAll(async () => {
	await cleanupLedger(api, ledger)
	await api.dispose()
	// eslint-disable-next-line no-console
	console.log(`[game-mechanics] RUN_ID=${RUN_ID} — fixtures cleaned up via ledger.`)
})

/**
 * Whether skipping is legitimate — i.e. the in-instance harness cannot run at
 * all on this developer box. NEVER on CI, where the shared workflow always
 * provides a server root: a null result there means the harness broke, and a
 * skip would report as a pass in every summary the pipeline prints.
 *
 * @param {unknown} computed Harness result.
 * @return {boolean} True when skipping is honest.
 */
function harnessUnavailable(computed: unknown): boolean {
	const onCI = process.env.GITHUB_ACTIONS === 'true' || (process.env.CI ?? '') !== ''
	return computed === null && !onCI
}

/**
 * Seed one ability and return its UUID.
 *
 * @param {string} label Human label, prefixed with the run id.
 * @param {number} base  Base score.
 * @return {Promise<string>} The ability UUID.
 */
async function seedAbility(label: string, base: number): Promise<string> {
	return ledger.track('ability', await createObject(api, 'ability', {
		name: fixtureName(label),
		description: `base ${base}`,
		base,
	}))
}

/**
 * Seed one effect and return its UUID.
 *
 * @param {string}   label        Human label, prefixed with the run id.
 * @param {object}   spec         Effect shape.
 * @param {number}   spec.modifier     Absolute modifier; direction comes from `modification`.
 * @param {string}   spec.modification `positive` or `negative`.
 * @param {string}   spec.cumulative   `cumulative` or `non-cumulative`.
 * @param {string[]} spec.abilities    Target ability UUIDs.
 * @return {Promise<string>} The effect UUID.
 */
async function seedEffect(
	label: string,
	spec: {
		modifier: number
		modification: 'positive' | 'negative'
		cumulative: 'cumulative' | 'non-cumulative'
		abilities: string[]
	},
): Promise<string> {
	return ledger.track('effect', await createObject(api, 'effect', {
		name: fixtureName(label),
		description: `${spec.modification} ${spec.modifier}`,
		...spec,
	}))
}

/**
 * Seed one carrier (skill / item / condition / event) holding effects.
 *
 * @param {string}   type    Carrier object type.
 * @param {string}   label   Human label, prefixed with the run id.
 * @param {string[]} effects Effect UUIDs the carrier grants.
 * @return {Promise<string>} The carrier UUID.
 */
async function seedCarrier(type: string, label: string, effects: string[]): Promise<string> {
	return ledger.track(type, await createObject(api, type, {
		name: fixtureName(label),
		effects,
	}))
}

/**
 * Seed a character. `ocName` is a REQUIRED relation to a `player` object
 * (`format: uuid`, `$ref: player`), not a display name — passing a plain string
 * is rejected with HTTP 400.
 *
 * @param {string}                label   Human label, prefixed with the run id.
 * @param {Record<string, unknown>} carriers Any of skills/items/conditions/events.
 * @return {Promise<string>} The character UUID.
 */
async function seedCharacter(label: string, carriers: Record<string, unknown> = {}): Promise<string> {
	const playerId = ledger.track('player', await createObject(api, 'player', {
		name: fixtureName(`${label}-player`),
	}))
	return ledger.track('character', await createObject(api, 'character', {
		name: fixtureName(label),
		ocName: playerId,
		type: 'player',
		...carriers,
	}))
}

/**
 * Assert the harness produced a stats block, then return it.
 *
 * @param {DerivedStats|null} stats     Harness result.
 * @param {string}            abilityId Ability the caller is about to read.
 * @return {DerivedStats} The stats block.
 */
function requireStats(stats: DerivedStats | null, abilityId: string): DerivedStats {
	expect(stats, 'CharacterService harness must be runnable on CI').not.toBeNull()
	expect(
		Object.keys(stats!),
		'the seeded ability must appear in the derived stats block',
	).toContain(abilityId)
	return stats!
}

// ===========================================================================
// Requirement: All game mechanics MUST be unit-tested over the OpenRegister
// object model — one focused derivation per mechanic.
// ===========================================================================

test.describe('game-mechanics — each mechanic derives over the OR object model', () => {

	// @e2e openspec/specs/game-mechanics/spec.md#ability-mechanic-serialises-into-derived-stats
	test('ability mechanic serialises into derived stats', async () => {
		// Expectation first: an ability carrying id/name/base, on a character
		// with NO carriers at all, derives to value == base and an EMPTY audit.
		const base = 7
		const abilityName = fixtureName('ability-serialise')
		const abilityId = ledger.track('ability', await createObject(api, 'ability', {
			name: abilityName,
			description: `base ${base}`,
			base,
		}))
		const characterId = await seedCharacter('ability-serialise-hero')

		const stats = await computeStatsLive(characterId)
		test.skip(harnessUnavailable(stats), 'CharacterService harness not runnable off CI (no server root, no docker).')
		const entry = requireStats(stats, abilityId)[abilityId]

		expect(entry.name, 'the ability NAME must serialise through').toBe(abilityName)
		expect(entry.base, 'base must be the persisted base').toBe(base)
		expect(entry.value, 'with no carriers, value must equal base').toBe(base)
		expect(entry.audit, 'an unmodified ability must carry an EMPTY audit trail').toEqual([])
	})

	// @e2e openspec/specs/game-mechanics/spec.md#effect-mechanic-applies-its-modifier-and-records-an-audit-entry
	test('effect mechanic applies its modifier and records an audit entry', async () => {
		// 10 + 3 = 13, with exactly one audit entry naming the effect.
		const base = 10
		const modifier = 3
		const expected = base + modifier
		const effectName = fixtureName('effect-audit')

		const abilityId = await seedAbility('effect-audit-strength', base)
		const effectId = ledger.track('effect', await createObject(api, 'effect', {
			name: effectName,
			modifier,
			modification: 'positive',
			cumulative: 'cumulative',
			abilities: [abilityId],
		}))
		const skillId = await seedCarrier('skill', 'effect-audit-skill', [effectId])
		const characterId = await seedCharacter('effect-audit-hero', { skills: [skillId] })

		const stats = await computeStatsLive(characterId)
		test.skip(harnessUnavailable(stats), 'CharacterService harness not runnable off CI (no server root, no docker).')
		const entry = requireStats(stats, abilityId)[abilityId]

		expect(entry.value, `base ${base} + effect ${modifier} must derive to ${expected}`).toBe(expected)
		expect(entry.audit, 'exactly one effect applied means exactly one audit entry').toHaveLength(1)

		const [audit] = entry.audit
		expect(audit.type).toBe('effect')
		expect(audit.effectId, 'the audit must name the effect that caused the change').toBe(effectId)
		expect(audit.effectName).toBe(effectName)
		expect(audit.old, 'before/after delta must be recorded').toBe(base)
		expect(audit.new).toBe(expected)
	})

	// @e2e openspec/specs/game-mechanics/spec.md#skill-mechanic-routes-its-linked-effects
	test('skill mechanic routes its linked effects', async () => {
		// A skill WITH effects applies them; a skill with NO effects on the same
		// character contributes nothing — so the value moves by exactly +4 and
		// the audit holds exactly one entry, not two.
		const base = 10
		const modifier = 4
		const expected = base + modifier

		const abilityId = await seedAbility('skill-route-strength', base)
		const effectId = await seedEffect('skill-route-effect', {
			modifier, modification: 'positive', cumulative: 'cumulative', abilities: [abilityId],
		})
		const carryingSkill = await seedCarrier('skill', 'skill-route-carrying', [effectId])
		const emptySkill = await seedCarrier('skill', 'skill-route-empty', [])
		const characterId = await seedCharacter('skill-route-hero', {
			skills: [carryingSkill, emptySkill],
		})

		const stats = await computeStatsLive(characterId)
		test.skip(harnessUnavailable(stats), 'CharacterService harness not runnable off CI (no server root, no docker).')
		const entry = requireStats(stats, abilityId)[abilityId]

		expect(entry.value, `only the carrying skill may contribute: ${base} + ${modifier}`).toBe(expected)
		expect(entry.audit, 'the effect-less skill must add no audit entry').toHaveLength(1)
	})

	// @e2e openspec/specs/game-mechanics/spec.md#item-mechanic-applies-worn-effects
	test('item mechanic applies worn effects', async () => {
		// The item route is independent of the skill route: this character has
		// no skills at all and still derives 10 + 6 = 16.
		const base = 10
		const modifier = 6
		const expected = base + modifier

		const abilityId = await seedAbility('item-worn-defense', base)
		const effectId = await seedEffect('item-worn-effect', {
			modifier, modification: 'positive', cumulative: 'cumulative', abilities: [abilityId],
		})
		const itemId = await seedCarrier('item', 'item-worn-ring', [effectId])
		const characterId = await seedCharacter('item-worn-hero', { items: [itemId] })

		const stats = await computeStatsLive(characterId)
		test.skip(harnessUnavailable(stats), 'CharacterService harness not runnable off CI (no server root, no docker).')
		const entry = requireStats(stats, abilityId)[abilityId]

		expect(entry.value, `an item-borne effect must apply with no skill present: ${expected}`).toBe(expected)
		expect(entry.audit).toHaveLength(1)
		expect(entry.audit[0].effectId).toBe(effectId)
	})

	// @e2e openspec/specs/game-mechanics/spec.md#condition-mechanic-applies-a-negative-modifier
	test('condition mechanic applies a negative modifier', async () => {
		// A `negative` modification SUBTRACTS, and the audit records the debuff
		// delta: 12 - 5 = 7, old 12 -> new 7.
		const base = 12
		const modifier = 5
		const expected = base - modifier

		const abilityId = await seedAbility('condition-debuff-hp', base)
		const effectId = await seedEffect('condition-debuff-curse', {
			modifier, modification: 'negative', cumulative: 'cumulative', abilities: [abilityId],
		})
		const conditionId = await seedCarrier('condition', 'condition-debuff-poisoned', [effectId])
		const characterId = await seedCharacter('condition-debuff-hero', { conditions: [conditionId] })

		const stats = await computeStatsLive(characterId)
		test.skip(harnessUnavailable(stats), 'CharacterService harness not runnable off CI (no server root, no docker).')
		const entry = requireStats(stats, abilityId)[abilityId]

		expect(entry.value, `a negative modification must SUBTRACT: ${base} - ${modifier}`).toBe(expected)
		expect(entry.audit).toHaveLength(1)
		expect(entry.audit[0].old, 'the audit must record the debuff delta').toBe(base)
		expect(entry.audit[0].new).toBe(expected)
	})
})

// ===========================================================================
// Requirement: The effect chain MUST be verified end-to-end.
// ===========================================================================

test.describe('game-mechanics — the effect chain end to end', () => {

	// @e2e openspec/specs/game-mechanics/spec.md#full-chain-derives-a-loaded-character-across-all-abilities
	test('full chain derives a loaded character across all abilities', async () => {
		// HP: 20 + 5 (skill) + 3 (item) - 2 (condition) + 1 (event) = 27, with a
		// four-entry audit in exactly that carrier order (CALC ordering is part
		// of the contract: skills, items, conditions, events).
		// MANA is seeded alongside and touched by nothing — it must stay at its
		// base with an empty audit, which is what makes this a claim about
		// "across all abilities" rather than about one number.
		const hpBase = 20
		const manaBase = 4
		const expectedHp = hpBase + 5 + 3 - 2 + 1

		const hpId = await seedAbility('chain-hp', hpBase)
		const manaId = await seedAbility('chain-mana', manaBase)

		const skillEffect = await seedEffect('chain-skill-effect', {
			modifier: 5, modification: 'positive', cumulative: 'cumulative', abilities: [hpId],
		})
		const itemEffect = await seedEffect('chain-item-effect', {
			modifier: 3, modification: 'positive', cumulative: 'cumulative', abilities: [hpId],
		})
		const conditionEffect = await seedEffect('chain-condition-effect', {
			modifier: 2, modification: 'negative', cumulative: 'cumulative', abilities: [hpId],
		})
		const eventEffect = await seedEffect('chain-event-effect', {
			modifier: 1, modification: 'positive', cumulative: 'cumulative', abilities: [hpId],
		})

		const characterId = await seedCharacter('chain-tank', {
			skills: [await seedCarrier('skill', 'chain-skill', [skillEffect])],
			items: [await seedCarrier('item', 'chain-item', [itemEffect])],
			conditions: [await seedCarrier('condition', 'chain-condition', [conditionEffect])],
			events: [await seedCarrier('event', 'chain-event', [eventEffect])],
		})

		const stats = await computeStatsLive(characterId)
		test.skip(harnessUnavailable(stats), 'CharacterService harness not runnable off CI (no server root, no docker).')
		const all = requireStats(stats, hpId)
		const hp = all[hpId]

		expect(hp.value, `${hpBase} + 5 + 3 - 2 + 1 must derive to ${expectedHp}`).toBe(expectedHp)
		expect(hp.audit, 'all four carrier types must contribute one entry each').toHaveLength(4)
		expect(
			hp.audit.map(a => a.effectId),
			'application order is skills, then items, then conditions, then events',
		).toEqual([skillEffect, itemEffect, conditionEffect, eventEffect])

		expect(all[manaId].value, 'an ability no effect targets must keep its base').toBe(manaBase)
		expect(all[manaId].audit, 'and must keep an EMPTY audit — no cross-ability bleed').toEqual([])
	})

	// @e2e openspec/specs/game-mechanics/spec.md#cumulative-and-non-cumulative-effects-behave-correctly-in-the-chain
	test('cumulative and non-cumulative effects behave correctly in the chain', async () => {
		// Both effects are reachable TWICE in one derivation pass — once via a
		// skill and once via an item. The cumulative one stacks on every
		// encounter; the non-cumulative one applies exactly once. The audit
		// length has to reflect the number of APPLICATIONS, not of encounters.
		const stackBase = 0
		const onceBase = 0
		const modifier = 2
		const expectedStack = stackBase + (modifier * 2)
		const expectedOnce = onceBase + modifier

		const stackId = await seedAbility('cum-stacking', stackBase)
		const onceId = await seedAbility('cum-once', onceBase)

		const cumulativeEffect = await seedEffect('cum-cumulative', {
			modifier, modification: 'positive', cumulative: 'cumulative', abilities: [stackId],
		})
		const nonCumulativeEffect = await seedEffect('cum-non-cumulative', {
			modifier, modification: 'positive', cumulative: 'non-cumulative', abilities: [onceId],
		})

		// The SAME two effects on both carriers — that is what makes them
		// reachable twice in one pass.
		const both = [cumulativeEffect, nonCumulativeEffect]
		const characterId = await seedCharacter('cum-hero', {
			skills: [await seedCarrier('skill', 'cum-skill', both)],
			items: [await seedCarrier('item', 'cum-item', both)],
		})

		const stats = await computeStatsLive(characterId)
		test.skip(harnessUnavailable(stats), 'CharacterService harness not runnable off CI (no server root, no docker).')
		const all = requireStats(stats, stackId)

		expect(
			all[stackId].value,
			`a cumulative effect reached twice must apply twice: ${expectedStack}`,
		).toBe(expectedStack)
		expect(all[stackId].audit, 'two applications means two audit entries').toHaveLength(2)

		expect(
			all[onceId].value,
			`a non-cumulative effect reached twice must apply once: ${expectedOnce}`,
		).toBe(expectedOnce)
		expect(all[onceId].audit, 'one application means ONE audit entry').toHaveLength(1)
	})

	// @e2e openspec/specs/game-mechanics/spec.md#characters-are-computed-independently-across-the-roster
	test('characters are computed independently across the roster', async () => {
		// Driven through calculateAllCharacters() — the BATCH entry point — so
		// this is a claim about roster derivation, not two separate calls.
		// The loaded character derives 10 + 3 = 13; the bare character, derived
		// in the same pass, keeps a pure base score and an empty audit.
		const base = 10
		const modifier = 3
		const expectedLoaded = base + modifier

		const abilityId = await seedAbility('roster-strength', base)
		const effectId = await seedEffect('roster-effect', {
			modifier, modification: 'positive', cumulative: 'cumulative', abilities: [abilityId],
		})
		const skillId = await seedCarrier('skill', 'roster-skill', [effectId])

		const loadedId = await seedCharacter('roster-loaded', { skills: [skillId] })
		const bareId = await seedCharacter('roster-bare')

		const roster = await computeRosterLive([loadedId, bareId])
		test.skip(harnessUnavailable(roster), 'CharacterService harness not runnable off CI (no server root, no docker).')
		expect(roster, 'calculateAllCharacters() must be runnable on CI').not.toBeNull()

		expect(
			Object.keys(roster!).sort(),
			'both seeded characters must be resolved by the batch call',
		).toEqual([loadedId, bareId].sort())

		const loaded = roster![loadedId][abilityId]
		const bare = roster![bareId][abilityId]

		expect(loaded.value, `the loaded character derives ${base} + ${modifier}`).toBe(expectedLoaded)
		expect(loaded.audit.length, 'and carries an audit trail').toBeGreaterThan(0)

		expect(bare.value, 'the bare character keeps its pure base score').toBe(base)
		expect(bare.audit, 'and an EMPTY audit — no cross-character bleed').toEqual([])
	})
})
