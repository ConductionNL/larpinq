// SPDX-License-Identifier: EUPL-1.2
// SPDX-FileCopyrightText: 2026 Conduction B.V.

// ─────────────────────────────────────────────────────────────────────────────
// WHY THIS FILE IS `.mjs` AND NOT `"type": "module"` + `eslint.config.js`
// ─────────────────────────────────────────────────────────────────────────────
// `@nextcloud/eslint-config@9` is an ESM-only package, so the config that
// consumes it has to be an ES module. The reference implementation
// (`nextcloud/forms`) gets there by putting `"type": "module"` in package.json
// and keeping the config at `eslint.config.js`.
//
// That works for forms because forms is a Vite app whose every remaining `.js`
// file is already ESM. It does NOT work here. `"type": "module"` reinterprets
// EVERY `.js` file in the package, and this repo has 20 non-`src` ones that are
// CommonJS today:
//
//   webpack.config.js, stylelint.config.js, vitest.config.js,
//   tests/validate-{manifest,register,json-strict}.js,
//   tests/l10n/check-l10n{,-parity}.js,
//   tests/vitest/*.spec.js (5), tests/vitest/stubs/*.js (2),
//   docs/docusaurus.config.js, docs/sidebars.js, docs/src/**/*.js (2)
//
// Flipping the flag converts all of them at once — a webpack-config, a
// stylelint-config, a vitest-config and the whole unit-test suite — which is a
// separate migration with its own failure modes, bundled into a lint change
// where any breakage it caused would be indistinguishable from breakage the
// lint migration caused.
//
// `eslint.config.mjs` is a first-class, documented ESLint config filename and
// is genuinely ESM. It buys the exact thing the migration needs (an ES module
// that can `import` an ESM-only preset) at zero blast radius. The `"type":
// "module"` flip is worth doing on its own merits, later, as its own change.
// ─────────────────────────────────────────────────────────────────────────────

import { recommendedJavascript } from '@nextcloud/eslint-config'
import eslintConfigPrettier from 'eslint-config-prettier'
import vuePlugin from 'eslint-plugin-vue'

// The SHARED Conduction Vue 3 fix layer, shipped inside the component library
// so every app arms the same gate from one import instead of hand-rolling it.
//
// It supplies: `ecmaVersion: 'latest'` on both `languageOptions` and
// `languageOptions.parserOptions`; `vue-eslint-parser` wired in the documented
// OBJECT `parser: { js, ts }` form; the complete `vue/no-deprecated-*` family
// at `error`; `vue/v-on-event-hyphenation` with `update:modelValue` excluded;
// and the three INVERTED Vue-2 rules (`vue/no-v-model-argument`,
// `vue/no-v-for-template-key`, `vue/no-multiple-template-root`) switched off.
//
// VERIFIED against eslint-plugin-vue@10 (which `@nextcloud/eslint-config@9`
// pulls in place of the v9 this app used to carry): all 21 `vue/no-deprecated-*`
// rules still exist under the SAME names — none renamed, none absorbed into
// another rule, and v10 adds no 22nd one. So the layer transfers 1:1 and the
// gate keeps exactly the coverage it had on eslint 8.
//
// IMPORT SPELLING — deliberate, and documented in the preset's own header:
// `@conduction/nextcloud-vue` ships NO `exports` map, and Node's native ESM
// resolver does no directory-index resolution, so the extensionless subpath
// `@conduction/nextcloud-vue/eslint` throws ERR_UNSUPPORTED_DIR_IMPORT from an
// ES module. `/eslint/index.js` is the spelling that resolves. The preset is
// CommonJS (`module.exports = { … }`), so it arrives as a DEFAULT import and is
// destructured here rather than named-imported.
//
// Spread LAST (before prettier): flat config is last-wins, and the whole point
// of this layer is to override the Vue rule set the Nextcloud preset sets up.
import conductionEslint from '@conduction/nextcloud-vue/eslint/index.js'

const { conductionVue3Fixes } = conductionEslint

/**
 * Every file extension in this repo that can contain a Vue component or plain
 * ECMAScript — i.e. everything ESLint parses with a JavaScript-family language.
 *
 * Deliberately NOT `**\/*.json`. See `conductionVue3FixesScoped` below.
 */
const JS_FAMILY_FILES = [
	'**/*.js',
	'**/*.cjs',
	'**/*.mjs',
	'**/*.jsx',
	'**/*.ts',
	'**/*.mts',
	'**/*.cts',
	'**/*.tsx',
	'**/*.vue',
]

/**
 * `conductionVue3Fixes` with its unscoped layers pinned to the JavaScript
 * family — REQUIRED under `@nextcloud/eslint-config@9`, and not a weakening.
 *
 * Two of the preset's three layers (`conduction/language-level` and
 * `conduction/vue3-deprecations`, the latter carrying all 26 rules) carry no
 * `files` key on purpose: in flat config a `files` glob also ENROLS the matched
 * paths, and the preset supplies a parser for `.vue` only, so scoping them once
 * shipped a regression. "No `files`" means "apply to whatever the consumer
 * already lints", which under eslint 8 was exactly right — the consumer only
 * ever linted JavaScript and Vue.
 *
 * `@nextcloud/eslint-config@9` breaks that assumption. It adds `@eslint/json`
 * and lints `**\/*.json` under a DIFFERENT ESLint language (`json/json`), whose
 * AST has no `parserServices` and no Vue document fragment. The preset's
 * unscoped Vue rules therefore got handed a JSON file and crashed the whole
 * run before reporting anything:
 *
 *   TypeError: Error while loading rule 'vue/no-deprecated-delete-set':
 *   Cannot read properties of undefined (reading 'getDocumentFragment')
 *   Occurred while linting src/manifest.d/_placeholder.json
 *
 * Pinning those layers to the JavaScript family restores the eslint-8 reach
 * exactly — every file they used to cover, they still cover — while keeping
 * them off a language they were never written for. It enrols nothing new:
 * config@9 already lints all nine of these globs. Coverage of the 21
 * deprecation rules is unchanged, which the post-migration `--print-config`
 * check confirms.
 *
 * FLEET NOTE: like the plugin registration below, this belongs upstream in
 * `@conduction/nextcloud-vue` — "unscoped" stopped meaning "JavaScript only"
 * the moment a Nextcloud preset started linting a non-JS language.
 */
const conductionVue3FixesScoped = conductionVue3Fixes.map((layer) =>
	layer.files ? layer : { ...layer, files: JS_FAMILY_FILES },
)

// ─────────────────────────────────────────────────────────────────────────────
// WHAT THIS MIGRATION COSTS — the `import/resolver` alias map, and `n/*`
// ─────────────────────────────────────────────────────────────────────────────
// The config this replaces carried a `settings['import/resolver'].alias` map:
//
//   '@'                          -> './src'
//   '@floating-ui/dom-actual'    -> './node_modules/@floating-ui/dom'
//   '@conduction/nextcloud-vue'  -> '../nextcloud-vue/src'
//
// It is GONE, and it could not be carried across. `@nextcloud/eslint-config@9`
// does not depend on `eslint-plugin-import` at all — it replaces that plugin
// with `eslint-plugin-perfectionist` (import ORDERING) plus its own
// `import-extensions` plugin (extension SPELLING). Neither resolves a module
// specifier to a file, so there is no resolver left for an alias map to
// configure. Nor could the plugin simply be kept: `eslint-plugin-import@2.32.0`
// declares `peerDependencies.eslint: "^2 || … || ^9"` — it does not support
// ESLint 10, so this is a dead end on both sides, not a choice.
//
// MEASURED COST, so nobody has to guess at it later. Comparing
// `eslint --print-config src/App.vue` before and after, ARMED rules go
// 266 -> 248, and the losses are concentrated in exactly two namespaces:
//
//   import/*   9 rules -> 0   (import/no-unresolved, import/named, import/export,
//                              import/no-duplicates, import/no-cycle, …)
//   n/*       16 rules -> 0   (n/no-deprecated-api, n/no-missing-require,
//                              n/no-process-exit, n/shebang, …)
//   promise/*  1 rule  -> 0
//
// against gains of vue/* 99 -> 126, jsdoc/* 26 -> 33, @nextcloud/* 2 -> 6, and
// the new perfectionist / @stylistic / import-extensions / antfu rules.
//
// The one worth arguing about is `import/no-unresolved`, which caught a
// mistyped import path at lint time. It is not unguarded now — webpack fails
// the build on an unresolvable specifier, and `npm run build` runs in CI on
// every PR — but the feedback moved from lint (seconds) to build (~2 minutes),
// and a path that only some webpack alias resolved is no longer checked by two
// independent tools. That is a real reduction in defence-in-depth and it is the
// single largest cost of this migration.
// ─────────────────────────────────────────────────────────────────────────────

export default [
	// `recommendedJavascript`, NOT `recommended`. Both are Vue 3 presets; they
	// differ in what they expect inside `<script>`. `recommended` is the
	// TypeScript one and turns on type-aware `typescript-eslint` rules. This
	// app's `src/` is 10 `.js` files and 5 `.vue` files with plain-JS script
	// blocks — there is no TypeScript surface for those rules to describe, and
	// enrolling one costs a full type-check on every lint run to report nothing.
	...recommendedJavascript,

	{
		// JSDoc overrides, scoped to MATCH the layer they override.
		//
		// `@nextcloud/eslint-config@9` registers `eslint-plugin-jsdoc` inside its
		// `nextcloud/documentation/*` layers, each of which carries an explicit
		// `files` + `ignores`. An unscoped override naming `jsdoc/*` therefore hits
		// the same wall the Vue rules did — "could not find plugin jsdoc" — on
		// every file outside those globs. Mirroring the preset's own scoping is
		// what makes the override land exactly where the rule it overrides lives.
		//
		// `ignores` matters as much as `files`: the preset excludes test globs
		// (`**/tests/**` among them) from its documentation layers, so an override
		// that did not exclude them too would re-arm a jsdoc rule across this
		// repo's entire `tests/` tree, which the preset deliberately leaves alone.
		name: 'larpingapp/jsdoc',
		files: JS_FAMILY_FILES,
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
			// visual-coverage gate) are this project's own JSDoc tags. They must be
			// passed as RULE OPTIONS, not via `settings.jsdoc.definedTags` — once an
			// extended preset has configured the rule, the rule reads `definedTags`
			// from its own options object and the shared setting is ignored. That is
			// why 71 `@spec` annotations reported "Invalid JSDoc tag name".
			//
			// Still required on eslint 10, and MORE load-bearing than before:
			// `@nextcloud/eslint-config@9` sets `jsdoc/check-tag-names: 'error'` in
			// its documentation layer with no `definedTags`, so without this
			// override every one of those annotations comes back — and now as an
			// ERROR rather than the warning it was under the v8 preset.
			'jsdoc/check-tag-names': ['warn', { definedTags: ['spec', 'visual'] }],
			'jsdoc/require-jsdoc': 'off',
		},
	},

	{
		name: 'larpingapp/rules',
		rules: {
			// Allow unused i18n functions (t, n) — imported for future translation wiring
			'no-unused-vars': [
				'error',
				{
					varsIgnorePattern: '^(t|n)$',
					argsIgnorePattern: '^_',
					ignoreRestSiblings: true,
				},
			],
			'vue/first-attribute-linebreak': 'off',
			// RESTORING the allow-list `@nextcloud/eslint-config@8` shipped, not
			// loosening a rule this app used to satisfy.
			//
			// v8 configured `no-console: ['error', { allow: ['error','warn','info','debug'] }]`;
			// v9 configures it as `['error', {}]` — the allow-list is gone. That
			// single policy change is the entire content of the 5 `no-console`
			// errors this migration surfaced, and all 5 are `console.error(...)`
			// calls in error branches (3 in store/modules/settings.js, 2 in
			// views/settings/Settings.vue). Not one of them is new code and not one
			// is a stray debug `console.log` — the pre-existing
			// `eslint-disable-next-line no-console` comments elsewhere in this repo
			// are precisely the `console.log` sites that v8 DID reject.
			//
			// Deliberately kept as a lint decision rather than silently rewritten:
			// migrating five error-logging call sites to `@nextcloud/logger` is a
			// runtime behaviour change (different sink, different formatting) and
			// does not belong inside an ESLint upgrade. Revisit it as its own
			// change; until then this keeps the rule doing exactly what it did on
			// eslint 8, which is what makes the before/after counts comparable.
			'no-console': ['error', { allow: ['error', 'warn', 'info', 'debug'] }],
			// `@typescript-eslint/no-explicit-any` is GONE from this list, and its
			// removal costs nothing. `recommendedJavascript` scopes every
			// `typescript-eslint` layer to the TypeScript globs only
			// (`vueIsTypescript: false`, so `.vue` is not included either), and this
			// app's `src/` is 10 `.js` files and 5 `.vue` files with plain-JS script
			// blocks — zero TypeScript. The rule was therefore never armed on any
			// file here, so switching it off was already a no-op; on eslint 10 the
			// same line becomes a hard "could not find plugin @typescript-eslint"
			// startup failure instead. Re-add it (scoped to the TS globs) if and
			// when this app grows a TypeScript surface.
		},
	},

	{
		// `require` is webpack's, not Node's.
		//
		// `src/main.js` calls `require.context('./manifest.d', false, /\.json$/)`
		// — a webpack-only API the bundler resolves at BUILD time to enumerate the
		// manifest fragment files. There is no `require` at runtime (this ships as
		// an ES module bundle) and there is no import form of `require.context`,
		// so the call is correct exactly as written.
		//
		// v8 reached this through `eslint-plugin-n`, which declared Node's globals
		// including `require`. `@nextcloud/eslint-config@9` drops that plugin, and
		// its own `configs/node.js` scopes Node globals to `**/*.config.*`,
		// `**/*.cjs` and test globs — none of which match `src/main.js`. So
		// `no-undef` started reporting a symbol that is genuinely defined, just by
		// the bundler rather than by the runtime.
		//
		// Declared `readonly` and scoped to `src/`: the app source is the only
		// place webpack processes, and `readonly` still catches an accidental
		// assignment to `require`.
		name: 'larpingapp/webpack-build-time-globals',
		files: ['src/**/*.js', 'src/**/*.vue'],
		languageOptions: {
			globals: { require: 'readonly' },
		},
	},

	{
		// Node-side CLI tools (build / validate scripts) legitimately use
		// console + process.exit and ship as plain JS (no shebang).
		//
		// `n/no-process-exit` and `n/shebang` are GONE from this override, and not
		// because they stopped mattering. `@nextcloud/eslint-config@9` does not
		// depend on `eslint-plugin-n` at all (its `configs/node.js` only supplies
		// Node globals), so the `n/` namespace is no longer registered. Naming an
		// unregistered plugin's rule in flat config is a hard config error, not a
		// no-op — keeping the two lines would have made ESLint refuse to start.
		// Nothing regressed: with the plugin absent the rules were not enforcing
		// anything anywhere, so switching them off here had nothing left to do.
		name: 'larpingapp/node-cli-scripts',
		files: [
			'tests/validate-manifest.js',
			'tests/validate-register.js',
			'tests/validate-json-strict.js',
		],
		rules: {
			'no-console': 'off',
		},
	},

	{
		// REQUIRED ADAPTATION for `@nextcloud/eslint-config@9` — read before
		// deleting this, it is not redundant with the preset above.
		//
		// `conductionVue3Fixes`'s `conduction/vue3-deprecations` layer carries NO
		// `files` key, on purpose: a `files` glob in flat config also ENROLS the
		// matched paths into the lint set, and the preset supplies a parser for
		// `.vue` only, so scoping that layer once shipped a regression. Leaving it
		// unscoped means it applies to every file the consumer already lints.
		//
		// Under eslint 8 that was fine, because `compat.extends('@nextcloud')`
		// registered the `vue` plugin GLOBALLY (eslintrc configs have no file
		// scoping to translate). `@nextcloud/eslint-config@9` registers it as
		// `restrictConfigFiles(vuePlugin.configs['flat/recommended'], GLOB_FILES_VUE)`
		// — scoped to `**/*.vue`. So on a plain `.js` file the preset's unscoped
		// `vue/no-deprecated-*` rules referred to a plugin namespace that was not
		// registered there, and ESLint refused to start at all:
		//
		//   A configuration object specifies rule
		//   "vue/no-deprecated-destroyed-lifecycle", but could not find plugin "vue".
		//
		// Registering the plugin unscoped restores the eslint-8 shape. It is the
		// same module instance the preset already loaded (verified: both resolve to
		// node_modules/eslint-plugin-vue/dist/index.js), so this is not a second,
		// conflicting registration. It carries no `files` key, so it enrols
		// nothing — it only makes the `vue/` namespace resolvable everywhere,
		// which is exactly what the unscoped rules layer needs. On a file with no
		// Vue component in it every one of those rules is a no-op.
		//
		// FLEET NOTE: this is not a larpingapp quirk. Every app that spreads
		// `conductionVue3Fixes` onto `@nextcloud/eslint-config@9` will hit it. The
		// durable fix belongs in `@conduction/nextcloud-vue` — either register the
		// plugin in the preset itself, or scope the rules layer to the Vue globs
		// and accept the narrower coverage.
		name: 'larpingapp/vue-plugin-namespace',
		plugins: { vue: vuePlugin },
	},

	// Vue 3 gate — MUST stay after the Nextcloud preset so its rules win.
	...conductionVue3FixesScoped,

	// eslint-config-prettier LAST OF ALL, and it has to be: it only turns rules
	// OFF — every stylistic rule prettier now owns (indent, quotes,
	// operator-linebreak, comma-dangle…). Anything spread after it would switch
	// some of them back on, and eslint and prettier would then demand opposite
	// things — the unfixable state this fleet already hit once with php-cs-fixer
	// and PHPCS.
	//
	// It matters MORE on eslint 10, not less. `@nextcloud/eslint-config@9`
	// replaces the core stylistic rules with `@stylistic/eslint-plugin`, so the
	// formatting rules prettier collides with now arrive under `@stylistic/*`
	// names. `eslint-config-prettier@10` is the version that knows to switch
	// that namespace off as well — an older one would have left them armed.
	//
	// It disables no CORRECTNESS rule. Verified after the migration: all 21
	// `vue/no-deprecated-*` rules are still present and still ON, because
	// prettier has no opinion about them. `indent` and `@stylistic/indent` are
	// off HERE and enforced by prettier's `useTabs: true` instead.
	eslintConfigPrettier,

	{
		// The ONE rule `eslint-config-prettier` does not yet know to switch off.
		//
		// This is a coverage gap in `eslint-config-prettier`, NOT a mistake in the
		// ordering above — measured, not assumed: `eslint-config-prettier@10.1.8`
		// turns off 358 rules, 180 of them in the `@stylistic/*` namespace, so the
		// layer is landing and the namespace IS handled. `@stylistic/exp-list-style`
		// is simply newer than its rule list; it appears nowhere in it.
		//
		// It is a direct contradiction of prettier, not a style preference. The
		// rule demands no line break between a trailing `,` and the closing `)`;
		// prettier's own output puts one there when it breaks a call across lines.
		// The single occurrence (src/views/skillTreeGraph.js:28) is prettier's
		// output, byte for byte — `npm run format` passes on it. Left armed, the
		// two tools would each revert the other forever, which is the unfixable
		// state this fleet already reached once with php-cs-fixer and PHPCS.
		//
		// `nextcloud/forms` — the reference implementation for this whole
		// migration — carries the identical `'@stylistic/exp-list-style': 'off'`
		// for the identical reason, so this is the upstream-sanctioned shape and
		// not a local workaround. Scoped, because `@nextcloud/eslint-config@9`
		// registers `@stylistic` only on the JavaScript-family globs.
		name: 'larpingapp/prettier-coverage-gap',
		files: JS_FAMILY_FILES,
		rules: {
			'@stylistic/exp-list-style': 'off',
		},
	},
]
