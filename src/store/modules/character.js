/* eslint-disable no-console */
import { defineStore } from 'pinia'
import { Character } from '../../entities/index.js'

export const useCharacterStore = defineStore(
	'character', {
		state: () => ({
			characterItem: false,
			characterList: [],
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
			/* istanbul ignore next */ // ignore this for Jest until moved into a service
			async refreshCharacterList(search = null) {
				// @todo this might belong in a service?
				let endpoint = '/index.php/apps/larpingapp/api/characters'
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
				const endpoint = `/index.php/apps/larpingapp/api/characters/${id}`
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
			// Delete a character
			deleteCharacter() {
				if (!this.characterItem || !this.characterItem.id) {
					throw new Error('No character item to delete')
				}

				console.log('Deleting character...')

				const endpoint = `/index.php/apps/larpingapp/api/characters/${this.characterItem.id}`

				return fetch(endpoint, {
					method: 'DELETE',
				})
					.then((response) => {
						this.refreshCharacterList()
					})
					.catch((err) => {
						console.error('Error deleting character:', err)
						throw err
					})
			},
			// Create or save a character from store
			saveCharacter(characterItem) {
				if (!characterItem) {
					throw new Error('No character item to save')
				}

				console.log('Saving character...')

				const isNewCharacter = !characterItem.id
				const endpoint = isNewCharacter
					? '/index.php/apps/larpingapp/api/characters'
					: `/index.php/apps/larpingapp/api/characters/${characterItem.id}`
				const method = isNewCharacter ? 'POST' : 'PUT'

				// Create a copy of the character item and remove empty properties
				const characterToSave = { ...characterItem }
				Object.keys(characterToSave).forEach(key => {
					if (characterToSave[key] === '' || (Array.isArray(characterToSave[key]) && characterToSave[key].length === 0)) {
						delete characterToSave[key]
					}
				})

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
						// Refresh the character list
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
