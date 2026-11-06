<!--
  SPDX-FileCopyrightText: 2026 Conduction B.V.
  SPDX-License-Identifier: EUPL-1.2

  The active-world switcher in the dashboard header, beside the dashboard's own
  buttons (events-world-scope-and-upcoming). The dashboard has no header slot
  of its own to replace, so the switcher sits among its actions without the
  page title.
-->
<template>
	<WorldSwitcher placement="actions" />
</template>

<script>
import WorldSwitcher from './WorldSwitcher.vue'

export default {
	name: 'WorldSwitcherActions',

	components: { WorldSwitcher },
}
</script>
