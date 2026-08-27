# Larpinq API-contract tests (Newman)

Newman/Postman contract tests that exercise larpinq's HTTP surface directly,
locking the API contract. Per the gate-19 split, **API/contract correctness lives
in Newman**; Playwright drives the UI only.

## What is covered

| Folder | Endpoints | Happy | Error | Authz |
| --- | --- | --- | --- | --- |
| 1. Character (OR CRUD) | OpenRegister `/api/objects/{register}/character` (ADR-022; larpinq owns no domain-CRUD controller) | create → read → list → update | 400 missing-required (`ocName`), not 500 | anon single-read → 404 (owner-scoped), anon write → 401 |
| 2. Domain objects | OR objects for `item`, `skill`, `condition`, `effect`, `event`, `player` | create each + list items | — | — |
| 3. Character PDF compute | `GET /characters/{id}/download/{template}` (`CharactersController::downloadPdf`) | — (needs a real DocuDesk template) | 400 non-UUID template, 4xx (404/424) unresolved-UUID — never 500 | 401 anonymous |
| 4. Settings | `GET /api/settings`, `POST /api/settings` | 200 + contract shape (`openRegisters`, `availableRegisters[]`) | — | 401 no-auth (GET + POST) |
| 9. Teardown | deletes every seeded object | idempotent cleanup | — | — |

The collection is **self-contained and idempotent**: setup requests create the
prerequisite OpenRegister objects across all seven schemas, and teardown deletes
everything created. Register slug `larpingapp` (live id 8) with schema slugs
`character`/`item`/`skill`/`condition`/`effect`/`event`/`player`.

Result: **27 requests / 43 assertions / 0 failures.**

## ADR-022 — larpinq is a thin OpenRegister client

larpinq owns no domain-CRUD controller; all LARP-object CRUD goes through the
OpenRegister object API (`/apps/openregister/api/objects/{register}/{schema}`).
The only larpinq-native controllers are Settings, the character PDF compute
endpoint (`downloadPdf`, which delegates rendering to DocuDesk), and the generic
preferences endpoint. The collection therefore exercises the OR object API for
the domain objects and the larpinq controllers for the rest.

### OpenRegister object-read authorization (honest)

The `character` schema carries an `ownerUid`, so OR scopes a **single-object**
read to the owner: an anonymous GET of an admin-owned character returns **404**
(not the object — the data is not leaked). The unscoped **list** endpoint returns
200 to anon but only exposes the anon-visible subset. Object **writes**
(POST/PUT/DELETE) always require auth and return **401** anonymously. The folder-1
authz tests assert all three honestly. This is OpenRegister's enforcement (ADR-022),
not larpinq's — the suite documents reality rather than asserting a flat 401
the OR read API never returns.

### Character PDF (`downloadPdf`)

`downloadPdf` is `#[NoAdminRequired]` (a logged-in session is still required) and
then additionally **admin-gated** in its body (only GMs may download sheets —
closes #205). It validates the `template` path segment to a UUID before touching
DocuDesk (path-traversal defence, #218). The suite asserts:
- non-UUID template → **400** (the UUID guard fires before DocuDesk),
- valid-but-unresolved UUID → clean **4xx** (404 template-not-found, or 424 if
  DocuDesk is absent) — **never 500**,
- anonymous → **401**.

A full happy-path PDF (200 `application/pdf`) needs a real DocuDesk template seeded
and is left to the UI/Playwright e2e + DocuDesk's own suite; the compute endpoint's
error and authz contract is fully locked here.

## Running

```bash
# defaults: BASE_URL=http://localhost:8080, ADMIN_USER=admin, ADMIN_PASS=admin
./run-newman.sh

# or directly:
npx newman run larpinq.postman_collection.json \
  --env-var baseUrl=http://localhost:8080 \
  --env-var adminUser=admin \
  --env-var adminPass=admin
```

`run-newman.sh` prefers a globally-installed `newman`, falls back to `npx newman`,
and serialises runs under `flock /tmp/uiaudit-larpinq.lock` to avoid tripping
the Nextcloud brute-force protection when multiple agents run in parallel.

## Auth-isolation detail (important for reuse)

Newman keeps a per-run cookie jar. Authenticated requests against `baseUrl`
(`localhost`) establish a Nextcloud session cookie; because the jar is shared,
that cookie would silently authenticate the no-auth requests too (they then return
200 instead of 401). Two measures keep the authorization tests honest:

1. **Host split** — authenticated requests use `{{baseUrl}}` (`http://localhost:8080`);
   the no-auth requests use `{{noAuthBase}}` (`http://127.0.0.1:8080`). NC session
   cookies are host-scoped, so the `localhost` session is never sent to `127.0.0.1`,
   making those requests genuinely unauthenticated. `run-newman.sh` derives
   `noAuthBase` from `BASE_URL` automatically (override with `NO_AUTH_BASE`).
2. **`--ignore-redirects` + `Accept: application/json`** — unauthenticated requests
   get NC's JSON `401`, not the `303`→login-page `200` HTML that a browser `Accept`
   would follow.

This is the reusable Newman authz pattern for the fleet.

## Collection variables

`baseUrl`, `noAuthBase`, `adminUser`, `adminPass`, the OR `register` slug
(`larpingapp`), and the seven schema slugs. The per-object ids
(`charId`/`itemId`/…) are captured at runtime from the create responses, so the
suite is not pinned to specific seed UUIDs.
