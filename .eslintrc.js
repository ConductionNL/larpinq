module.exports = {
	root: true,
	extends: [
		'@nextcloud',
	],
	rules: {
		'jsdoc/require-jsdoc': 'off',
		'vue/first-attribute-linebreak': 'off',
		'vue/enforce-style-attribute': ['error', { allow: ['scoped'] }],
	},
}
