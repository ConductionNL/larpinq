/**
 * SPDX-FileCopyrightText: 2026 Conduction / LarpingApp Contributors
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Vitest configuration for LarpingApp frontend unit tests.
 *
 * LarpingApp delegates object CRUD to OpenRegister (GraphQL + the shared
 * object store), so the app-local OFFLINE logic worth pinning is:
 *   • src/store/modules/settings.js — fetch envelope handling, the
 *     openRegisters / isAdmin flag derivation, the loading/error lifecycle
 *     and the save / reimport round-trips.
 *   • src/services/graphql.js — the HTTP-status → Error mapping (401 / 429 /
 *     non-ok) and the GraphQL-errors-without-data rejection, driven with a
 *     mocked fetch.
 *
 * These need no DOM, so the environment is `node`. global fetch + the `OC`
 * global are mocked per-test; @nextcloud/router + @nextcloud/auth are aliased
 * to deterministic stubs.
 */

const path = require('path')

module.exports = {
	test: {
		environment: 'node',
		globals: false,
		include: ['tests/vitest/**/*.spec.{js,ts}'],
		exclude: [
			'tests/e2e/**',
			'tests/integration/**',
			'src/**',
			'node_modules/**',
		],
	},
	resolve: {
		alias: [
			{ find: '@', replacement: path.resolve(__dirname, 'src') },
			{
				find: /^@nextcloud\/router$/,
				replacement: path.resolve(
					__dirname,
					'tests/vitest/stubs/nextcloud-router.js',
				),
			},
			{
				find: /^@nextcloud\/auth$/,
				replacement: path.resolve(
					__dirname,
					'tests/vitest/stubs/nextcloud-auth.js',
				),
			},
			{
				// `src/logger.js` (added because @nextcloud/eslint-config@9 enables
				// `no-console` with no allowances) is imported by the settings store,
				// and the real @nextcloud/logger touches `window` at module load via
				// @nextcloud/auth → @nextcloud/browser-storage. Stubbing it keeps this
				// suite's `environment: 'node'` intact.
				find: /^@nextcloud\/logger$/,
				replacement: path.resolve(
					__dirname,
					'tests/vitest/stubs/nextcloud-logger.js',
				),
			},
		],
	},
}
