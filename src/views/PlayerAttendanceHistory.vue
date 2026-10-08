<!--
 SPDX-License-Identifier: EUPL-1.2
 SPDX-FileCopyrightText: 2026 Conduction B.V.

 Events attended tab on the player page (players-attendance-history): the
 events the player was checked in at, newest first, with the character and
 the date, and how many events that makes. Game masters see every player's;
 a player sees their own.

 @spec openspec/specs/events-players/spec.md
 @visual exclude Sidebar-tab section (not a routed page); covered by PlayerAttendanceControllerTest and tests/e2e/workflows/player-history.workflow.spec.ts.
-->
<template>
	<div class="player-attendance" data-testid="player-attendance">
		<p v-if="loading">
			{{ t('larpinq', 'Loading events attended') }}
		</p>
		<p v-else-if="history.state === 'refused'">
			{{
				t(
					'larpinq',
					'Only game masters and the player can see which events this player attended.',
				)
			}}
		</p>
		<p v-else-if="history.state === 'failed'" role="alert">
			{{ t('larpinq', 'Could not load the events attended.') }}
		</p>
		<template v-else>
			<p
				class="player-attendance__count"
				data-testid="player-attendance-count">
				{{
					t('larpinq', 'Events attended: {count}', {
						count: history.count,
					})
				}}
			</p>
			<p v-if="history.events.length === 0">
				{{ t('larpinq', 'No check-ins recorded yet.') }}
			</p>
			<ul v-else class="player-attendance__list">
				<li
					v-for="row in history.events"
					:key="row.id"
					data-testid="player-attendance-row">
					<router-link
						:to="{ name: 'EventDetail', params: { id: row.event.id } }">
						{{ row.event.name || t('larpinq', 'Unnamed event') }}
					</router-link>
					<span class="player-attendance__meta">
						{{ row.character.name }}
						<template v-if="formatEventDate(row.eventStartDate)">
							· {{ formatEventDate(row.eventStartDate) }}
						</template>
					</span>
				</li>
			</ul>
		</template>
	</div>
</template>

<script>
import { translate as t } from '@nextcloud/l10n'
import {
	fetchPlayerAttendance,
	formatEventDate,
} from '../services/playerAttendance.js'

export default {
	name: 'PlayerAttendanceHistory',

	inject: {
		cnObjectContext: { default: () => ({}) },
	},

	props: {
		/**
		 * The player UUID. Falls back to the injected object context or the route.
		 *
		 * @spec openspec/specs/events-players/spec.md
		 */
		objectId: {
			type: String,
			default: '',
		},
	},

	data() {
		return {
			loading: true,
			history: { state: 'ok', count: 0, events: [] },
		}
	},

	computed: {
		/**
		 * The player id from prop, injected context or route.
		 *
		 * @return {string} The id.
		 *
		 * @spec openspec/specs/events-players/spec.md
		 */
		playerId() {
			return (
				this.objectId
				|| this.cnObjectContext?.objectId
				|| String(this.$route?.params?.id ?? '')
			)
		},
	},

	/**
	 * Load the history.
	 *
	 * @return {Promise<void>}
	 *
	 * @spec openspec/specs/events-players/spec.md
	 */
	async mounted() {
		this.history = await fetchPlayerAttendance(this.playerId)
		this.loading = false
	},

	methods: {
		t,
		formatEventDate,
	},
}
</script>

<style scoped>
.player-attendance__list {
	list-style: none;
	margin: 0;
	padding: 0;
}

.player-attendance__list li {
	display: flex;
	flex-direction: column;
	padding: calc(var(--default-grid-baseline, 4px) * 2) 0;
	border-bottom: 1px solid var(--color-border);
}

.player-attendance__meta {
	color: var(--color-text-maxcontrast);
}
</style>
