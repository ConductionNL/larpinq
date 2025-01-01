/* eslint-disable no-console */
import { defineStore } from 'pinia'
import { Player } from '../../entities/index.js'

/**
 * Store for managing player data
 * @phpstan-type PlayerData {id: string, name: string, email: string, characters: Array<string>, ...}
 */
export const usePlayerStore = defineStore(
	'player', {
		state: () => ({
			/** @var {Player|false} Current active player */
			playerItem: false,
			/** @var {Array<Player>} List of all players */
			playerList: [],
			/** @var {Array<Object>} Audit trail entries for current player */
			auditTrails: [],
			/** @var {Array<Object>} Relations for current player */
			relations: [],
			/** @var {Array<Object>} Uses of current player */
			uses: [],
			// Loading states
			/** @var {boolean} Whether player is being loaded */
			isLoadingPlayer: false,
			/** @var {boolean} Whether player list is being loaded */
			isLoadingPlayerList: false,
			/** @var {boolean} Whether audit trails are being loaded */
			isLoadingAuditTrails: false,
			/** @var {boolean} Whether relations are being loaded */
			isLoadingRelations: false,
			/** @var {boolean} Whether uses are being loaded */
			isLoadingUses: false,
			/** @var {string} Current search term for players */
			searchTerm: '',
			/** @var {number|null} Debounce timer for search */
			searchDebounceTimer: null,
		}),
		actions: {
			/**
			 * Sets the active player item and loads its audit trails and relations
			 * @param {PlayerData|null} playerItem - The player item to set, or null to clear
			 * @throws {Error} When loading player data fails
			 * @returns {Promise<void>}
			 */
			async setPlayerItem(playerItem) {
				this.isLoadingPlayer = true
				try {
					this.playerItem = playerItem && new Player(playerItem)
					console.log('Active player item set to ' + playerItem)

					if (this.playerItem && this.playerItem.id) {
						await Promise.all([
							this.getAuditTrails(this.playerItem.id),
							this.getRelations(this.playerItem.id)
						])
					}
				} catch (err) {
					console.error('Error loading player data:', err)
				} finally {
					this.isLoadingPlayer = false
				}
			},
			/**
			 * Sets the list of players
			 * @param {Array<PlayerData>} playerList - Array of player data
			 * @returns {void}
			 */
			setPlayerList(playerList) {
				this.playerList = playerList.map(
					(playerItem) => new Player(playerItem),
				)
				console.log('Player list set to ' + playerList.length + ' items')
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
					this.refreshPlayerList()
				}, 500)
			},
			/**
			 * Clears the search term and refreshes the list
			 * @returns {Promise<void>}
			 */
			async clearSearch() {
				this.searchTerm = ''
				await this.refreshPlayerList()
			},
			async refreshPlayerList() {
				this.isLoadingPlayerList = true
				let endpoint = '/index.php/apps/larpingapp/api/objects/player'
				
				if (this.searchTerm) {
					endpoint += `${endpoint.includes('?') ? '&' : '?'}_search=${encodeURIComponent(this.searchTerm)}`
				}

				try {
					const response = await fetch(endpoint, { method: 'GET' })
					const data = await response.json()
					this.setPlayerList(data.results)
				} catch (err) {
					console.error('Error fetching player list:', err)
					throw err
				} finally {
					this.isLoadingPlayerList = false
				}
			},
			/**
			 * Fetches a single player by ID
			 * @param {string} id - The player ID to fetch
			 * @throws {Error} When fetching player fails
			 * @returns {Promise<PlayerData>}
			 */
			async getPlayer(id) {
				const endpoint = `/index.php/apps/larpingapp/api/objects/player/${id}`
				try {
					const response = await fetch(endpoint, { method: 'GET' })
					const data = await response.json()
					this.setPlayerItem(data)
					return data
				} catch (err) {
					console.error(err)
					throw err
				}
			},
			// Get audit trails for a player
			async getAuditTrails(id) {
				this.isLoadingAuditTrails = true
				if (!id) {
					throw new Error('Player ID required to fetch audit trails')
				}

				console.log('Fetching audit trails...')
				const endpoint = `/index.php/apps/larpingapp/api/objects/player/${id}/audit`

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
			// Get relations for a player
			async getRelations(id) {
				this.isLoadingRelations = true
				if (!id) {
					throw new Error('Player ID required to fetch relations')
				}

				console.log('Fetching player relations...')
				const endpoint = `/index.php/apps/larpingapp/api/objects/player/${id}/relations`

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
			// Get uses for a player
			async getUses(id) {
				this.isLoadingUses = true
				if (!id) {
					throw new Error('Player ID required to fetch uses')
				}

				console.log('Fetching player uses...')
				const endpoint = `/index.php/apps/larpingapp/api/objects/player/${id}/uses`

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
			 * Deletes the current player
			 * @throws {Error} When no player is set or deletion fails
			 * @returns {Promise<void>}
			 */
			async deletePlayer() {
				if (!this.playerItem || !this.playerItem.id) {
					throw new Error('No player item to delete')
				}

				console.log('Deleting player...')

				const endpoint = `/index.php/apps/larpingapp/api/objects/player/${this.playerItem.id}`

				return fetch(endpoint, {
					method: 'DELETE',
				})
					.then((response) => {
						this.refreshPlayerList()
						this.setPlayerItem(null)
					})
					.catch((err) => {
						console.error('Error deleting player:', err)
						throw err
					})
			},
			/**
			 * Creates or updates a player
			 * @param {PlayerData} playerItem - The player data to save
			 * @throws {Error} When saving player fails
			 * @returns {Promise<void>}
			 */
			async savePlayer(playerItem) {
				if (!playerItem) {
					throw new Error('No player item to save')
				}

				console.log('Saving player...')

				const isNewPlayer = !playerItem.id
				const endpoint = isNewPlayer
					? '/index.php/apps/larpingapp/api/objects/player'
					: `/index.php/apps/larpingapp/api/objects/player/${playerItem.id}`
				const method = isNewPlayer ? 'POST' : 'PUT'

				const playerToSave = { ...playerItem }
				Object.keys(playerToSave).forEach(key => {
					if (playerToSave[key] === '' || (Array.isArray(playerToSave[key]) && playerToSave[key].length === 0)) {
						delete playerToSave[key]
					}
				})

				return fetch(
					endpoint,
					{
						method,
						headers: {
							'Content-Type': 'application/json',
						},
						body: JSON.stringify(playerToSave),
					},
				)
					.then((response) => response.json())
					.then((data) => {
						this.setPlayerItem(data)
						console.log('Player saved')
						return this.refreshPlayerList()
					})
					.catch((err) => {
						console.error('Error saving player:', err)
						throw err
					})
			},
		},
	},
)
