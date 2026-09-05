// SPDX-License-Identifier: EUPL-1.2
// SPDX-FileCopyrightText: 2026 Conduction B.V.
//
// eslint 10 + @nextcloud/eslint-config 9, matching what Nextcloud's own apps run
// (nextcloud/forms is the reference). Flat config, ESM.
//
// WHY THIS FILE IS `.mjs` AND package.json IS STILL COMMONJS
// ---------------------------------------------------------
// `@nextcloud/eslint-config@9` is `"type": "module"`, so the config that imports
// it has to be ESM. forms achieves that by setting `"type": "module"` on the
// whole package — this app cannot: `webpack.config.js`, `vitest.config.js` and
// the `tests/validate-*.js` CLI scripts are all CommonJS and would stop parsing.
// Naming the config `.mjs` scopes the module system to the one file that needs
// it, which is the smaller change and touches nothing else.
//
// WHAT DISAPPEARED FROM THIS FILE, AND WHY THAT IS CORRECT
// -------------------------------------------------------
// `conductionVue3Fixes` (from @conduction/nextcloud-vue/eslint) is GONE, and
// with it the FlatCompat bridge to the old eslintrc-style `@nextcloud` config.
// That preset existed to patch a Vue-2-era ruleset. Measured against
// @nextcloud/eslint-config@9's `recommended` on this app, every one of its jobs
// is already done:
//
//   21 `vue/no-deprecated-*` rules   present and ON, all 21 — the preset's whole
//                                    purpose. Verified with `--print-config`.
//   `vue/no-v-model-argument`        not enabled at all, so nothing to disable
//   `vue/no-v-for-template-key`      not enabled at all
//   object-form `vue-eslint-parser`  supplied by the config itself
//
// Keeping it would have re-asserted opinions the library has since revised —
// exactly the "a local copy carries a stale opinion" failure its own comment
// warned about.
import { recommended } from '@nextcloud/eslint-config'
import eslintConfigPrettier from 'eslint-config-prettier'

export default [
	...recommended,

	{
		// 🔴 SCOPED, for the same reason the TS block below is. v9 lints `.json`
		// too (it ships `@eslint/json`), and the `jsdoc` plugin is not registered
		// for those files — an unscoped `jsdoc/*` rule aborts the whole run with
		// "The jsdoc plugin is not defined in your configuration file". Flat config
		// resolves a rule's plugin from the object the rule sits in, so an override
		// must name files whose config already registers it.
		files: ['**/*.js', '**/*.mjs', '**/*.ts', '**/*.tsx', '**/*.vue'],
		// 🔴 THE `ignores` ARE PART OF THAT SCOPE, NOT AN OPINION. v9 registers
		// the jsdoc plugin ONLY inside `nextcloud/documentation/*`, and every one
		// of those blocks carries exactly this ignore list: Nextcloud does not
		// require JSDoc in tests. Naming a `jsdoc/*` rule for a test file
		// therefore points at a plugin that is not registered there, and eslint
		// refuses to run AT ALL rather than reporting a finding.
		//
		// Measured here: with the list absent, `eslint tests` died with "A
		// configuration object specifies rule 'jsdoc/check-tag-names', but could
		// not find plugin 'jsdoc'" and linted nothing. `eslint src` was green
		// throughout, because it never reached a test file.
		ignores: [
			'**/*.test.*',
			'**/*.spec.*',
			'**/*.cy.*',
			'**/test/**',
			'**/tests/**',
			'**/__tests__/**',
			'**/__mocks__/**',
		],
		rules: {
			// `@spec` (hydra gate-16 / gate-19 traceability) and `@visual` (the
			// visual-coverage gate) are this project's own JSDoc tags. v9 sets
			// `jsdoc/check-tag-names` to `[2, { typed: false }]` with NO
			// `definedTags`, so without this every annotation reports as an
			// invalid tag name — 71 of them on this app under the old config.
			//
			// It must be passed as RULE OPTIONS. Once an extended preset has
			// configured the rule, the rule reads `definedTags` from its own
			// options object and `settings.jsdoc.definedTags` is ignored.
			'jsdoc/check-tag-names': ['error', { definedTags: ['spec', 'visual'] }],

		},
	},

	{
		// `t` and `n` are imported for translation wiring that is not always called
		// yet. For `.ts`/`.vue` v9 turns the CORE `no-unused-vars` off and drives
		// `@typescript-eslint/no-unused-vars` instead, so the ignore pattern has to
		// be set on the TS rule.
		//
		// ⚠️ This swap is per-file-type, NOT global: `--print-config src/main.js`
		// reports `@typescript-eslint/no-unused-vars: undefined` and core
		// `no-unused-vars: [2, …]`. So a plain `.js` file gets NO ignore pattern
		// from this block. Left that way deliberately — `t`/`n` are imported in
		// components, and widening it to `.js` would hide genuinely dead bindings.
		//
		// 🔴 AND IT MUST BE SCOPED. Flat config resolves a rule's plugin from the
		// config object it appears in. Setting this in an unscoped object applies it
		// to plain `.js` too, where v9 has not registered `@typescript-eslint`, and
		// eslint then refuses to run at all:
		//
		//   A configuration object specifies rule
		//   "@typescript-eslint/no-unused-vars", but could not find plugin
		//   "@typescript-eslint".
		//
		// That is a hard config error, not a lint finding — it takes out every file
		// in the same invocation. Found by planting a violation in a plain `.js`
		// probe; `eslint src` had passed without complaint beforehand, because the
		// error only fires once a file the object matches is actually linted.
		files: ['**/*.ts', '**/*.tsx', '**/*.vue'],
		rules: {
			'@typescript-eslint/no-unused-vars': [
				'error',
				{
					varsIgnorePattern: '^(t|n)$',
					argsIgnorePattern: '^_',
					ignoreRestSiblings: true,
				},
			],
		},
	},

	{
		// `require.context()` is a webpack compile-time construct, not a runtime
		// CommonJS call: webpack rewrites it into a generated module map. The file
		// is otherwise ESM, so `no-undef` is right to say `require` is not defined
		// — declaring the global describes the build environment rather than
		// relaxing the rule, and it stays scoped to the one file that needs it.
		files: ['src/main.js'],
		languageOptions: {
			globals: { require: 'readonly' },
		},
	},

	{
		// Node-side CLI checkers under tests/ legitimately use console and
		// process.exit, and ship as plain JS with no shebang.
		//
		// 🔴 A GLOB, NOT A FILE LIST. This block used to name three files by hand
		// (`validate-manifest.js`, `validate-register.js`, `validate-json-strict.js`)
		// and had silently stopped covering every checker added since. That is the
		// failure mode a hand-maintained list always has: adding a file does not
		// add it to the list, and the omission is invisible.
		files: ['tests/**/*.js', 'tests/**/*.mjs', 'tests/**/*.ts'],
		rules: {
			'no-console': 'off',
			'n/no-process-exit': 'off',
			'n/hashbang': 'off',
			// Tests import devDependencies by definition; this rule is about what
			// ships in the published package, which tests/ never does.
			'n/no-unpublished-import': 'off',
		},
	},

	{
		// 🔴 Node-side CLI tooling under `scripts/`, which is COMMONJS. Flat
		// config defaults every `.js` to ESM with browser-ish globals, so without
		// this block eslint reports the CommonJS wrapper itself as undefined
		// identifiers. Measured on this app: 52 of the 233 errors under
		// `tests/` + `scripts/` were `no-undef`, ALL of them in `scripts/`, and
		// all five names were the environment rather than a typo — `process` 23,
		// `require` 20, `__dirname` 6, `__filename` 2, `module` 1.
		//
		// This is describing the environment, not relaxing a rule, and it is the
		// same argument the test-globals block below makes: declaring them keeps
		// `no-undef` able to do its real job, which is catching a genuinely
		// misspelled identifier. Suppressing the rule instead would bury that.
		//
		// `no-console` is off because printing its report is what a CLI checker
		// is FOR.
		//
		// 🔴 NO `n/*` ENTRIES HERE, DELIBERATELY. `eslint-plugin-n` is NOT
		// registered for these files under eslint 10 + @nextcloud/eslint-config
		// 9, so `'n/no-process-exit': 'off'` would be dead config that reads as
		// if it were doing something. Measured both ways on this app: 0 `n/`
		// findings with the entries and 0 without.
		//
		// What DID report was the opposite — four `scripts/*.js` carried
		// `/* eslint-disable n/no-process-exit */` and `/* eslint-disable
		// n/shebang */` left over from the eslintrc era, and an inline disable
		// naming an unregistered plugin is itself an error ("Definition for rule
		// 'n/shebang' was not found"). Those 8 comments are removed; do not add
		// `n/*` rules back to replace them.
		//
		// ⚠️ `.js` and `.cjs` ONLY. A `scripts/*.mjs` is genuinely ESM and must
		// keep the default `sourceType`, or `import` stops parsing there.
		files: ['scripts/**/*.js', 'scripts/**/*.cjs'],
		languageOptions: {
			sourceType: 'commonjs',
			globals: {
				require: 'readonly',
				module: 'writable',
				exports: 'writable',
				process: 'readonly',
				__dirname: 'readonly',
				__filename: 'readonly',
				console: 'readonly',
				Buffer: 'readonly',
				global: 'readonly',
				URL: 'readonly',
				TextEncoder: 'readonly',
				TextDecoder: 'readonly',
			},
		},
		rules: {
			'no-console': 'off',
		},
	},

	{
		// The ESM half of the block above. A `scripts/*.mjs` is genuinely a module
		// and must keep the default `sourceType`, so it gets Node's globals but
		// none of the CommonJS wrapper. Measured: `process` reported undefined 2x
		// in hermiq's generate-opengemeenten-icons.mjs and 4x in openregister's
		// l10n/runtime-check.mjs, which the `.js`/`.cjs` block deliberately does
		// not match.
		files: ['scripts/**/*.mjs', 'tests/**/*.mjs'],
		languageOptions: {
			globals: {
				process: 'readonly',
				console: 'readonly',
				Buffer: 'readonly',
				global: 'readonly',
				URL: 'readonly',
				TextEncoder: 'readonly',
				TextDecoder: 'readonly',
			},
		},
		rules: {
			'no-console': 'off',
		},
	},

	{
		// eslint must not try to PARSE a shell script. `tests/e2e/seed.test.sh`
		// matches the `**/*.test.*` glob some presets use, and eslint then reads
		// it as JavaScript and reports "Parsing error: Unexpected character" —
		// a finding about a file it should never have opened.
		ignores: ['**/*.sh', '**/*.bash'],
	},


	// eslint-config-prettier LAST OF ALL, and it has to be last: it only turns
	// rules OFF, and what it turns off is everything prettier owns — including
	// the `@stylistic/*` family v9 introduces (`indent`, `quotes`, `semi`).
	//
	// Those three AGREE with @nextcloud/prettier-config (tab / single / never),
	// which is why Nextcloud ships both packages. Agreement is not the point
	// though: two tools formatting the same bytes is the unfixable state this
	// fleet already hit with php-cs-fixer and PHPCS, so exactly one of them is
	// allowed an opinion and prettier is it. Prettier runs as its own CI job.
	//
	// NOTE: forms uses `eslint-plugin-prettier/recommended`, which additionally
	// reports prettier violations AS eslint errors. Deliberately not adopted —
	// this fleet already runs `prettier --check` as a separate gate, and doing
	// both means one defect reported twice in two places.
	eslintConfigPrettier,

	{
		// AFTER eslint-config-prettier, because prettier's config does NOT cover
		// this rule and it has to stay off. `@stylistic/exp-list-style` rewrites a
		// wrapped expression list to put a trailing comma before the closing paren
		// (`… : v,)`), which prettier then reformats back — the two tools fight
		// over the same bytes forever. `nextcloud/forms` disables exactly this one
		// rule in its own flat config for the same reason, so switching it off is
		// matching Nextcloud's own resolution, not diverging from it.
		name: 'conduction/prettier-jurisdiction',
		rules: {
			'@stylistic/exp-list-style': 'off',
		},
	},
]
