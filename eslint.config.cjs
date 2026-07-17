const defaultConfig = require( '@wordpress/scripts/config/eslint.config.cjs' );

module.exports = [
	{
		ignores: [ '.github/**', 'dist/**', 'vendor/**' ],
	},
	...defaultConfig,
];
