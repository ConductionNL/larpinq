/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The flat config map the admin "Data storage" form saves.
 *
 * Each object type sends its source. The register and schema go along only
 * when the source is OpenRegister (settings-management-ui REQ-003); an
 * internal type sends no register or schema.
 *
 * @param {Object<string, {source?: {value: string}, register?: {value: string}|null, schema?: {value: string}|null}>} configuration
 *   The form state per object type.
 * @return {Object<string, string>} The `{type}_source` / `{type}_register` / `{type}_schema` map.
 * @spec openspec/specs/settings-management-ui/spec.md#REQ-003
 */
export function buildStorageSettingsPayload(configuration) {
	const payload = {}
	for (const [type, config] of Object.entries(configuration || {})) {
		const source = config?.source?.value || 'internal'
		payload[`${type}_source`] = source
		if (source === 'openregister') {
			payload[`${type}_register`] = config.register?.value || ''
			payload[`${type}_schema`] = config.schema?.value || ''
		}
	}
	return payload
}
