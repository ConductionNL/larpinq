<!--
  SPDX-FileCopyrightText: 2026 Conduction B.V.
  SPDX-License-Identifier: EUPL-1.2

  Buy an item for a character with its in-game money (REQ-RPG-001). Opened by
  the CharacterDetail header action "Buy item" (open-modal). The server adds
  the item and takes the money in one write and refuses what the character
  cannot pay; this dialog shows the purse, offers the priced items of the
  character's world, and reports the outcome.
-->
<template>
	<NcDialog
		:name="t('larpinq', 'Buy item')"
		data-testid="buy-item-dialog"
		@closing="$emit('close')">
		<p v-if="status === 'loading'">
			{{ t('larpinq', 'Loading the items for sale') }}
		</p>
		<NcNoteCard
			v-else-if="status === 'failed'"
			type="error"
			data-testid="buy-item-load-failed">
			{{ t('larpinq', 'Could not read the character.') }}
		</NcNoteCard>
		<template v-else>
			<p data-testid="buy-item-purse">
				{{ t('larpinq', '{character} has {money}.', { character: characterName, money: moneyText(purse) }) }}
			</p>
			<NcNoteCard
				v-if="bought"
				type="success"
				data-testid="buy-item-done">
				{{ t('larpinq', '{character} now carries {item}.', { character: characterName, item: bought }) }}
			</NcNoteCard>
			<p v-if="items.length === 0" data-testid="buy-item-none">
				{{ t('larpinq', 'No item of this world is for sale.') }}
			</p>
			<template v-else>
				<NcSelect
					v-model="selected"
					data-testid="buy-item-select"
					:inputLabel="t('larpinq', 'Item')"
					:options="options"
					label="label"
					:clearable="false" />
				<p v-if="selected && !affordable" data-testid="buy-item-short" role="alert">
					{{ t('larpinq', '{character} cannot pay {price}.', { character: characterName, price: moneyText(selected.price) }) }}
				</p>
			</template>
			<NcNoteCard
				v-if="refusal"
				type="error"
				data-testid="buy-item-refused">
				{{ refusal }}
			</NcNoteCard>
		</template>

		<template #actions>
			<NcButton data-testid="buy-item-cancel" @click="$emit('close')">
				{{ bought ? t('larpinq', 'Close') : t('larpinq', 'Cancel') }}
			</NcButton>
			<NcButton
				data-testid="buy-item-buy"
				variant="primary"
				:disabled="!buyable"
				@click="buy">
				{{ t('larpinq', 'Buy') }}
			</NcButton>
		</template>
	</NcDialog>
</template>

<script>
import { translate as t } from '@nextcloud/l10n'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import { firstId } from '../services/characterBuilds.js'
import { buyItem, canPay, COINS, fetchPurchaseOptions, purseOf } from '../services/itemPurchase.js'

export default {
	name: 'BuyItemDialog',

	components: { NcButton, NcDialog, NcNoteCard, NcSelect },

	props: {
		/**
		 * The character to buy for. Falls back to the route: an `open-modal`
		 * header action forwards its props verbatim.
		 */
		characterId: {
			type: String,
			default: '',
		},
	},

	emits: ['close'],

	data() {
		return {
			status: 'loading',
			character: null,
			items: [],
			selected: null,
			busy: false,
			refusal: '',
			bought: '',
		}
	},

	computed: {
		/**
		 * The character this dialog buys for.
		 *
		 * @return {string} The id.
		 *
		 * @spec openspec/changes/capability-rows-missing-requirements/specs/rpg-system/spec.md#requirement-characters-buy-items-with-in-game-money-req-rpg-001
		 */
		id() {
			return firstId(this.characterId, this.$route?.params?.id)
		},

		/**
		 * The character's name.
		 *
		 * @return {string} The name.
		 */
		characterName() {
			return String(this.character?.name ?? '')
		},

		/**
		 * The money the character holds now.
		 *
		 * @return {{gold: number, silver: number, copper: number}} The purse.
		 *
		 * @spec openspec/changes/capability-rows-missing-requirements/specs/rpg-system/spec.md#requirement-characters-buy-items-with-in-game-money-req-rpg-001
		 */
		purse() {
			return purseOf(this.character)
		},

		/**
		 * The items for sale as select options, each with its price in words.
		 *
		 * @return {Array<{id: string, label: string, price: object}>} The options.
		 *
		 * @spec openspec/changes/capability-rows-missing-requirements/specs/rpg-system/spec.md#requirement-characters-buy-items-with-in-game-money-req-rpg-001
		 */
		options() {
			return this.items.map((item) => ({
				...item,
				label: t('larpinq', '{item} ({price})', { item: item.name, price: this.moneyText(item.price) }),
			}))
		},

		/**
		 * Whether the character holds every coin of the chosen item's price.
		 *
		 * @return {boolean} True when it can pay.
		 *
		 * @spec openspec/changes/capability-rows-missing-requirements/specs/rpg-system/spec.md#requirement-characters-buy-items-with-in-game-money-req-rpg-001
		 */
		affordable() {
			return this.selected !== null && canPay(this.purse, this.selected.price)
		},

		/**
		 * Whether Buy may be pressed.
		 *
		 * @return {boolean} True when an affordable item is chosen and nothing is posting.
		 */
		buyable() {
			return this.status === 'ready' && this.affordable && !this.busy
		},
	},

	/**
	 * Read the character and the items for sale.
	 *
	 * @return {Promise<void>}
	 *
	 * @spec openspec/changes/capability-rows-missing-requirements/specs/rpg-system/spec.md#requirement-characters-buy-items-with-in-game-money-req-rpg-001
	 */
	async mounted() {
		const options = await fetchPurchaseOptions(this.id)
		if (options === null) {
			this.status = 'failed'
			return
		}
		this.character = options.character
		this.items = options.items
		this.status = 'ready'
	},

	methods: {
		t,

		/**
		 * An amount of money in words, leaving out coins of which there are none.
		 *
		 * @param {{gold: number, silver: number, copper: number}} money The amount.
		 * @return {string} For example "2 gold, 5 copper", or "no money".
		 *
		 * @spec openspec/changes/capability-rows-missing-requirements/specs/rpg-system/spec.md#requirement-characters-buy-items-with-in-game-money-req-rpg-001
		 */
		moneyText(money) {
			const words = {
				gold: (count) => t('larpinq', '{count} gold', { count }),
				silver: (count) => t('larpinq', '{count} silver', { count }),
				copper: (count) => t('larpinq', '{count} copper', { count }),
			}
			const parts = COINS.filter((coin) => (money?.[coin] ?? 0) > 0).map((coin) => words[coin](money[coin]))
			return parts.length > 0 ? parts.join(', ') : t('larpinq', 'no money')
		},

		/**
		 * The server's refusal in the user's language.
		 *
		 * @param {string} message The server's English reason.
		 * @return {string} The sentence to show.
		 *
		 * @spec openspec/changes/capability-rows-missing-requirements/specs/rpg-system/spec.md#requirement-characters-buy-items-with-in-game-money-req-rpg-001
		 */
		refusalText(message) {
			const known = {
				'The character cannot pay this item': t('larpinq', 'The character cannot pay this item.'),
				'The character already carries this item': t('larpinq', 'The character already carries this item.'),
				'This item belongs to another world': t('larpinq', 'This item belongs to another world.'),
				'This item has no price': t('larpinq', 'This item is not for sale.'),
				'Only the owner and game masters buy for this character': t('larpinq', 'Only the owner and game masters buy for this character.'),
			}
			return known[message] ?? t('larpinq', 'Nothing was bought: {reason}', { reason: message || t('larpinq', 'unknown error') })
		},

		/**
		 * Buy the chosen item; on success show the new purse and drop the item from the offer.
		 *
		 * @return {Promise<void>}
		 *
		 * @spec openspec/changes/capability-rows-missing-requirements/specs/rpg-system/spec.md#requirement-characters-buy-items-with-in-game-money-req-rpg-001
		 */
		async buy() {
			if (!this.buyable) {
				return
			}
			const item = this.selected
			this.busy = true
			this.refusal = ''
			this.bought = ''
			const outcome = await buyItem(this.id, item.id)
			this.busy = false
			if (!outcome.ok) {
				this.refusal = this.refusalText(outcome.message)
				return
			}
			this.character = outcome.character && typeof outcome.character === 'object' ? { ...this.character, ...outcome.character } : this.character
			this.items = this.items.filter((candidate) => candidate.id !== item.id)
			this.selected = null
			this.bought = item.name
		},
	},
}
</script>
