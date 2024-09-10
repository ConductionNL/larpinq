/* eslint-disable no-console */
import { defineStore } from 'pinia'
import { Condition } from '../../entities/index.js'

export const useConditionStore = defineStore(
	'condition', {
		state: () => ({
			conditionItem: false,
			conditionList: [],
		}),
		actions: {
			// Set the active condition item
			setConditionItem(conditionItem) {
				this.conditionItem = conditionItem && new Condition(conditionItem)
				console.log('Active condition item set to ' + conditionItem)
			},
			// Set the list of conditions
			setConditionList(conditionList) {
				this.conditionList = conditionList.map(
					(conditionItem) => new Condition(conditionItem),
				)
				console.log('Condition list set to ' + conditionList.length + ' items')
			},
			// Fetch and refresh the list of conditions
			async refreshConditionList(search = null) {
				let endpoint = '/index.php/apps/larpingapp/api/conditions'
				if (search !== null && search !== '') {
					endpoint = endpoint + '?_search=' + search
				}
				try {
					const response = await fetch(endpoint, { method: 'GET' })
					const data = await response.json()
					this.setConditionList(data.results)
				} catch (err) {
					console.error(err)
				}
			},
			// Fetch a single condition by ID
			async getCondition(id) {
				const endpoint = `/index.php/apps/larpingapp/api/conditions/${id}`
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
			// Delete a condition by ID
			deleteCondition() {
				if (!this.conditionItem || !this.conditionItem.id) {
					throw new Error('No condition item to delete')
				}

				console.log('Deleting condition...')

				const endpoint = `/index.php/apps/larpingapp/api/conditions/${this.conditionItem.id}`

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
			// Create or update a condition
			saveCondition(conditionItem) {
				if (!conditionItem) {
					throw new Error('No condition item to save')
				}

				console.log('Saving condition...')

				const isNewCondition = !conditionItem.id
				const endpoint = isNewCondition
					? '/index.php/apps/larpingapp/api/conditions'
					: `/index.php/apps/larpingapp/api/conditions/${conditionItem.id}`
				const method = isNewCondition ? 'POST' : 'PUT'

				return fetch(
					endpoint,
					{
						method,
						headers: {
							'Content-Type': 'application/json',
						},
						body: JSON.stringify(conditionItem),
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
		},
	},
)
