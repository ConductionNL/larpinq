/* eslint-disable no-console */
import { defineStore } from 'pinia'
import { Player } from '../../entities/index.js'

export const usePlayerStore = defineStore(
	'player', {
		state: () => ({
			playerItem: false,
			playerList: [],
			auditTrails: [],
			relations: [],
			uses: []
		}),
		actions: {
			setPlayerItem(playerItem) {
				this.playerItem = playerItem && new Player(playerItem)
				console.log('Active player item set to ' + playerItem)
			},
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
			/* istanbul ignore next */ // ignore this for Jest until moved into a service
			async refreshPlayerList(search = null) {
				let endpoint = '/index.php/apps/larpingapp/api/objects/player'
				if (search !== null && search !== '') {
					endpoint = endpoint + '&_search=' + search
				}
				try {
					const response = await fetch(endpoint, { method: 'GET' })
					const data = await response.json()
					this.setPlayerList(data.results)
				} catch (err) {
					console.error(err)
				}
			},
			// Fetch a single player by ID
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
				}
			},
			// Get relations for a player
			async getRelations(id) {
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
				}
			},
			// Get uses for a player
			async getUses(id) {
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
				}
			},
			// Delete a player
			deletePlayer() {
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
			// Create or save a player
			savePlayer(playerItem) {
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
