import { test, expect } from '@playwright/test';

test.describe( 'Tsubakuro insight management', () => {
	test( 'can create, display, edit, and delete an insight', async ( {
		page,
	} ) => {
		const marker = Date.now();
		const title = `E2E insight ${ marker }`;
		const updatedTitle = `E2E insight updated ${ marker }`;

		await page.goto( '/wp-admin/admin.php?page=tsubakuro-insight-form' );
		await page.fill( '#tsubakuro-insight-title', title );
		await page.fill( '#tsubakuro-insight-detail', 'Created by Playwright' );
		await page.getByRole( 'button', { name: '保存' } ).click();

		await expect( page ).toHaveURL( /page=tsubakuro-insights/ );
		await expect( page.locator( '.notice-success' ) ).toBeVisible();
		const insightLink = page.getByRole( 'link', { name: title } );
		await expect( insightLink ).toBeVisible();

		await insightLink.click();
		await expect( page.locator( '#tsubakuro-insight-title' ) ).toHaveValue(
			title
		);
		await expect( page.locator( '#tsubakuro-insight-detail' ) ).toHaveValue(
			'Created by Playwright'
		);

		await page.fill( '#tsubakuro-insight-title', updatedTitle );
		await page.fill( '#tsubakuro-insight-site', 'https://example.com' );
		await page.fill( '#tsubakuro-insight-kind', '比較記事' );
		await page.fill( '#tsubakuro-insight-hypothesis', 'E2E hypothesis' );
		await page.fill( '#tsubakuro-insight-conclusion', 'E2E conclusion' );
		await page.fill( '#tsubakuro-insight-total', '4' );
		await page.fill( '#tsubakuro-insight-success', '3' );
		await page.selectOption( '#tsubakuro-insight-status', 'effective' );
		await page.selectOption( '#tsubakuro-insight-action', 'standardize' );
		await page.getByRole( 'button', { name: '保存' } ).click();

		const updatedLink = page.getByRole( 'link', { name: updatedTitle } );
		const updatedRow = updatedLink.locator( 'xpath=ancestor::tr' );
		await expect( updatedLink ).toBeVisible();
		await expect( updatedRow ).toContainText( '有効' );
		await expect( updatedRow ).toContainText( '4' );
		await expect( updatedRow ).toContainText( '75%' );
		await expect( updatedRow ).toContainText( '比較記事' );
		await expect( updatedRow ).toContainText( '標準施策として採用する' );
		await expect( insightLink ).toHaveCount( 0 );

		page.once( 'dialog', ( dialog ) => dialog.accept() );
		await updatedRow.getByRole( 'button', { name: '削除' } ).click();
		await expect( page.locator( '.notice-success' ) ).toContainText(
			'改善知見を削除しました。'
		);
		await expect( updatedLink ).toHaveCount( 0 );
	} );
} );
