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
} );
