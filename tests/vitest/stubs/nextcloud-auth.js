/**
 * SPDX-FileCopyrightText: 2026 Conduction / LarpingApp Contributors
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Minimal @nextcloud/auth stub for the offline Vitest suite — a deterministic
 * request token so the service's fetch headers are assertable.
 */

export function getRequestToken() {
	return 'test-token'
}

export function getCurrentUser() {
	return { uid: 'admin', displayName: 'Admin' }
}
