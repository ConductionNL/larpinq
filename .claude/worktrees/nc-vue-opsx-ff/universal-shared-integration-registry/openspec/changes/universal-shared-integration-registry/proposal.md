# Universal shared integration registry — converge on one window-global instance

## Why

Each consuming app bundles its own copy of nextcloud-vue, so each has its
own module-level `integrations` singleton. `useIntegrationRegistry()`
defaulted to that per-bundle singleton, and `installIntegrationRegistry()`
*overwrote* `window.OCA.OpenRegister.integrations` with whichever bundle
called it last.

Consequence: a leaf (generic or Path-2) registers into one registry while
a consuming app's detail page reads a different one. Today the only apps
that show integrations are those that call `installIntegrationRegistry()`
themselves at boot (e.g. decidesk) — and even they only see leaves their
own bundle registered. OpenCatalogi shows nothing because it never
bootstraps, and openconnector's Path-2 component queues on a stub that
never drains on an OpenCatalogi page.

We want: register once, anywhere; render everywhere — without each
consuming app re-bootstrapping.

## What

Make every register/read path converge on a single shared registry held
at `window.OCA.OpenRegister.integrations`.

- **`installIntegrationRegistry` converges, doesn't clobber.** If another
  bundle already installed a *real* registry (has `register()`, no
  `_queue`), return that instance instead of overwriting it. First bundle
  to install wins; all others share it. Stub-queue draining is unchanged.
- **`sharedRegistryIfInstalled(globalRef?)`** (new, read-only) — returns
  the installed shared registry or `null`. No mutation, so unit tests
  that never install a global stay on the module-singleton path.
- **`getSharedRegistry(globalRef?)`** (new, install-if-needed) — resolves
  the shared registry, installing the module singleton when none exists
  (draining any stub). The entry point OpenRegister's global bootstrap
  uses so builtins/leaves/icons populate the one shared registry.
- **`useIntegrationRegistry()` default → `sharedRegistryIfInstalled() ||
  moduleSingleton`.** A consuming app now reads the registry OR's
  bootstrap populated; falls back to the local singleton when no global
  is installed (standalone / tests). Reactivity is unchanged — it
  subscribes via `onChange` on whatever instance it resolves.

The companion OpenRegister change ships a global bootstrap script
(`addInitScript` on every page) that calls `getSharedRegistry()` +
`registerBuiltinIntegrations` + `registerLeafIntegrations` +
`registerIntegrationIcons` against it, so the registry is universally
populated without per-consumer bootstrap.

## Non-goals

- Changing the descriptor shape, collision policy (AD-13), or surface
  fallback (AD-19).
- Changing `registerBuiltinIntegrations` / `registerLeafIntegrations`
  defaults (still module-singleton) — OpenRegister's bootstrap passes the
  shared instance explicitly, keeping the unit-test default isolated.

## Backwards compatibility

- Apps that already call `installIntegrationRegistry()` (decidesk) keep
  working — they become the first installer and own the shared instance.
- Apps that pass an explicit registry to `useIntegrationRegistry(reg)`
  are unaffected.
- No global installed (standalone, tests) → module-singleton path, as before.
