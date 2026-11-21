/**
 * SPDX-FileCopyrightText: 2026 Conduction / Larpinq Contributors
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Shared options of the tabs that show one object: the injected object
 * context, the `objectId` prop and the id the tab works on.
 */

/**
 * The injected object context and the `objectId` prop of an object tab.
 */
export const objectTabSource = {
	inject: {
		cnObjectContext: { default: () => ({}) },
	},

	props: {
		/**
		 * The object UUID. Falls back to the injected object context or the route.
		 */
		objectId: {
			type: String,
			default: '',
		},
	},
}

/**
 * An object tab whose id is the `objectId` prop, then the injected object
 * context, then the route id as a string.
 *
 * @param {string} name The computed property that holds the id.
 * @return {object} The mixin.
 *
 * @spec openspec/specs/character-custom-fields/spec.md
 * @spec openspec/specs/character-management/spec.md
 * @spec openspec/specs/event-registration/spec.md
 * @spec openspec/specs/events-players/spec.md
 */
export function objectTab(name) {
	return {
		mixins: [objectTabSource],

		computed: {
			[name]() {
				return (
					this.objectId
					|| this.cnObjectContext?.objectId
					|| String(this.$route?.params?.id ?? '')
				)
			},
		},
	}
}
