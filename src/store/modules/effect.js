/* eslint-disable no-console */
import { defineStore } from 'pinia'
import { Effect } from '../../entities/index.js'

/**
 * Store for managing effect data
 * @phpstan-type EffectData {id: string, name: string, description: string, type: string, value: number, duration: number, ...}
 */
export const useEffectStore = defineStore(
	'effect', {
		state: () => ({
			/** @var {Effect|false} Current active effect */
			effectItem: false,
			/** @var {Array<Effect>} List of all effects */
			effectList: [],
			/** @var {Array<Object>} Audit trail entries for current effect */
			auditTrails: [],
			/** @var {Array<Object>} Relations for current effect */
			relations: [],
			/** @var {Array<Object>} Uses of current effect */
			uses: [],
			// Loading states
			/** @var {boolean} Whether effect is being loaded */
			isLoadingEffect: false,
			/** @var {boolean} Whether effect list is being loaded */
			isLoadingEffectList: false,
			/** @var {boolean} Whether audit trails are being loaded */
			isLoadingAuditTrails: false,
			/** @var {boolean} Whether relations are being loaded */
			isLoadingRelations: false,
			/** @var {boolean} Whether uses are being loaded */
			isLoadingUses: false,
			/** @var {string} Current search term for effects */
			searchTerm: '',
			/** @var {number|null} Debounce timer for search */
			searchDebounceTimer: null,
		}),
		actions: {
			/**
			 * Sets the active effect item and loads its audit trails and relations
			 * @param {EffectData|null} effectItem - The effect item to set, or null to clear
			 * @throws {Error} When loading effect data fails
			 * @returns {Promise<void>}
			 */
			async setEffectItem(effectItem) {
				this.isLoadingEffect = true
				try {
					// Set the effect item first
					this.effectItem = effectItem && new Effect(effectItem)
					console.log('Active effect item set to ' + effectItem)

					// If we have an effect item, load its audit trails and relations
					if (this.effectItem && this.effectItem.id) {
						// Load audit trails and relations in parallel
						await Promise.all([
							this.getAuditTrails(this.effectItem.id),
							this.getRelations(this.effectItem.id)
						])
					}
				} catch (err) {
					console.error('Error loading effect data:', err)
				} finally {
					this.isLoadingEffect = false
				}
			},
			/**
			 * Sets the list of effects
			 * @param {Array<EffectData>} effectList - Array of effect data
			 * @returns {void}
			 */
			setEffectList(effectList) {
				this.effectList = effectList.map(
					(effectItem) => new Effect(effectItem),
				)
				console.log('Effect list set to ' + effectList.length + ' items')
			},
			/**
			 * Sets the search term and triggers a debounced search
			 * @param {string} term - The search term to set
			 * @returns {void}
			 */
			setSearchTerm(term) {
				this.searchTerm = term

				if (this.searchDebounceTimer) {
					clearTimeout(this.searchDebounceTimer)
				}

				this.searchDebounceTimer = setTimeout(() => {
					this.refreshEffectList()
				}, 500)
			},
			/**
			 * Clears the search term and refreshes the list
			 * @returns {Promise<void>}
			 */
			async clearSearch() {
				this.searchTerm = ''
				await this.refreshEffectList()
			},
			/**
			 * Fetches and refreshes the list of effects
			 * @param {string|null} search - Optional search term
			 * @throws {Error} When fetching effects fails
			 * @returns {Promise<void>}
			 */
			async refreshEffectList(search = null) {
				this.isLoadingEffectList = true
				let endpoint = '/index.php/apps/larpingapp/api/objects/effect'
				
				if (this.searchTerm) {
					endpoint += `${endpoint.includes('?') ? '&' : '?'}_search=${encodeURIComponent(this.searchTerm)}`
				}

				try {
					const response = await fetch(endpoint, { method: 'GET' })
					const data = await response.json()
					this.setEffectList(data.results)
				} catch (err) {
					console.error('Error fetching effect list:', err)
					throw err
				} finally {
					this.isLoadingEffectList = false
				}
			},
			// Fetch a single effect by ID
			async getEffect(id) {
				const endpoint = `/index.php/apps/larpingapp/api/objects/effect/${id}?_extend=abilities`
				try {
					const response = await fetch(endpoint, { method: 'GET' })
					const data = await response.json()
					this.setEffectItem(data)
					return data
				} catch (err) {
					console.error(err)
					throw err
				}
			},
			// Delete an effect by ID
			deleteEffect() {
				if (!this.effectItem || !this.effectItem.id) {
					throw new Error('No effect item to delete')
				}

				console.log('Deleting effect...')

				const endpoint = `/index.php/apps/larpingapp/api/objects/effect/${this.effectItem.id}`

				return fetch(endpoint, {
					method: 'DELETE',
				})
					.then((response) => {
						this.refreshEffectList()
					})
					.catch((err) => {
						console.error('Error deleting effect:', err)
						throw err
					})
			},
			// Create or update an effect
			saveEffect(effectItem) {
				if (!effectItem) {
					throw new Error('No effect item to save')
				}

				console.log('Saving effect...')

				const isNewEffect = !effectItem.id
				const endpoint = isNewEffect
					? '/index.php/apps/larpingapp/api/objects/effect?_extend=abilities'
					: `/index.php/apps/larpingapp/api/objects/effect/${effectItem.id}?_extend=abilities`
				const method = isNewEffect ? 'POST' : 'PUT'

				return fetch(
					endpoint,
					{
						method,
						headers: {
							'Content-Type': 'application/json',
						},
						body: JSON.stringify(effectItem),
					},
				)
					.then((response) => response.json())
					.then((data) => {
						this.setEffectItem(data)
						console.log('Effect saved')
						return this.refreshEffectList()
					})
					.catch((err) => {
						console.error('Error saving effect:', err)
						throw err
					})
			},
			setAuditTrails(auditTrails) {
				this.auditTrails = auditTrails
				console.log('Audit trails set with ' + auditTrails.length + ' items')
			},
			setRelations(relations) {
				this.relations = relations
				console.log('Relations set with ' + relations.length + ' items')
			},
			setUses(uses) {
				this.uses = uses
				console.log('Uses set with ' + uses.length + ' items')
			},
			/**
			 * Fetches audit trails for an effect
			 * @param {string} id - The effect ID
			 * @throws {Error} When ID is missing or fetch fails
			 * @returns {Promise<Array<Object>>}
			 */
			async getAuditTrails(id) {
				this.isLoadingAuditTrails = true
				if (!id) {
					throw new Error('ID required to fetch audit trails')
				}

				console.log('Fetching audit trails...')
				const endpoint = `/index.php/apps/larpingapp/api/objects/effect/${id}/audit`

				try {
					const response = await fetch(endpoint, {
						method: 'GET'
					})
					const data = await response.json()
					this.setAuditTrails(data)
					return data
				} catch (err) {
					console.error('Error fetching audit trails:', err)
					throw err
				} finally {
					this.isLoadingAuditTrails = false
				}
			},
			/**
			 * Fetches relations for an effect
			 * @param {string} id - The effect ID
			 * @throws {Error} When ID is missing or fetch fails
			 * @returns {Promise<Array<Object>>}
			 */
			async getRelations(id) {
				this.isLoadingRelations = true
				if (!id) {
					throw new Error('ID required to fetch relations')
				}

				console.log('Fetching relations...')
				const endpoint = `/index.php/apps/larpingapp/api/objects/effect/${id}/relations`

				try {
					const response = await fetch(endpoint, {
						method: 'GET'
					})
					const data = await response.json()
					this.setRelations(data)
					return data
				} catch (err) {
					console.error('Error fetching relations:', err)
					throw err
				} finally {
					this.isLoadingRelations = false
				}
			},
			/**
			 * Fetches uses for an effect
			 * @param {string} id - The effect ID
			 * @throws {Error} When ID is missing or fetch fails
			 * @returns {Promise<Array<Object>>}
			 */
			async getUses(id) {
				this.isLoadingUses = true
				if (!id) {
					throw new Error('ID required to fetch uses')
				}

				console.log('Fetching uses...')
				const endpoint = `/index.php/apps/larpingapp/api/objects/effect/${id}/uses`

				try {
					const response = await fetch(endpoint, {
						method: 'GET'
					})
					const data = await response.json()
					this.setUses(data)
					return data
				} catch (err) {
					console.error('Error fetching uses:', err)
					throw err
				} finally {
					this.isLoadingUses = false
				}
			}
		},
	},
)
