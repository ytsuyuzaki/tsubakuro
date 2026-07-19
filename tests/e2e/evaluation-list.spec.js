import { test, expect } from '@playwright/test';

test.describe( 'Tsubakuro evaluation list', () => {
	test( 'new evaluation remains visible at desktop and narrow widths', async ( {
		page,
	} ) => {
		const title = `E2E evaluation ${ Date.now() }`;

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
		page.once( 'dialog', ( dialog ) => dialog.accept() );
		await evaluationLink
			.locator( 'xpath=ancestor::tr' )
			.getByRole( 'button', { name: '削除' } )
			.click();
		await expect( evaluationLink ).toHaveCount( 0 );
	} );
} );
