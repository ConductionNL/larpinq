/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The manifest wiring of characters-plot-threads-and-writing: the Plots pages
 * and menu entry, the writer, step and plots on the character page, and the
 * writing progress on the Character roster report (REQ-CPW-001, REQ-CPW-003,
 * REQ-CPW-004).
 */

import { describe, expect, it } from 'vitest'
import fragment from '../../src/manifest.d/characters-plot-threads-and-writing.json'
import manifest from '../../src/manifest.json'
import layout from '../../src/menu-layout.json'

const page = (id) => manifest.pages.find((p) => p.id === id)
const widget = (p, id) => p.config.widgets.find((w) => w.id === id)
const cells = (p, id) => p.config.layout.filter((cell) => cell.widgetId === id)

describe('the Plots pages (REQ-CPW-001, REQ-CPW-003)', () => {
	it('lists plots and opens a plot with its parts', () => {
		const index = fragment.pages.find((p) => p.id === 'Plots')
		expect(index.type).toBe('index')
		expect(index.config.schema).toBe('larping_plot')
		const detail = fragment.pages.find((p) => p.id === 'PlotDetail')
		expect(detail.route).toBe('/plots/:id')
		expect(detail.config.schema).toBe('larping_plot')
		const parts = widget(detail, 'plot-parts')
		expect(parts.content.schema).toBe('larping_plot_part')
		expect(parts.content.filter).toEqual({ plot: '@objectId' })
		expect(cells(detail, 'plot-parts')).toHaveLength(1)
	})

	it('moves the writing step with the lifecycle buttons', () => {
		const detail = fragment.pages.find((p) => p.id === 'PlotDetail')
		expect(detail.config.lifecycleActions).toEqual({ field: 'writingStep' })
	})

	it('puts Plots in the Characters menu group', () => {
		expect(fragment.menu.find((m) => m.id === 'Plots').route).toBe('Plots')
		expect(layout.relocations.Plots).toBe('CharactersGroup')
	})
})

describe('the character page (REQ-CPW-001, REQ-CPW-003)', () => {
	it('shows the writer and the writing step with the game state', () => {
		const include = widget(page('CharacterDetail'), 'char-progress').content
			.include
		expect(include).toContain('writer')
		expect(include).toContain('writingStep')
	})

	it('lists the plot parts that touch the character', () => {
		const detail = page('CharacterDetail')
		const plots = widget(detail, 'char-plots')
		expect(plots.type).toBe('object-list')
		expect(plots.content.schema).toBe('larping_plot_part')
		expect(plots.content.filter).toEqual({ character: '@objectId' })
		expect(plots.content.columns.map((c) => c.key)).toEqual([
			'plot',
			'playerText',
		])
		expect(cells(detail, 'char-plots')).toHaveLength(1)
	})
})

describe('the Character roster report (REQ-CPW-004)', () => {
	it('counts characters by writing step', () => {
		const report = page('CharacterRosterReport')
		const chart = widget(report, 'roster-by-writing-step')
		expect(chart.type).toBe('chart')
		expect(chart.content.dataSource.aggregate).toEqual({
			groupBy: 'writingStep',
			metric: 'count',
		})
		expect(cells(report, 'roster-by-writing-step')).toHaveLength(1)
	})

	it('lists the characters not yet approved in writing, by writer', () => {
		const report = page('CharacterRosterReport')
		const table = widget(report, 'roster-writing-open')
		expect(table.type).toBe('object-table')
		expect(table.content.filter).toEqual({ writingStep: ['draft', 'ready'] })
		expect(table.content.sort).toEqual({ field: 'writer', dir: 'asc' })
		expect(table.content.columns.map((c) => c.key)).toEqual([
			'name',
			'writer',
			'writingStep',
		])
		expect(cells(report, 'roster-writing-open')).toHaveLength(1)
	})
})
