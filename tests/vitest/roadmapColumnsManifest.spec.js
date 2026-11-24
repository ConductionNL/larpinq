/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The manifest wiring of admin-roadmap-page: the Features & roadmap page draws
 * its Roadmap tab as three columns, Gepland, In ontwikkeling and Beschikbaar
 * (REQ-ARM-003).
 *
 * @spec openspec/changes/admin-roadmap-page/specs/admin-roadmap-page/spec.md#requirement-req-arm-003-the-roadmap-shows-planned-in-progress-and-available
 */

import { describe, expect, it } from 'vitest'
import manifest from '../../src/manifest.json'

const page = manifest.pages.find((p) => p.id === 'FeaturesRoadmap')

describe('the Features & roadmap page (REQ-ARM-003)', () => {
	it('is the roadmap page type on its own route', () => {
		expect(page.type).toBe('roadmap')
		expect(page.route).toBe('/features-roadmap')
	})

	it('draws the Roadmap tab as the three columns', () => {
		expect(page.config.roadmapLayout).toBe('columns')
	})

	it('keeps the documentation link', () => {
		expect(page.config.documentationUrl).toBe('https://larpinq.conduction.nl')
	})
})
