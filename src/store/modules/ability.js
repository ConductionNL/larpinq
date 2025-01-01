/* eslint-disable no-console */
import { defineStore } from 'pinia'
import { Ability } from '../../entities/index.js'

/**
 * Store for managing ability data
 * @phpstan-type AbilityData {id: string, name: string, base: string, ...}
 */
export const useAbilityStore = defineStore(
	'ability', {
		state: () => ({
			/** @var {Ability|false} Current active ability */
			abilityItem: false,
			/** @var {Array<Ability>} List of all abilities */
			abilityList: [],
			/** @var {Array<Object>} Audit trail entries for current ability */
			auditTrails: [],
			/** @var {Array<Object>} Relations for current ability */
			relations: [],
			/** @var {Array<Object>} Uses of current ability */
			uses: [],
			// Loading states
			/** @var {boolean} Whether ability is being loaded */
			isLoadingAbility: false,
			/** @var {boolean} Whether ability list is being loaded */
			isLoadingAbilityList: false,
			/** @var {boolean} Whether audit trails are being loaded */
			isLoadingAuditTrails: false,
			/** @var {boolean} Whether relations are being loaded */
			isLoadingRelations: false,
			/** @var {boolean} Whether uses are being loaded */
			isLoadingUses: false,
			/** @var {string} Current search term for abilities */
			searchTerm: '',
			/** @var {number|null} Debounce timer for search */
			searchDebounceTimer: null,
		}),
		actions: {
			/**
			 * Sets the active ability item and loads its audit trails and relations
			 * @param {AbilityData|null} abilityItem - The ability item to set, or null to clear
			 * @throws {Error} When loading ability data fails
			 * @returns {Promise<void>}
			 */
			async setAbilityItem(abilityItem) {
				this.isLoadingAbility = true
				try {
					// Set the ability item first
					this.abilityItem = abilityItem && new Ability(abilityItem)
					console.log('Active ability item set to ' + abilityItem && abilityItem?.id)

					// If we have an ability item, load its audit trails and relations
					if (this.abilityItem && this.abilityItem.id) {
						await Promise.all([
							this.getAuditTrails(this.abilityItem.id),
							this.getRelations(this.abilityItem.id)
						])
					}
				} catch (err) {
					console.error('Error loading ability data:', err)
					throw err
				} finally {
					this.isLoadingAbility = false
				}
			},
			/**
			 * Sets the list of abilities
			 * @param {Array<AbilityData>} abilityList - Array of ability data
			 * @returns {void}
			 */
			setAbilityList(abilityList) {
				this.abilityList = abilityList.map(
					(abilityItem) => new Ability(abilityItem),
				)
				console.log('Ability list set to ' + abilityList.length + ' item')
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
					this.refreshAbilityList()
				}, 500)
			},
			/**
			 * Clears the search term and refreshes the list
			 * @returns {Promise<void>}
			 */
			async clearSearch() {
				this.searchTerm = ''
				await this.refreshAbilityList()
			},
			/**
			 * Fetches and refreshes the list of abilities
			 * @throws {Error} When fetching abilities fails
			 * @returns {Promise<void>}
			 */
			async refreshAbilityList() {
				this.isLoadingAbilityList = true
				let endpoint = '/index.php/apps/larpingapp/api/objects/ability'
				
				if (this.searchTerm) {
					endpoint += `${endpoint.includes('?') ? '&' : '?'}_search=${encodeURIComponent(this.searchTerm)}`
				}

				try {
					const response = await fetch(endpoint, { method: 'GET' })
					const data = await response.json()
					this.setAbilityList(data.results)
				} catch (err) {
					console.error('Error fetching ability list:', err)
					throw err
				} finally {
					this.isLoadingAbilityList = false
				}
			},
			/**
			 * Deletes the current ability
			 * @throws {Error} When no ability is set or deletion fails
			 * @returns {Promise<void>}
			 */
			async deleteAbility() {
				if (!this.abilityItem || !this.abilityItem.id) {
					throw new Error('No ability item to delete')
				}

				console.log('Deleting ability...')

				const endpoint = `/index.php/apps/larpingapp/api/objects/ability/${this.abilityItem.id}`

				return fetch(endpoint, {
					method: 'DELETE',
				})
					.then((response) => {
						this.refreshAbilityList()
					})
					.catch((err) => {
						console.error('Error deleting ability:', err)
						throw err
					})
			},
			/**
			 * Creates or updates an ability
			 * @param {AbilityData} abilityItem - The ability data to save
			 * @throws {Error} When saving ability fails
			 * @returns {Promise<void>}
			 */
			async saveAbility(abilityItem) {
				if (!abilityItem) {
					throw new Error('No ability item to save')
				}

				console.log('Saving ability...')

				const isNewAbility = !abilityItem.id
				const endpoint = isNewAbility
					? '/index.php/apps/larpingapp/api/objects/ability'
					: `/index.php/apps/larpingapp/api/objects/ability/${abilityItem.id}`
				const method = isNewAbility ? 'POST' : 'PUT'

				const abilityToSave = { ...abilityItem }
				Object.keys(abilityToSave).forEach(key => {
					if (abilityToSave[key] === '' || (Array.isArray(abilityToSave[key]) && abilityToSave[key].length === 0)) {
						delete abilityToSave[key]
					}
				})

				return fetch(
					endpoint,
					{
						method,
						headers: {
							'Content-Type': 'application/json',
						},
						body: JSON.stringify(abilityToSave),

					},
				)
					.then((response) => response.json())
					.then((data) => {
						this.setAbilityItem(data)
						console.log('Ability saved')
						return this.refreshAbilityList()
					})
					.catch((err) => {
						console.error('Error saving ability:', err)
						throw err
					})
			},

			/**
			 * Sets the audit trails for the current ability
			 * @param {Array<Object>} auditTrails - The audit trails to set
			 * @returns {void}
			 */
			setAuditTrails(auditTrails) {
				this.auditTrails = auditTrails
				console.log('Audit trails set with ' + auditTrails.length + ' items')
			},

			/**
			 * Sets the relations for the current ability
			 * @param {Array<Object>} relations - The relations to set
			 * @returns {void}
			 */
			setRelations(relations) {
				this.relations = relations
				console.log('Relations set with ' + relations.length + ' items')
			},

			/**
			 * Sets the uses for the current ability
			 * @param {Array<Object>} uses - The uses to set
			 * @returns {void}
			 */
			setUses(uses) {
				this.uses = uses
				console.log('Uses set with ' + uses.length + ' items')
			},

			/**
			 * Fetches audit trails for an ability
			 * @param {string} id - The ability ID
			 * @throws {Error} When ID is missing or fetch fails
			 * @returns {Promise<Array<Object>>}
			 */
			async getAuditTrails(id) {
				this.isLoadingAuditTrails = true
				if (!id) {
					throw new Error('ID required to fetch audit trails')
				}

				console.log('Fetching audit trails...')
				const endpoint = `/index.php/apps/larpingapp/api/objects/ability/${id}/audit`

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
			 * Fetches relations for an ability
			 * @param {string} id - The ability ID
			 * @throws {Error} When ID is missing or fetch fails
			 * @returns {Promise<Array<Object>>}
			 */
			async getRelations(id) {
				this.isLoadingRelations = true
				if (!id) {
					throw new Error('ID required to fetch relations')
				}

				console.log('Fetching relations...')
				const endpoint = `/index.php/apps/larpingapp/api/objects/ability/${id}/relations`

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
			 * Fetches uses for an ability
			 * @param {string} id - The ability ID
			 * @throws {Error} When ID is missing or fetch fails
			 * @returns {Promise<Array<Object>>}
			 */
			async getUses(id) {
				this.isLoadingUses = true
				if (!id) {
					throw new Error('ID required to fetch uses')
				}

				console.log('Fetching uses...')
				const endpoint = `/index.php/apps/larpingapp/api/objects/ability/${id}/uses`

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
