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
