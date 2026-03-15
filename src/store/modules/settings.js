import { defineStore } from 'pinia'

export const useSettingsStore = defineStore('settings', {
	state: () => ({
		config: null,
		loading: false,
		error: null,
		initialized: false,
	}),
	getters: {
		isLoading: (state) => state.loading,
		getError: (state) => state.error,
		isInitialized: (state) => state.initialized,
		getConfig: (state) => state.config,
	},
	actions: {
		async fetchSettings() {
			this.loading = true
			this.error = null

			try {
				const response = await fetch('/apps/larpingapp/api/settings', {
					method: 'GET',
					headers: {
						'Content-Type': 'application/json',
						requesttoken: OC.requestToken,
						'OCS-APIREQUEST': 'true',
					},
				})

				if (!response.ok) {
					throw new Error(`Failed to fetch settings: ${response.statusText}`)
				}

				const data = await response.json()
				this.config = data.configuration || data.config || data
				this.initialized = true

				return this.config
			} catch (error) {
				this.error = error.message
				console.error('Error fetching LarpingApp settings:', error)
				return null
			} finally {
				this.loading = false
			}
		},
	},
})
