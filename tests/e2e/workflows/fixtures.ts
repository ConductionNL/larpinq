/*
 * SPDX-FileCopyrightText: 2026 Larping Contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
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

export const BASE = '/apps/larpingapp'
export const OR_BASE = 'http://localhost:8080/index.php/apps/openregister/api/objects'

/** Register the app's data actually lives in (numeric, not the 156 slug-dupe). */
export const REGISTER_ID = process.env.LARP_REGISTER_ID || '8'

/** Numeric OpenRegister schema ids per entity type on the dev instance. */
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
	const res = await api.post(url, { headers: HEADERS, data: body })
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
	const characterId = ledger.track('character', await createObject(api, 'character', {
		name: fixtureName('hero'),
		ocName: fixtureName('hero'),
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
	const php = [
		'<?php',
		"require_once '/var/www/html/lib/base.php';",
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

	const php = [
		'<?php',
		"require_once '/var/www/html/lib/base.php';",
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
function runPhpHarness(
	php: string,
	args: string[],
	deps: { fs: any; os: any; path: any; execSync: any },
): ComputedStat | null {
	const { fs, os, path, execSync } = deps
	const localTmp = path.join(os.tmpdir(), `la-calc-${Date.now()}-${Math.floor(Math.random() * 1e6)}.php`)
	const containerName = `la-calc-${path.basename(localTmp)}`
	try {
		fs.writeFileSync(localTmp, php)
		execSync(`docker cp ${localTmp} nextcloud:/tmp/${containerName}`, { stdio: 'pipe' })
		const argStr = args.map(a => `'${a}'`).join(' ')
		const out: string = execSync(
			`docker exec -u www-data nextcloud php /tmp/${containerName} ${argStr}`,
			{ encoding: 'utf-8', timeout: 60_000 },
		)
		const jsonStart = out.indexOf('{')
		if (jsonStart < 0) return null
		return JSON.parse(out.slice(jsonStart))
	} catch {
		return null
	} finally {
		try { fs.unlinkSync(localTmp) } catch { /* noop */ }
		try { execSync(`docker exec -u root nextcloud rm -f /tmp/${containerName}`, { stdio: 'pipe' }) } catch { /* noop */ }
	}
}
/* eslint-enable @typescript-eslint/no-explicit-any */
