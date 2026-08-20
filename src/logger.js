/**
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * App logger.
 *
 * `@nextcloud/eslint-config@9` enables `no-console` with no allowances — not
 * even `console.error` — so error paths route through the logger Nextcloud's
 * own apps use. Beyond satisfying the rule this is the better instrument: the
 * builder tags every line with the app id and the acting user, so a report
 * from a live instance says which app and whose session produced it.
 */
import { getLoggerBuilder } from '@nextcloud/logger'

export default getLoggerBuilder().setApp('larpingapp').detectUser().build()
