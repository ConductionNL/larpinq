/* eslint-disable no-console */
import { defineStore } from 'pinia'
import { Event } from '../../entities/index.js'

export const useEventStore = defineStore(
	'event', {
		state: () => ({
			eventItem: false,
			eventList: [],
			auditTrails: [],
			relations: [],
			uses: []
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
				let endpoint = '/index.php/apps/larpingapp/api/objects/event?_extend=effects'
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
				const endpoint = `/index.php/apps/larpingapp/api/objects/event/${id}?_extend=effects`
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

				const endpoint = `/index.php/apps/larpingapp/api/objects/event/${this.eventItem.id}`

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
				if (!eventItem) {
					throw new Error('No event item to save')
				}

				console.log('Saving event...')

				const isNewEvent = !eventItem.id
				const endpoint = isNewEvent
					? '/index.php/apps/larpingapp/api/objects/event'
					: `/index.php/apps/larpingapp/api/objects/event/${eventItem.id}`
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
				const endpoint = `/index.php/apps/larpingapp/api/objects/event/${id}/audit`

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
				const endpoint = `/index.php/apps/larpingapp/api/objects/event/${id}/relations`

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
				const endpoint = `/index.php/apps/larpingapp/api/objects/event/${id}/uses`

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
