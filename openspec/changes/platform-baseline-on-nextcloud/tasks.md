# Tasks: platform-baseline-on-nextcloud

<!-- HYDRA CAP: max 20 unindented `- [ ]` lines. This file uses 2. -->

## 1. Record the inherited rows

- [x] 1.1 `openspec/parity/capabilities.json`: rows adm-access-group, adm-two-factor, adm-multi-organisation, adm-api, adm-self-hosted and adm-multilingual cite this change and their requirement (REQ-PLB-001 to REQ-PLB-006). Read from code: `appinfo/info.xml` group comment, no `PublicPage` in `lib/Controller`, `useTenantContext()` in `src/App.vue` with the organisation header in `src/store/modules/object.js`, the register in `lib/Settings/larpinq_register.json`, translations loaded in `src/main.js`.

## 2. Language switch on the phone pages

- [ ] 2.1 `src/components/LanguageSwitch.vue` (new): Nederlands and English, defaulting to the account language; saves through `PUT /ocs/v2.php/cloud/users/{userId}` with key `language` and reloads; placed on the phone pages of `admin-phone-friendly-pages` in `src/manifest.json`; registered in `src/registry.js` (REQ-PLB-007). Verify: `tests/vitest/languageSwitch.spec.js` (default, save call, reload).
- [ ] 2.2 Strings in `l10n/nl.json` and `l10n/en.json` (REQ-PLB-007). Verify: `npm run test:l10n`.
