import { test, expect } from '@playwright/test';

test.describe( 'Tsubakuro task management', () => {
	test( 'admin task list page is accessible', async ( { page } ) => {
		await page.goto( '/wp-admin/admin.php?page=tsubakuro-tasks' );
		await expect( page ).toHaveTitle( /タスク/ );
	} );

	test( 'new task form page is accessible', async ( { page } ) => {
		await page.goto( '/wp-admin/admin.php?page=tsubakuro-add-task' );
		await expect( page ).toHaveTitle( /タスク/ );
	} );

	test( 'REST API tasks endpoint returns 200', async ( { request } ) => {
		const response = await request.get( '/wp-json/tsubakuro/v1/tasks' );
		expect( response.status() ).toBe( 200 );
	} );

	test( 'can create and delete a task via REST API', async ( {
		request,
	} ) => {
		// Create a task.
		const createResponse = await request.post(
			'/wp-json/tsubakuro/v1/tasks',
			{
				data: {
					title: 'E2E test task',
					content: 'Created by Playwright',
					status: 'todo',
				},
			}
		);
		expect( createResponse.status() ).toBe( 200 );

		const task = await createResponse.json();
		expect( task.title ).toBe( 'E2E test task' );
		expect( task.id ).toBeGreaterThan( 0 );

		// Delete the task.
		const deleteResponse = await request.delete(
			`/wp-json/tsubakuro/v1/tasks/${ task.id }`
		);
		expect( deleteResponse.status() ).toBe( 200 );

		const deleteBody = await deleteResponse.json();
		expect( deleteBody.deleted ).toBe( true );
	} );
} );
