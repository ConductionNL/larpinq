/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The event run sheet download (pdf-export RUN-020): where the PDF of one
 * event in one template is served. The templates are the document app's, the
 * same list the character sheet download reads; the server keeps the run sheet
 * to game masters.
 *
 * @spec openspec/specs/pdf-export/spec.md
 */

import { generateUrl } from '@nextcloud/router'

/**
 * The run sheet of an event in a template.
 *
 * @param {string} eventId The event.
 * @param {string} templateId The run sheet template.
 * @return {string} The download URL.
 *
 * @spec openspec/specs/pdf-export/spec.md
 */
export function runsheetUrl(eventId, templateId) {
	return generateUrl(
		`/apps/larpinq/events/${encodeURIComponent(eventId)}/runsheet/${encodeURIComponent(templateId)}`,
	)
}
