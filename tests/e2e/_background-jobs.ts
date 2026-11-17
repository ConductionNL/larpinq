/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Run the instance's queued background jobs once, from a spec.
 *
 * OpenRegister delivers declared notifications from a background job, so a
 * spec that asserts a notification has to run the queue first. Web cron runs
 * one job per request, which is not enough; this runs `cron.php` on the CLI:
 *
 *   - NC_CONTAINER set: inside that container (a local rig);
 *   - otherwise: in the Nextcloud server root this checkout sits in (the CI
 *     runner), found by walking up to `lib/base.php` + `config/config.php`.
 *
 * There is no default container: `nextcloud` is the shared dev container.
 */

import { execSync } from 'child_process'
import * as fs from 'fs'
import * as path from 'path'

/**
 * The Nextcloud server root above this file, or null.
 *
 * @return {string|null}
 */
function serverRoot(): string | null {
	let dir = __dirname
	for (let up = 0; up < 8; up++) {
		if (fs.existsSync(path.join(dir, 'lib', 'base.php')) && fs.existsSync(path.join(dir, 'config', 'config.php'))) {
			return dir
		}
		const parent = path.dirname(dir)
		if (parent === dir) {
			break
		}
		dir = parent
	}
	return null
}

/**
 * Run `cron.php` once on the instance under test.
 *
 * @throws {Error} When neither NC_CONTAINER nor a server root is available.
 */
export function runBackgroundJobs(): void {
	const container = (process.env.NC_CONTAINER ?? '').trim()
	if (container !== '') {
		execSync(`docker exec -u www-data ${container} php -f /var/www/html/cron.php`, { stdio: 'pipe', timeout: 220_000 })
		return
	}
	const root = serverRoot()
	if (root === null) {
		throw new Error('Cannot run background jobs: set NC_CONTAINER to the rig\'s Nextcloud container.')
	}
	execSync('php -f cron.php', { cwd: root, stdio: 'pipe', timeout: 220_000 })
}
