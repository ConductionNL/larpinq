/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Unit tests for the event run sheet download (capability-rows-missing-requirements
 * 4.1, pdf-export RUN-020): the URL, RunsheetDownloadDialog's template picker and
 * download, and the EventDetail header action that only game masters see.
 *
 * @spec openspec/specs/pdf-export/spec.md
 */

import fs from 'fs'
import path from 'path'
import { fileURLToPath } from 'url'
import { afterEach, describe, expect, it, vi } from 'vitest'
import RunsheetDownloadDialog from '../../src/dialogs/RunsheetDownloadDialog.vue'
import manifest from '../../src/manifest.json'
import { runsheetUrl } from '../../src/services/eventRunsheet.js'

vi.mock('@nextcloud/l10n', () => ({ translate: (app, key) => key }))
vi.mock('@nextcloud/vue/components/NcButton', () => ({ default: {} }))
vi.mock('@nextcloud/vue/components/NcDialog', () => ({ default: {} }))
vi.mock('@nextcloud/vue/components/NcSelect', () => ({ default: {} }))

const here = path.dirname(fileURLToPath(import.meta.url))
const REGISTRY = fs.readFileSync(path.resolve(here, '../../src/registry.js'), 'utf8')
const ROUTES = fs.readFileSync(path.resolve(here, '../../appinfo/routes.php'), 'utf8')

const TPL = '00000000-0000-0000-0000-000000000010'

/**
 * Fake fetch with one answer.
 *
 * @param {number} status The HTTP status.
 * @param {object} body The JSON body.
 * @return {Array<string>} The requested URLs.
 */
function answer(status, body) {
	const urls = []
	globalThis.fetch = async (url) => {
		urls.push(url)
		return { ok: status < 400, status, json: async () => body }
	}
	return urls
}

afterEach(() => {
	delete globalThis.fetch
	vi.unstubAllGlobals()
})

describe('runsheetUrl', () => {
	it('serves the event in the template, both encoded', () => {
		expect(runsheetUrl('evt-1', TPL)).toBe(`/index.php/apps/larpinq/events/evt-1/runsheet/${TPL}`)
		expect(runsheetUrl('a/b', 'c d')).toBe('/index.php/apps/larpinq/events/a%2Fb/runsheet/c%20d')
	})
})

describe('RunsheetDownloadDialog', () => {
	it.each([
		['the prop', { eventId: 'evt-1', $route: { params: { id: 'evt-2' } } }, 'evt-1'],
		['the route', { eventId: '', $route: { params: { id: 'evt-2' } } }, 'evt-2'],
		['a placeholder skipped', { eventId: '@objectId', $route: { params: { id: 'evt-2' } } }, 'evt-2'],
	])('reads the event id from %s', (name, self, id) => {
		expect(RunsheetDownloadDialog.computed.id.call(self)).toBe(id)
	})

	it('loads the document app\'s templates', async () => {
		const urls = answer(200, { available: true, templates: [{ id: TPL, name: 'Cast list' }, { id: '', name: 'broken' }] })
		const self = RunsheetDownloadDialog.data()

		await RunsheetDownloadDialog.mounted.call(self)

		expect(urls).toEqual(['/index.php/apps/larpinq/api/pdf/templates'])
		expect(self).toMatchObject({ loading: false, available: true, templates: [{ id: TPL, name: 'Cast list' }] })
	})

	it('says the document app is missing', async () => {
		answer(200, { available: false, templates: [] })
		const self = RunsheetDownloadDialog.data()

		await RunsheetDownloadDialog.mounted.call(self)

		expect(self).toMatchObject({ loading: false, available: false, templates: [] })
	})

	it('allows Download only once loaded, with a template and an event', () => {
		const downloadable = RunsheetDownloadDialog.computed.downloadable
		expect(downloadable.call({ id: 'evt-1', loading: false, selected: { id: TPL } })).toBe(true)
		expect(downloadable.call({ id: 'evt-1', loading: true, selected: { id: TPL } })).toBe(false)
		expect(downloadable.call({ id: 'evt-1', loading: false, selected: null })).toBe(false)
		expect(downloadable.call({ id: '', loading: false, selected: { id: TPL } })).toBe(false)
	})

	it('opens the run sheet in a new tab and closes', () => {
		const open = vi.fn()
		vi.stubGlobal('window', { open })
		const self = { id: 'evt-1', downloadable: true, selected: { id: TPL }, $emit: vi.fn() }

		RunsheetDownloadDialog.methods.download.call(self)

		expect(open).toHaveBeenCalledWith(`/index.php/apps/larpinq/events/evt-1/runsheet/${TPL}`, '_blank', 'noopener')
		expect(self.$emit).toHaveBeenCalledWith('close')
	})

	it('opens nothing while Download is not allowed', () => {
		const open = vi.fn()
		vi.stubGlobal('window', { open })
		const self = { downloadable: false, $emit: vi.fn() }

		RunsheetDownloadDialog.methods.download.call(self)

		expect(open).not.toHaveBeenCalled()
		expect(self.$emit).not.toHaveBeenCalled()
	})
})

describe('the Download run sheet action', () => {
	it('opens the dialog from the event page, shown when the run-sheet access answer allows it', () => {
		const page = manifest.pages.find((candidate) => candidate.id === 'EventDetail')
		const action = page.config.headerActions.find((candidate) => candidate.id === 'download-runsheet')

		expect(action).toEqual({
			id: 'download-runsheet',
			type: 'open-modal',
			target: 'RunsheetDownloadDialog',
			label: 'Download run sheet',
			icon: 'FilePdfBox',
			visibleWhen: { endpoint: '/apps/larpinq/api/events/runsheet-access', field: 'allowed', op: 'eq', value: true },
		})
		expect(REGISTRY).toMatch(/RunsheetDownloadDialog: \{\s*kind: 'modal',\s*component: RunsheetDownloadDialog,/)
		expect(ROUTES).toContain("['name' => 'events#runsheetAccess', 'url' => '/api/events/runsheet-access', 'verb' => 'GET']")
	})
})
