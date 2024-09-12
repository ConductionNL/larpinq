/* eslint-disable no-console */
import { defineStore } from 'pinia'
import { Skill } from '../../entities/index.js'

export const useSkillStore = defineStore(
	'skill', {
		state: () => ({
			skillItem: false,
			skillList: [],
		}),
		actions: {
			// Set the active skill item
			setSkillItem(skillItem) {
				this.skillItem = skillItem && new Skill(skillItem)
				console.log('Active skill item set to ' + skillItem)
			},
			// Set the list of skills
			setSkillList(skillList) {
				this.skillList = skillList.map(
					(skillItem) => new Skill(skillItem),
				)
				console.log('Skill list set to ' + skillList.length + ' items')
			},
			// Fetch and refresh the list of skills
			async refreshSkillList(search = null) {
				let endpoint = '/index.php/apps/larpingapp/api/skills'
				if (search !== null && search !== '') {
					endpoint = endpoint + '?_search=' + search
				}
				try {
					const response = await fetch(endpoint, { method: 'GET' })
					const data = await response.json()
					this.setSkillList(data.results)
				} catch (err) {
					console.error(err)
				}
			},
			// Fetch a single skill by ID
			async getSkill(id) {
				const endpoint = `/index.php/apps/larpingapp/api/skills/${id}`
				try {
					const response = await fetch(endpoint, { method: 'GET' })
					const data = await response.json()
					this.setSkillItem(data)
					return data
				} catch (err) {
					console.error(err)
					throw err
				}
			},
			// Delete a skill by ID
			deleteSkill() {
				if (!this.skillItem || !this.skillItem.id) {
					throw new Error('No skill to delete')
				}

				console.log('Deleting skill...')

				const endpoint = `/index.php/apps/larpingapp/api/skills/${this.skillItem.id}`

				return fetch(endpoint, {
					method: 'DELETE',
				})
					.then((response) => {
						this.refreshSkillList()
					})
					.catch((err) => {
						console.error('Error deleting skill:', err)
						throw err
					})
			},
			// Create or update a skill
			saveSkill(skillItem) {
				if (!skillItem) {
					throw new Error('No skill to save')
				}

				console.log('Saving skill...')

				const isNewSkill = !skillItem.id
				const endpoint = isNewSkill
					? '/index.php/apps/larpingapp/api/skills'
					: `/index.php/apps/larpingapp/api/skills/${skillItem.id}`
				const method = isNewSkill ? 'POST' : 'PUT'

				return fetch(
					endpoint,
					{
						method,
						headers: {
							'Content-Type': 'application/json',
						},
						body: JSON.stringify(skillItem),
					},
				)
					.then((response) => response.json())
					.then((data) => {
						this.setSkillItem(data)
						console.log('Skill saved')
						return this.refreshSkillList()
					})
					.catch((err) => {
						console.error('Error saving skill:', err)
						throw err
					})
			},
		},
	},
)
