/* eslint-disable no-console */
import { defineStore } from 'pinia'
import { Character } from '../../entities/index.js'

export const useCharacterStore = defineStore(
	'character', {
		state: () => ({
			characterItem: false,
			characterList: [],
			auditTrails: [],
			relations: [],
			uses: [] // Added uses array to state
		}),
		actions: {
			setCharacterItem(characterItem) {
				this.characterItem = characterItem && new Character(characterItem)
				console.log('Active character item set to ' + characterItem)
			},
			setCharacterList(characterList) {
				this.characterList = characterList.map(
					(characterItem) => new Character(characterItem),
				)
				console.log('Character list set to ' + characterList.length + ' items')
			},
			setAuditTrails(auditTrails) {
				this.auditTrails = auditTrails
				console.log('Audit trails set with ' + auditTrails.length + ' items')
			},
			setRelations(relations) {
				this.relations = relations
				console.log('Relations set with ' + relations.length + ' items')
			},
			setUses(uses) { // Added setter for uses
				this.uses = uses
				console.log('Uses set with ' + uses.length + ' items')
			},
			/* istanbul ignore next */ // ignore this for Jest until moved into a service
			async refreshCharacterList(search = null) {
				// @todo this might belong in a service?
				//let endpoint = '/index.php/apps/larpingapp/api/objects/character'
				let endpoint = '/index.php/apps/larpingapp/api/objects/character?_extend=ocName,skills,items,conditions,events'
				if (search !== null && search !== '') {
					endpoint = endpoint + '?_search=' + search
				}
				return fetch(endpoint, {
					method: 'GET',
				})
					.then(
						(response) => {
							response.json().then(
								(data) => {
									this.setCharacterList(data.results)
								},
							)
						},
					)
					.catch(
						(err) => {
							console.error(err)
						},
					)
			},
			// New function to get a single character
			async getCharacter(id) {
				const endpoint = `/index.php/apps/larpingapp/api/objects/character/${id}?_extend=ocName,skills,items,conditions,events`
				try {
					const response = await fetch(endpoint, {
						method: 'GET',
					})
					const data = await response.json()
					this.setCharacterItem(data)
					return data
				} catch (err) {
					console.error(err)
					throw err
				}
			},
			// Get audit trails for a character
			async getAuditTrails(id) {
				if (!id) {
					throw new Error('Character ID required to fetch audit trails')
				}

				console.log('Fetching audit trails...')
				const endpoint = `/index.php/apps/larpingapp/api/objects/character/${id}/audit`

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
			// Get relations for a character
			async getRelations(id) {
				if (!id) {
					throw new Error('Character ID required to fetch relations')
				}

				console.log('Fetching character relations...')
				const endpoint = `/index.php/apps/larpingapp/api/objects/character/${id}/relations`

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
			// Get uses for a character
			async getUses(id) {
				if (!id) {
					throw new Error('Character ID required to fetch uses')
				}

				console.log('Fetching character uses...')
				const endpoint = `/index.php/apps/larpingapp/api/objects/character/${id}/uses`

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
			},
			// Delete a character
			deleteCharacter() {
				if (!this.characterItem || !this.characterItem.id) {
					throw new Error('No character item to delete')
				}

				console.log('Deleting character...')

				const endpoint = `/index.php/apps/larpingapp/api/objects/character/${this.characterItem.id}`

				return fetch(endpoint, {
					method: 'DELETE',
				})
					.then((response) => {
						this.refreshCharacterList()
						this.setCharacterItem(null)
					})
					.catch((err) => {
						console.error('Error deleting character:', err)
						throw err
					})
			},
			// Create or save a character from store
			async saveCharacter(characterItem) {
				if (!characterItem) {
					throw new Error('No character item to save')
				}

				console.log('Saving character...')

				// Create a copy of the character item to avoid modifying the original
				const characterToSave = { ...characterItem }

				// Transform arrays of objects to arrays of UUIDs if needed
				if (characterToSave.skills) {
					characterToSave.skills = characterToSave.skills.map(skill => typeof skill === 'object' ? skill.id : skill)
				}
				if (characterToSave.items) {
					characterToSave.items = characterToSave.items.map(item => typeof item === 'object' ? item.id : item)
				}
				if (characterToSave.conditions) {
					characterToSave.conditions = characterToSave.conditions.map(condition => typeof condition === 'object' ? condition.id : condition)
				}
				if (characterToSave.effects) {
					characterToSave.effects = characterToSave.effects.map(effect => typeof effect === 'object' ? effect.id : effect)
				}
				// Transform ocName object to UUID if needed
				if (characterToSave.ocName && typeof characterToSave.ocName === 'object') {
					characterToSave.ocName = characterToSave.ocName.id
				}

				const isNewCharacter = !characterToSave.id
				const endpoint = isNewCharacter
					? '/index.php/apps/larpingapp/api/objects/character'
					: `/index.php/apps/larpingapp/api/objects/character/${characterToSave.id}`
				const method = isNewCharacter ? 'POST' : 'PUT'

				return fetch(
					endpoint,
					{
						method,
						headers: {
							'Content-Type': 'application/json',
						},
						body: JSON.stringify(characterToSave),
					},
				)
					.then((response) => response.json())
					.then((data) => {
						this.setCharacterItem(data)
						console.log('Character saved')
						return this.refreshCharacterList()
					})
					.catch((err) => {
						console.error('Error saving character:', err)
						throw err
					})
			},
		},
	},
)
