import { chromium } from '@playwright/test';
import { execSync } from 'node:child_process';

/**
 * @param {import('@playwright/test').FullConfig} config
 * @return {Promise<void>}
 */
async function globalSetup( config ) {
	const { storageState, baseURL } = config.projects[ 0 ].use;
	const storageStatePath =
		typeof storageState === 'string' ? storageState : undefined;
	const browser = await chromium.launch();
	const context = await browser.newContext( {
		baseURL,
	} );
	const page = await context.newPage();

	// Log in using the default wp-env admin account and persist auth state.
	await page.goto( '/wp-login.php' );
	await page.fill( '#user_login', process.env.WP_ADMIN_USERNAME || 'admin' );
	await page.fill(
		'#user_pass',
		process.env.WP_ADMIN_PASSWORD || 'password'
	);
	await page.click( '#wp-submit' );
	await page.waitForURL( '**/wp-admin/**' );

	if ( storageStatePath ) {
		await context.storageState( { path: storageStatePath } );
	}

	await context.close();
	await browser.close();

	// Ensure plugin state is deterministic even when wp-env server is reused.
	execSync(
		"npx wp-env run tests-cli sh -c 'wp plugin activate mcp-adapter tsubakuro >/dev/null 2>&1 || true'",
		{ stdio: 'ignore' }
	);

	// Ensure plugins required by E2E scenarios are active in the test site.
	// Try to activate a default theme if available; keep setup resilient in CI.
	execSync(
		"npx wp-env run tests-cli sh -c 'for t in twentytwentyone twentytwentytwo twentytwentythree twentytwentyfour twentytwentyfive twentytwentysix; do wp theme activate $t >/dev/null 2>&1 && break || true; done'",
		{ stdio: 'ignore' }
	);
}

export default globalSetup;
