/* eslint-disable no-console */
import { defineStore } from 'pinia'
import { Template } from '../../entities/index.js'

export const useTemplateStore = defineStore(
	'template', {
		state: () => ({
			templateItem: false,
			templateList: [],
		}),
		actions: {
			// Set the active template item
			setTemplateItem(templateItem) {
				this.templateItem = templateItem && new Template(templateItem)
				console.log('Active template item set to ' + templateItem)
			},
			// Set the list of templates
			setTemplateList(templateList) {
				this.templateList = templateList.map(
					(templateItem) => new Template(templateItem),
				)
				console.log('Template list set to ' + templateList.length + ' items')
			},
			// Fetch and refresh the list of templates
			async refreshTemplateList(search = null) {
				let endpoint = '/index.php/apps/larpingapp/api/templates'
				if (search !== null && search !== '') {
					endpoint = endpoint + '?_search=' + search
				}
				try {
					const response = await fetch(endpoint, { method: 'GET' })
					const data = await response.json()
					this.setTemplateList(data.results)
				} catch (err) {
					console.error(err)
				}
			},
			// Fetch a single template by ID
			async getTemplate(id) {
				const endpoint = `/index.php/apps/larpingapp/api/templates/${id}`
				try {
					const response = await fetch(endpoint, { method: 'GET' })
					const data = await response.json()
					this.setTemplateItem(data)
					return data
				} catch (err) {
					console.error(err)
					throw err
				}
			},
			// Delete a template by ID
			deleteTemplate() {
				if (!this.templateItem || !this.templateItem.id) {
					throw new Error('No template to delete')
				}

				console.log('Deleting template...')

				const endpoint = `/index.php/apps/larpingapp/api/templates/${this.templateItem.id}`

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
			// Create or update a template
			saveTemplate() {
				if (!this.templateItem) {
					throw new Error('No template to save')
				}

				console.log('Saving template...')

				const isNewTemplate = !this.templateItem.id
				const endpoint = isNewTemplate
					? '/index.php/apps/larpingapp/api/templates'
					: `/index.php/apps/larpingapp/api/templates/${this.templateItem.id}`
				const method = isNewTemplate ? 'POST' : 'PUT'

				return fetch(
					endpoint,
					{
						method: method,
						headers: {
							'Content-Type': 'application/json',
						},
						body: JSON.stringify(this.templateItem),
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
		},
	},
)