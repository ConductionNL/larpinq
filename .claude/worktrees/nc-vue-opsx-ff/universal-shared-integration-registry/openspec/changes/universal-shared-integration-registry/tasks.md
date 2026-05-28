# Tasks: universal shared integration registry

- [x] `installIntegrationRegistry` — converge-not-clobber on an existing
      real registry; keep stub-queue draining.
- [x] `sharedRegistryIfInstalled(globalRef?)` — read-only resolver (null
      when only a stub / nothing).
- [x] `getSharedRegistry(globalRef?)` — install-if-needed, idempotent.
- [x] `useIntegrationRegistry()` default → shared resolver, module-singleton
      fallback.
- [x] Barrel exports (`src/integrations/index.js`, `src/index.js`).
- [x] Unit tests: converge-not-clobber, idempotent getShared, read-only
      sharedRegistryIfInstalled, stub-queue drain, foreign-registry
      convergence. Existing registry + composable suites stay green (32
      passing).
- [ ] Docs: register-integration.md + a short note on the shared-registry
      model + how OpenRegister bootstraps it globally.
- [ ] `npm run check:docs` + build green.

## Verification

- [ ] Companion OpenRegister PR adds the global bootstrap; then an
      OpenCatalogi publication detail page shows the "Synced from" leaf
      (openconnector) with ZERO OpenCatalogi changes.
