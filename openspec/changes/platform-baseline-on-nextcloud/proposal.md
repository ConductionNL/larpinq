---
kind: code
---

# Proposal: platform-baseline-on-nextcloud

## Summary

Write down what larpinq takes from the Nextcloud and OpenRegister it runs on, as testable requirements. larpinq keeps no user store and no login of its own. Sign-in, the second factor, who may open the app, organisation scoping and the data API all come from the instance. A buyer asks for each of these by name, and until now no spec said where larpinq stands.

The same spec covers the language rows: the interface follows the user's Nextcloud language, and LrTelefoon draws a language switch on the phone pages.

## Motivation

Six built rows in `openspec/parity/capabilities.json` had `spec: none`: adm-access-group, adm-two-factor, adm-multi-organisation, adm-api, adm-self-hosted and adm-multilingual. A build lane cannot plan against a row without a requirement, and an answer to a buyer cannot cite one. Read from code on development on 30 October 2026:

- `appinfo/info.xml` restricts nothing itself; the comment there names the per-instance group restriction (group `larpers`).
- `grep -rn PublicPage lib/Controller` has no hits, so every route runs in a Nextcloud session.
- `src/App.vue` mounts `useTenantContext()` and sends the active organisation as `X-OpenRegister-Organisation` on every object request (`src/store/modules/object.js`).
- `lib/Settings/larpinq_register.json` registers the `larpinq` register, which OpenRegister serves over its object API; larpinq adds no data endpoint of its own for other tools.
- `src/main.js` loads the `larpinq` translations; `l10n/` ships Dutch and English among others. No in-app language switch exists yet.

adm-open-source is not in this change: it cites `openspec/specs/admin-settings` SET-091 (EUPL-1.2).

## Scope

### In scope

1. Sign-in, the second factor and access to the app are the Nextcloud instance's.
2. Every request is scoped to the active OpenRegister organisation.
3. Other tools reach the data through the OpenRegister API.
4. larpinq runs on any self-hosted Nextcloud with OpenRegister.
5. The interface follows the user's Nextcloud language, and the phone pages offer a language switch as LrTelefoon draws it.

### Out of scope

- A login, a two-factor provider or user management inside larpinq.
- A larpinq API next to OpenRegister's.

## Matrix rows

- adm-access-group: Restrict who can open the app to one Nextcloud group.
- adm-two-factor: Protect organiser accounts with a second login step such as an authenticator code.
- adm-multi-organisation: Host several LARP organisations with separate data in one installation.
- adm-api: Reach the data through an API for other tools.
- adm-self-hosted: Run the tool on your own server with your data under your control.
- adm-multilingual: Use the app in Dutch as well as English.
