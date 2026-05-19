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
const useLocalLib = fs.existsSync(localLib)

// Extend the base resolve config (preserves defaults from @nextcloud/webpack-vue-config)
webpackConfig.resolve = webpackConfig.resolve || {}
webpackConfig.resolve.modules = [path.resolve(__dirname, 'node_modules'), 'node_modules']
webpackConfig.resolve.alias = {
	...(webpackConfig.resolve.alias || {}),
	'@': path.resolve(__dirname, 'src'),
	...(useLocalLib ? { '@conduction/nextcloud-vue': localLib } : {}),
	vue$: path.resolve(__dirname, 'node_modules/vue'),
	pinia$: path.resolve(__dirname, 'node_modules/pinia'),
	'@nextcloud/vue$': path.resolve(__dirname, 'node_modules/@nextcloud/vue'),
	'@nextcloud/dialogs': path.resolve(__dirname, 'node_modules/@nextcloud/dialogs'),
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

// Share Vue + @nextcloud/vue + pinia + icons + @conduction/nextcloud-vue across
// every entry-point so each bundle no longer inlines its own ~3 MB framework copy.
// Each PHP template MUST load shared chunks before its entry-point bundle.
webpackConfig.optimization = {
	...(webpackConfig.optimization || {}),
	splitChunks: {
		...(webpackConfig.optimization?.splitChunks || {}),
		chunks: 'all',
		cacheGroups: {
			default: false,
			defaultVendors: false,
			ncVue: {
				name: appId + '-shared-nc-vue',
				test: /[\\/]node_modules[\\/](@nextcloud[\\/]vue|@conduction[\\/]nextcloud-vue)[\\/]|[\\/]nextcloud-vue[\\/]src[\\/]/,
				priority: 30,
				reuseExistingChunk: true,
				enforce: true,
				filename: appId + '-shared-nc-vue.js',
			},
			vendor: {
				name: appId + '-shared-vendor',
				test: /[\\/]node_modules[\\/](vue|pinia|vue-material-design-icons|@vueuse|core-js)[\\/]/,
				priority: 20,
				reuseExistingChunk: true,
				enforce: true,
				filename: appId + '-shared-vendor.js',
			},
		},
	},
}

module.exports = webpackConfig
