/* eslint-disable no-console */
import { defineStore } from 'pinia'
import { Ability } from '../../entities/index.js'

export const useAbilityStore = defineStore(
	'ability', {
		state: () => ({
			abilityItem: false,
			abilityList: [],
		}),
		actions: {
			setAbilityItem(abilityItem) {
				this.abilityItem = abilityItem && new Ability(abilityItem)
				console.log('Active ability item set to ' + abilityItem && abilityItem?.id)
			},
			setAbilityList(abilityList) {
				this.abilityList = abilityList.map(
					(abilityItem) => new Ability(abilityItem),
				)
				console.log('Ability list set to ' + abilityList.length + ' item')
			},
			/* istanbul ignore next */ // ignore this for Jest until moved into a service
			async refreshAbilityList(search = null) {
				let endpoint = '/index.php/apps/larpingapp/api/abilities'
				if (search !== null && search !== '') {
					endpoint = endpoint + '?_search=' + search
				}
				try {
					const response = await fetch(endpoint, { method: 'GET' })
					const data = await response.json()
					this.setAbilityList(data.results)
				} catch (err) {
					console.error(err)
				}
			},
			deleteAbility() {
				if (!this.abilityItem || !this.abilityItem.id) {
					throw new Error('No ability item to delete')
				}

				console.log('Deleting ability...')

				const endpoint = `/index.php/apps/larpingapp/api/abilities/${this.abilityItem.id}`

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

			saveAbility(abilityItem) {
				if (!abilityItem) {
					throw new Error('No ability item to save')
				}

				console.log('Saving ability...')

				const isNewAbility = !abilityItem.id
				const endpoint = isNewAbility
					? '/index.php/apps/larpingapp/api/abilities'
					: `/index.php/apps/larpingapp/api/abilities/${abilityItem.id}`
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
		},
	},
)
