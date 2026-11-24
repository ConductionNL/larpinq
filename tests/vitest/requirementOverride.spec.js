/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Unit tests for a game master's requirement override from the character page
 * (capability-rows-missing-requirements 5.1, REQ-SRE-001): the skills offered,
 * the one write that adds the skill with its requirementOverrides[] entry,
 * OverrideRequirementDialog, and the header action only game masters see.
 *
 * @spec openspec/specs/skill-requirement-enforcement/spec.md#requirement-a-game-master-records-an-override-on-the-character-page-req-sre-001
 */

import fs from 'fs'
import path from 'path'
import { fileURLToPath } from 'url'
import { afterEach, describe, expect, it, vi } from 'vitest'
import OverrideRequirementDialog from '../../src/dialogs/OverrideRequirementDialog.vue'
import manifest from '../../src/manifest.json'
import { fetchOverrideOptions, grantWithOverride, idsOf, overrideBody } from '../../src/services/requirementOverride.js'

vi.mock('@nextcloud/l10n', () => ({
	translate: (app, key, vars = {}) => key.replace(/\{(\w+)\}/g, (match, name) => String(vars[name] ?? match)),
}))
vi.mock('@nextcloud/vue/components/NcButton', () => ({ default: {} }))
vi.mock('@nextcloud/vue/components/NcDialog', () => ({ default: {} }))
vi.mock('@nextcloud/vue/components/NcNoteCard', () => ({ default: {} }))
vi.mock('@nextcloud/vue/components/NcSelect', () => ({ default: {} }))
vi.mock('@nextcloud/vue/components/NcTextArea', () => ({ default: {} }))

const here = path.dirname(fileURLToPath(import.meta.url))
const REGISTRY = fs.readFileSync(path.resolve(here, '../../src/registry.js'), 'utf8')
const ROUTES = fs.readFileSync(path.resolve(here, '../../appinfo/routes.php'), 'utf8')

/**
 * Fake fetch answering each request in turn.
 *
 * @param {Array<{status: number, body: object}>} answers The answers, in order.
 * @return {Array<{url: string, method: string, body: object|undefined}>} The calls.
 */
function fakeFetch(answers) {
	const calls = []
	globalThis.fetch = async (url, options = {}) => {
		calls.push({ url, method: options.method ?? 'GET', body: options.body ? JSON.parse(options.body) : undefined })
		const answer = answers.shift()
		return { ok: answer.status < 400, status: answer.status, json: async () => answer.body }
	}
	return calls
}

afterEach(() => {
	delete globalThis.fetch
})

const PREVIOUS = { skill: 's-old', reason: 'Granted at character creation', overriddenBy: 'joris' }
const CHOSEN = { id: 'char-1', name: 'Chosen One', setting: 'w-1', skills: ['s-sword', { id: 's-old' }], requirementOverrides: [PREVIOUS] }
const SKILLS = [
	{ id: 's-dragon', name: 'Dragon Speech' },
	{ id: 's-sword', name: 'Swordplay' },
	{ '@self': { id: 's-alchemy' }, name: 'Alchemy' },
	{ name: 'No id' },
]

describe('idsOf', () => {
	it('reads ids stored as strings or objects and drops empty ones', () => {
		expect(idsOf(['a', { id: 'b' }, '', null, { name: 'x' }])).toEqual(['a', 'b'])
		expect(idsOf('a')).toEqual([])
	})
})

describe('fetchOverrideOptions', () => {
	it('offers the world\'s skills the character does not have, by name', async () => {
		const calls = fakeFetch([{ status: 200, body: CHOSEN }, { status: 200, body: { results: SKILLS } }])

		const options = await fetchOverrideOptions('char-1')

		expect(calls.map((call) => call.url)).toEqual([
			'/index.php/apps/openregister/api/objects/larpinq/character/char-1',
			'/index.php/apps/openregister/api/objects/larpinq/larping_skill?_limit=500&setting=w-1',
		])
		expect(options.skills).toEqual([{ id: 's-alchemy', name: 'Alchemy' }, { id: 's-dragon', name: 'Dragon Speech' }])
	})

	it('answers null without an id or when the character cannot be read, and offers nothing when the skills cannot be', async () => {
		expect(await fetchOverrideOptions('')).toBeNull()
		fakeFetch([{ status: 404, body: {} }])
		expect(await fetchOverrideOptions('char-x')).toBeNull()
		fakeFetch([{ status: 200, body: { id: 'char-2' } }, { status: 500, body: {} }])
		expect((await fetchOverrideOptions('char-2')).skills).toEqual([])
	})
})

describe('the override write', () => {
	it('adds the skill and an override entry with the trimmed reason, keeping the earlier ones', () => {
		expect(overrideBody(CHOSEN, 's-dragon', '  Plot reward from Summer LARP finale ')).toEqual({
			skills: ['s-sword', 's-old', 's-dragon'],
			requirementOverrides: [PREVIOUS, { skill: 's-dragon', reason: 'Plot reward from Summer LARP finale' }],
		})
	})

	it('does not list a skill twice', () => {
		expect(overrideBody({ skills: ['s-dragon'] }, 's-dragon', 'why').skills).toEqual(['s-dragon'])
		expect(overrideBody({}, 's-dragon', 'why')).toEqual({ skills: ['s-dragon'], requirementOverrides: [{ skill: 's-dragon', reason: 'why' }] })
	})

	it('sends the skill and the override in one PATCH', async () => {
		const calls = fakeFetch([{ status: 200, body: {} }])

		expect(await grantWithOverride(CHOSEN, 's-dragon', 'Plot reward')).toEqual({ ok: true, message: '' })
		expect(calls).toEqual([{
			url: '/index.php/apps/openregister/api/objects/larpinq/character/char-1',
			method: 'PATCH',
			body: { skills: ['s-sword', 's-old', 's-dragon'], requirementOverrides: [PREVIOUS, { skill: 's-dragon', reason: 'Plot reward' }] },
		}])
	})

	it('passes the server\'s refusal on', async () => {
		fakeFetch([{ status: 422, body: { message: 'Only game masters may record requirement overrides.' } }])

		expect(await grantWithOverride(CHOSEN, 's-dragon', 'x')).toEqual({ ok: false, message: 'Only game masters may record requirement overrides.' })
	})
})

describe('OverrideRequirementDialog', () => {
	it('reads the character id from the prop, else the route', () => {
		expect(OverrideRequirementDialog.computed.id.call({ characterId: 'char-1', $route: { params: { id: 'char-2' } } })).toBe('char-1')
		expect(OverrideRequirementDialog.computed.id.call({ characterId: '@objectId', $route: { params: { id: 'char-2' } } })).toBe('char-2')
	})

	it('loads the skills, or fails when the character cannot be read', async () => {
		fakeFetch([{ status: 200, body: CHOSEN }, { status: 200, body: { results: SKILLS } }])
		const ready = { ...OverrideRequirementDialog.data(), id: 'char-1' }
		await OverrideRequirementDialog.mounted.call(ready)
		expect(ready).toMatchObject({ status: 'ready', character: CHOSEN })
		expect(ready.skills).toHaveLength(2)

		fakeFetch([{ status: 403, body: {} }])
		const failed = { ...OverrideRequirementDialog.data(), id: 'char-1' }
		await OverrideRequirementDialog.mounted.call(failed)
		expect(failed.status).toBe('failed')
	})

	it('asks for a skill and a reason that is more than spaces', () => {
		const savable = (self) => OverrideRequirementDialog.computed.savable.call({ status: 'ready', selected: { id: 's-dragon' }, reason: 'Plot reward', busy: false, ...self })
		expect(savable({})).toBe(true)
		expect(savable({ reason: '   ' })).toBe(false)
		expect(savable({ selected: null })).toBe(false)
		expect(savable({ busy: true })).toBe(false)
		expect(savable({ status: 'loading' })).toBe(false)
	})

	it('grants Dragon Speech with the reason and says so', async () => {
		const calls = fakeFetch([{ status: 200, body: {} }])
		const self = { ...OverrideRequirementDialog.data(), id: 'char-1', savable: true, status: 'ready', character: CHOSEN, selected: { id: 's-dragon', name: 'Dragon Speech' }, reason: 'Plot reward from Summer LARP finale' }

		await OverrideRequirementDialog.methods.save.call(self)

		expect(calls[0].body.requirementOverrides.at(-1)).toEqual({ skill: 's-dragon', reason: 'Plot reward from Summer LARP finale' })
		expect(self).toMatchObject({ status: 'granted', grantedName: 'Dragon Speech', busy: false, refusal: '' })
	})

	it.each([
		['the server\'s reason', { message: 'A requirement override must carry a non-empty reason.' }, 'The override was not saved: A requirement override must carry a non-empty reason.'],
		['no reason', {}, 'The override was not saved: unknown error'],
	])('explains a refusal with %s and stays open', async (name, body, refusal) => {
		fakeFetch([{ status: 422, body }])
		const self = { ...OverrideRequirementDialog.data(), id: 'char-1', savable: true, status: 'ready', character: CHOSEN, selected: { id: 's-dragon', name: 'Dragon Speech' }, reason: 'x' }

		await OverrideRequirementDialog.methods.save.call(self)

		expect(self).toMatchObject({ status: 'ready', refusal, busy: false, grantedName: '' })
	})

	it('writes nothing while it may not save', async () => {
		const calls = fakeFetch([])
		await OverrideRequirementDialog.methods.save.call({ savable: false })
		expect(calls).toEqual([])
	})
})

describe('the Override a requirement action', () => {
	it('opens the dialog from the character page, shown on the override access answer', () => {
		const page = manifest.pages.find((candidate) => candidate.id === 'CharacterDetail')
		const action = page.config.headerActions.find((candidate) => candidate.id === 'override-requirement')

		expect(action).toEqual({
			id: 'override-requirement',
			type: 'open-modal',
			target: 'OverrideRequirementDialog',
			label: 'Override a requirement',
			icon: 'ShieldAccountOutline',
			visibleWhen: { endpoint: '/apps/larpinq/api/characters/game-master-access', field: 'allowed', op: 'eq', value: true },
		})
		expect(REGISTRY).toMatch(/OverrideRequirementDialog: \{\s*kind: 'modal',\s*component: OverrideRequirementDialog,/)
		expect(ROUTES).toContain("['name' => 'characters#gameMasterAccess', 'url' => '/api/characters/game-master-access', 'verb' => 'GET']")
	})
})
