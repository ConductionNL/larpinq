/* eslint-disable no-console */
import { defineStore } from 'pinia'
import { Skill } from '../../entities/index.js'

export const useSkillStore = defineStore(
	'skill', {
		state: () => ({
			skillItem: false,
			skillList: [],
			auditTrails: [],
			relations: [],
			uses: []
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
				let endpoint = '/index.php/apps/larpingapp/api/objects/skill?_extend=effects'
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
				const endpoint = `/index.php/apps/larpingapp/api/objects/skill/${id}?_extend=effects`
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

				const endpoint = `/index.php/apps/larpingapp/api/objects/skill/${this.skillItem.id}`

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
					? '/index.php/apps/larpingapp/api/objects/skill?_extend[]=effects'
					: `/index.php/apps/larpingapp/api/objects/skill/${skillItem.id}?_extend[]=effects`
				const method = isNewSkill ? 'POST' : 'PUT'

				// Create a copy of the skill to avoid modifying the original
				const skillToSave = { ...skillItem }

				// Transform effects array to array of UUIDs if needed
				if (skillToSave.effects) {
					skillToSave.effects = skillToSave.effects.map(effect => 
						typeof effect === 'object' ? effect.id : effect
					)
				}

				return fetch(
					endpoint,
					{
						method,
						headers: {
							'Content-Type': 'application/json',
						},
						body: JSON.stringify(skillToSave),
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
				const endpoint = `/index.php/apps/larpingapp/api/objects/skill/${id}/audit`

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
				const endpoint = `/index.php/apps/larpingapp/api/objects/skill/${id}/relations`

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
				const endpoint = `/index.php/apps/larpingapp/api/objects/skill/${id}/uses`

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
