// SPDX-License-Identifier: EUPL-1.2
const path = require('path')
const fs = require('fs')
const webpack = require('webpack')
const webpackConfig = require('@nextcloud/webpack-vue-config')
const { VueLoaderPlugin } = require('vue-loader')
const NodePolyfillPlugin = require('node-polyfill-webpack-plugin')

const buildMode = process.env.NODE_ENV
const isDev = buildMode === 'development'
webpackConfig.devtool = isDev ? 'cheap-source-map' : 'source-map'

webpackConfig.stats = {
	colors: true,
	modules: false,
}

const appId = 'larpinq'

webpackConfig.entry = {
	main: {
		import: path.join(__dirname, 'src', 'main.js'),
		filename: appId + '-main.js',
	},
	adminSettings: {
		import: path.join(__dirname, 'src', 'settings.js'),
		filename: appId + '-settings.js',
	},
}

// `@nextcloud/webpack-vue-config` hardcodes `output.publicPath` to
// `/apps/<appName>/js/`. That is only correct when the app is installed under
// the apps path whose URL is `/apps`. Nextcloud supports several apps paths,
// and the standard Docker image registers a SECOND one — `/var/www/html/custom_apps`
// served at `/custom_apps` — which is where a `docker cp`-deployed app lands.
//
// The entry bundle is unaffected (Nextcloud generates that script tag itself and
// gets the path right), so the failure only shows up on LAZY-LOADED chunks: they
// 404, Nextcloud's error page comes back as `text/html`, the browser refuses it
// on MIME grounds, and the page dies with a `ChunkLoadError`. Nothing in the
// build reports a problem. It bites this app hard because the manifest renderer
// code-splits nearly every page component.
//
// `'auto'` makes webpack derive the public path at runtime from the URL the
// entry script was actually loaded from, so it is correct under every apps path.
webpackConfig.output = {
	...(webpackConfig.output || {}),
	publicPath: 'auto',
}

// Use local source when available (monorepo dev), otherwise fall back to the
// npm package.
//
// ⚠️ This alias is opt-OUT, and it silently OVERRIDES the exactly-pinned
// `@conduction/nextcloud-vue` dependency. A sibling `../nextcloud-vue` checkout
// sitting on the Vue 2 (`1.x` / `beta.*`) line therefore builds this Vue 3 app
// against Vue 2 library sources — the build succeeds, and the first symptom is
// a runtime failure that looks like a migration bug.
//
// That is not hypothetical: the shared `apps-extra/nextcloud-vue` checkout was
// on `wip/cnindexpage-export-action` at `1.0.0-beta.184` while this migration
// was in progress, so any build run from `apps-extra/larpinq` would have
// picked it up.
//
// So the local lib is used only when its MAJOR matches the version this app
// depends on. A mismatch is a hard, loud failure rather than a silent
// downgrade; `USE_LOCAL_LIB=false` still disables the alias outright.
const localLib = path.resolve(__dirname, '../nextcloud-vue/src')

/**
 * Decide whether the sibling nc-vue checkout may be aliased in.
 *
 * @return {boolean} True when the local source should replace the npm package.
 */
function resolveUseLocalLib() {
	// Opt-IN (ADR-090). This was opt-OUT, and unset — its normal state — meant
	// "alias whatever sibling is on disk into a build that can ship".
	if (process.env.USE_LOCAL_LIB !== 'true' || !fs.existsSync(localLib)) {
		return false
	}

	// The MAJOR comparison this replaces could not see the skew it was written
	// for. nc-vue's Vue 2 line and its Vue 3 line are BOTH major 2 — the sibling
	// is 2.0.5 (Vue 2) while this app declares ^2.3.0 (Vue 3) — so the check
	// compared 2 against 2, passed, and aliased Vue 2 sources into a Vue 3 app.
	// Compare against the declared RANGE instead, which is the thing that is
	// actually being violated.
	let localVersion = 'unreadable'
	let satisfied = false
	try {
		// eslint-disable-next-line n/no-extraneous-require
		const semver = require('semver')
		const required =
			require('./package.json').dependencies['@conduction/nextcloud-vue']
		localVersion = String(
			JSON.parse(
				fs.readFileSync(
					path.resolve(localLib, '..', 'package.json'),
					'utf8',
				),
			).version || '',
		)
		satisfied = semver.satisfies(localVersion, required, {
			includePrerelease: true,
		})
	} catch (e) {
		// Fail CLOSED: if the check cannot run, the sibling is refused.
		satisfied = false
	}

	if (!satisfied) {
		// A warning rather than a throw: refusing the sibling is a complete,
		// correct build against the pinned npm package, so there is nothing for
		// the developer to repair before the build can proceed.
		// eslint-disable-next-line no-console
		console.warn(
			`[larpinq] IGNORING sibling @conduction/nextcloud-vue@${localVersion} — `
				+ "it does not satisfy this app's declared range. Building against the npm dist.",
		)
		return false
	}

	return true
}

const useLocalLib = resolveUseLocalLib()

// Extend the base resolve config (preserves defaults from @nextcloud/webpack-vue-config)
webpackConfig.resolve = webpackConfig.resolve || {}
webpackConfig.resolve.modules = [
	path.resolve(__dirname, 'node_modules'),
	'node_modules',
]
webpackConfig.resolve.alias = {
	...(webpackConfig.resolve.alias || {}),
	'@': path.resolve(__dirname, 'src'),
	...(useLocalLib ? { '@conduction/nextcloud-vue': localLib } : {}),
	vue$: path.resolve(__dirname, 'node_modules/vue'),
	// MANDATORY, not an optimisation. `@nextcloud/vue@9` hard-depends on
	// `vue-router ^5.1.0` while this app is on `vue-router@4`, so npm installs
	// BOTH — `node_modules/vue-router` (4.x) and
	// `node_modules/@nextcloud/vue/node_modules/vue-router` (5.x). Without this
	// exact-match alias, `main.js` gets the 4.x singleton while every
	// `@nextcloud/vue` component that calls `useRoute()` / `useRouter()`
	// resolves the 5.x copy — a DIFFERENT injection key, so those components
	// see no router at all. `<NcAppNavigationItem :to="…">` then renders as an
	// inert element and nothing is logged.
	'vue-router$': path.resolve(__dirname, 'node_modules/vue-router'),
	pinia$: path.resolve(__dirname, 'node_modules/pinia'),
	// These two MUST point at the entry FILE, not the package directory.
	// @nextcloud/vue@9 and @nextcloud/dialogs@7 declare no `main` and no
	// `module` — only an `exports` map. A directory alias bypasses that map, so
	// webpack finds no entry point and every `from '@nextcloud/vue'` in the app
	// AND inside @conduction/nextcloud-vue's dist fails with
	// "Can't resolve '@nextcloud/vue'" (233 errors on the first Vue 3 build).
	//
	// `@nextcloud/dialogs` carries the `$` exact-match suffix for the same
	// reason `@nextcloud/vue` does: without it the alias would also rewrite the
	// subpath `@nextcloud/dialogs/style.css`, which must keep going through the
	// exports map.
	'@nextcloud/vue$': path.resolve(
		__dirname,
		'node_modules/@nextcloud/vue/dist/index.mjs',
	),
	'@nextcloud/dialogs$': path.resolve(
		__dirname,
		'node_modules/@nextcloud/dialogs/dist/index.mjs',
	),
	// Force the lib's transitive @nextcloud/axios import to resolve to
	// the app's installed copy. Without the `$` exact-match suffix,
	// webpack would walk up to the lib's own node_modules and load a
	// second axios instance, breaking shared interceptors / CSRF tokens.
	// Point directly to the CJS build to avoid webpack picking the ESM
	// `.mjs` entrypoint (which triggers "fully specified" errors in
	// transitive deps that do `require('buffer')` without an extension).
	'@nextcloud/axios$': path.resolve(
		__dirname,
		'node_modules/@nextcloud/axios/dist/index.cjs',
	),
}

// Allow `.js` import requests to resolve to `.cjs` files.
// @nextcloud/vue ships .cjs/.mjs; without this, requests like
// `import './foo.js'` inside ESM dist files fail to find `./foo.cjs`.
webpackConfig.resolve.extensionAlias = {
	'.js': ['.cjs', '.js'],
	...(webpackConfig.resolve.extensionAlias || {}),
}

// Add SCSS rule to the existing module rules
webpackConfig.module.rules.push({
	test: /\.scss$/,
	use: ['style-loader', 'css-loader', 'sass-loader'],
})

// Replace plugins to avoid duplicate VueLoaderPlugin (base config also registers one).
// CRITICAL: re-add the appName / appVersion DefinePlugin entries — without them
// every @nextcloud/vue widget mount logs `[ERROR] @nextcloud/vue: The library
// was used without setting / replacing the appName`.
webpackConfig.plugins = [
	new VueLoaderPlugin(),
	new NodePolyfillPlugin({ additionalAliases: ['process'] }),
	new webpack.DefinePlugin({ appName: JSON.stringify(appId) }),
	new webpack.DefinePlugin({
		appVersion: JSON.stringify(process.env.npm_package_version),
	}),
]

// Force @nextcloud/dialogs to resolve from this app's node_modules (the Vue-2
// `^3.x` line), preventing @conduction/nextcloud-vue's nested `@nextcloud/dialogs@^7`
// — which drags in a Vue-3 `@nextcloud/vue` + floating-vue and breaks the Vue-2
// build with "export 'createApp' was not found in 'vue'". Mirrors procest/decidesk.
webpackConfig.resolve.alias['@nextcloud/dialogs'] = path.resolve(
	__dirname,
	'node_modules/@nextcloud/dialogs',
)

module.exports = webpackConfig
