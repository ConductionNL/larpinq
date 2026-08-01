// SPDX-License-Identifier: EUPL-1.2
// SPDX-FileCopyrightText: 2026 Conduction B.V.

const {
	defineConfig,
} = require('@eslint/config-helpers')

const js = require('@eslint/js')

const {
	FlatCompat,
} = require('@eslint/eslintrc')

// The SHARED Conduction Vue 3 fix layer, shipped inside the component library
// so every app arms the same gate from one import instead of hand-rolling it.
//
// It supplies: `ecmaVersion: 'latest'` on both `languageOptions` and
// `languageOptions.parserOptions`; `vue-eslint-parser` wired in the documented
// OBJECT `parser: { js, ts }` form; the complete `vue/no-deprecated-*` family
// at `error`; `vue/v-on-event-hyphenation` with `update:modelValue` excluded;
// and the two INVERTED Vue-2 rules (`vue/no-v-model-argument`,
// `vue/no-v-for-template-key`) switched off.
//
// Those last two used to be disabled by hand in every migrated app. The preset
// disables them ITSELF, so no local copies belong here — a local copy is how a
// consumer ends up carrying a stale opinion the library has since revised.
//
// Spread LAST: flat config is last-wins, and the whole point of this layer is
// to override the Vue-2 rule set that `compat.extends('@nextcloud')` reaches.
const { conductionVue3Fixes } = require('@conduction/nextcloud-vue/eslint')

const compat = new FlatCompat({
	baseDirectory: __dirname,
	recommendedConfig: js.configs.recommended,
	allConfig: js.configs.all,
})

module.exports = defineConfig([{
	// `@nextcloud/eslint-config`'s DEFAULT entry point, not its `/vue3` one.
	// The `/vue3` preset sets `parserOptions.parser` to a bare string, which
	// makes `vue-eslint-parser` route template expressions through
	// `@typescript-eslint/parser` and lose `v-for` scope — hundreds of bogus
	// `vue/valid-v-for` errors on correct Vue 3 code. `conductionVue3Fixes`
	// (spread below) supplies the correct object-form parser wiring plus the
	// Vue 3 deprecation gate on top of this base.
	extends: compat.extends('@nextcloud'),

	settings: {
		'import/resolver': {
			alias: {
				map: [
					['@', './src'],
					['@floating-ui/dom-actual', './node_modules/@floating-ui/dom'],
					['@conduction/nextcloud-vue', '../nextcloud-vue/src'],
				],
				extensions: ['.js', '.ts', '.vue', '.json', '.css'],
			},
		},
	},

	rules: {
		// `@spec` (hydra gate-16 / gate-19 traceability) and `@visual` (the
		// visual-coverage gate) are this project's own JSDoc tags. They must be
		// passed as RULE OPTIONS, not via `settings.jsdoc.definedTags` — once an
		// extended preset has configured the rule, the rule reads `definedTags`
		// from its own options object and the shared setting is ignored. That is
		// why 71 `@spec` annotations reported "Invalid JSDoc tag name".
		'jsdoc/check-tag-names': ['warn', { definedTags: ['spec', 'visual'] }],
		// Allow unused i18n functions (t, n) — imported for future translation wiring
		'no-unused-vars': ['error', { varsIgnorePattern: '^(t|n)$', argsIgnorePattern: '^_', ignoreRestSiblings: true }],
		'jsdoc/require-jsdoc': 'off',
		'vue/first-attribute-linebreak': 'off',
		'@typescript-eslint/no-explicit-any': 'off',
		'n/no-missing-import': 'off',
		'import/namespace': 'off',
		'import/default': 'off',
		'import/no-named-as-default': 'off',
		'import/no-named-as-default-member': 'off',
	},
}, {
	// Node-side CLI tools (build / validate scripts) legitimately use
	// console + process.exit and ship as plain JS (no shebang).
	files: ['tests/validate-manifest.js', 'tests/validate-register.js', 'tests/validate-json-strict.js'],
	rules: {
		'no-console': 'off',
		'n/no-process-exit': 'off',
		'n/shebang': 'off',
	},
},
// Vue 3 gate — MUST stay last so its rules win over the Vue-2 base above.
...conductionVue3Fixes,
])
