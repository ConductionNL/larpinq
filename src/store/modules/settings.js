import { defineStore } from 'pinia'
import logger from '../../logger.js'

/**
 * Call a larpinq settings endpoint with the app's JSON request headers.
 *
 * @param {string} path The path after `/apps/larpinq/api/settings`.
 * @param {object} init The method and, for a write, the body.
 * @return {Promise<Response>} The response.
 *
 * @spec openspec/changes/retrofit-2026-05-25-larpingapp-frontend/tasks.md#task-6
 */
function settingsRequest(path, init) {
	return fetch(`/apps/larpinq/api/settings${path}`, {
		...init,
		headers: {
			'Content-Type': 'application/json',
			requesttoken: OC.requestToken,
			'OCS-APIREQUEST': 'true',
		},
	})
}

/**
 * The store state a settings envelope carries: the OpenRegister and admin
 * flags, and the configuration under whichever key the endpoint used.
 *
 * @param {object} data The settings envelope.
 * @return {{openRegisters: boolean, isAdmin: boolean, config: object}} The state.
 *
 * @spec openspec/changes/retrofit-2026-05-25-larpingapp-frontend/tasks.md#task-6
 */
function settingsState(data) {
	return {
		openRegisters: data.openRegisters ?? false,
		isAdmin: data.isAdmin ?? false,
		config: data.configuration || data.config || data,
	}
}

/**
 * The JSON body of a settings answer.
 *
 * @param {Response} response The answer.
 * @param {string} failure What failed, for the error when the answer is not ok.
 * @return {Promise<object>} The body.
 * @throws {Error} When the answer is not ok.
 *
 * @spec openspec/changes/retrofit-2026-05-25-larpingapp-frontend/tasks.md#task-6
 */
async function okJson(response, failure) {
	if (!response.ok) {
		throw new Error(`${failure}: ${response.statusText}`)
	}
	return response.json()
}

export const useSettingsStore = defineStore('settings', {
	state: () => ({
		config: null,
		loading: false,
		error: null,
		initialized: false,
		openRegisters: false,
		isAdmin: false,
	}),
	getters: {
		isLoading: (state) => state.loading,
		getError: (state) => state.error,
		isInitialized: (state) => state.initialized,
		getConfig: (state) => state.config,
		hasOpenRegisters: (state) => state.openRegisters,
		getIsAdmin: (state) => state.isAdmin,
	},
	actions: {
		/**
		 * @spec openspec/changes/retrofit-2026-05-25-larpingapp-frontend/tasks.md#task-6
		 */
		async fetchSettings() {
			return this.track(async () => {
				const response = await settingsRequest('', { method: 'GET' })
				const data = await okJson(response, 'Failed to fetch settings')
				Object.assign(this, settingsState(data))
				this.initialized = true
				return this.config
			}, 'Error fetching Larpinq settings')
		},

		/**
		 * Persist the data-storage configuration.
		 *
		 * @param {object} settingsData Flat `{ <type>_source, <type>_register, <type>_schema }` map to save.
		 * @return {Promise<object|null>} The saved settings envelope, or null on failure.
		 * @spec openspec/changes/retrofit-2026-05-25-larpingapp-frontend/tasks.md#task-6
		 */
		async saveSettings(settingsData) {
			return this.track(async () => {
				const response = await settingsRequest('', {
					method: 'POST',
					body: JSON.stringify(settingsData),
				})
				const data = await okJson(response, 'Failed to save settings')
				this.config = data.config || data
				return this.config
			}, 'Error saving Larpinq settings')
		},

		/**
		 * @spec openspec/changes/retrofit-2026-05-25-larpingapp-frontend/tasks.md#task-6
		 */
		async reimportConfiguration() {
			return this.track(async () => {
				const response = await settingsRequest('/reimport', {
					method: 'POST',
				})
				const data = await okJson(response, 'Failed to reimport')
				this.config = data.config || data
				return data
			}, 'Error reimporting Larpinq configuration')
		},

		/**
		 * Run one settings request with the loading and error state around it.
		 *
		 * @param {() => Promise<object>} work Sends the request and returns the answer.
		 * @param {string} failure The log message when the request fails.
		 * @return {Promise<object|null>} The answer, or null when it failed.
		 * @spec openspec/changes/retrofit-2026-05-25-larpingapp-frontend/tasks.md#task-6
		 */
		async track(work, failure) {
			this.loading = true
			this.error = null
			try {
				return await work()
			} catch (error) {
				this.error = error.message
				logger.error(failure, { error })
				return null
			} finally {
				this.loading = false
			}
		},
	},
})
