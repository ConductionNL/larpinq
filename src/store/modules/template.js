/* eslint-disable no-console */
import { defineStore } from 'pinia'
import { Template } from '../../entities/index.js'

/**
 * Store for managing template data
 * @phpstan-type TemplateData {id: string, name: string, description: string, type: string, content: Object, ...}
 */
export const useTemplateStore = defineStore(
	'template', {
		state: () => ({
			/** @var {Template|false} Current active template */
			templateItem: false,
			/** @var {Array<Template>} List of all templates */
			templateList: [],
			/** @var {Array<Object>} Audit trail entries for current template */
			auditTrails: [],
			/** @var {Array<Object>} Relations for current template */
			relations: [],
			/** @var {Array<Object>} Uses of current template */
			uses: [],
			// Loading states
			/** @var {boolean} Whether template is being loaded */
			isLoadingTemplate: false,
			/** @var {boolean} Whether template list is being loaded */
			isLoadingTemplateList: false,
			/** @var {boolean} Whether audit trails are being loaded */
			isLoadingAuditTrails: false,
			/** @var {boolean} Whether relations are being loaded */
			isLoadingRelations: false,
			/** @var {boolean} Whether uses are being loaded */
			isLoadingUses: false,
			/** @var {string} Current search term for templates */
			searchTerm: '',
			/** @var {number|null} Debounce timer for search */
			searchDebounceTimer: null,
		}),
		actions: {
			/**
			 * Sets the active template item and loads its audit trails and relations
			 * @param {TemplateData|null} templateItem - The template item to set, or null to clear
			 * @throws {Error} When loading template data fails
			 * @returns {Promise<void>}
			 */
			async setTemplateItem(templateItem) {
				this.isLoadingTemplate = true
				try {
					this.templateItem = templateItem && new Template(templateItem)
					console.log('Active template item set to ' + templateItem)

					if (this.templateItem && this.templateItem.id) {
						await Promise.all([
							this.getAuditTrails(this.templateItem.id),
							this.getRelations(this.templateItem.id)
						])
					}
				} catch (err) {
					console.error('Error loading template data:', err)
				} finally {
					this.isLoadingTemplate = false
				}
			},
			/**
			 * Sets the list of templates
			 * @param {Array<TemplateData>} templateList - Array of template data
			 * @returns {void}
			 */
			setTemplateList(templateList) {
				this.templateList = templateList.map(
					(templateItem) => new Template(templateItem),
				)
				console.log('Template list set to ' + templateList.length + ' items')
			},
			/**
			 * Fetches and refreshes the list of templates
			 * @throws {Error} When fetching templates fails
			 * @returns {Promise<void>}
			 */
			async refreshTemplateList() {
				this.isLoadingTemplateList = true
				let endpoint = '/index.php/apps/larpingapp/api/objects/template'
				
				if (this.searchTerm) {
					endpoint += `${endpoint.includes('?') ? '&' : '?'}_search=${encodeURIComponent(this.searchTerm)}`
				}

				try {
					const response = await fetch(endpoint, { method: 'GET' })
					const data = await response.json()
					this.setTemplateList(data.results)
				} catch (err) {
					console.error('Error fetching template list:', err)
					throw err
				} finally {
					this.isLoadingTemplateList = false
				}
			},
			/**
			 * Deletes the current template
			 * @throws {Error} When no template is set or deletion fails
			 * @returns {Promise<void>}
			 */
			async deleteTemplate() {
				if (!this.templateItem || !this.templateItem.id) {
					throw new Error('No template to delete')
				}

				console.log('Deleting template...')

				const endpoint = `/index.php/apps/larpingapp/api/objects/template/${this.templateItem.id}`

				return fetch(endpoint, {
					method: 'DELETE',
				})
					.then((response) => {
						this.refreshTemplateList()
					})
					.catch((err) => {
						console.error('Error deleting template:', err)
						throw err
					})
			},
			/**
			 * Creates or updates a template
			 * @param {TemplateData} templateItem - The template data to save
			 * @throws {Error} When saving template fails
			 * @returns {Promise<void>}
			 */
			async saveTemplate(templateItem) {
				if (!templateItem) {
					throw new Error('No template to save')
				}

				console.log('Saving template...')

				const isNewTemplate = !templateItem?.id
				const endpoint = isNewTemplate
					? '/index.php/apps/larpingapp/api/objects/template'
					: `/index.php/apps/larpingapp/api/objects/template/${templateItem.id}`
				const method = isNewTemplate ? 'POST' : 'PUT'

				return fetch(
					endpoint,
					{
						method,
						headers: {
							'Content-Type': 'application/json',
						},
						body: JSON.stringify(templateItem),
					},
				)
					.then((response) => response.json())
					.then((data) => {
						this.setTemplateItem(data)
						console.log('Template saved')
						return this.refreshTemplateList()
					})
					.catch((err) => {
						console.error('Error saving template:', err)
						throw err
					})
			},
			/**
			 * Sets the audit trails for the current template
			 * @param {Array<Object>} auditTrails - The audit trails to set
			 * @returns {void}
			 */
			setAuditTrails(auditTrails) {
				this.auditTrails = auditTrails
				console.log('Audit trails set with ' + auditTrails.length + ' items')
			},
			/**
			 * Sets the relations for the current template
			 * @param {Array<Object>} relations - The relations to set
			 * @returns {void}
			 */
			setRelations(relations) {
				this.relations = relations
				console.log('Relations set with ' + relations.length + ' items')
			},
			/**
			 * Sets the uses for the current template
			 * @param {Array<Object>} uses - The uses to set
			 * @returns {void}
			 */
			setUses(uses) {
				this.uses = uses
				console.log('Uses set with ' + uses.length + ' items')
			},
			/**
			 * Fetches audit trails for a template
			 * @param {string} id - The template ID
			 * @throws {Error} When ID is missing or fetch fails
			 * @returns {Promise<Array<Object>>}
			 */
			async getAuditTrails(id) {
				this.isLoadingAuditTrails = true
				if (!id) {
					throw new Error('Template ID required to fetch audit trails')
				}

				try {
					const response = await fetch(`/index.php/apps/larpingapp/api/objects/template/${id}/audit`, {
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
			 * Fetches relations for a template
			 * @param {string} id - The template ID
			 * @throws {Error} When ID is missing or fetch fails
			 * @returns {Promise<Array<Object>>}
			 */
			async getRelations(id) {
				this.isLoadingRelations = true
				if (!id) {
					throw new Error('Template ID required to fetch relations')
				}

				try {
					const response = await fetch(`/index.php/apps/larpingapp/api/objects/template/${id}/relations`, {
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
			 * Fetches uses for a template
			 * @param {string} id - The template ID
			 * @throws {Error} When ID is missing or fetch fails
			 * @returns {Promise<Array<Object>>}
			 */
			async getUses(id) {
				this.isLoadingUses = true
				if (!id) {
					throw new Error('Template ID required to fetch uses')
				}

				try {
					const response = await fetch(`/index.php/apps/larpingapp/api/objects/template/${id}/uses`, {
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
					this.refreshTemplateList()
				}, 500)
			},
			/**
			 * Clears the search term and refreshes the list
			 * @returns {Promise<void>}
			 */
			async clearSearch() {
				this.searchTerm = ''
				await this.refreshTemplateList()
			},
		},
	},
)
