/* eslint-disable no-console */
import { defineStore } from 'pinia'
import { Character } from '../../entities/index.js'

/**
 * Store for managing character data
 * @phpstan-type CharacterData {id: string, name: string, skills: Array<string>, items: Array<string>, conditions: Array<string>, events: Array<string>, ...}
 */
export const useCharacterStore = defineStore(
	'character', {
		state: () => ({
			/** @var {Character|false} Current active character */
			characterItem: false,
			/** @var {Array<Character>} List of all characters */
			characterList: [],
			/** @var {Array<Object>} Audit trail entries for current character */
			auditTrails: [],
			/** @var {Array<Object>} Relations for current character */
			relations: [],
			/** @var {Array<Object>} Uses of current character */
			uses: [],
			// Loading states
			/** @var {boolean} Whether character is being loaded */
			isLoadingCharacter: false,
			/** @var {boolean} Whether character list is being loaded */
			isLoadingCharacterList: false,
			/** @var {boolean} Whether audit trails are being loaded */
			isLoadingAuditTrails: false,
			/** @var {boolean} Whether relations are being loaded */
			isLoadingRelations: false,
			/** @var {boolean} Whether uses are being loaded */
			isLoadingUses: false,
			/** @var {string} The current search term */
			searchTerm: '',
			/** @var {NodeJS.Timeout} The debounce timer for search */
			searchDebounceTimer: null,
		}),
		actions: {
			/**
			 * Sets the active character item and loads its audit trails and relations
			 * @param {CharacterData|null} characterItem - The character item to set, or null to clear
			 * @throws {Error} When loading character data fails
			 * @returns {Promise<void>}
			 */
			async setCharacterItem(characterItem) {
				// Set the character item first
				this.characterItem = characterItem && new Character(characterItem)
				console.log('Active character item set to ' + characterItem)

				// If we have a character item, load its audit trails and relations
				if (this.characterItem && this.characterItem.id) {
					try {
						// Load audit trails and relations in parallel
						await Promise.all([
							this.getAuditTrails(this.characterItem.id),
							this.getRelations(this.characterItem.id)
						])
					} catch (err) {
						console.error('Error loading character data:', err)
					}
				}
			},
			/**
			 * Sets the list of characters
			 * @param {Array<CharacterData>} characterList - Array of character data
			 * @returns {void}
			 */
			setCharacterList(characterList) {
				this.characterList = characterList.map(
					(characterItem) => new Character(characterItem),
				)
				console.log('Character list set to ' + characterList.length + ' items')
			},
			/**
			 * Sets the audit trails for the current character
			 * @param {Array<Object>} auditTrails - The audit trails to set
			 * @returns {void}
			 */
			setAuditTrails(auditTrails) {
				this.auditTrails = auditTrails
				console.log('Audit trails set with ' + auditTrails.length + ' items')
			},
			/**
			 * Sets the relations for the current character
			 * @param {Array<Object>} relations - The relations to set
			 * @returns {void}
			 */
			setRelations(relations) {
				this.relations = relations
				console.log('Relations set with ' + relations.length + ' items')
			},
			/**
			 * Sets the uses for the current character
			 * @param {Array<Object>} uses - The uses to set
			 * @returns {void}
			 */
			setUses(uses) {
				this.uses = uses
				console.log('Uses set with ' + uses.length + ' items')
			},
			/**
			 * Fetches and refreshes the list of characters
			 * @param {string|null} search - Optional search term
			 * @throws {Error} When fetching characters fails
			 * @returns {Promise<void>}
			 */
			async refreshCharacterList(search = null) {
				this.isLoadingCharacterList = true
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
							throw err
						},
					)
					.finally(() => {
						this.isLoadingCharacterList = false
					})
			},
			/**
			 * Fetches a single character by ID
			 * @param {string} id - The character ID to fetch
			 * @throws {Error} When fetching character fails
			 * @returns {Promise<CharacterData>}
			 */
			async getCharacter(id) {
				this.isLoadingCharacter = true
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
				} finally {
					this.isLoadingCharacter = false
				}
			},
			/**
			 * Fetches audit trails for a character
			 * @param {string} id - The character ID
			 * @throws {Error} When ID is missing or fetch fails
			 * @returns {Promise<Array<Object>>}
			 */
			async getAuditTrails(id) {
				this.isLoadingAuditTrails = true
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
				} finally {
					this.isLoadingAuditTrails = false
				}
			},
			/**
			 * Fetches relations for a character
			 * @param {string} id - The character ID
			 * @throws {Error} When ID is missing or fetch fails
			 * @returns {Promise<Array<Object>>}
			 */
			async getRelations(id) {
				this.isLoadingRelations = true
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
				} finally {
					this.isLoadingRelations = false
				}
			},
			/**
			 * Fetches uses for a character
			 * @param {string} id - The character ID
			 * @throws {Error} When ID is missing or fetch fails
			 * @returns {Promise<Array<Object>>}
			 */
			async getUses(id) {
				this.isLoadingUses = true
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
				} finally {
					this.isLoadingUses = false
				}
			},
			/**
			 * Deletes the current character
			 * @throws {Error} When no character is set or deletion fails
			 * @returns {Promise<void>}
			 */
			async deleteCharacter() {
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
			/**
			 * Creates or updates a character
			 * @param {CharacterData} characterItem - The character data to save
			 * @throws {Error} When saving character fails
			 * @returns {Promise<void>}
			 */
			async saveCharacter(characterItem) {
				if (!characterItem) {
					throw new Error('No character item to save')
				}

				console.log('Saving character...')

				// Create a copy of the character item to avoid modifying the original
				const characterToSave = { ...characterItem }

				// Ensure all array properties are initialized with empty arrays if not set
				characterToSave.skills = characterToSave.skills || []
				characterToSave.items = characterToSave.items || []
				characterToSave.conditions = characterToSave.conditions || []
				characterToSave.effects = characterToSave.effects || []
				characterToSave.events = characterToSave.events || []
				characterToSave.ocName = characterToSave.ocName || ''

				// Transform arrays of objects to arrays of UUIDs
				characterToSave.skills = characterToSave.skills.map(skill => 
					typeof skill === 'object' ? skill.id : skill
				)
				characterToSave.items = characterToSave.items.map(item =>
					typeof item === 'object' ? item.id : item
				)
				characterToSave.conditions = characterToSave.conditions.map(condition =>
					typeof condition === 'object' ? condition.id : condition
				)
				characterToSave.effects = characterToSave.effects.map(effect =>
					typeof effect === 'object' ? effect.id : effect
				)
				characterToSave.events = characterToSave.events.map(event =>
					typeof event === 'object' ? event.id : event
				)

				// Transform ocName object to UUID if needed
				characterToSave.ocName = characterToSave.ocName && typeof characterToSave.ocName === 'object' 
					? characterToSave.ocName.id 
					: characterToSave.ocName || null

				const isNewCharacter = !characterToSave.id
				const endpoint = isNewCharacter
					? '/index.php/apps/larpingapp/api/objects/character?_extend=effects'
					: `/index.php/apps/larpingapp/api/objects/character/${characterToSave.id}?_extend=effects`
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
			/**
			 * Sets the search term and triggers a debounced search
			 * @param {string} term - The search term to set
			 * @returns {void}
			 */
			setSearchTerm(term) {
				this.searchTerm = term

				// Clear any existing timer
				if (this.searchDebounceTimer) {
					clearTimeout(this.searchDebounceTimer)
				}

				// Set a new timer
				this.searchDebounceTimer = setTimeout(() => {
					this.refreshCharacterList()
				}, 500) // 500ms delay (half a second)
			},
		},
	},
)
