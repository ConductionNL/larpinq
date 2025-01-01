/* eslint-disable no-console */
import { defineStore } from 'pinia'
import { Item } from '../../entities/index.js'

/**
 * Store for managing item data
 * @phpstan-type ItemData {id: string, name: string, description: string, type: string, effects: Array<string>, ...}
 */
export const useItemStore = defineStore(
	'item', {
		state: () => ({
			/** @var {Item|false} Current active item */
			itemItem: false,
			/** @var {Array<Item>} List of all items */
			itemList: [],
			/** @var {Array<Object>} Audit trail entries for current item */
			auditTrails: [],
			/** @var {Array<Object>} Relations for current item */
			relations: [],
			/** @var {Array<Object>} Uses of current item */
			uses: [],
			// Loading states
			/** @var {boolean} Whether item is being loaded */
			isLoadingItem: false,
			/** @var {boolean} Whether item list is being loaded */
			isLoadingItemList: false,
			/** @var {boolean} Whether audit trails are being loaded */
			isLoadingAuditTrails: false,
			/** @var {boolean} Whether relations are being loaded */
			isLoadingRelations: false,
			/** @var {boolean} Whether uses are being loaded */
			isLoadingUses: false,
			/** @var {string} Current search term for items */
			searchTerm: '',
			/** @var {number|null} Debounce timer for search */
			searchDebounceTimer: null,
		}),
		actions: {
			/**
			 * Sets the active item and loads its audit trails and relations
			 * @param {ItemData|null} itemItem - The item to set, or null to clear
			 * @throws {Error} When loading item data fails
			 * @returns {Promise<void>}
			 */
			async setItemItem(itemItem) {
				this.isLoadingItem = true
				try {
					this.itemItem = itemItem && new Item(itemItem)
					console.log('Active item item set to ' + itemItem)

					if (this.itemItem && this.itemItem.id) {
						await Promise.all([
							this.getAuditTrails(this.itemItem.id),
							this.getRelations(this.itemItem.id)
							])
					}
				} catch (err) {
					console.error('Error loading item data:', err)
				} finally {
					this.isLoadingItem = false
				}
			},
			/**
			 * Sets the list of items
			 * @param {Array<ItemData>} itemList - Array of item data
			 * @returns {void}
			 */
			setItemList(itemList) {
				this.itemList = itemList.map(
					(itemItem) => new Item(itemItem),
				)
				console.log('Item list set to ' + itemList.length + ' items')
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
					this.refreshItemList()
				}, 500)
			},
			/**
			 * Clears the search term and refreshes the list
			 * @returns {Promise<void>}
			 */
			async clearSearch() {
				this.searchTerm = ''
				await this.refreshItemList()
			},
			/**
			 * Fetches and refreshes the list of items
			 * @param {string|null} search - Optional search term
			 * @throws {Error} When fetching items fails
			 * @returns {Promise<void>}
			 */
			async refreshItemList(search = null) {
				this.isLoadingItemList = true
				let endpoint = '/index.php/apps/larpingapp/api/objects/item'
				
				if (this.searchTerm) {
					endpoint += `${endpoint.includes('?') ? '&' : '?'}_search=${encodeURIComponent(this.searchTerm)}`
				}

				try {
					const response = await fetch(endpoint, { method: 'GET' })
					const data = await response.json()
					this.setItemList(data.results)
				} catch (err) {
					console.error('Error fetching item list:', err)
					throw err
				} finally {
					this.isLoadingItemList = false
				}
			},
			// Fetch a single item by ID
			async getItem(id) {
				const endpoint = `/index.php/apps/larpingapp/api/objects/item/${id}?_extend=effects`
				try {
					const response = await fetch(endpoint, { method: 'GET' })
					const data = await response.json()
					this.setItemItem(data)
					return data
				} catch (err) {
					console.error(err)
					throw err
				}
			},
			// Delete an item by ID
			deleteItem() {
				if (!this.itemItem || !this.itemItem.id) {
					throw new Error('No item to delete')
				}

				console.log('Deleting item...')

				const endpoint = `/index.php/apps/larpingapp/api/objects/item/${this.itemItem.id}`

				return fetch(endpoint, {
					method: 'DELETE',
				})
					.then((response) => {
						this.refreshItemList()
					})
					.catch((err) => {
						console.error('Error deleting item:', err)
						throw err
					})
			},
			// Create or update an item
			saveItem(itemItem) {
				if (!itemItem) {
					throw new Error('No item to save')
				}

				console.log('Saving item...')

				const isNewItem = !itemItem.id
				const endpoint = isNewItem
					? '/index.php/apps/larpingapp/api/objects/item?_extend=effects'
					: `/index.php/apps/larpingapp/api/objects/item/${itemItem.id}?_extend=effects`
				const method = isNewItem ? 'POST' : 'PUT'

				// Create a copy of the item to avoid modifying the original
				const itemToSave = { ...itemItem }

				// Initialize effects array if not set
				itemToSave.effects = itemToSave.effects || []

				// Transform effects array to array of UUIDs
				itemToSave.effects = itemToSave.effects.map(effect => 
					typeof effect === 'object' ? effect.id : effect
				)

				return fetch(
					endpoint,
					{
						method,
						headers: {
							'Content-Type': 'application/json',
						},
						body: JSON.stringify(itemToSave),
					},
				)
					.then((response) => response.json())
					.then((data) => {
						this.setItemItem(data)
						console.log('Item saved')
						return this.refreshItemList()
					})
					.catch((err) => {
						console.error('Error saving item:', err)
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
			async getAuditTrails(id) {
				this.isLoadingAuditTrails = true
				if (!id) {
					throw new Error('Item ID required to fetch audit trails')
				}

				try {
					const response = await fetch(`/index.php/apps/larpingapp/api/objects/item/${id}/audit`, {
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
					throw new Error('Item ID required to fetch relations')
				}

				try {
					const response = await fetch(`/index.php/apps/larpingapp/api/objects/item/${id}/relations`, {
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
					throw new Error('Item ID required to fetch uses')
				}

				try {
					const response = await fetch(`/index.php/apps/larpingapp/api/objects/item/${id}/uses`, {
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
	})
