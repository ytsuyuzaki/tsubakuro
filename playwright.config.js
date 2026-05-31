import { defineConfig } from '@playwright/test';

const baseConfig = require( '@wordpress/scripts/config/playwright.config.js' );

const config = defineConfig( {
	...baseConfig,
	globalSetup: require.resolve( './tests/e2e/global-setup.js' ),
	testDir: './tests/e2e',
	webServer: {
		...baseConfig.webServer,
		command:
			"npm run wp-env:start && npx wp-env run tests-cli --env-cwd='wp-content/plugins/tsubakuro/' sh -c 'wp core update-db && wp plugin activate mcp-adapter tsubakuro >/dev/null 2>&1 || true'",
	},
} );

export default config;
