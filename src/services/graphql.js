/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */
/**
 * GraphQL query utility for OpenRegister.
 *
 * Posts GraphQL queries to the OpenRegister API endpoint
 * with Nextcloud CSRF token authentication.
 */

import { getRequestToken } from '@nextcloud/auth'
import { generateUrl } from '@nextcloud/router'

const GRAPHQL_ENDPOINT = '/apps/openregister/api/graphql'

/**
 * Execute a GraphQL query against the OpenRegister API.
 *
 * @param {string} query The GraphQL query string
 * @param {object} variables Optional query variables
 * @return {Promise<object>} The GraphQL response data
 * @throws {Error} On network, auth, or GraphQL errors
 *
 * @spec openspec/changes/retrofit-2026-05-25-larpingapp-frontend/tasks.md#task-5
 */
export async function queryGraphQL(query, variables = null) {
	const url = generateUrl(GRAPHQL_ENDPOINT)

	const response = await fetch(url, {
		method: 'POST',
		headers: {
			'Content-Type': 'application/json',
			requesttoken: getRequestToken(),
		},
		credentials: 'same-origin',
		body: JSON.stringify(requestBody(query, variables)),
	})
	assertAnswered(response)

	return resultOf(await response.json())
}

/**
 * The request body: the query, plus the variables when there are any.
 *
 * @param {string} query The GraphQL query string
 * @param {object|null} variables The query variables
 * @return {object} The body
 *
 * @spec openspec/changes/retrofit-2026-05-25-larpingapp-frontend/tasks.md#task-5
 */
function requestBody(query, variables) {
	const body = { query }
	if (variables) {
		body.variables = variables
	}
	return body
}

/**
 * Throw for an auth error, a rate limit or any other failed status.
 *
 * @param {Response} response The fetch response
 * @throws {Error} When the response is not ok
 *
 * @spec openspec/changes/retrofit-2026-05-25-larpingapp-frontend/tasks.md#task-5
 */
function assertAnswered(response) {
	if (response.status === 401) {
		throw new Error('Authentication error — please log in again')
	}

	if (response.status === 429) {
		const retryAfter = response.headers.get('Retry-After') || '60'
		throw new Error(`Rate limited — retry after ${retryAfter} seconds`)
	}

	if (!response.ok) {
		throw new Error(
			`GraphQL request failed: ${response.status} ${response.statusText}`,
		)
	}
}

/**
 * The GraphQL result, unless it carries errors and no data.
 *
 * @param {object} result The parsed response
 * @return {object} The result
 * @throws {Error} When the query failed without data
 *
 * @spec openspec/changes/retrofit-2026-05-25-larpingapp-frontend/tasks.md#task-5
 */
function resultOf(result) {
	if (result.errors && result.errors.length > 0 && !result.data) {
		throw new Error(result.errors[0].message || 'GraphQL query error')
	}

	return result
}
