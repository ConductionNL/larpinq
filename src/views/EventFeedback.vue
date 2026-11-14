<!--
 SPDX-License-Identifier: EUPL-1.2
 SPDX-FileCopyrightText: 2026 Conduction B.V.

 Feedback tab on the event page (events-post-event-survey), for game masters:
 how many invited participants answered the feedback form, who has not, and
 the link to the answers in Nextcloud Forms.

 @spec openspec/changes/events-post-event-survey/specs/event-feedback/spec.md#requirement-game-masters-see-who-answered-req-eps-004
 @visual exclude Sidebar-tab section (not a routed page); covered by EventFeedbackControllerTest and tests/vitest/eventFeedback.spec.js.
-->
<template>
	<div class="event-feedback" data-testid="event-feedback">
		<p v-if="loading">
			{{ t('larpinq', 'Loading feedback') }}
		</p>
		<p v-else-if="feedback.state === 'refused'">
			{{ t('larpinq', 'Only game masters can see who answered the feedback form.') }}
		</p>
		<p v-else-if="feedback.state === 'failed'" role="alert">
			{{ t('larpinq', 'Could not load the feedback.') }}
		</p>
		<p v-else-if="!feedback.summary.formId">
			{{ t('larpinq', 'This event has no feedback form.') }}
		</p>
		<template v-else>
			<p data-testid="event-feedback-window">
				{{ t('larpinq', 'Feedback opens {opens} and closes {closes}.', { opens: day(feedback.summary.opensAt), closes: day(feedback.summary.closesAt) }) }}
			</p>
			<p v-if="feedback.summary.answered === null" data-testid="event-feedback-count">
				{{ t('larpinq', 'Invited: {count}', { count: feedback.summary.invited }) }}
			</p>
			<p v-else data-testid="event-feedback-count">
				{{ t('larpinq', '{answered} of {invited} answered', { answered: feedback.summary.answered, invited: feedback.summary.invited }) }}
			</p>
			<template v-if="feedback.summary.notAnswered && feedback.summary.notAnswered.length > 0">
				<h4>{{ t('larpinq', 'Not answered yet') }}</h4>
				<ul class="event-feedback__list">
					<li v-for="row in feedback.summary.notAnswered" :key="row.uid" data-testid="event-feedback-missing">
						{{ row.character || row.uid }}
						<span class="event-feedback__meta">{{ row.uid }}</span>
					</li>
				</ul>
			</template>
			<a
				v-if="feedback.summary.resultsLink"
				:href="feedback.summary.resultsLink"
				target="_blank"
				rel="noopener noreferrer"
				data-testid="event-feedback-results">
				{{ t('larpinq', 'Open the answers in Forms') }}
			</a>
		</template>
	</div>
</template>

<script>
import { translate as t } from '@nextcloud/l10n'
import { objectTab } from '../mixins/objectTab.js'
import { fetchEventFeedback } from '../services/eventFeedback.js'
import { formatEventDate } from '../services/playerAttendance.js'

export default {
	name: 'EventFeedback',

	mixins: [objectTab('eventId')],

	data() {
		return {
			loading: true,
			feedback: { state: 'ok', summary: { formId: 0 } },
		}
	},

	/**
	 * Load the summary.
	 *
	 * @return {Promise<void>}
	 */
	async mounted() {
		this.feedback = await fetchEventFeedback(this.eventId)
		this.loading = false
	},

	methods: {
		t,

		/**
		 * A moment as a day in the user's locale, or a dash.
		 *
		 * @param {string} value An ISO date-time.
		 * @return {string}
		 */
		day(value) {
			return formatEventDate(value) || '–'
		},
	},
}
</script>

<style scoped>
.event-feedback__list {
	list-style: none;
	margin: 0;
	padding: 0;
}

.event-feedback__list li {
	display: flex;
	flex-direction: column;
	padding: calc(var(--default-grid-baseline, 4px) * 2) 0;
	border-bottom: 1px solid var(--color-border);
	overflow-wrap: anywhere;
}

.event-feedback__meta {
	color: var(--color-text-maxcontrast);
}
</style>
