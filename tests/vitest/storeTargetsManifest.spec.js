/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The Marketplace page asks which world a bundle goes into (REQ-ACS-003): its
 * `targets` read the larpinq worlds (`setting` schema), the same relation the
 * skills, conditions and effects carry, so the picked id is a world id.
 *
 * @spec openspec/changes/admin-configuration-store/specs/admin-configuration-store/spec.md#requirement-req-acs-003-a-bundle-goes-into-the-world-the-organiser-chooses
 */

import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { describe, expect, it } from 'vitest'
import register from '../../lib/Settings/larpinq_register.json'
import pkg from '../../package.json'
import manifest from '../../src/manifest.json'

const store = manifest.pages.find((p) => p.id === 'Store')
const schemas = register.components.schemas
const l10n = (locale) => JSON.parse(readFileSync(resolve(__dirname, `../../l10n/${locale}.json`), 'utf8')).translations

describe('the Marketplace install target (REQ-ACS-003)', () => {
	it('offers the larpinq worlds as install targets, labelled by their name', () => {
		expect(store.type).toBe('store')
		expect(store.config.targets).toEqual({ register: 'larpinq', schema: 'setting', valueField: 'id', labelField: 'name' })
		expect(schemas.setting.properties.name).toBeDefined()
	})

	it('targets the relation the world-scoped game rules carry', () => {
		for (const schema of ['skill', 'condition', 'effect']) {
			expect(schemas[schema].properties.setting, schema).toBeDefined()
		}
	})

	it('declares where an install target lands, so the engine accepts the picked world', () => {
		expect(manifest.store.installTarget).toEqual({ property: 'setting', schemas: ['larping_skill', 'condition', 'effect'] })
		const slugs = Object.values(schemas).map((schema) => schema.slug)
		for (const slug of manifest.store.installTarget.schemas) {
			expect(slugs, slug).toContain(slug)
		}
	})

	it('narrows the list to the bundles that fit the active world ("Past bij")', () => {
		// The same optional token the world-scoped index pages filter on, so the
		// Marketplace and the lists agree on which world is active.
		expect(store.config.activeTarget).toBe('@workspace.activeWorld?')
	})

	it('names the picker in English and Dutch', () => {
		expect(store.config.targetLabel).toBe('Install into world')
		expect(l10n('en')['Install into world']).toBe('Install into world')
		expect(l10n('nl')['Install into world']).toBe('Installeren in wereld')
	})

	it('depends on the nextcloud-vue release whose cards say where an item is installed', () => {
		// "Geïnstalleerd in {world}" per card: CnStorePage reads the engine's
		// `installed` answer (openregister store-installed-in) from 2.226.0 on.
		const floor = pkg.dependencies['@conduction/nextcloud-vue'].replace(/^[\^~]/, '').split('.').map(Number)
		expect(floor[0] > 2 || (floor[0] === 2 && floor[1] >= 226)).toBe(true)
	})
})
