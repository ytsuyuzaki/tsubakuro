import { request } from '@playwright/test';
import { RequestUtils } from '@wordpress/e2e-test-utils-playwright';
import { execSync } from 'node:child_process';

/**
 * @param {import('@playwright/test').FullConfig} config
 * @return {Promise<void>}
 */
async function globalSetup( config ) {
	const { storageState, baseURL } = config.projects[ 0 ].use;
	const storageStatePath =
		typeof storageState === 'string' ? storageState : undefined;

	const requestContext = await request.newContext( {
		baseURL,
	} );

	const requestUtils = new RequestUtils( requestContext, {
		storageStatePath,
	} );

	// Authenticate and save the storageState to disk.
	await requestUtils.setupRest();

	// Ensure plugin state is deterministic even when wp-env server is reused.
	execSync(
		"npx wp-env run tests-cli sh -c 'wp plugin activate mcp-adapter tsubakuro >/dev/null 2>&1 || true'",
		{ stdio: 'ignore' }
	);

	// Ensure plugins required by E2E scenarios are active in the test site.
	for ( const pluginSlug of [ 'mcp-adapter', 'tsubakuro' ] ) {
		try {
			await requestUtils.activatePlugin( pluginSlug );
		} catch {
			// Keep setup resilient when plugin API or plugin availability differs.
		}
	}

	const themeCandidates = [
		'twentytwentyone',
		'twentytwentytwo',
		'twentytwentythree',
		'twentytwentyfour',
		'twentytwentyfive',
		'twentytwentysix',
	];

	for ( const themeSlug of themeCandidates ) {
		try {
			await requestUtils.activateTheme( themeSlug );
			break;
		} catch {
			// Ignore missing themes; CI images can differ by bundled default themes.
		}
	}

	// Reset the test environment before running the tests.
	await Promise.all( [
		requestUtils.deleteAllPosts(),
		requestUtils.deleteAllBlocks(),
		requestUtils.resetPreferences(),
	] );

	await requestContext.dispose();
}

export default globalSetup;
