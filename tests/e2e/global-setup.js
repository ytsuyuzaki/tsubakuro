import { request } from '@playwright/test';
import { RequestUtils } from '@wordpress/e2e-test-utils-playwright';

/**
 * @param {import('@playwright/test').FullConfig} config
 * @return {Promise<void>}
 */
async function globalSetup(config) {
	const { storageState, baseURL } = config.projects[0].use;
	const storageStatePath =
		typeof storageState === 'string' ? storageState : undefined;

	const requestContext = await request.newContext({
		baseURL,
	});

	const requestUtils = new RequestUtils(requestContext, {
		storageStatePath,
	});

	// Authenticate and save the storageState to disk.
	await requestUtils.setupRest();

	const themeCandidates = [
		'twentytwentyone',
		'twentytwentytwo',
		'twentytwentythree',
		'twentytwentyfour',
		'twentytwentyfive',
		'twentytwentysix',
	];

	for (const themeSlug of themeCandidates) {
		try {
			await requestUtils.activateTheme(themeSlug);
			break;
		} catch (error) {
			// Ignore missing themes; CI images can differ by bundled default themes.
		}
	}

	// Reset the test environment before running the tests.
	await Promise.all([
		requestUtils.deleteAllPosts(),
		requestUtils.deleteAllBlocks(),
		requestUtils.resetPreferences(),
	]);

	await requestContext.dispose();
}

export default globalSetup;
