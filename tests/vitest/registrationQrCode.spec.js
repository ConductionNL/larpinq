/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Unit tests for src/components/RegistrationQrCode.vue: only an accepted
 * registration's code is read and drawn, as an SVG of the right size with its
 * quiet zone, with the player's name for the print (events-qr-checkin,
 * REQ-EQC-002).
 *
 * @spec openspec/specs/event-checkin-roster/spec.md
 */

import { afterEach, describe, expect, it, vi } from 'vitest'
import RegistrationQrCode from '../../src/components/RegistrationQrCode.vue'
import { qrDrawing } from '../../src/services/checkinCode.js'

const CODE = 'ABCDEFGHIJKLMNOPQRSTUVWXY2'
const REG = 'e0000000-0000-4000-8000-000000000001'
const c = RegistrationQrCode.computed

/**
 * The cell's `this` for a row, with its computed values.
 *
 * @param {object} row The registration row.
 * @param {string} code The code read so far.
 * @return {object} The cell.
 */
function cell(row, code = '') {
	const self = { row, code }
	self.acceptedId = c.acceptedId.call(self)
	self.drawing = c.drawing.call(self)
	self.viewBox = c.viewBox.call(self)
	return self
}

afterEach(() => {
	vi.restoreAllMocks()
	delete globalThis.fetch
})

describe('RegistrationQrCode', () => {
	it('reads and draws the code of an accepted registration', async () => {
		globalThis.fetch = vi
			.fn()
			.mockResolvedValueOnce({
				ok: true,
				json: async () => ({ checkinCode: CODE }),
			})
		const self = cell({ id: REG, status: 'accepted', checkinCode: '*****XY2' })

		await RegistrationQrCode.watch.acceptedId.handler.call(self, self.acceptedId)

		expect(globalThis.fetch.mock.calls[0][0]).toContain(
			`/larping_registration/${REG}?_reveal=checkinCode`,
		)
		expect(self.code).toBe(CODE)
		const drawn = cell(self.row, self.code)
		const { size, path } = qrDrawing(CODE)
		expect(drawn.drawing).toEqual({ size, path })
		expect(drawn.viewBox).toBe(`-2 -2 ${size + 4} ${size + 4}`)
	})

	it.each([
		['pending', { id: REG, status: 'pending' }],
		['declined', { id: REG, status: 'declined' }],
		['no row', null],
	])('reads nothing for a %s registration', async (name, row) => {
		globalThis.fetch = vi.fn()
		const self = cell(row, 'stale')

		expect(self.acceptedId).toBe('')
		await RegistrationQrCode.watch.acceptedId.handler.call(self, self.acceptedId)

		expect(globalThis.fetch).not.toHaveBeenCalled()
		expect(self.code).toBe('')
		expect(cell(row, self.code).drawing).toBeNull()
	})

	it('takes the uuid when the row has no id', () => {
		expect(cell({ uuid: REG, status: 'accepted' }).acceptedId).toBe(REG)
	})

	it('prints the signed-in player name', () => {
		expect(c.playerName.call({})).toBe('Admin')
	})
})
