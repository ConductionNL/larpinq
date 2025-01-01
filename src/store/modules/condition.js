/* eslint-disable no-console */
import { defineStore } from 'pinia'
import { Condition } from '../../entities/index.js'

/**
 * Store for managing condition data
 * @phpstan-type ConditionData {id: string, name: string, effects: Array<string>, ...}
 */
export const useConditionStore = defineStore(
	'condition', {
		state: () => ({
			/** @var {Condition|false} Current active condition */
			conditionItem: false,
			/** @var {Array<Condition>} List of all conditions */
			conditionList: [],
			/** @var {Array<Object>} Audit trail entries for current condition */
			auditTrails: [],
			/** @var {Array<Object>} Relations for current condition */
			relations: [],
			/** @var {Array<Object>} Uses of current condition */
			uses: [],
			// Loading states
			/** @var {boolean} Whether condition is being loaded */
			isLoadingCondition: false,
			/** @var {boolean} Whether condition list is being loaded */
			isLoadingConditionList: false,
			/** @var {boolean} Whether audit trails are being loaded */
			isLoadingAuditTrails: false,
			/** @var {boolean} Whether relations are being loaded */
			isLoadingRelations: false,
			/** @var {boolean} Whether uses are being loaded */
			isLoadingUses: false,
			/** @var {string} Current search term for conditions */
			searchTerm: '',
			/** @var {number|null} Debounce timer for search */
			searchDebounceTimer: null,
		}),
		actions: {
			/**
			 * Sets the active condition item and loads its audit trails and relations
			 * @param {ConditionData|null} conditionItem - The condition item to set, or null to clear
			 * @throws {Error} When loading condition data fails
			 * @returns {Promise<void>}
			 */
			async setConditionItem(conditionItem) {
				this.isLoadingCondition = true
				try {
					this.conditionItem = conditionItem && new Condition(conditionItem)
					console.log('Active condition item set to ' + conditionItem)

					if (this.conditionItem && this.conditionItem.id) {
						await Promise.all([
							this.getAuditTrails(this.conditionItem.id),
							this.getRelations(this.conditionItem.id)
							])
					}
				} catch (err) {
					console.error('Error loading condition data:', err)
				} finally {
					this.isLoadingCondition = false
				}
			},
			/**
			 * Sets the list of conditions
			 * @param {Array<ConditionData>} conditionList - Array of condition data
			 * @returns {void}
			 */
			setConditionList(conditionList) {
				this.conditionList = conditionList.map(
					(conditionItem) => new Condition(conditionItem),
				)
				console.log('Condition list set to ' + conditionList.length + ' items')
			},
			/**
			 * Fetches and refreshes the list of conditions
			 * @param {string|null} search - Optional search term
			 * @throws {Error} When fetching conditions fails
			 * @returns {Promise<void>}
			 */
			async refreshConditionList(search = null) {
				this.isLoadingConditionList = true
				let endpoint = '/index.php/apps/larpingapp/api/objects/condition?_extend=effects'
				if (search !== null && search !== '') {
					endpoint = endpoint + '?_search=' + search
				}
				try {
					const response = await fetch(endpoint, { method: 'GET' })
					const data = await response.json()
					this.setConditionList(data.results)
				} catch (err) {
					console.error(err)
				} finally {
					this.isLoadingConditionList = false
				}
			},
			/**
			 * Fetches a single condition by ID
			 * @param {string} id - The condition ID to fetch
			 * @throws {Error} When fetching condition fails
			 * @returns {Promise<ConditionData>}
			 */
			async getCondition(id) {
				const endpoint = `/index.php/apps/larpingapp/api/objects/condition/${id}?_extend=effects`
				try {
					const response = await fetch(endpoint, { method: 'GET' })
					const data = await response.json()
					this.setConditionItem(data)
					return data
				} catch (err) {
					console.error(err)
					throw err
				}
			},
			/**
			 * Deletes the current condition
			 * @throws {Error} When no condition is set or deletion fails
			 * @returns {Promise<void>}
			 */
			async deleteCondition() {
				if (!this.conditionItem || !this.conditionItem.id) {
					throw new Error('No condition item to delete')
				}

				console.log('Deleting condition...')

				const endpoint = `/index.php/apps/larpingapp/api/objects/condition/${this.conditionItem.id}`

				return fetch(endpoint, {
					method: 'DELETE',
				})
					.then((response) => {
						this.refreshConditionList()
					})
					.catch((err) => {
						console.error('Error deleting condition:', err)
						throw err
					})
			},
			/**
			 * Creates or updates a condition
			 * @param {ConditionData} conditionItem - The condition data to save
			 * @throws {Error} When saving condition fails
			 * @returns {Promise<void>}
			 */
			async saveCondition(conditionItem) {
				if (!conditionItem) {
					throw new Error('No condition item to save')
				}

				console.log('Saving condition...')

				// Create a copy of the condition item to avoid modifying the original
				const conditionToSave = { ...conditionItem }

				// Initialize effects array if not set
				conditionToSave.effects = conditionToSave.effects || []

				// Transform effects array to array of UUIDs
				conditionToSave.effects = conditionToSave.effects.map(effect => 
					typeof effect === 'object' ? effect.id : effect
				)

				const isNewCondition = !conditionToSave.id
				const endpoint = isNewCondition
					? '/index.php/apps/larpingapp/api/objects/condition?_extend=effects'
					: `/index.php/apps/larpingapp/api/objects/condition/${conditionToSave.id}?_extend=effects`
				const method = isNewCondition ? 'POST' : 'PUT'

				return fetch(
					endpoint,
					{
						method,
						headers: {
							'Content-Type': 'application/json',
						},
						body: JSON.stringify(conditionToSave),
					},
				)
					.then((response) => response.json())
					.then((data) => {
						this.setConditionItem(data)
						console.log('Condition saved')
						return this.refreshConditionList()
					})
					.catch((err) => {
						console.error('Error saving condition:', err)
						throw err
					})
			},
			/**
			 * Sets the audit trails for the current condition
			 * @param {Array<Object>} auditTrails - The audit trails to set
			 * @returns {void}
			 */
			setAuditTrails(auditTrails) {
				this.auditTrails = auditTrails
				console.log('Audit trails set with ' + auditTrails.length + ' items')
			},
			/**
			 * Sets the relations for the current condition
			 * @param {Array<Object>} relations - The relations to set
			 * @returns {void}
			 */
			setRelations(relations) {
				this.relations = relations
				console.log('Relations set with ' + relations.length + ' items')
			},
			/**
			 * Sets the uses for the current condition
			 * @param {Array<Object>} uses - The uses to set
			 * @returns {void}
			 */
			setUses(uses) {
				this.uses = uses
				console.log('Uses set with ' + uses.length + ' items')
			},
			async getAuditTrails(id) {
				this.isLoadingAuditTrails = true
				if (!id) {
					throw new Error('Condition ID required to fetch audit trails')
				}

				try {
					const response = await fetch(`/index.php/apps/larpingapp/api/objects/condition/${id}/audit`, {
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
			async getRelations(id) {
				this.isLoadingRelations = true
				if (!id) {
					throw new Error('Condition ID required to fetch relations')
				}

				try {
					const response = await fetch(`/index.php/apps/larpingapp/api/objects/condition/${id}/relations`, {
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
			async getUses(id) {
				this.isLoadingUses = true
				if (!id) {
					throw new Error('Condition ID required to fetch uses')
				}

				try {
					const response = await fetch(`/index.php/apps/larpingapp/api/objects/condition/${id}/uses`, {
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
					this.refreshConditionList()
				}, 500)
			},
			/**
			 * Clears the search term and refreshes the list
			 * @returns {Promise<void>}
			 */
			async clearSearch() {
				this.searchTerm = ''
				await this.refreshConditionList()
			},
			async refreshConditionList() {
				this.isLoadingConditionList = true
				let endpoint = '/index.php/apps/larpingapp/api/objects/condition?_extend=effects'
				
				if (this.searchTerm) {
					endpoint += `&_search=${encodeURIComponent(this.searchTerm)}`
				}

				try {
					const response = await fetch(endpoint, { method: 'GET' })
					const data = await response.json()
					this.setConditionList(data.results)
				} catch (err) {
					console.error('Error fetching condition list:', err)
					throw err
				} finally {
					this.isLoadingConditionList = false
				}
			},
		},
	},
)
