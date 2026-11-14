<!--
 SPDX-License-Identifier: EUPL-1.2
 SPDX-FileCopyrightText: 2026 Conduction B.V.

 The page a staff invite link opens (admin-staff-roles-and-invites): it names
 the role and asks the signed-in user to confirm before they join it. An
 expired, used-up or revoked link says so and joins nothing.

 @spec openspec/changes/admin-staff-roles-and-invites/specs/staff-roles/spec.md#requirement-a-game-master-invites-with-a-link-req-asr-004
-->
<template>
	<div class="staff-invite" data-testid="staff-invite">
		<h2>{{ t('larpinq', 'Join the team') }}</h2>
		<NcLoadingIcon v-if="loading" :size="44" />
		<template v-else-if="state === 'valid'">
			<p data-testid="staff-invite-role">
				{{ t('larpinq', 'You are invited to join as {role}.', { role: roleLabel(role) }) }}
			</p>
			<NcButton
				variant="primary"
				:disabled="busy"
				data-testid="staff-invite-confirm"
				@click="confirm">
				{{ t('larpinq', 'Join as {role}', { role: roleLabel(role) }) }}
			</NcButton>
		</template>
		<NcNoteCard
			v-else-if="state === 'joined' || state === 'member'"
			type="success"
			data-testid="staff-invite-done">
			{{ state === 'joined'
				? t('larpinq', 'You joined as {role}.', { role: roleLabel(role) })
				: t('larpinq', 'You already have the role {role}.', { role: roleLabel(role) }) }}
		</NcNoteCard>
		<NcNoteCard v-else type="warning" data-testid="staff-invite-refused">
			{{ refusal }}
		</NcNoteCard>
	</div>
</template>

<script>
import { translate as t } from '@nextcloud/l10n'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import { inspectInvite, redeemInvite, roleLabel } from '../services/staffInvites.js'

export default {
	name: 'StaffInviteLanding',
	components: { NcButton, NcLoadingIcon, NcNoteCard },

	data() {
		return {
			loading: true,
			busy: false,
			state: '',
			role: '',
		}
	},

	computed: {
		/**
		 * Why the link adds nobody.
		 *
		 * @return {string}
		 */
		refusal() {
			switch (this.state) {
			case 'expired':
				return t('larpinq', 'This invite has expired. Ask a game master for a new one.')
			case 'used-up':
				return t('larpinq', 'This invite is used up. Ask a game master for a new one.')
			case 'revoked':
				return t('larpinq', 'This invite was revoked.')
			case 'unknown':
				return t('larpinq', 'This invite link is not valid.')
			default:
				return t('larpinq', 'The invite could not be read. Try again later.')
			}
		},
	},

	/**
	 * Read the invite the link opens.
	 *
	 * @return {Promise<void>}
	 */
	async mounted() {
		const invite = await inspectInvite(String(this.$route?.params?.token || ''))
		this.state = invite.state
		this.role = invite.role || ''
		this.loading = false
	},

	methods: {
		t,
		roleLabel,

		/**
		 * Join the role.
		 *
		 * @return {Promise<void>}
		 */
		async confirm() {
			this.busy = true
			const answer = await redeemInvite(String(this.$route?.params?.token || ''))
			this.state = answer.state
			this.role = answer.role || this.role
			this.busy = false
		},
	},
}
</script>

<style scoped>
.staff-invite {
	max-width: 640px;
	padding: calc(var(--default-grid-baseline, 4px) * 4);
}
</style>
