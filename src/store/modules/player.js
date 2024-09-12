/* eslint-disable no-console */
import { defineStore } from 'pinia'
import { Player } from '../../entities/index.js'

export const usePlayerStore = defineStore(
	'player', {
		state: () => ({
			playerItem: false,
			playerList: [],
		}),
		actions: {
			// Set the active player item
			setPlayerItem(playerItem) {
				this.playerItem = playerItem && new Player(playerItem)
				console.log('Active player item set to ' + playerItem)
			},
			// Set the list of players
			setPlayerList(playerList) {
				this.playerList = playerList.map(
					(playerItem) => new Player(playerItem),
				)
				console.log('Player list set to ' + playerList.length + ' items')
			},
			/* istanbul ignore next */ // ignore this for Jest until moved into a service
			async refreshPlayerList(search = null) {
				// @todo this might belong in a service?
				let endpoint = '/index.php/apps/larpingapp/api/players'
				if (search !== null && search !== '') {
					endpoint = endpoint + '?_search=' + search
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
				const endpoint = `/index.php/apps/larpingapp/api/players/${id}`
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
			// Delete a player by ID
			deletePlayer() {
				if (!this.playerItem || !this.playerItem.id) {
					throw new Error('No player to delete')
				}

				console.log('Deleting player...')

				const endpoint = `/index.php/apps/larpingapp/api/players/${this.playerItem.id}`

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
			// Create or update a player
			savePlayer(playerItem) {
				if (!playerItem) {
					throw new Error('No player to save')
				}

				console.log('Saving player...')

				const isNewPlayer = !playerItem.id
				const endpoint = isNewPlayer
					? '/index.php/apps/larpingapp/api/players'
					: `/index.php/apps/larpingapp/api/players/${playerItem.id}`
				const method = isNewPlayer ? 'POST' : 'PUT'

				return fetch(
					endpoint,
					{
						method,
						headers: {
							'Content-Type': 'application/json',
						},
						body: JSON.stringify(playerItem),
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
