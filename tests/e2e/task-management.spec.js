import { test, expect } from '@wordpress/e2e-test-utils-playwright';

test.describe( 'Tsubakuro task management', () => {
	test( 'admin task list page is accessible', async ( { page } ) => {
		await page.goto( '/wp-admin/admin.php?page=tsubakuro-tasks' );
		await expect( page ).toHaveTitle( /タスク/ );
	} );

	test( 'new task form page is accessible', async ( { page } ) => {
		await page.goto( '/wp-admin/admin.php?page=tsubakuro-task-form' );
		await expect( page ).toHaveTitle( /タスク/ );
	} );

	test( 'REST API tasks endpoint returns an array', async ( {
		requestUtils,
	} ) => {
		const tasks = await requestUtils.rest( {
			method: 'GET',
			path: 'tsubakuro/v1/tasks',
		} );
		expect( Array.isArray( tasks ) ).toBe( true );
	} );

	test( 'can create and delete a task via REST API', async ( {
		requestUtils,
	} ) => {
		// Create a task.
		const task = await requestUtils.rest( {
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
		const deleteBody = await requestUtils.rest( {
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
