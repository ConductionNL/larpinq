/* eslint-disable no-console */
import { defineStore } from 'pinia'
import { Event } from '../../entities/index.js'

export const useEventStore = defineStore(
	'event', {
		state: () => ({
			eventItem: false,
			eventList: [],
		}),
		actions: {
			// Set the active event item
			setEventItem(eventItem) {
				this.eventItem = eventItem && new Event(eventItem)
				console.log('Active event item set to ' + eventItem)
			},
			// Set the list of events
			setEventList(eventList) {
				this.eventList = eventList.map(
					(eventItem) => new Event(eventItem),
				)
				console.log('Event list set to ' + eventList.length + ' items')
			},
			// Fetch and refresh the list of events
			async refreshEventList(search = null) {
				let endpoint = '/index.php/apps/larpingapp/api/events'
				if (search !== null && search !== '') {
					endpoint = endpoint + '?_search=' + search
				}
				try {
					const response = await fetch(endpoint, { method: 'GET' })
					const data = await response.json()
					this.setEventList(data.results)
				} catch (err) {
					console.error(err)
				}
			},
			// Fetch a single event by ID
			async getEvent(id) {
				const endpoint = `/index.php/apps/larpingapp/api/events/${id}`
				try {
					const response = await fetch(endpoint, { method: 'GET' })
					const data = await response.json()
					this.setEventItem(data)
					return data
				} catch (err) {
					console.error(err)
					throw err
				}
			},
			// Delete an event by ID
			deleteEvent() {
				if (!this.eventItem || !this.eventItem.id) {
					throw new Error('No event item to delete')
				}

				console.log('Deleting event...')

				const endpoint = `/index.php/apps/larpingapp/api/events/${this.eventItem.id}`

				return fetch(endpoint, {
					method: 'DELETE',
				})
					.then((response) => {
						this.refreshEventList()
					})
					.catch((err) => {
						console.error('Error deleting event:', err)
						throw err
					})
			},
			// Create or update an event
			saveEvent(eventItem) {
				if (!this.eventItem) {
					throw new Error('No event item to save')
				}

				console.log('Saving event...')

				const isNewEvent = !this.eventItem.id
				const endpoint = isNewEvent
					? '/index.php/apps/larpingapp/api/events'
					: `/index.php/apps/larpingapp/api/events/${this.eventItem.id}`
				const method = isNewEvent ? 'POST' : 'PUT'

				const eventToSeave = { ...eventItem }
				Object.keys(eventToSeave).forEach(key => {
					if (eventToSeave[key] === '' || (Array.isArray(eventToSeave[key]) && eventToSeave[key].length === 0)) {
						delete eventToSeave[key]
					}
				})

				return fetch(
					endpoint,
					{
						method,
						headers: {
							'Content-Type': 'application/json',
						},
						body: JSON.stringify(eventToSeave),
					},
				)
					.then((response) => response.json())
					.then((data) => {
						this.setEventItem(data)
						console.log('Event saved')
						return this.refreshEventList()
					})
					.catch((err) => {
						console.error('Error saving event:', err)
						throw err
					})
			},
		},
	},
)
