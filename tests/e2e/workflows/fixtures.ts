/*
 * SPDX-FileCopyrightText: 2026 Larping Contributors
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Seeded-fixture helper for the DEEP, data-dependent larpingapp e2e
 * workflow layer (tests/e2e/workflows/).
 *
 * Why this exists
 * ---------------
 * The spec-coverage suite (tests/e2e/spec-coverage/) proves the SPA pages
 * *render*. This workflow layer proves the character FEATURES actually
 * *work* with persisted data — full CRUD round-trips and the
 * character-stat computation (effects/skills/items actually computing onto
 * a character's ability scores).
 *
 * Every fixture is created and torn down through OpenRegister's object
 * REST API (the authoritative backing store the app reads from). Using the
 * REST API for SETUP/TEARDOWN is permitted; the assertions live in the
 * spec files. Each helper returns the created object's UUID so a workflow
 * can read it back, edit it, and finally delete it — proving persistence.
 *
 * Register / schema resolution
 * ----------------------------
 * larpingapp stores every entity type in OpenRegister register 8 on the
 * dev instance (the per-type `<type>_register` config keys all resolve to
 * 8; the PHP `RegisterObjectFetcher` reads from there, and the numeric
 * `/api/objects/8/<schema>` REST route returns the rows). The legacy
 * top-level `register` config key (156) is a SEPARATE register that
 * accumulated env churn — do not seed into it. See
 * tests/e2e/spec-coverage/detail-forms-admin.spec.ts for the matching
 * note. Override with LARP_REGISTER_ID if the dev instance differs.
 *
 * Data model (verified against the live schemas, 2026-06-10)
 * ----------------------------------------------------------
 *   ability   (schema 20): { name, base }                         base stat
 *   effect    (schema 24): { name, modifier, modification,        the modifier
 *                            cumulative, abilities:[abilityId] }
 *   skill     (schema 21): { name, effects:[effectId] }           carrier
 *   item      (schema 22): { name, effects:[effectId] }           carrier
 *   condition (schema 23): { name, effects:[effectId] }           carrier
 *   character (schema 18): { name, ocName(required), type,        the subject
 *                            skills:[id], items:[id],
 *                            conditions:[id], events:[id] }
 *
 * The CharacterService computes:
 *   stats[abilityId].value = ability.base
 *                          ± Σ (effect.modifier) for every effect reachable
 *                            from the character's skills/items/conditions/events.
 */

import { request, type APIRequestContext } from '@playwright/test'
import { OR_OBJECTS_API, LARPINGAPP_SETTINGS_API } from '../_base-url'

export const BASE = '/apps/larpingapp'

// Resolved centrally in `tests/e2e/_base-url.ts`. These were hardcoded to
// `http://localhost:8080` — the SHARED dev container — so every fixture this
// module created and deleted landed in somebody else's environment regardless
// of which instance the specs were navigating.
export const OR_BASE = OR_OBJECTS_API

export const SETTINGS_API = LARPINGAPP_SETTINGS_API

/**
 * Register the app's data actually lives in. Resolved from LarpingApp's own
 * settings API at runtime (resolveSchemaIds), never hardcoded — numeric
 * register/schema IDs are assigned by OpenRegister per instance and DRIFT on a
 * shared instance with many registers. The literal here is only a last-resort
 * fallback when the settings call fails. Override with LARP_REGISTER_ID.
 */
export let REGISTER_ID = process.env.LARP_REGISTER_ID || '8'

/**
 * Numeric OpenRegister schema ids per LarpingApp object type.
 *
 * MUST NOT be trusted as static: a previous bug bound `item` to a foreign
 * app's QTI "Item" schema (id 22) because the bare `item` slug collided
 * globally, so item creation 400'd. The fix namespaced the slug to
 * `larping_item` / `larping_event`, which changes the numeric id. To stay
 * correct on ANY instance, resolveSchemaIds() overwrites this map from the
 * live `{type}_schema` config returned by the LarpingApp settings API before
 * the workflow runs. These literals are only a bootstrap fallback.
 */
export const SCHEMA_IDS: Record<string, string> = {
	character: '18',
	player: '19',
	ability: '20',
	skill: '21',
	item: '22',
	condition: '23',
	effect: '24',
	event: '25',
}

/**
 * Resolve register + schema ids from LarpingApp's own settings API — the
 * single source of truth the app itself reads at runtime — and mutate
 * REGISTER_ID / SCHEMA_IDS in place. Keeps the e2e workflow correct after a
 * schema rename or any instance-specific id reassignment instead of failing on
 * a stale hardcoded id. Falls back to the bootstrap literals on any error.
 */
export async function resolveSchemaIds(api: APIRequestContext): Promise<void> {
	const res = await api.get(SETTINGS_API, { headers: { 'OCS-APIRequest': 'true' } })
	if (!res.ok()) {
		return
	}
	const json = await res.json().catch(() => null)
	const cfg = json?.configuration
	if (!cfg || typeof cfg !== 'object') {
		return
	}
	if (typeof cfg.register === 'string' && cfg.register !== '') {
		REGISTER_ID = cfg.register
	}
	for (const type of Object.keys(SCHEMA_IDS)) {
		const id = cfg[`${type}_schema`]
		if (typeof id === 'string' && id !== '') {
			SCHEMA_IDS[type] = id
		}
	}
}

/** Unique run id so parallel/serial runs never collide and cleanup is exact. */
export const RUN_ID = `e2e-${Date.now()}-${Math.floor(Math.random() * 1e4)}`

/** Prefix every fixture name with this so afterAll can find + purge them. */
export function fixtureName(label: string): string {
	return `${RUN_ID}-${label}`
}

const AUTH = {
	username: process.env.NC_USER || 'admin',
	password: process.env.NC_PASS || 'admin',
}

const HEADERS = { 'OCS-APIRequest': 'true', 'Content-Type': 'application/json' }

/** Build an authenticated APIRequestContext against the OpenRegister REST API. */
export async function newApi(): Promise<APIRequestContext> {
	return request.newContext({ httpCredentials: AUTH })
}

/**
 * Tracks every UUID this run created, keyed by entity type, so afterAll can
 * delete them in dependency-safe order (characters first, then carriers,
 * then effects, then abilities).
 */
export class FixtureLedger {

	private created: Record<string, string[]> = {}

	track(type: string, id: string): string {
		(this.created[type] ??= []).push(id)
		return id
	}

	ids(type: string): string[] {
		return this.created[type] ?? []
	}

}

/** Create one OpenRegister object; returns its UUID (real saveObject path). */
export async function createObject(
	api: APIRequestContext,
	type: string,
	body: Record<string, unknown>,
): Promise<string> {
	const url = `${OR_BASE}/${REGISTER_ID}/${SCHEMA_IDS[type]}`
	// Mirror `name` into `title` (and vice versa) so a create satisfies the
	// schema whichever of the two it marks required.
	//
	// This is not cosmetic: schema slugs COLLIDE across the fleet. The `skill`
	// schema larpingapp is configured against (id 21) is shared by 12
	// registers (pipelinq, procest, shillinq, openconnector, …) and currently
	// declares `required: ["title"]`, while larpingapp's own
	// `lib/Settings/larpingapp_register.json` declares `name`. Whichever app
	// imported last wins, so a payload keyed only on `name` fails with
	// HTTP 400 "The required property (title) is missing".
	// See also: the hardcoded `item` id bound to scholiq's QTI schema until
	// `resolveSchemaIds()` was wired in (larpingapp's real one is
	// `larping_item`). Cross-app slug collision — OR #2150 class.
	const payload: Record<string, unknown> = { ...body }
	if (payload.name != null && payload.title == null) {
		payload.title = payload.name
	} else if (payload.title != null && payload.name == null) {
		payload.name = payload.title
	}
	const res = await api.post(url, { headers: HEADERS, data: payload })
	if (!res.ok()) {
		throw new Error(`create ${type} failed: HTTP ${res.status()} ${await res.text()}`)
	}
	const json = await res.json()
	const id = json?.['@self']?.id || json?.id
	if (!id) {
		throw new Error(`create ${type} returned no id: ${JSON.stringify(json).slice(0, 200)}`)
	}
	return id
}

/** Read one object back by UUID (real find() path). Returns the rendered object. */
export async function getObject(
	api: APIRequestContext,
	type: string,
	id: string,
): Promise<Record<string, unknown> | null> {
	const url = `${OR_BASE}/${REGISTER_ID}/${SCHEMA_IDS[type]}/${id}`
	const res = await api.get(url, { headers: HEADERS })
	if (!res.ok()) {
		return null
	}
	return res.json()
}

/** Full-replace update of one object (real saveObject/PUT path). */
export async function updateObject(
	api: APIRequestContext,
	type: string,
	id: string,
	body: Record<string, unknown>,
): Promise<Record<string, unknown>> {
	const url = `${OR_BASE}/${REGISTER_ID}/${SCHEMA_IDS[type]}/${id}`
	const res = await api.put(url, { headers: HEADERS, data: body })
	if (!res.ok()) {
		throw new Error(`update ${type}/${id} failed: HTTP ${res.status()} ${await res.text()}`)
	}
	return res.json()
}

/** Delete one object by UUID. Returns true on 2xx. */
export async function deleteObject(
	api: APIRequestContext,
	type: string,
	id: string,
): Promise<boolean> {
	const url = `${OR_BASE}/${REGISTER_ID}/${SCHEMA_IDS[type]}/${id}`
	const res = await api.delete(url, { headers: HEADERS })
	return res.ok()
}

/**
 * Tear down everything a ledger created. Deletes characters first (so the
 * carrier/effect/ability rows they reference are no longer linked), then
 * carriers, effects, and abilities. Best-effort: a failed delete is logged
 * but does not fail the run (the unique RUN_ID prefix keeps leftovers
 * identifiable).
 */
export async function cleanupLedger(api: APIRequestContext, ledger: FixtureLedger): Promise<void> {
	const order = ['character', 'event', 'condition', 'item', 'skill', 'effect', 'ability', 'player']
	for (const type of order) {
		for (const id of ledger.ids(type)) {
			const ok = await deleteObject(api, type, id).catch(() => false)
			if (!ok) {
				// eslint-disable-next-line no-console
				console.warn(`[workflows cleanup] could not delete ${type}/${id} (RUN_ID=${RUN_ID})`)
			}
		}
	}
}

/**
 * Seed a complete stat-computation scenario:
 *   ability "strength" (base 10)
 *   effect  "mighty"  (+3 strength, positive, cumulative)
 *   skill   "power"   (carries the mighty effect)
 *   character "hero"  (has the power skill)
 *
 * Returns all four UUIDs plus the expected computed value so the workflow
 * can assert base(10) + modifier(3) = 13.
 */
export async function seedStatScenario(
	api: APIRequestContext,
	ledger: FixtureLedger,
	opts: { base?: number; modifier?: number; modification?: 'positive' | 'negative' } = {},
): Promise<{
	abilityId: string
	effectId: string
	skillId: string
	characterId: string
	base: number
	modifier: number
	expected: number
}> {
	const base = opts.base ?? 10
	const modifier = opts.modifier ?? 3
	const modification = opts.modification ?? 'positive'

	const abilityId = ledger.track('ability', await createObject(api, 'ability', {
		name: fixtureName('strength'),
		description: 'base strength stat',
		base,
	}))
	const effectId = ledger.track('effect', await createObject(api, 'effect', {
		name: fixtureName('mighty'),
		description: `${modification} ${modifier} to strength`,
		modifier,
		modification,
		cumulative: 'cumulative',
		abilities: [abilityId],
	}))
	const skillId = ledger.track('skill', await createObject(api, 'skill', {
		name: fixtureName('power'),
		description: 'carries the mighty effect',
		effects: [effectId],
	}))
	// `character.ocName` is a RELATION, not a display name: the live schema
	// declares it `{"type":"string","format":"uuid","$ref":"player"}` ("The
	// player who plays this character") and marks it `required`. Passing the
	// character's own name string is rejected with HTTP 400
	// "Property 'ocName' should match format 'uuid'", which failed every test
	// in this file. So seed a real `player` row first and reference its UUID.
	// (`x-allow-create: true` is a picker affordance for the Vue relation
	// widget — it does NOT make the REST API accept a bare name.)
	const playerId = ledger.track('player', await createObject(api, 'player', {
		name: fixtureName('hero-player'),
	}))
	const characterId = ledger.track('character', await createObject(api, 'character', {
		name: fixtureName('hero'),
		ocName: playerId,
		type: 'player',
		skills: [skillId],
	}))

	const expected = modification === 'positive' ? base + modifier : base - modifier
	return { abilityId, effectId, skillId, characterId, base, modifier, expected }
}

export interface ComputedStat {
	base: number | null
	value: number | null
	audit: unknown[]
}

/** IDs of the persisted OR rows that make up a stat-computation scenario. */
export interface StatScenarioIds {
	characterId: string
	abilityId: string
	effectId: string
	skillId?: string
	itemId?: string
	conditionId?: string
}

/**
 * Run the REAL CharacterService::calculateCharacter() against REAL persisted
 * OpenRegister rows and return the computed ability score.
 *
 * The computation is not exposed through any HTTP controller or the SPA (only
 * CharactersController::downloadPdf exists), so this drives it inside the
 * nextcloud container via a short bootstrap script. To assert the genuine
 * arithmetic against genuinely persisted data while OpenRegister's findAll is
 * broken in this environment (FINDALL_EMPTY_BLOCKER — see the spec), the
 * harness reads each scenario entity back by UUID through the working
 * RegisterObjectFetcher::getObject() (find) path, then injects those real rows
 * into the service's indexed collections, bypassing ONLY the broken findAll
 * loader. The arithmetic under test is untouched and exercises the true path:
 *   calculateCharacter -> applyEntityEffects -> applyEffects
 *     -> calculateEffect -> applyModifierToAbility
 *
 * Returns null if the in-container harness cannot run at all (e.g. no docker).
 * Returns { base: null, value: null } only if the persisted rows could not be
 * read — which would itself be a defect the caller asserts against.
 */
export async function computeCharacterStat(ids: StatScenarioIds): Promise<ComputedStat | null> {
	const { execSync } = await import('child_process')
	const fs = await import('fs')
	const os = await import('os')
	const path = await import('path')

	// PHP harness: read persisted rows by UUID (working find path), inject into
	// the real service, call the real calculateCharacter().
	const php = (bootstrap: string) => [
		'<?php',
		`require_once '${bootstrap}';`,
		'$server = \\OC::$server;',
		'$fetcher = $server->get(\\OCA\\LarpingApp\\Service\\RegisterObjectFetcher::class);',
		'$svc = $server->get(\\OCA\\LarpingApp\\Service\\CharacterService::class);',
		'$charId=$argv[1]; $abId=$argv[2]; $efId=$argv[3];',
		'$skId=$argv[4]??""; $itId=$argv[5]??""; $cdId=$argv[6]??"";',
		'$g=function($t,$id) use($fetcher){ return $id!=="" ? $fetcher->getObject($t,$id) : null; };',
		'$character=$fetcher->getObject("character",$charId);',
		'$ro=new ReflectionObject($svc);',
		'$set=function($n,$v) use($svc,$ro){ $p=$ro->getProperty($n); $p->setAccessible(true); $p->setValue($svc,$v); };',
		'$idx=function($e){ return $e ? [(string)$e["id"]=>$e] : []; };',
		'$set("allAbilities",$idx($g("ability",$abId)));',
		'$set("allEffects",$idx($g("effect",$efId)));',
		'$set("allSkills",$idx($g("skill",$skId)));',
		'$set("allItems",$idx($g("item",$itId)));',
		'$set("allConditions",$idx($g("condition",$cdId)));',
		'$set("allEvents",[]);',
		'$set("entitiesLoaded",true);',
		'$result=$svc->calculateCharacter($character);',
		'$ab=($result["stats"] ?? [])[$abId] ?? null;',
		'echo json_encode(["base"=>$ab["base"]??null,"value"=>$ab["value"]??null,"audit"=>$ab["audit"]??[]]);',
	].join('\n')

	const args = [ids.characterId, ids.abilityId, ids.effectId, ids.skillId || '', ids.itemId || '', ids.conditionId || '']
	return runPhpHarness(php, args, { fs, os, path, execSync })
}

/**
 * Run the REAL CharacterService::calculateCharacter() via its NATIVE
 * loadAllEntities()/findAll loader (no reflection injection), against a
 * persisted character. This is the genuine, un-assisted production path the
 * app would use to surface stats.
 *
 * On a healthy instance it returns the computed score. On THIS instance it
 * returns { base: null, value: null } because OR's findAll yields no abilities
 * (FINDALL_EMPTY_BLOCKER) — which is exactly the live defect the dedicated
 * test.fixme records. Kept separate from computeCharacterStat so the
 * correctness tests can still assert the arithmetic on real persisted rows.
 */
export async function computeCharacterStatLive(characterId: string, abilityId: string): Promise<ComputedStat | null> {
	const { execSync } = await import('child_process')
	const fs = await import('fs')
	const os = await import('os')
	const path = await import('path')

	const php = (bootstrap: string) => [
		'<?php',
		`require_once '${bootstrap}';`,
		'$server = \\OC::$server;',
		'$fetcher = $server->get(\\OCA\\LarpingApp\\Service\\RegisterObjectFetcher::class);',
		'$svc = $server->get(\\OCA\\LarpingApp\\Service\\CharacterService::class);',
		'$character=$fetcher->getObject("character",$argv[1]);',
		'$result=$svc->calculateCharacter($character);',
		'$ab=($result["stats"] ?? [])[$argv[2]] ?? null;',
		'echo json_encode(["base"=>$ab["base"]??null,"value"=>$ab["value"]??null,"audit"=>$ab["audit"]??[]]);',
	].join('\n')

	return runPhpHarness(php, [characterId, abilityId], { fs, os, path, execSync })
}

/* eslint-disable @typescript-eslint/no-explicit-any */
/**
 * Locate the Nextcloud server root this checkout is installed INTO, by walking
 * up from this file until `lib/base.php` and `config/config.php` are both
 * present.
 *
 * WHY THIS EXISTS
 * ---------------
 * The harness below used to be docker-only: `docker cp` + `docker exec … nextcloud`
 * with the bootstrap hardcoded to `/var/www/html/lib/base.php`. That is correct
 * on a developer box and WRONG everywhere else — most importantly on the shared
 * `E2E Tests (Playwright)` CI job, which has no docker daemon and no container
 * called `nextcloud`. It checks the server out to `$GITHUB_WORKSPACE/server`,
 * the app to `server/apps/larpingapp`, and serves it with `php -S`.
 *
 * The failure that produces is worth naming, because it is not loud: `execSync`
 * throws, the catch returns `null`, and two of the three computation tests are
 * guarded by `test.skip(computed === null, …)`. So on CI they would report as
 * SKIPPED — a conclusion that looks like a pass in every summary — while the
 * third (`live:`) asserts `expect(computed).not.toBeNull()` and fails for a
 * reason ("harness not runnable") that has nothing to do with the stat
 * arithmetic it claims to be testing.
 *
 * Both are harness faults, not product bugs, and both are fixed by running the
 * bootstrap in-process where there is no container to exec into.
 *
 * @param {any} path Node's `path` module (injected — this file imports lazily).
 * @param {any} fs   Node's `fs` module (injected).
 * @return {string|null} Absolute server root, or null when not found.
 */
function findServerRoot(path: any, fs: any): string | null {
	// Explicit override wins: a checkout served from an unusual layout can say so.
	const override = process.env.NC_SERVER_ROOT
	if (override && fs.existsSync(path.join(override, 'lib', 'base.php'))) {
		return override
	}
	let dir = __dirname
	for (let up = 0; up < 8; up++) {
		if (fs.existsSync(path.join(dir, 'lib', 'base.php'))
			&& fs.existsSync(path.join(dir, 'config', 'config.php'))) {
			return dir
		}
		const parent = path.dirname(dir)
		if (parent === dir) break
		dir = parent
	}
	return null
}

function runPhpHarness(
	php: (bootstrap: string) => string,
	args: string[],
	deps: { fs: any; os: any; path: any; execSync: any },
): ComputedStat | null {
	const { fs, os, path, execSync } = deps
	const argStr = args.map(a => `'${a}'`).join(' ')

	/**
	 * Parse the harness stdout. The bootstrap can emit deprecation notices
	 * before our JSON, so slice from the first `{`.
	 *
	 * @param {string} out Raw stdout.
	 * @return {ComputedStat|null} Parsed result, or null when nothing usable.
	 */
	const parse = (out: string): ComputedStat | null => {
		const jsonStart = out.indexOf('{')
		if (jsonStart < 0) return null
		try {
			return JSON.parse(out.slice(jsonStart))
		} catch {
			return null
		}
	}

	// ── Path A: this checkout IS inside a Nextcloud server root (CI runner,
	// or any non-docker install). Run the bootstrap directly — same PHP binary
	// and same config.php the `php -S` instance under test is using.
	const serverRoot = findServerRoot(path, fs)
	if (serverRoot !== null) {
		const script = path.join(os.tmpdir(), `la-calc-${Date.now()}-${Math.floor(Math.random() * 1e6)}.php`)
		try {
			fs.writeFileSync(script, php(path.join(serverRoot, 'lib', 'base.php')))
			const out: string = execSync(`php ${script} ${argStr}`, {
				encoding: 'utf-8',
				timeout: 120_000,
				cwd: serverRoot,
			})
			return parse(out)
		} catch (err) {
			// eslint-disable-next-line no-console
			console.warn(`[stat harness] in-process run failed under ${serverRoot}: ${(err as Error).message}`)
			return null
		} finally {
			try { fs.unlinkSync(script) } catch { /* noop */ }
		}
	}

	// ── Path B: developer box — the app is bind-mounted into the `nextcloud`
	// container and its server root is not an ancestor of this file.
	const localTmp = path.join(os.tmpdir(), `la-calc-${Date.now()}-${Math.floor(Math.random() * 1e6)}.php`)
	const containerName = `la-calc-${path.basename(localTmp)}`
	try {
		fs.writeFileSync(localTmp, php('/var/www/html/lib/base.php'))
		execSync(`docker cp ${localTmp} nextcloud:/tmp/${containerName}`, { stdio: 'pipe' })
		const out: string = execSync(
			`docker exec -u www-data nextcloud php /tmp/${containerName} ${argStr}`,
			{ encoding: 'utf-8', timeout: 60_000 },
		)
		return parse(out)
	} catch {
		return null
	} finally {
		try { fs.unlinkSync(localTmp) } catch { /* noop */ }
		try { execSync(`docker exec -u root nextcloud rm -f /tmp/${containerName}`, { stdio: 'pipe' }) } catch { /* noop */ }
	}
}
/* eslint-enable @typescript-eslint/no-explicit-any */
