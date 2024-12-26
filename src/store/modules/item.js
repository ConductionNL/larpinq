/* eslint-disable no-console */
import { defineStore } from 'pinia'
import { Item } from '../../entities/index.js'

export const useItemStore = defineStore(
	'item', {
		state: () => ({
			  itemItem: false,
			  itemList: [],
		}),
		actions: {
		// Set the active item
			setItemItem(itemItem) {
				this.itemItem = itemItem && new Item(itemItem)
				console.log('Active item set to ' + itemItem)
			},
			// Set the list of items		
			setItemList(itemList) {
				this.itemList = itemList.map(
					(itemItem) => new Item(itemItem),
				)
				console.log('Item list set to ' + itemList.length + ' items')
			},
			// Fetch and refresh the list of items
			async refreshItemList(search = null) {
				let endpoint = '/index.php/apps/larpingapp/api/objects/item'
				if (search !== null && search !== '') {
					endpoint = endpoint + '?_search=' + search
				}
				try {
					const response = await fetch(endpoint, { method: 'GET' })
					const data = await response.json()
					this.setItemList(data.results)
				} catch (err) {
					console.error(err)
				}
			},
			// Fetch a single item by ID
			async getItem(id) {
				const endpoint = `/index.php/apps/larpingapp/api/objects/item/${id}`
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
					? '/index.php/apps/larpingapp/api/objects/item'
					: `/index.php/apps/larpingapp/api/objects/item/${itemItem.id}`
				const method = isNewItem ? 'POST' : 'PUT'

				const itemToSave = { ...itemItem }
				Object.keys(itemToSave).forEach(key => {
					if (itemToSave[key] === '' || (Array.isArray(itemToSave[key]) && itemToSave[key].length === 0)) {
						delete itemToSave[key]
					}
				})

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
		},
	})
