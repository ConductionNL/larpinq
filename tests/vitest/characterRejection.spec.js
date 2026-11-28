/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Unit tests for rejecting a character (capability-rows-missing-requirements
 * 6.1, REQ-CTA-001): the reject transition request, RejectCharacterDialog,
 * the Reject and Submit again actions on the character page, and the reason
 * in the Characters list.
 *
 * @spec openspec/specs/character-management/spec.md#requirement-a-game-master-rejects-a-submitted-character-with-a-reason-req-cta-001
 */

import fs from 'fs'
import path from 'path'
import { fileURLToPath } from 'url'
import { afterEach, describe, expect, it, vi } from 'vitest'
import RejectCharacterDialog from '../../src/dialogs/RejectCharacterDialog.vue'
import manifest from '../../src/manifest.json'
import { rejectCharacter } from '../../src/services/characterApproval.js'

vi.mock('@nextcloud/l10n', () => ({
	translate: (app, key, vars = {}) => key.replace(/\{(\w+)\}/g, (match, name) => String(vars[name] ?? match)),
}))
vi.mock('@nextcloud/vue/components/NcButton', () => ({ default: {} }))
vi.mock('@nextcloud/vue/components/NcDialog', () => ({ default: {} }))
vi.mock('@nextcloud/vue/components/NcNoteCard', () => ({ default: {} }))
vi.mock('@nextcloud/vue/components/NcTextArea', () => ({ default: {} }))

const here = path.dirname(fileURLToPath(import.meta.url))
const REGISTRY = fs.readFileSync(path.resolve(here, '../../src/registry.js'), 'utf8')
const ICONS = fs.readFileSync(path.resolve(here, '../../src/icons.js'), 'utf8')
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

const page = (id) => manifest.pages.find((candidate) => candidate.id === id)

describe('rejectCharacter', () => {
	it('posts the reject transition with the trimmed reason', async () => {
		const calls = fakeFetch([{ status: 200, body: {} }])

		expect(await rejectCharacter('char-1', '  Pick one bloodline, not two ')).toEqual({ ok: true, message: '' })
		expect(calls).toEqual([{
			url: '/index.php/apps/openregister/api/objects/char-1/transition',
			method: 'POST',
			body: { action: 'reject', data: { rejectionReason: 'Pick one bloodline, not two' } },
		}])
	})

	it.each([
		['an error field', { error: 'Transition "reject" is not allowed from current state "approved".' }, 'Transition "reject" is not allowed from current state "approved".'],
		['a message field', { message: 'You do not have permission to transition object "char-1".' }, 'You do not have permission to transition object "char-1".'],
		['nothing', {}, ''],
	])('passes a refusal with %s on', async (name, body, message) => {
		fakeFetch([{ status: 403, body }])

		expect(await rejectCharacter('char-1', 'x')).toEqual({ ok: false, message })
	})
})

describe('RejectCharacterDialog', () => {
	it('reads the character id from the prop, else the route', () => {
		expect(RejectCharacterDialog.computed.id.call({ characterId: 'char-1', $route: { params: { id: 'char-2' } } })).toBe('char-1')
		expect(RejectCharacterDialog.computed.id.call({ characterId: '@objectId', $route: { params: { id: 'char-2' } } })).toBe('char-2')
	})

	it('asks for a reason that is more than spaces', () => {
		const rejectable = (self) => RejectCharacterDialog.computed.rejectable.call({ id: 'char-1', reason: 'Pick one bloodline', busy: false, ...self })
		expect(rejectable({})).toBe(true)
		expect(rejectable({ reason: '  ' })).toBe(false)
		expect(rejectable({ busy: true })).toBe(false)
		expect(rejectable({ id: '' })).toBe(false)
	})

	it('rejects with the reason and says the owner is told', async () => {
		const calls = fakeFetch([{ status: 200, body: {} }])
		const self = { ...RejectCharacterDialog.data(), id: 'char-1', rejectable: true, reason: 'Pick one bloodline, not two' }

		await RejectCharacterDialog.methods.reject.call(self)

		expect(calls[0].body).toEqual({ action: 'reject', data: { rejectionReason: 'Pick one bloodline, not two' } })
		expect(self).toMatchObject({ done: true, busy: false, refusal: '' })
	})

	it.each([
		['the server\'s reason', { error: 'Not allowed from "approved".' }, 'The character was not rejected: Not allowed from "approved".'],
		['no reason', {}, 'The character was not rejected: unknown error'],
	])('explains a refusal with %s and stays open', async (name, body, refusal) => {
		fakeFetch([{ status: 422, body }])
		const self = { ...RejectCharacterDialog.data(), id: 'char-1', rejectable: true, reason: 'x' }

		await RejectCharacterDialog.methods.reject.call(self)

		expect(self).toMatchObject({ done: false, busy: false, refusal })
	})

	it('posts nothing while it may not reject', async () => {
		const calls = fakeFetch([])
		await RejectCharacterDialog.methods.reject.call({ rejectable: false })
		expect(calls).toEqual([])
	})
})

describe('the character page and list', () => {
	it('offers Reject to game masters and Submit again on a rejected character', () => {
		const actions = page('CharacterDetail').config.headerActions

		expect(actions.find((action) => action.id === 'reject-character')).toEqual({
			id: 'reject-character',
			type: 'open-modal',
			target: 'RejectCharacterDialog',
			label: 'Reject',
			icon: 'CloseCircleOutline',
			visibleWhen: { endpoint: '/apps/larpinq/api/characters/game-master-access', field: 'allowed', op: 'eq', value: true },
		})
		expect(actions.find((action) => action.id === 'resubmit-character')).toEqual({
			id: 'resubmit-character',
			type: 'api-call',
			label: 'Submit again',
			icon: 'Refresh',
			url: '/apps/larpinq/api/characters/{objectId}/resubmit',
			method: 'POST',
			successMessage: 'Submitted again. The character waits for a game master.',
			visibleWhen: { field: 'approved', op: 'eq', value: 'rejected' },
		})
		expect(REGISTRY).toMatch(/RejectCharacterDialog: \{\s*kind: 'modal',\s*component: RejectCharacterDialog,/)
		expect(ICONS).toMatch(/^\tCloseCircleOutline,$/m)
		expect(ICONS).toMatch(/^\tRefresh,$/m)
		expect(ROUTES).toContain("['name' => 'characterResubmission#resubmit', 'url' => '/api/characters/{id}/resubmit', 'verb' => 'POST']")
	})

	it('shows the rejection reason beside the approval state in the Characters list', () => {
		const keys = page('Characters').config.columns.map((column) => column.key)

		expect(keys.indexOf('rejectionReason')).toBe(keys.indexOf('approved') + 1)
	})
})
