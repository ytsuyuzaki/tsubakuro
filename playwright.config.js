import { defineConfig } from '@playwright/test';

const baseConfig = require( '@wordpress/scripts/config/playwright.config.js' );

const config = defineConfig( {
	...baseConfig,
	globalSetup: require.resolve( './tests/e2e/global-setup.js' ),
	testDir: './tests/e2e',
} );

export default config;
