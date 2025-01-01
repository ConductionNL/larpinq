/* eslint-disable no-console */
import { defineStore } from 'pinia'
import { Skill } from '../../entities/index.js'

/**
 * Store for managing skill data
 * @phpstan-type SkillData {id: string, name: string, description: string, level: number, effects: Array<string>, prerequisites: Array<string>, ...}
 */
export const useSkillStore = defineStore(
	'skill', {
		state: () => ({
			/** @var {Skill|false} Current active skill */
			skillItem: false,
			/** @var {Array<Skill>} List of all skills */
			skillList: [],
			/** @var {Array<Object>} Audit trail entries for current skill */
			auditTrails: [],
			/** @var {Array<Object>} Relations for current skill */
			relations: [],
			/** @var {Array<Object>} Uses of current skill */
			uses: [],
			// Loading states
			/** @var {boolean} Whether skill is being loaded */
			isLoadingSkill: false,
			/** @var {boolean} Whether skill list is being loaded */
			isLoadingSkillList: false,
			/** @var {boolean} Whether audit trails are being loaded */
			isLoadingAuditTrails: false,
			/** @var {boolean} Whether relations are being loaded */
			isLoadingRelations: false,
			/** @var {boolean} Whether uses are being loaded */
			isLoadingUses: false,
			/** @var {string} Current search term for skills */
			searchTerm: '',
			/** @var {number|null} Debounce timer for search */
			searchDebounceTimer: null,
		}),
		actions: {
			/**
			 * Sets the active skill item and loads its audit trails and relations
			 * @param {SkillData|null} skillItem - The skill item to set, or null to clear
			 * @throws {Error} When loading skill data fails
			 * @returns {Promise<void>}
			 */
			async setSkillItem(skillItem) {
				this.isLoadingSkill = true
				try {
					this.skillItem = skillItem && new Skill(skillItem)
					console.log('Active skill item set to ' + skillItem)

					if (this.skillItem && this.skillItem.id) {
						await Promise.all([
							this.getAuditTrails(this.skillItem.id),
							this.getRelations(this.skillItem.id)
						])
					}
				} catch (err) {
					console.error('Error loading skill data:', err)
				} finally {
					this.isLoadingSkill = false
				}
			},
			/**
			 * Sets the list of skills
			 * @param {Array<SkillData>} skillList - Array of skill data
			 * @returns {void}
			 */
			setSkillList(skillList) {
				this.skillList = skillList.map(
					(skillItem) => new Skill(skillItem),
				)
				console.log('Skill list set to ' + skillList.length + ' items')
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
					this.refreshSkillList()
				}, 500)
			},
			/**
			 * Clears the search term and refreshes the list
			 * @returns {Promise<void>}
			 */
			async clearSearch() {
				this.searchTerm = ''
				await this.refreshSkillList()
			},
			/**
			 * Fetches and refreshes the list of skills
			 * @param {string|null} search - Optional search term
			 * @throws {Error} When fetching skills fails
			 * @returns {Promise<void>}
			 */
			async refreshSkillList(search = null) {
				this.isLoadingSkillList = true
				let endpoint = '/index.php/apps/larpingapp/api/objects/skill'
				
				if (this.searchTerm) {
					endpoint += `${endpoint.includes('?') ? '&' : '?'}_search=${encodeURIComponent(this.searchTerm)}`
				}

				try {
					const response = await fetch(endpoint, { method: 'GET' })
					const data = await response.json()
					this.setSkillList(data.results)
				} catch (err) {
					console.error('Error fetching skill list:', err)
					throw err
				} finally {
					this.isLoadingSkillList = false
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
				this.isLoadingAuditTrails = true
				if (!id) {
					throw new Error('Skill ID required to fetch audit trails')
				}

				try {
					const response = await fetch(`/index.php/apps/larpingapp/api/objects/skill/${id}/audit`, {
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
			async getRelations(id) {
				this.isLoadingRelations = true
				if (!id) {
					throw new Error('Skill ID required to fetch relations')
				}

				try {
					const response = await fetch(`/index.php/apps/larpingapp/api/objects/skill/${id}/relations`, {
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
			async getUses(id) {
				this.isLoadingUses = true
				if (!id) {
					throw new Error('Skill ID required to fetch uses')
				}

				try {
					const response = await fetch(`/index.php/apps/larpingapp/api/objects/skill/${id}/uses`, {
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
			}
		},
	},
)
