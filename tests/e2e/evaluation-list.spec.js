import { test, expect } from '@playwright/test';

test.describe( 'Tsubakuro evaluation list', () => {
	test( 'can create, display, edit, and delete an evaluation', async ( {
		page,
	} ) => {
		const marker = Date.now();
		const title = `E2E evaluation ${ marker }`;
		const updatedTitle = `E2E evaluation updated ${ marker }`;

		await page.goto( '/wp-admin/admin.php?page=tsubakuro-evaluation-form' );
		await page.fill( '#tsubakuro-eval-title', title );
		await page.fill( '#tsubakuro-eval-detail', 'Created by Playwright' );
		await page.getByRole( 'button', { name: '保存' } ).click();

		await expect( page ).toHaveURL( /page=tsubakuro-evaluations/ );
		await expect( page.locator( '.notice-success' ) ).toBeVisible();

		const evaluationLink = page.getByRole( 'link', { name: title } );
		const list = page.locator( '.tsubakuro-table-scroll' );
		await expect( list ).toBeVisible();
		await expect( evaluationLink ).toBeVisible();

		await page.setViewportSize( { width: 600, height: 800 } );
		await expect( list ).toBeVisible();
		await expect( evaluationLink ).toBeVisible();

		await page.setViewportSize( { width: 1280, height: 800 } );
		await evaluationLink.click();
		await expect( page.locator( '#tsubakuro-eval-title' ) ).toHaveValue(
			title
		);
		await expect( page.locator( '#tsubakuro-eval-detail' ) ).toHaveValue(
			'Created by Playwright'
		);

		await page.fill( '#tsubakuro-eval-title', updatedTitle );
		await page.fill(
			'#tsubakuro-eval-purpose',
			'Verify evaluation editing'
		);
		await page.selectOption( '#tsubakuro-eval-metric', 'ctr' );
		await page.selectOption( '#tsubakuro-eval-judgment', 'success' );
		await page.fill( '#tsubakuro-eval-result', 'Editing succeeded' );
		await page.getByRole( 'button', { name: '保存' } ).click();

		await expect( page ).toHaveURL( /page=tsubakuro-evaluations/ );
		const updatedLink = page.getByRole( 'link', { name: updatedTitle } );
		const updatedRow = updatedLink.locator( 'xpath=ancestor::tr' );
		await expect( updatedLink ).toBeVisible();
		await expect( updatedRow ).toContainText( '成功' );
		await expect( evaluationLink ).toHaveCount( 0 );

		page.once( 'dialog', ( dialog ) => dialog.accept() );
		await updatedRow.getByRole( 'button', { name: '削除' } ).click();
		await expect( page.locator( '.notice-success' ) ).toContainText(
			'記事評価を削除しました。'
		);
		await expect( updatedLink ).toHaveCount( 0 );
	} );
} );
