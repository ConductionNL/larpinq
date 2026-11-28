# Design: larpinq-manifest-tier-4

## Why this change exists

`larpinq-adopt-or-abstractions §5.2` flagged Tier-4 graduation as a
follow-up. Two W22-W24 events unblocked it: ADR-036 (kind-agnostic slot
resolver, `nextcloud-vue#459`) shipped, and `useAppStatus('openregister')`
was verified live on the dev `nextcloud` container. This change exists
to convert that follow-up flag from a deferred `[~]` into an actionable
plan.

## Approach

The cohort migration pattern (used in other Tier-4 apps) is:

```js
// before — src/main.js
Vue.use(CnPageRenderer)
new Vue({ render: h => h(App) }).$mount('#content')

// after
import { CnAppRoot } from '@conduction/nextcloud-vue'
import manifest from './manifest.json'
import registry from './registry'
new Vue({
  render: h => h(CnAppRoot, { props: { manifest, registry } })
}).$mount('#content')
```

`registry.js` already lists every override as
`{ component, kind: 'page', _note }`; non-page entries need their
`kind:` field flipped to the matching ADR-036 discriminator (most are
`'widget'` for dashboard widgets, `'tab'` for sidebar detail tabs).

## Risk

Low — the prerequisites are shipped + live-verified; the migration is
mechanical. The one watch-out is forgetting a `kind:` value, which
would surface immediately at boot ("unknown component for slot X")
rather than as a silent failure.

## Verification

`npm run check:manifest` + `composer check:strict` are the structural
guardrails; the live-deploy regression check (every menu / page /
widget / tab renders against the dev container) is the runtime
guardrail.
