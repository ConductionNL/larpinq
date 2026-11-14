/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The manifest wiring of communication-help-desk: the player's Help page, the
 * game masters' Help desk (oldest activity first, marked after 3 days), the
 * question page with its thread, and the open-question count on the
 * Dashboard (REQ-CHD-001, REQ-CHD-002, REQ-CHD-003).
 */

import { describe, expect, it } from 'vitest'
import icons from '../../src/icons.js'
import fragment from '../../src/manifest.d/communication-help-desk.json'
import manifest from '../../src/manifest.json'

const page = (id) => fragment.pages.find((p) => p.id === id)

describe('the Help page (REQ-CHD-001)', () => {
	it('lists the questions the player may read, with their state', () => {
		const help = page('Help')
		expect(help.type).toBe('index')
		expect(help.route).toBe('/help')
		expect(help.config.schema).toBe('larping_help_question')
		expect(help.config.readOnly).toBeUndefined()
		expect(help.config.columns.map((c) => c.key)).toEqual([
			'subject',
			'event',
			'character',
			'status',
			'lastMessageAt',
		])
	})
})

describe('the Help desk (REQ-CHD-002)', () => {
	const desk = page('HelpDesk')

	it('shows open questions first and can switch to answered and closed ones', () => {
		expect(desk.config.schema).toBe('larping_help_question')
		expect(desk.config.quickFilters.map((f) => f.filter.status)).toEqual(['open', 'answered', 'closed'])
		expect(desk.config.quickFilters[0].default).toBe(true)
	})

	it('sorts by the oldest activity and marks a question waiting more than 3 days', () => {
		expect(desk.config.defaultSort).toEqual([{ field: 'lastMessageAt', order: 'asc' }])
		const last = desk.config.columns.find((c) => c.key === 'lastMessageAt')
		expect(last.widget).toBe('age')
		expect(last.widgetProps).toEqual({ olderThanDays: 3 })
		expect(desk.config.columns.map((c) => c.key)).toContain('assignee')
	})

	it('has menu entries for both pages', () => {
		expect(fragment.menu.map((m) => [m.id, m.route])).toEqual([
			['Help', 'Help'],
			['HelpDesk', 'HelpDesk'],
		])
	})
})

describe('the question page (REQ-CHD-002, REQ-CHD-003)', () => {
	const detail = page('HelpQuestionDetail')

	it('opens a question with its lifecycle buttons', () => {
		expect(detail.type).toBe('detail')
		expect(detail.route).toBe('/help/:id')
		expect(detail.config.schema).toBe('larping_help_question')
		expect(detail.config.lifecycleActions).toEqual({ field: 'status' })
	})

	it('shows the thread of messages under the question, and lays out every widget', () => {
		const thread = detail.config.widgets.find((w) => w.id === 'help-question-thread')
		expect(thread.type).toBe('object-list')
		expect(thread.content.schema).toBe('larping_help_message')
		expect(thread.content.filter).toEqual({ question: '@objectId' })
		const laid = detail.config.layout.map((cell) => cell.widgetId)
		expect(laid).toEqual(detail.config.widgets.map((w) => w.id))
	})
})

describe('the Dashboard count (REQ-CHD-002)', () => {
	const dashboard = manifest.pages.find((p) => p.id === 'Dashboard')

	it('counts the open questions and leads to the Help desk', () => {
		const stat = dashboard.config.widgets.find((w) => w.id === 'kpi-open-questions')
		expect(stat.type).toBe('stat')
		expect(stat.content.route).toEqual({ name: 'HelpDesk' })
		expect(stat.content.source).toEqual({
			register: 'larpinq',
			schema: 'larping_help_question',
			metric: 'count',
			filter: { status: 'open' },
		})
		expect(dashboard.config.layout.filter((cell) => cell.widgetId === 'kpi-open-questions')).toHaveLength(1)
	})
})

describe('icons', () => {
	it('registers every icon the help desk names', () => {
		const named = [
			...fragment.menu.map((m) => m.icon),
			...page('HelpQuestionDetail').config.widgets.map((w) => w.icon),
			'HelpCircleOutline',
		]
		for (const name of named) {
			expect(icons[name], name).toBeDefined()
		}
	})
})
