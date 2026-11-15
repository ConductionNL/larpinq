/**
 * SPDX-FileCopyrightText: 2026 Conduction / Larpinq Contributors
 * SPDX-License-Identifier: EUPL-1.2
 *
 * src/pinia.js is the one Pinia instance main.js installs: a module
 * singleton that stores defined against it share state through.
 */

import { defineStore, setActivePinia } from 'pinia'
import { describe, expect, it } from 'vitest'
import pinia from '../../src/pinia.js'

describe('the app pinia', () => {
	it('is an installable Pinia instance', () => {
		expect(typeof pinia.install).toBe('function')
		expect(typeof pinia.use).toBe('function')
		expect(pinia.state.value).toEqual({})
	})

	it('is the same instance on every import', async () => {
		const again = (await import('../../src/pinia.js')).default

		expect(again).toBe(pinia)
	})

	it('holds the state of the stores used against it', () => {
		setActivePinia(pinia)
		const useCounter = defineStore('pinia-spec-counter', {
			state: () => ({ count: 0 }),
			actions: { bump() { this.count++ } },
		})

		useCounter().bump()
		useCounter(pinia).bump()

		expect(useCounter().count).toBe(2)
		expect(pinia.state.value['pinia-spec-counter']).toEqual({ count: 2 })
	})
})
