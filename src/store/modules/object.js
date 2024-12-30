/* eslint-disable no-console */
import { defineStore } from 'pinia'
import { AuditTrail } from '../../entities/index.js'

export const useObjectStore = defineStore('object', {
	state: () => ({
		auditTrailItem: false,
	}),
	actions: {
		setAuditTrailItem(auditTrailItem) {
			this.auditTrailItem = auditTrailItem && new AuditTrail(auditTrailItem)
		},
		setAuditTrails(auditTrails) {
			this.auditTrails = auditTrails.map(
				(auditTrail) => new AuditTrail(auditTrail),
			)
		},
	}
})
