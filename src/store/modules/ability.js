/* eslint-disable no-console */
import { defineStore } from 'pinia'
import { Ability } from '../../entities/index.js'

export const useAbilityStore = defineStore(
	'ability', {
		state: () => ({
			abilityItem: false,
			abilityList: [],
			auditTrails: [],
			relations: [],
			uses: []
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
				let endpoint = '/index.php/apps/larpingapp/api/objects/ability'
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

			saveAbility(abilityItem) {
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
				}
			},

			async getRelations(id) {
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
				}
			},

			async getUses(id) {
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
				}
			}
		},
	},
)
