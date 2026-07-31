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

const appId = 'larpingapp'

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

// Use local source when available (monorepo dev), otherwise fall back to npm package
const localLib = path.resolve(__dirname, '../nextcloud-vue/src')
const useLocalLib = process.env.USE_LOCAL_LIB !== 'false' && fs.existsSync(localLib)

// Extend the base resolve config (preserves defaults from @nextcloud/webpack-vue-config)
webpackConfig.resolve = webpackConfig.resolve || {}
webpackConfig.resolve.modules = [path.resolve(__dirname, 'node_modules'), 'node_modules']
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
	'@nextcloud/vue$': path.resolve(__dirname, 'node_modules/@nextcloud/vue/dist/index.mjs'),
	'@nextcloud/dialogs$': path.resolve(__dirname, 'node_modules/@nextcloud/dialogs/dist/index.mjs'),
	// Force the lib's transitive @nextcloud/axios import to resolve to
	// the app's installed copy. Without the `$` exact-match suffix,
	// webpack would walk up to the lib's own node_modules and load a
	// second axios instance, breaking shared interceptors / CSRF tokens.
	// Point directly to the CJS build to avoid webpack picking the ESM
	// `.mjs` entrypoint (which triggers "fully specified" errors in
	// transitive deps that do `require('buffer')` without an extension).
	'@nextcloud/axios$': path.resolve(__dirname, 'node_modules/@nextcloud/axios/dist/index.cjs'),
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
	new webpack.DefinePlugin({ appVersion: JSON.stringify(process.env.npm_package_version) }),
]

module.exports = webpackConfig
