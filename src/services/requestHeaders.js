/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The headers of a JSON request to larpinq or OpenRegister.
 */

import { getRequestToken } from '@nextcloud/auth'

/**
 * The headers of a JSON request: the CSRF token, and JSON both ways.
 *
 * @return {object} The headers.
 *
 * @spec openspec/specs/character-builds/spec.md
 * @spec openspec/specs/character-custom-fields/spec.md
 * @spec openspec/specs/character-management/spec.md
 * @spec openspec/specs/event-registration/spec.md
 * @spec openspec/specs/portal-contribution/spec.md
 */
export function jsonHeaders() {
	return {
		requesttoken: getRequestToken(),
		Accept: 'application/json',
		'Content-Type': 'application/json',
	}
}

/**
 * The headers of a request to larpinq's OCS-style API: the CSRF token from
 * the page and the OCS request marker.
 *
 * @return {object} The headers.
 *
 * @spec openspec/specs/event-checkin-roster/spec.md
 * @spec openspec/specs/rpg-system/spec.md
 * @spec openspec/changes/retrofit-2026-05-25-larpingapp-frontend/tasks.md#task-6
 */
export function ocsHeaders() {
	return {
		requesttoken: OC.requestToken,
		'OCS-APIREQUEST': 'true',
	}
}

/**
 * The OCS-style headers of a request that sends JSON.
 *
 * @return {object} The headers.
 *
 * @spec openspec/specs/event-checkin-roster/spec.md
 * @spec openspec/changes/retrofit-2026-05-25-larpingapp-frontend/tasks.md#task-6
 */
export function ocsJsonHeaders() {
	return {
		'Content-Type': 'application/json',
		...ocsHeaders(),
	}
}
