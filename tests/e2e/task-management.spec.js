import { test, expect, request } from '@playwright/test';

const baseURL = process.env.WP_BASE_URL || 'http://localhost:8889';
const storageStatePath =
	process.env.STORAGE_STATE_PATH || 'artifacts/storage-states/admin.json';

async function getRestNonce( context ) {
	const response = await context.get( '/wp-admin/' );
	const html = await response.text();
	const match = html.match( /"nonce":"([^"]+)"/ );

	if ( ! match ) {
		throw new Error( 'Failed to detect REST nonce from wp-admin HTML.' );
	}

	return match[ 1 ];
}

async function fetchJson( context, url, options ) {
	const response = await context.fetch( url, options );
	const contentType = response.headers()[ 'content-type' ] || '';
	const textBody = await response.text();

	if ( ! contentType.includes( 'application/json' ) ) {
		return {
			response,
			json: null,
			textBody,
		};
	}

	return {
		response,
		json: JSON.parse( textBody ),
		textBody,
	};
}

async function rest( { method, path, data } ) {
	const context = await request.newContext( {
		baseURL,
		storageState: storageStatePath,
	} );
	const nonce = await getRestNonce( context );
	const options = {
		method,
		data,
		headers: {
			'X-WP-Nonce': nonce,
		},
	};

	const normalizedPath = path.replace( /^\//, '' );
	const candidates = [
		`/wp-json/${ normalizedPath }`,
		`/index.php?rest_route=/${ normalizedPath }`,
	];

	let result = null;

	for ( const url of candidates ) {
		result = await fetchJson( context, url, options );
		if ( result.json !== null ) {
			break;
		}
	}

	await context.dispose();

	if ( ! result || result.json === null ) {
		throw new Error(
			`REST response is not JSON for ${ method } ${ path }: ${
				result?.textBody?.slice( 0, 200 ) || 'no response body'
			}`
		);
	}

	if ( ! result.response.ok() ) {
		throw new Error(
			`REST request failed: ${ method } ${ path } (${ result.response.status() }) ${ JSON.stringify(
				result.json
			) }`
		);
	}

	return result.json;
}

test.describe( 'Tsubakuro task management', () => {
	test( 'admin task list page is accessible', async ( { page } ) => {
		await page.goto( '/wp-admin/admin.php?page=tsubakuro-tasks' );
		await expect( page ).toHaveTitle( /タスク/ );
	} );

	test( 'new task form page is accessible', async ( { page } ) => {
		await page.goto( '/wp-admin/admin.php?page=tsubakuro-task-form' );
		await expect( page ).toHaveTitle( /タスク/ );
	} );

	test( 'REST API tasks endpoint returns an array', async () => {
		const tasks = await rest( {
			method: 'GET',
			path: 'tsubakuro/v1/tasks',
		} );
		expect( Array.isArray( tasks ) ).toBe( true );
	} );

	test( 'can create and delete a task via REST API', async () => {
		// Create a task.
		const task = await rest( {
			method: 'POST',
			path: 'tsubakuro/v1/tasks',
			data: {
				title: 'E2E test task',
				content: 'Created by Playwright',
				status: 'todo',
			},
		} );
		expect( task.title ).toBe( 'E2E test task' );
		expect( task.id ).toBeGreaterThan( 0 );

		// Delete the task.
		const deleteBody = await rest( {
			method: 'DELETE',
			path: `tsubakuro/v1/tasks/${ task.id }`,
		} );
		expect( deleteBody.deleted ).toBe( true );
	} );

	test.describe( 'task list column visibility', () => {
		test.beforeEach( async ( { page } ) => {
			// Clear localStorage before each test to ensure default state.
			await page.goto( '/wp-admin/admin.php?page=tsubakuro-tasks' );
			await page.evaluate( () =>
				window.localStorage.removeItem( 'tsubakuro_visible_cols' )
			);
			await page.reload();
		} );

		test( 'optional columns are hidden by default', async ( { page } ) => {
			const assigneeHeader = page.locator( 'th.tsubakuro-col--assignee' );
			const dateHeader = page.locator( 'th.tsubakuro-col--date' );

			await expect( assigneeHeader ).toBeHidden();
			await expect( dateHeader ).toBeHidden();
		} );

		test( 'display options panel opens on button click', async ( {
			page,
		} ) => {
			const panel = page.locator( '#tsubakuro-screen-options-panel' );
			await expect( panel ).toBeHidden();

			await page.locator( '#tsubakuro-screen-options-toggle' ).click();
			await expect( panel ).toBeVisible();
		} );

		test( 'checking assignee column makes it visible', async ( {
			page,
		} ) => {
			await page.locator( '#tsubakuro-screen-options-toggle' ).click();

			const checkbox = page.locator(
				'.tsubakuro-col-toggle[data-column="assignee"]'
			);
			await checkbox.check();

			const assigneeHeader = page.locator( 'th.tsubakuro-col--assignee' );
			await expect( assigneeHeader ).toBeVisible();
		} );

		test( 'column visibility preference is persisted across page reloads', async ( {
			page,
		} ) => {
			// Enable the assignee column.
			await page.locator( '#tsubakuro-screen-options-toggle' ).click();
			await page
				.locator( '.tsubakuro-col-toggle[data-column="assignee"]' )
				.check();

			// Reload and verify column is still visible.
			await page.reload();
			const assigneeHeader = page.locator( 'th.tsubakuro-col--assignee' );
			await expect( assigneeHeader ).toBeVisible();

			// The checkbox should reflect the saved state.
			const checkbox = page.locator(
				'.tsubakuro-col-toggle[data-column="assignee"]'
			);
			await page.locator( '#tsubakuro-screen-options-toggle' ).click();
			await expect( checkbox ).toBeChecked();
		} );
	} );
} );
