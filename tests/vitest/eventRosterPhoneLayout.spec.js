/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Unit tests for the phone layout of src/views/EventRoster.vue: within the
 * roster's narrow-width container query the check-in actions are 44 by 44
 * pixels, the scan field spans the width with its button stacked below it,
 * and the code text is 16 pixels so a phone does not zoom in
 * (admin-phone-friendly-pages, REQ-APF-002).
 *
 * @spec openspec/changes/admin-phone-friendly-pages/specs/phone-friendly-pages/spec.md
 */

import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { describe, expect, it } from 'vitest'

const source = readFileSync(resolve(__dirname, '../../src/views/EventRoster.vue'), 'utf8')
const style = source.slice(source.indexOf('<style'), source.indexOf('</style>')).replace(/\/\*[\s\S]*?\*\//g, '')
const phoneStart = style.indexOf('@container (max-width: 600px)')
const phoneEnd = closingBrace(style, style.indexOf('{', phoneStart))
const phoneCss = style.slice(style.indexOf('{', phoneStart) + 1, phoneEnd)
const baseCss = style.slice(0, phoneStart) + style.slice(phoneEnd + 1)

/**
 * The index of the brace that closes the one at `open`.
 *
 * @param {string} css The stylesheet.
 * @param {number} open Index of an opening brace.
 * @return {number} Index of its closing brace.
 */
function closingBrace(css, open) {
	let depth = 0
	for (let i = open; i < css.length; i++) {
		if (css[i] === '{') {
			depth++
		} else if (css[i] === '}' && --depth === 0) {
			return i
		}
	}
	return css.length
}

/**
 * The declarations of one selector, inside the phone container query or at
 * the top level.
 *
 * @param {string} selector The rule's selector.
 * @param {boolean} phone Whether to look inside the phone container query.
 * @return {object} Property to value.
 */
function declarations(selector, phone) {
	const found = {}
	const rule = /([^{}]+)\{([^{}]*)\}/g
	let match
	while ((match = rule.exec(phone ? phoneCss : baseCss)) !== null) {
		if (match[1].trim() !== selector) {
			continue
		}
		for (const line of match[2].split(';')) {
			const colon = line.indexOf(':')
			if (colon > 0) {
				found[line.slice(0, colon).trim()] = line.slice(colon + 1).trim()
			}
		}
	}
	return found
}

describe('EventRoster phone layout', () => {
	it('makes the roster a size container', () => {
		expect(declarations('.event-roster', false)['container-type']).toBe('inline-size')
	})

	it('gives the check-in actions a 44 pixel tap target', () => {
		expect(declarations('.event-roster__action', true)).toMatchObject({
			'min-width': '44px',
			'min-height': '44px',
		})
	})

	it('lets the scan panel span the width', () => {
		expect(declarations('.event-roster__scan', true)['align-items']).toBe('stretch')
		expect(declarations('.event-roster__scan-panel', true)['max-width']).toBe('none')
		expect(declarations('.event-roster__scan-form', true)).toMatchObject({
			'flex-direction': 'column',
			'align-items': 'stretch',
		})
	})

	it('keeps the code text large enough that the phone does not zoom', () => {
		expect(declarations('.event-roster__scan-form :deep(input)', true)['font-size']).toBe('16px')
	})

	it('gives the scan buttons a 44 pixel height', () => {
		expect(declarations('.event-roster__scan :deep(button)', true)['min-height']).toBe('44px')
	})

	it('keeps the wide layout unchanged outside the phone query', () => {
		expect(declarations('.event-roster__scan-panel', false)['max-width']).toBe('480px')
		expect(declarations('.event-roster__scan-form', false)['align-items']).toBe('flex-end')
	})

	it('places the phone query after the base scan rules so it wins', () => {
		const container = source.indexOf('@container (max-width: 600px)')
		expect(container).toBeGreaterThan(source.indexOf('.event-roster__scan-form {'))
	})
})
