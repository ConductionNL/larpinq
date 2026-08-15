#!/usr/bin/env node
/**
 * gate: lockfile-matches-install
 *
 * Fails when the installed node_modules tree disagrees with package-lock.json.
 *
 * Why this gate exists (2026-08-15): six app checkouts had vue-router 3.6.5 —
 * the `legacy`, Vue 2 line — installed while their lockfiles pinned 4.6.4.
 * webpack compiled all of them with NO error and NO warning, and the resulting
 * bundles threw `createWebHashHistory is not a function` at runtime, so the app
 * silently never mounted and the page rendered as bare Nextcloud chrome.
 *
 * CI never saw it: CI runs `npm ci`, which installs the lockfile by definition.
 * The divergence only exists on a developer machine — and a bundle built there
 * is a green build of a broken app.
 *
 * `npm ls` is deliberately NOT used: it compares the tree against package.json
 * RANGES, so it also flags every unrelated peer/dev drift. Measured on this
 * fleet it reported 10-14 "invalid" entries per healthy-looking app, which is
 * far too noisy to gate on — a gate that always fires is a gate nobody reads.
 * Comparing against the LOCKFILE is the exact invariant we care about: "what I
 * build here is what CI builds".
 *
 * Exit codes: 0 = clean (or lockfile/tree absent), 1 = runtime dependency drift.
 * Dev-dependency drift is reported but does not fail, since it cannot ship.
 */
const fs = require('fs')
const path = require('path')

const root = process.cwd()
const read = (p) => {
	try {
		return JSON.parse(fs.readFileSync(p, 'utf8'))
	} catch (e) {
		return null
	}
}

const pkg = read(path.join(root, 'package.json'))
const lock = read(path.join(root, 'package-lock.json'))

if (!pkg || !lock || !lock.packages) {
	console.log(
		'[lockfile-matches-install] no package.json/package-lock.json — skipped',
	)
	process.exit(0)
}
if (!fs.existsSync(path.join(root, 'node_modules'))) {
	console.log(
		'[lockfile-matches-install] no node_modules — skipped (nothing built yet)',
	)
	process.exit(0)
}

const runtime = new Set(Object.keys(pkg.dependencies || {}))
const drift = { runtime: [], dev: [], missing: [] }

for (const [entry, meta] of Object.entries(lock.packages)) {
	// Top-level installs only. Nested copies (node_modules/x/node_modules/y) are
	// npm's own conflict resolution and are expected to differ.
	if (!entry.startsWith('node_modules/')) continue
	const name = entry.slice('node_modules/'.length)
	if (name.includes('/node_modules/')) continue
	if (!meta || !meta.version) continue
	// A dep npm chose not to install here (optional, or platform-specific) is
	// not drift.
	if (
		meta.optional === true
		|| (meta.dev === true && !fs.existsSync(path.join(root, entry)))
	)
		continue

	const installed = read(path.join(root, entry, 'package.json'))
	if (!installed) {
		if (runtime.has(name)) drift.missing.push({ name, want: meta.version })
		continue
	}
	if (installed.version !== meta.version) {
		const row = { name, want: meta.version, got: installed.version }
		;(runtime.has(name) ? drift.runtime : drift.dev).push(row)
	}
}

const fmt = (r) =>
	`  ${r.name.padEnd(38)} lock ${String(r.want).padEnd(14)} installed ${r.got || '(absent)'}`

if (drift.dev.length) {
	console.log(
		`[lockfile-matches-install] ${drift.dev.length} dev-dependency drift (not fatal):`,
	)
	drift.dev.slice(0, 10).forEach((r) => console.log(fmt(r)))
}

if (!drift.runtime.length && !drift.missing.length) {
	console.log(
		'[lockfile-matches-install] OK — installed runtime tree matches the lockfile',
	)
	process.exit(0)
}

console.error('')
console.error(
	'[lockfile-matches-install] FAIL — the installed tree does not match package-lock.json.',
)
console.error('A build from this tree compiles clean and ships broken code.')
console.error('')
drift.runtime.forEach((r) => console.error(fmt(r)))
drift.missing.forEach((r) => console.error(fmt(r)))
console.error('')
console.error(
	'Fix: npm ci    (npm install will NOT repair this — it keeps an already-present version,',
)
console.error(
	'               and it also skips postinstall for packages that are already there.)',
)
process.exit(1)
