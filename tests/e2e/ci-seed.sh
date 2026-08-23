#!/usr/bin/env bash
#
# SPDX-FileCopyrightText: 2026 Larpinq Contributors
# SPDX-License-Identifier: EUPL-1.2
#
# Provision Larpinq's OpenRegister register + schemas on a freshly installed
# Nextcloud, for the shared `E2E Tests (Playwright)` CI job.
#
# Wired up as the workflow's `playwright-seed-command`. That step runs AFTER
# `php -S` is up and with cwd set to the Nextcloud server root, so this is
# invoked as:
#
#     playwright-seed-command: 'bash apps/larpinq/tests/e2e/ci-seed.sh'
#
# WHY THIS IS NEEDED
# ------------------
# `occ app:enable larpinq` runs the post-migration repair step that is
# supposed to import `lib/Settings/larpinq_register.json` (plus every
# `lib/Settings/register.d/*.json` fragment) into OpenRegister. That is not a
# reliable fresh-install path, and it fails SILENTLY:
#
#   1. An IRepairStep runs with NO user session. OpenRegister's RBAC evaluates
#      the acting user, so the import is denied outright —
#      "User 'Anonymous' does not have permission to 'create' objects in schema
#      '…'" — and the repair step catches \Throwable and downgrades it to a
#      warning, so `occ app:enable larpinq` still exits 0.
#   2. The non-forced path is version-gated: it can advance the recorded
#      configuration version WITHOUT applying the register, after which a
#      second run sees "already current" and does nothing either.
#
# Either way the app enables cleanly, the SPA boots, and the register simply is
# not there. The suite's failure shape in that state is every index page
# rendering an empty table and `workflows/fixtures.ts` throwing
# "create ability failed: HTTP 400" — messages that accuse the selectors and the
# fixtures, not the missing import.
#
# So this script does the import EXPLICITLY over the admin HTTP API (which has
# a real session and passes RBAC), FORCED, and then VERIFIES the register and
# every schema slug actually exists. A failed provision becomes ONE loud step
# failure here instead of a dozen misleading spec failures later.
#
# It is idempotent: the import is idempotent server-side and re-running only
# re-verifies.

set -euo pipefail

SCRIPT_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
APP_DIR="$(cd -- "${SCRIPT_DIR}/../.." && pwd)"

# ── Target resolution ────────────────────────────────────────────────────────
# The shared workflow's "Seed test data" step exports BASE_URL / NEXTCLOUD_URL /
# NC_BASE_URL / ADMIN_USER / ADMIN_PASSWORD / NC_ADMIN_USER / NC_ADMIN_PASS.
# Accept all of them, and fall back to the CI runner's own `php -S 0.0.0.0:8080`
# only when actually running on CI.
#
# On a developer box `localhost:8080` is the SHARED dev container, and this
# script performs ADMIN WRITES — it must never silently import a register into
# somebody else's environment. Off CI, an unset target is a hard error.
BASE="${PLAYWRIGHT_BASE_URL:-${BASE_URL:-${NEXTCLOUD_URL:-${NC_BASE_URL:-}}}}"
if [ -z "$BASE" ]; then
	if [ "${GITHUB_ACTIONS:-}" = "true" ] || [ "${CI:-}" = "true" ]; then
		BASE="http://localhost:8080"
	else
		echo "ERROR: no base URL set. Export PLAYWRIGHT_BASE_URL or BASE_URL." >&2
		echo "       Refusing to default to http://localhost:8080 outside CI —" >&2
		echo "       that is the SHARED dev container and this script writes to it." >&2
		exit 1
	fi
fi
BASE="${BASE%/}"

USER_NAME="${ADMIN_USER:-${NC_ADMIN_USER:-admin}}"
USER_PASS="${ADMIN_PASSWORD:-${NC_ADMIN_PASS:-admin}}"

echo "[ci-seed] target:  ${BASE}"
echo "[ci-seed] app dir: ${APP_DIR}"

# ── 1. Import the Larpinq configuration (forced) ─────────────────────────────
# `appinfo/routes.php` registers `settings#reimport` at
# POST /api/settings/reimport, which calls
# SettingsService::loadSettings(force: true) -> SettingsLoadService::loadSettings
# -> ConfigurationService::importFromApp(force: true). That is exactly the
# forced import the repair step cannot perform, AND it goes through
# ConfigFileLoaderService, which merges the `lib/Settings/register.d/*.json`
# fragments into the base register before importing. The OpenRegister generic
# importer below cannot do that merge, which is why this is the primary path.
#
# ⚠️ larpinq does NOT return `\OCA\OpenRegister\AppHost\Routes::standard()`
# from appinfo/routes.php (zero references), so there is no `settings#load`
# route here — `POST /api/settings/load` would 404. `settings/reimport` is the
# real one; do not "fix" it to match the fleet's usual spelling.
#
# SettingsController::reimport() carries no #[NoAdminRequired], so Nextcloud's
# admin middleware requires an admin session — HTTP Basic as admin.
#
# `OCS-APIRequest: true` is load-bearing, not decoration: the method carries no
# #[NoCSRFRequired] (it was deliberately removed to close #206), and Nextcloud's
# Request::passesCSRFCheck() short-circuits to true on that header (the
# strict-cookie precondition holds because a Basic-auth request carries no
# session cookie at all). Without the header this POST is a CSRF failure.
IMPORT_URL="${BASE}/index.php/apps/larpinq/api/settings/reimport"
echo "[ci-seed] POST ${IMPORT_URL} (forced import, register.d fragments merged)"

IMPORT_BODY="$(mktemp)"
IMPORT_CODE="$(
	curl -sS -o "$IMPORT_BODY" -w '%{http_code}' \
		-u "${USER_NAME}:${USER_PASS}" \
		-X POST \
		-H 'Content-Type: application/json' \
		-H 'OCS-APIRequest: true' \
		--data '{}' \
		"$IMPORT_URL" || echo 000
)"

echo "[ci-seed] settings#reimport HTTP ${IMPORT_CODE}"
head -c 2000 "$IMPORT_BODY"; echo

# HTTP 200 is necessary but NOT sufficient: reimport() returns
# `{"success": false, "message": "..."}` when the import itself failed, and a
# login redirect is also a 200 with an HTML body. Treat anything that is not an
# explicit success as a reason to try the generic importer below, and let the
# verification step decide the actual outcome.
IMPORT_OK=0
if [ "$IMPORT_CODE" = "200" ] && grep -q '"success":[[:space:]]*true' "$IMPORT_BODY"; then
	IMPORT_OK=1
	echo "[ci-seed] larpinq settings#reimport reported success."
else
	echo "[ci-seed] larpinq settings#reimport did not report success; falling back to the OpenRegister importer."
fi

# ── 1b. Fallback: OpenRegister's generic configuration importer ──────────────
# Independent of larpinq's own controller wiring, so it still provisions the
# register if `settings#reimport` is unavailable or the SettingsService rejects
# the file. Admin-only. It reads the upload under the literal form key `file`;
# a raw JSON request body is NOT one of its accepted shapes. `force` is compared
# `=== 'true' || === true` there, so the form-encoded string is fine.
#
# The base register goes first (it is the only file carrying the `registers`
# section), then each `register.d/*.json` fragment is posted individually. The
# fragments are PATCHES, not standalone configurations — several carry no
# `slug`/`title` at all and only add properties to a schema the base file
# defines — so an individual fragment failing here is expected and non-fatal.
# What matters is the verification below.
or_import() {
	local file="$1"
	local body code
	body="$(mktemp)"
	code="$(
		curl -sS -o "$body" -w '%{http_code}' \
			-u "${USER_NAME}:${USER_PASS}" \
			-X POST \
			-H 'OCS-APIRequest: true' \
			-F "file=@${file}" \
			-F 'force=true' \
			-F 'appId=larpingapp' \
			"${BASE}/index.php/apps/openregister/api/configurations/import" || echo 000
	)"
	echo "[ci-seed] configurations/import $(basename "$file") -> HTTP ${code}"
	head -c 600 "$body"; echo
}

if [ "$IMPORT_OK" != "1" ]; then
	REGISTER_JSON="${APP_DIR}/lib/Settings/larpinq_register.json"
	if [ ! -f "$REGISTER_JSON" ]; then
		echo "::error::larpinq_register.json not found at ${REGISTER_JSON}."
		exit 1
	fi
	or_import "$REGISTER_JSON"
	for fragment in "${APP_DIR}"/lib/Settings/register.d/*.json; do
		[ -f "$fragment" ] || continue
		or_import "$fragment"
	done
fi

# ── 2. Verify the register and schemas are actually there ────────────────────
# An import reporting success is not the same as the register existing.
#
# ⚠️ THE SLUGS BELOW ARE READ OUT OF THE REPO'S OWN REGISTER JSON, NOT DERIVED.
# Three of them are NOT the entity name:
#
#     item       -> larping_item          (lib/Settings/larpinq_register.json)
#     event      -> larping_event         (idem)
#     attendance -> larping_attendance    (register.d/event-checkin-roster.json)
#
# The prefixes are deliberate and structural, not cosmetic. The bare `item` slug
# COLLIDES globally with another app's QTI "Item" schema, and larpinq's
# fixtures used to bind to it and fail every create with HTTP 400. Mechanically
# kebab-casing or un-prefixing these is how that regression comes back.
#
# The comparison is case-insensitive because OpenRegister resolves schema URL
# segments via LOWER(slug) — `xpAward` and `xpaward` are the same schema to the
# router, so treating them as different here would manufacture a false failure.
#
# The HTTP status is captured and checked SEPARATELY from the payload on
# purpose: an endpoint that 404s or redirects to the login form yields an empty
# slug set, which is indistinguishable from "the import produced nothing" if you
# only look at the parsed list. A wrong lookup manufactures an absence for free,
# so the two are reported as different errors.
verify() {
	python3 - "$1" "$2" "$3" <<'PY'
import json, sys
path, kind, code = sys.argv[1], sys.argv[2], sys.argv[3]
required = {
    'registers': ['larpingapp'],
    'schemas': [
        'character', 'player', 'ability', 'skill', 'larping_item',
        'condition', 'effect', 'larping_event', 'setting', 'xpAward',
        'larping_attendance',
    ],
}[kind]
with open(path) as fh:
    raw = fh.read()
if code != '200':
    print(f'::error::OpenRegister {kind} endpoint returned HTTP {code}, so the '
          f'slug list below proves nothing about the import. First 500 bytes:')
    print(raw[:500])
    sys.exit(1)
try:
    body = json.loads(raw)
except json.JSONDecodeError:
    print(f'::error::{kind} endpoint did not return JSON (HTTP 200). First 500 bytes:')
    print(raw[:500])
    sys.exit(1)
items = body if isinstance(body, list) else body.get('results', [])
slugs = {(i.get('slug') or '').lower() for i in items if isinstance(i, dict)}
missing = [s for s in required if s.lower() not in slugs]
print(f'[ci-seed] {kind} present: {sorted(s for s in slugs if s)}')
if missing:
    print(f'::error::Larpinq {kind} missing after import: {missing}')
    print('::error::The e2e suite cannot seed abilities, effects, skills or characters without them.')
    sys.exit(1)
print(f'[ci-seed] {kind} OK ({len(required)} required slugs present)')
PY
}

REG_BODY="$(mktemp)"
REG_CODE="$(curl -sS -u "${USER_NAME}:${USER_PASS}" -H 'OCS-APIRequest: true' \
	-o "$REG_BODY" -w '%{http_code}' \
	"${BASE}/index.php/apps/openregister/api/registers?_limit=300" || echo 000)"
verify "$REG_BODY" registers "$REG_CODE"

SCH_BODY="$(mktemp)"
SCH_CODE="$(curl -sS -u "${USER_NAME}:${USER_PASS}" -H 'OCS-APIRequest: true' \
	-o "$SCH_BODY" -w '%{http_code}' \
	"${BASE}/index.php/apps/openregister/api/schemas?_limit=1000" || echo 000)"
verify "$SCH_BODY" schemas "$SCH_CODE"

# The register existing is still not the same as it being READ/WRITEABLE by the
# admin session the fixtures use. `tests/e2e/workflows/fixtures.ts` builds every
# fixture URL as /apps/openregister/api/objects/<register>/<schema>, so probe
# that shape here and give the failure a name.
OBJ_CODE="$(curl -sS -o /dev/null -w '%{http_code}' \
	-u "${USER_NAME}:${USER_PASS}" -H 'OCS-APIRequest: true' \
	"${BASE}/index.php/apps/openregister/api/objects/larpinq/character?_limit=1" || echo 000)"
echo "[ci-seed] objects/larpinq/character probe -> ${OBJ_CODE}"
if [ "$OBJ_CODE" -ge 400 ] 2>/dev/null; then
	echo "::error::The larpingapp character collection is not readable (HTTP ${OBJ_CODE})."
	echo "::error::Every workflow fixture create/read would fail with a message accusing the fixtures."
	exit 1
fi

# The app's OWN settings API is what workflows/fixtures.ts::resolveSchemaIds()
# reads to map entity type -> numeric schema id. If the import advanced the
# config version without writing the `<type>_schema` keys, that call returns a
# configuration with no ids, the fixtures silently fall back to the bootstrap
# literals (18/19/20/…), and every create lands in a FOREIGN app's schema. Show
# the resolved ids in the log so that case is diagnosable.
SET_BODY="$(mktemp)"
SET_CODE="$(curl -sS -u "${USER_NAME}:${USER_PASS}" -H 'OCS-APIRequest: true' \
	-o "$SET_BODY" -w '%{http_code}' \
	"${BASE}/index.php/apps/larpinq/api/settings" || echo 000)"
echo "[ci-seed] larpinq settings API -> HTTP ${SET_CODE}"
python3 - "$SET_BODY" <<'PY' || true
import json, sys
try:
    cfg = (json.load(open(sys.argv[1])) or {}).get('configuration') or {}
except Exception as exc:
    print(f'[ci-seed] settings payload not JSON ({exc}); skipping id dump.')
    raise SystemExit(0)
ids = {k: v for k, v in cfg.items() if k == 'register' or k.endswith('_schema')}
print('[ci-seed] resolved register/schema ids:', json.dumps(ids, sort_keys=True))
if not ids.get('register'):
    print('::warning::Larpinq settings API reports no `register` id — '
          'workflows/fixtures.ts will fall back to its bootstrap literals.')
PY

echo "[ci-seed] Larpinq register + schemas provisioned."

# ── 2b. Mark the first-visit walkthrough as already seen ─────────────────────
# `src/manifest.json` declares a six-step `trigger: "first-visit"` tour
# ("Welcome to LARPing"). It renders as `<div role="dialog" aria-modal="true"
# class="cn-walkthrough">` with a FULL-VIEWPORT dim layer
# (`.cn-walkthrough__dim--full`) that intercepts pointer events, so any click
# racing its mount fails actionability until the test times out. Measured: E2E
# job 91942310154 lost 'character list renders view toggle and add controls' to
# exactly that — 60 s of "…subtree intercepts pointer events" on a sidebar link
# whose element was resolved, visible, enabled and stable the whole time.
#
# `tests/e2e/_nav.ts::dismissSupportDialog()` closes it when it is already on
# screen, but it cannot close a dialog that has not mounted yet, and the tour
# mounts asynchronously after the walkthrough preference GET resolves. That is
# a race no amount of clicking fixes from the test side.
#
# So turn it off at the source. `useWalkthrough` treats the per-user preference
# named by `manifest.walkthrough.completionConfigKey` as AUTHORITATIVE for
# "which version has this user already seen", so writing a version above every
# step's `sinceVersion` means the tour never triggers for this user.
#
# This suppresses a first-run affordance, not a behaviour under test: no spec in
# this suite asserts the walkthrough — every mention of it in tests/e2e is a
# comment about fighting it.
#
# `OCS-APIRequest: true` is required (PreferencesController::setPreference has
# no #[NoCSRFRequired] — deliberately, to close #213).
WT_KEY="$(python3 - "${APP_DIR}/src/manifest.json" <<'PY' || true
import json, sys
try:
    print(((json.load(open(sys.argv[1])) or {}).get('walkthrough') or {}).get('completionConfigKey') or '')
except Exception:
    print('')
PY
)"
if [ -n "$WT_KEY" ]; then
	WT_CODE="$(curl -sS -o /dev/null -w '%{http_code}' \
		-u "${USER_NAME}:${USER_PASS}" \
		-X PUT \
		-H 'Content-Type: application/json' \
		-H 'OCS-APIRequest: true' \
		--data '{"value":"999.0.0"}' \
		"${BASE}/index.php/apps/larpinq/api/preferences/${WT_KEY}" || echo 000)"
	echo "[ci-seed] walkthrough '${WT_KEY}' marked seen -> HTTP ${WT_CODE}"
	# Read it back. A 200 alone is not proof: an app that does not serve the
	# route answers 200 with the SPA's HTML, which useWalkthrough explicitly
	# treats as "no opinion" and falls back to localStorage — i.e. the tour
	# would still open and this step would have done nothing.
	WT_READ="$(curl -sS -u "${USER_NAME}:${USER_PASS}" -H 'OCS-APIRequest: true' \
		"${BASE}/index.php/apps/larpinq/api/preferences/${WT_KEY}" || echo '')"
	echo "[ci-seed] walkthrough preference reads back: $(printf '%s' "$WT_READ" | head -c 200)"
	case "$WT_READ" in
		*'"value":"999.0.0"'*)
			echo "[ci-seed] walkthrough suppression verified."
			;;
		*)
			echo "::error::The walkthrough preference did not read back as written."
			echo "::error::The first-visit tour will open over the SPA and its full-viewport dim layer"
			echo "::error::will intercept clicks, which surfaces as unrelated 60s actionability timeouts."
			exit 1
			;;
	esac
else
	echo "[ci-seed] no walkthrough.completionConfigKey in src/manifest.json; nothing to suppress."
fi

# ── 3. Warm the SPA so the first spec doesn't pay the cold start ─────────────
# The shared workflow serves Nextcloud with `php -S 0.0.0.0:8080`. It sets
# PHP_CLI_SERVER_WORKERS=8, but the first hit still pays a cold opcache and the
# first parse of a multi-megabyte webpack bundle, and that cost lands entirely
# on whichever spec happens to run first. Warming it here puts that cost in the
# environment-preparation step where it belongs, rather than inside an assertion
# timeout that would then have to keep drifting upward.
#
# Failures are ignored on purpose: this is a warm-up, not a gate. The real
# checks are above and below.
for path in \
	"/index.php/apps/larpinq/" \
	"/index.php/apps/larpinq/api/settings" \
	"/index.php/settings/admin/larpinq" \
	"/index.php/apps/openregister/api/registers?_limit=1"
do
	code="$(curl -sS -o /dev/null -w '%{http_code}' -u "${USER_NAME}:${USER_PASS}" \
		-H 'OCS-APIRequest: true' "${BASE}${path}" || echo 000)"
	echo "[ci-seed] warm ${path} -> ${code}"
done

# Pull the main webpack bundle once so it is in the page cache.
#
# Do NOT hardcode the URL. Nextcloud serves an app's assets from whichever apps
# directory it was installed into — `/apps/larpinq/js/…` on the CI runner,
# `/custom_apps/larpinq/js/…` in the docker dev images — and asking for the
# wrong one does not 404. It returns **HTTP 200 with `text/html`**: the NC error
# page, served through index.php. A status-code check therefore reports success
# while fetching a 40 KB HTML page instead of a multi-MB bundle, so the warm-up
# silently warms nothing.
#
# Read the real src out of the rendered app page instead, and verify the
# response is actually JavaScript.
APP_HTML="$(mktemp)"
curl -sS -u "${USER_NAME}:${USER_PASS}" -H 'OCS-APIRequest: true' \
	"${BASE}/index.php/apps/larpinq/" -o "$APP_HTML" || true

# `|| true` is load-bearing: grep exits 1 when it matches nothing, and under
# `set -euo pipefail` that aborts the script right here — so the case the gate
# below exists to explain (no bundle) would die with a bare non-zero exit and
# none of the diagnosis. Let it fall through to the gate instead.
BUNDLE_SRC="$(grep -oE 'src="[^"]*larpinq-main[^"]*"' "$APP_HTML" \
	| head -1 | sed 's/^src="//; s/"$//' || true)"

if [ -n "$BUNDLE_SRC" ]; then
	BUNDLE_INFO="$(curl -sS -o /dev/null \
		-w '%{http_code} %{content_type} %{size_download}' \
		-u "${USER_NAME}:${USER_PASS}" "${BASE}${BUNDLE_SRC}" || echo '000 - 0')"
	echo "[ci-seed] warm bundle ${BUNDLE_SRC} -> ${BUNDLE_INFO}"
else
	echo "[ci-seed] could not locate the bundle src in the rendered app page."
	BUNDLE_INFO=""
fi

# On CI this is a GATE, not a warm-up.
#
# The single most likely way this job "succeeds" dishonestly is by passing
# without ever loading the app — and the environment hides it well: when the
# bundle is absent, Nextcloud does not 404. It serves its HTML error page with
# **HTTP 200 and Content-Type text/html**, so `npm run build` producing nothing
# looks, to every status-code check in the pipeline, exactly like success.
#
# Note that this gate reads the SERVED response, not the file on disk, and it is
# placed at the very end so that a run which reaches the specs has provably been
# able to fetch real JavaScript for the SPA.
if [ "${GITHUB_ACTIONS:-}" = "true" ] || [ "${CI:-}" = "true" ]; then
	case "$BUNDLE_INFO" in
		*javascript*)
			echo "[ci-seed] bundle verified as JavaScript."
			;;
		*)
			echo "::error::The Larpinq frontend bundle did not serve as JavaScript (got: ${BUNDLE_INFO:-<not found>})."
			echo "::error::The SPA cannot mount, so every UI spec would fail on a selector timeout with a misleading cause."
			echo "::error::Check the 'Build app frontend' step — a missing bundle returns HTTP 200 text/html, not 404."
			exit 1
			;;
	esac
fi

echo "[ci-seed] done."
