/**
 * GraphQL query utility for OpenRegister.
 *
 * Posts GraphQL queries to the OpenRegister API endpoint
 * with Nextcloud CSRF token authentication.
 */

import { generateUrl } from '@nextcloud/router'
import { getRequestToken } from '@nextcloud/auth'

const GRAPHQL_ENDPOINT = '/apps/openregister/api/graphql'

/**
 * Execute a GraphQL query against the OpenRegister API.
 *
 * @param {string} query The GraphQL query string
 * @param {object} variables Optional query variables
 * @return {Promise<object>} The GraphQL response data
 * @throws {Error} On network, auth, or GraphQL errors
 */
export async function queryGraphQL(query, variables = null) {
	const url = generateUrl(GRAPHQL_ENDPOINT)

	const body = { query }
	if (variables) {
		body.variables = variables
	}

	const response = await fetch(url, {
		method: 'POST',
		headers: {
			'Content-Type': 'application/json',
			requesttoken: getRequestToken(),
		},
		credentials: 'same-origin',
		body: JSON.stringify(body),
	})

	if (response.status === 401) {
		throw new Error('Authentication error — please log in again')
	}

	if (response.status === 429) {
		const retryAfter = response.headers.get('Retry-After') || '60'
		throw new Error(`Rate limited — retry after ${retryAfter} seconds`)
	}

	if (!response.ok) {
		throw new Error(`GraphQL request failed: ${response.status} ${response.statusText}`)
	}

	const result = await response.json()

	if (result.errors && result.errors.length > 0 && !result.data) {
		throw new Error(result.errors[0].message || 'GraphQL query error')
	}

	return result
}
