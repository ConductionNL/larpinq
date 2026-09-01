/**
 * SPDX-FileCopyrightText: 2026 Conduction / Larpinq Contributors
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Unit tests for the multi-tenancy wiring in src/store/modules/object.js.
 *
 * Covers (spec: larpinq-adopt-or-abstractions, Phase 4):
 *   • The module-local `setObjectStoreTenantUuid()` setter is exported
 *     and accepts string + null inputs without throwing.
 *   • The `organisationUuidGetter` option passed to the library's
 *     `createObjectStore()` reads the same closure value the setter
 *     writes — so the next outgoing request stamps
 *     `X-OpenRegister-Organisation` with the new UUID immediately.
 *
 * `@conduction/nextcloud-vue` is mocked with a minimal stand-in for
 * `createObjectStore()` + the three plugin factories the Larpinq
 * store registers. The stand-in captures the `organisationUuidGetter`
 * option so we can assert the closure wiring without dragging the
 * library's Vue / Nextcloud transitive deps into the unit harness
 * (the published nc-vue CJS bundle uses class fields not yet supported
 * by this vitest runner).
 */

import { beforeEach, describe, expect, it, vi } from 'vitest'

let capturedOptions = null

vi.mock('@conduction/nextcloud-vue', () => {
	function createObjectStore(storeId, options = {}) {
		capturedOptions = { storeId, ...options }
		// Return a function that, when invoked, hands back a tiny
		// fake "store" exposing only what App.vue calls + what we
		// want to assert in the tests.
		return function useFakeStore() {
			return {
				setActiveTenantOrganisation: vi.fn(),
				_resolveOrganisationUuid() {
					try {
						const v =
							options.organisationUuidGetter
							&& options.organisationUuidGetter()
						return typeof v === 'string' && v.length > 0 ? v : null
					} catch {
						return null
					}
				},
			}
		}
	}
	return {
		createObjectStore,
		filesPlugin: () => ({ name: 'files' }),
		auditTrailsPlugin: () => ({ name: 'auditTrails' }),
		relationsPlugin: () => ({ name: 'relations' }),
	}
})

// Import AFTER vi.mock — vitest hoists the mock above the import.
const { useObjectStore, setObjectStoreTenantUuid } =
	await import('../../src/store/modules/object.js')

describe('larpinq object store — multi-tenancy wiring', () => {
	beforeEach(() => {
		// Reset closure state between tests so cases are order-independent.
		setObjectStoreTenantUuid(null)
	})

	it('exports `setObjectStoreTenantUuid` as a function', () => {
		expect(typeof setObjectStoreTenantUuid).toBe('function')
	})

	it('registers all three sub-resource plugins with the factory', () => {
		expect(capturedOptions).not.toBeNull()
		expect(capturedOptions.storeId).toBe('object')
		const names = (capturedOptions.plugins || []).map((p) => p.name).sort()
		expect(names).toEqual(['auditTrails', 'files', 'relations'])
	})

	it('passes an `organisationUuidGetter` closure to the factory', () => {
		expect(typeof capturedOptions.organisationUuidGetter).toBe('function')
	})

	it('the getter closure reads the value written by setObjectStoreTenantUuid', () => {
		const getter = capturedOptions.organisationUuidGetter

		setObjectStoreTenantUuid(null)
		expect(getter()).toBeNull()

		setObjectStoreTenantUuid('tenant-aaa-111')
		expect(getter()).toBe('tenant-aaa-111')

		setObjectStoreTenantUuid('tenant-bbb-222')
		expect(getter()).toBe('tenant-bbb-222')

		// Empty string is treated like null per the setter contract.
		setObjectStoreTenantUuid('')
		expect(getter()).toBeNull()

		// Non-string is treated like null.
		setObjectStoreTenantUuid(undefined)
		expect(getter()).toBeNull()
	})

	it('the constructed store resolves the UUID through the closure (header-stamp path)', () => {
		const store = useObjectStore()
		setObjectStoreTenantUuid('tenant-xyz')
		expect(store._resolveOrganisationUuid()).toBe('tenant-xyz')

		setObjectStoreTenantUuid(null)
		expect(store._resolveOrganisationUuid()).toBeNull()
	})

	it('exposes the setActiveTenantOrganisation hook on the constructed store', () => {
		const store = useObjectStore()
		expect(typeof store.setActiveTenantOrganisation).toBe('function')
	})
})
