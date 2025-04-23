/**
 * Dialogs Component
 * @module Dialogs
 * @package
 * @author Ruben Linde
 * @copyright 2024
 * @license AGPL-3.0-or-later
 * @version 1.0.0
 * @see {@link https://github.com/opencatalogi/opencatalogi}
 *
 * This component serves as a container for all dialog components in the application.
 * It imports and renders generic dialogs that can be used across different object types.
 */

<template>
	<div>
		<CopyObjectDialog />
		<DeleteObjectDialog />
	</div>
</template>

<script>
import CopyObjectDialog from './generic/CopyObjectDialog.vue'
import DeleteObjectDialog from './generic/DeleteObjectDialog.vue'

export default {
	name: 'Dialogs',
	components: {
		CopyObjectDialog,
		DeleteObjectDialog,
	},
}
</script>
