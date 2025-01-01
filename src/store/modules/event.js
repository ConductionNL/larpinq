/* eslint-disable no-console */
import { defineStore } from 'pinia'
import { Event } from '../../entities/index.js'

/**
 * Store for managing event data
 * @phpstan-type EventData {id: string, name: string, description: string, date: string, location: string, characters: Array<string>, ...}
 */
export const useEventStore = defineStore(
	'event', {
		state: () => ({
			/** @var {Event|false} Current active event */
			eventItem: false,
			/** @var {Array<Event>} List of all events */
			eventList: [],
			/** @var {Array<Object>} Audit trail entries for current event */
			auditTrails: [],
			/** @var {Array<Object>} Relations for current event */
			relations: [],
			/** @var {Array<Object>} Uses of current event */
			uses: [],
			// Loading states
			/** @var {boolean} Whether event is being loaded */
			isLoadingEvent: false,
			/** @var {boolean} Whether event list is being loaded */
			isLoadingEventList: false,
			/** @var {boolean} Whether audit trails are being loaded */
			isLoadingAuditTrails: false,
			/** @var {boolean} Whether relations are being loaded */
			isLoadingRelations: false,
			/** @var {boolean} Whether uses are being loaded */
			isLoadingUses: false,
			/** @var {string} Current search term for events */
			searchTerm: '',
			/** @var {number|null} Debounce timer for search */
			searchDebounceTimer: null,
		}),
		actions: {
			/**
			 * Sets the active event item and loads its audit trails and relations
			 * @param {EventData|null} eventItem - The event item to set, or null to clear
			 * @throws {Error} When loading event data fails
			 * @returns {Promise<void>}
			 */
			async setEventItem(eventItem) {
				// Set the event item first
				this.eventItem = eventItem && new Event(eventItem)
				console.log('Active event item set to ' + eventItem)

				// If we have an event item, load its audit trails and relations
				if (this.eventItem && this.eventItem.id) {
					try {
						// Load audit trails and relations in parallel
						await Promise.all([
							this.getAuditTrails(this.eventItem.id),
							this.getRelations(this.eventItem.id)
						])
					} catch (err) {
						console.error('Error loading event data:', err)
					}
				}
			},
			/**
			 * Sets the list of events
			 * @param {Array<EventData>} eventList - Array of event data
			 * @returns {void}
			 */
			setEventList(eventList) {
				this.eventList = eventList.map(
					(eventItem) => new Event(eventItem),
				)
				console.log('Event list set to ' + eventList.length + ' items')
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
					this.refreshEventList()
				}, 500)
			},
			/**
			 * Clears the search term and refreshes the list
			 * @returns {Promise<void>}
			 */
			async clearSearch() {
				this.searchTerm = ''
				await this.refreshEventList()
			},
			/**
			 * Fetches and refreshes the list of events
			 * @param {string|null} search - Optional search term
			 * @throws {Error} When fetching events fails
			 * @returns {Promise<void>}
			 */
			async refreshEventList(search = null) {
				this.isLoadingEventList = true
				let endpoint = '/index.php/apps/larpingapp/api/objects/event'
				
				if (this.searchTerm) {
					endpoint += `${endpoint.includes('?') ? '&' : '?'}_search=${encodeURIComponent(this.searchTerm)}`
				}

				try {
					const response = await fetch(endpoint, { method: 'GET' })
					const data = await response.json()
					this.setEventList(data.results)
				} catch (err) {
					console.error('Error fetching event list:', err)
					throw err
				} finally {
					this.isLoadingEventList = false
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
					? '/index.php/apps/larpingapp/api/objects/event?_extend=effects'
					: `/index.php/apps/larpingapp/api/objects/event/${eventItem.id}?_extend=effects`
				const method = isNewEvent ? 'POST' : 'PUT'

				// Create a copy of the event to avoid modifying the original
				const eventToSave = { ...eventItem }

				// Initialize effects array if not set
				eventToSave.effects = eventToSave.effects || []

				// Transform effects array to array of UUIDs
				eventToSave.effects = eventToSave.effects.map(effect => 
					typeof effect === 'object' ? effect.id : effect
				)

				return fetch(
					endpoint,
					{
						method,
						headers: {
							'Content-Type': 'application/json',
						},
						body: JSON.stringify(eventToSave),
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
