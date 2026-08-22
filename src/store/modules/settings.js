import { defineStore } from 'pinia'
import logger from '../../logger.js'

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
			this.loading = true
			this.error = null

			try {
				const response = await fetch('/apps/larpinq/api/settings', {
					method: 'GET',
					headers: {
						'Content-Type': 'application/json',
						requesttoken: OC.requestToken,
						'OCS-APIREQUEST': 'true',
					},
				})

				if (!response.ok) {
					throw new Error(
						`Failed to fetch settings: ${response.statusText}`,
					)
				}

				const data = await response.json()
				this.openRegisters = data.openRegisters ?? false
				this.isAdmin = data.isAdmin ?? false
				this.config = data.configuration || data.config || data
				this.initialized = true

				return this.config
			} catch (error) {
				this.error = error.message
				logger.error('Error fetching Larpinq settings', { error })
				return null
			} finally {
				this.loading = false
			}
		},

		/**
		 * Persist the data-storage configuration.
		 *
		 * @param {object} settingsData Flat `{ <type>_source, <type>_register, <type>_schema }` map to save.
		 * @return {Promise<object|null>} The saved settings envelope, or null on failure.
		 * @spec openspec/changes/retrofit-2026-05-25-larpingapp-frontend/tasks.md#task-6
		 */
		async saveSettings(settingsData) {
			this.loading = true
			this.error = null

			try {
				const response = await fetch('/apps/larpinq/api/settings', {
					method: 'POST',
					headers: {
						'Content-Type': 'application/json',
						requesttoken: OC.requestToken,
						'OCS-APIREQUEST': 'true',
					},
					body: JSON.stringify(settingsData),
				})

				if (!response.ok) {
					throw new Error(
						`Failed to save settings: ${response.statusText}`,
					)
				}

				const data = await response.json()
				this.config = data.config || data

				return this.config
			} catch (error) {
				this.error = error.message
				logger.error('Error saving Larpinq settings', { error })
				return null
			} finally {
				this.loading = false
			}
		},

		/**
		 * @spec openspec/changes/retrofit-2026-05-25-larpingapp-frontend/tasks.md#task-6
		 */
		async reimportConfiguration() {
			this.loading = true
			this.error = null

			try {
				const response = await fetch('/apps/larpinq/api/settings/reimport', {
					method: 'POST',
					headers: {
						'Content-Type': 'application/json',
						requesttoken: OC.requestToken,
						'OCS-APIREQUEST': 'true',
					},
				})

				if (!response.ok) {
					throw new Error(`Failed to reimport: ${response.statusText}`)
				}

				const data = await response.json()
				this.config = data.config || data

				return data
			} catch (error) {
				this.error = error.message
				logger.error('Error reimporting Larpinq configuration', { error })
				return null
			} finally {
				this.loading = false
			}
		},
	},
})
