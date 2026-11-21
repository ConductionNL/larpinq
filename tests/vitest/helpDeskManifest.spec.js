/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The manifest wiring of communication-help-desk: the player's Help page, the
 * game masters' Help desk (a query preset of the same page, reached by its menu
 * entry: oldest activity first, marked after 3 days), the
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
	// One index page over the questions (ADR-097 D5): the desk is a query
	// preset of the Help page, chosen by its menu entry's query.
	const desk = page('Help').config.queryPresets.find((p) => p.id === 'help-desk')

	it('is no second index page over the questions', () => {
		const indexes = fragment.pages.filter((p) => p.type === 'index' && p.config.schema === 'larping_help_question')
		expect(indexes.map((p) => p.id)).toEqual(['Help'])
		expect(desk.match).toEqual({ _lens: 'desk' })
		expect(desk.title).toBe('Help desk')
	})

	it('shows open questions first and can switch to answered and closed ones', () => {
		expect(desk.quickFilters.map((f) => f.filter.status)).toEqual(['open', 'answered', 'closed'])
		expect(desk.quickFilters[0].default).toBe(true)
	})

	it('sorts by the oldest activity and marks a question waiting more than 3 days', () => {
		expect(desk.defaultSort).toEqual([{ field: 'lastMessageAt', order: 'asc' }])
		const last = desk.columns.find((c) => c.key === 'lastMessageAt')
		expect(last.widget).toBe('age')
		expect(last.widgetProps).toEqual({ olderThanDays: 3 })
		expect(desk.columns.map((c) => c.key)).toContain('assignee')
	})

	it('has menu entries for both, the desk one opening the preset', () => {
		expect(fragment.menu.map((m) => [m.id, m.route, m.query])).toEqual([
			['Help', 'Help', undefined],
			['HelpDesk', 'Help', desk.match],
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
		expect(stat.content.route).toEqual({ name: 'Help', query: { _lens: 'desk' } })
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
