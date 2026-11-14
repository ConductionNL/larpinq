<!--
 SPDX-License-Identifier: EUPL-1.2
 SPDX-FileCopyrightText: 2026 Conduction B.V.

 The Staff page (admin-staff-roles-and-invites), for game masters: the members
 of each role with a remove action, the invites with their state and a revoke
 action, and a form that creates an invite link and shows it once.

 @spec openspec/changes/admin-staff-roles-and-invites/specs/staff-roles/spec.md#requirement-game-masters-see-and-manage-the-team-req-asr-005
-->
<template>
	<div class="staff-page" data-testid="staff-page">
		<h2>{{ t('larpinq', 'Staff') }}</h2>
		<NcLoadingIcon v-if="loading" :size="44" />
		<NcNoteCard v-else-if="team.state === 'refused'" type="warning">
			{{ t('larpinq', 'Only game masters manage the staff.') }}
		</NcNoteCard>
		<NcNoteCard v-else-if="team.state === 'failed'" type="error">
			{{ t('larpinq', 'Could not load the staff.') }}
		</NcNoteCard>
		<template v-else>
			<NcNoteCard v-if="message" :type="messageType" data-testid="staff-message">
				{{ message }}
			</NcNoteCard>

			<section class="staff-page__section">
				<h3>{{ t('larpinq', 'Invite someone') }}</h3>
				<form class="staff-page__form" @submit.prevent="create">
					<NcSelect
						v-model="form.role"
						:options="roleOptions"
						:reduce="option => option.id"
						label="label"
						:inputLabel="t('larpinq', 'Role')"
						:clearable="false" />
					<NcTextField
						v-model="form.days"
						type="number"
						min="1"
						max="90"
						:label="t('larpinq', 'Valid for (days)')" />
					<NcTextField
						v-model="form.maxUses"
						type="number"
						min="1"
						max="100"
						:label="t('larpinq', 'Uses')" />
					<NcButton variant="primary" type="submit" :disabled="busy" data-testid="staff-create">
						{{ t('larpinq', 'Create invite link') }}
					</NcButton>
				</form>
				<div v-if="link" class="staff-page__link" data-testid="staff-link">
					<p>{{ t('larpinq', 'Copy this link now; it is shown only once.') }}</p>
					<NcTextField :modelValue="link" :label="t('larpinq', 'Invite link')" readonly />
				</div>
			</section>

			<section class="staff-page__section">
				<h3>{{ t('larpinq', 'Members') }}</h3>
				<div v-for="group in team.roles" :key="group.role" class="staff-page__role">
					<h4>{{ roleLabel(group.role) }}</h4>
					<p v-if="group.members.length === 0" class="staff-page__empty">
						{{ t('larpinq', 'Nobody has this role yet.') }}
					</p>
					<ul v-else>
						<li v-for="member in group.members" :key="member.uid" data-testid="staff-member">
							<span>{{ member.displayName || member.uid }}</span>
							<NcButton
								variant="tertiary"
								:disabled="busy"
								:aria-label="t('larpinq', 'Remove {name} from {role}', { name: member.displayName || member.uid, role: roleLabel(group.role) })"
								@click="remove(group.role, member)">
								{{ t('larpinq', 'Remove') }}
							</NcButton>
						</li>
					</ul>
				</div>
			</section>

			<section class="staff-page__section">
				<h3>{{ t('larpinq', 'Invites') }}</h3>
				<p v-if="team.invites.length === 0" class="staff-page__empty">
					{{ t('larpinq', 'No invites yet.') }}
				</p>
				<ul v-else>
					<li v-for="invite in team.invites" :key="invite.id" data-testid="staff-invite-row">
						<span>
							{{ roleLabel(invite.role) }} · {{ stateLabel(invite.state) }}
							· {{ t('larpinq', 'Joined: {names}', { names: joinedNames(invite) }) }}
						</span>
						<NcButton
							v-if="invite.state === 'valid'"
							variant="tertiary"
							:disabled="busy"
							@click="revoke(invite)">
							{{ t('larpinq', 'Revoke') }}
						</NcButton>
					</li>
				</ul>
			</section>
		</template>
	</div>
</template>

<script>
import { translate as t } from '@nextcloud/l10n'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import {
	createInvite,
	fetchTeam,
	removeMember,
	revokeInvite,
	roleLabel,
	STAFF_ROLES,
	stateLabel,
} from '../services/staffInvites.js'

export default {
	name: 'StaffPage',
	components: { NcButton, NcLoadingIcon, NcNoteCard, NcSelect, NcTextField },

	data() {
		return {
			loading: true,
			busy: false,
			team: { state: 'ok', roles: [], invites: [] },
			form: { role: 'larpinq-stewards', days: '7', maxUses: '1' },
			link: '',
			message: '',
			messageType: 'success',
		}
	},

	computed: {
		/**
		 * The roles to pick from.
		 *
		 * @return {Array<{id: string, label: string}>}
		 */
		roleOptions() {
			return STAFF_ROLES.map((id) => ({ id, label: roleLabel(id) }))
		},
	},

	/**
	 * Load the team.
	 *
	 * @return {Promise<void>}
	 */
	async mounted() {
		await this.reload()
		this.loading = false
	},

	methods: {
		t,
		roleLabel,
		stateLabel,

		/**
		 * Read the team again.
		 *
		 * @return {Promise<void>}
		 */
		async reload() {
			this.team = await fetchTeam()
		},

		/**
		 * Who joined with an invite.
		 *
		 * @param {object} invite The invite.
		 * @return {string} The uids, or a dash.
		 */
		joinedNames(invite) {
			const uses = Array.isArray(invite.uses) ? invite.uses : []
			return uses.length === 0 ? '–' : uses.map((use) => use.uid).join(', ')
		},

		/**
		 * Create an invite link and show it once.
		 *
		 * @return {Promise<void>}
		 */
		async create() {
			this.busy = true
			const answer = await createInvite(this.form.role, Number(this.form.days), Number(this.form.maxUses))
			if (answer.ok) {
				this.link = answer.link
				this.say('', 'success')
				await this.reload()
			} else {
				this.link = ''
				this.say(answer.error, 'error')
			}
			this.busy = false
		},

		/**
		 * Revoke an invite.
		 *
		 * @param {object} invite The invite.
		 * @return {Promise<void>}
		 */
		async revoke(invite) {
			this.busy = true
			if (await revokeInvite(invite.id)) {
				this.say('', 'success')
				await this.reload()
			} else {
				this.say(t('larpinq', 'Failed to save. Please try again.'), 'error')
			}
			this.busy = false
		},

		/**
		 * Remove a member from a role.
		 *
		 * @param {string} role The role group.
		 * @param {{uid: string, displayName: string}} member The member.
		 * @return {Promise<void>}
		 */
		async remove(role, member) {
			this.busy = true
			const answer = await removeMember(role, member.uid)
			if (answer.ok) {
				this.say('', 'success')
				await this.reload()
			} else {
				this.say(answer.error, 'error')
			}
			this.busy = false
		},

		/**
		 * Show a message above the page.
		 *
		 * @param {string} text The message.
		 * @param {string} type success or error.
		 */
		say(text, type) {
			this.message = text
			this.messageType = type
		},
	},
}
</script>

<style scoped>
.staff-page {
	padding: calc(var(--default-grid-baseline, 4px) * 4);
	max-width: 960px;
}

.staff-page__section {
	margin-block: calc(var(--default-grid-baseline, 4px) * 6);
}

.staff-page__form {
	display: flex;
	flex-wrap: wrap;
	gap: calc(var(--default-grid-baseline, 4px) * 2);
	align-items: flex-end;
}

.staff-page ul {
	list-style: none;
	margin: 0;
	padding: 0;
}

.staff-page li {
	display: flex;
	justify-content: space-between;
	align-items: center;
	gap: calc(var(--default-grid-baseline, 4px) * 2);
	padding: calc(var(--default-grid-baseline, 4px) * 2) 0;
	border-bottom: 1px solid var(--color-border);
	overflow-wrap: anywhere;
}

.staff-page__empty {
	color: var(--color-text-maxcontrast);
}
</style>
