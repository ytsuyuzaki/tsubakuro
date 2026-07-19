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
		return { response, json: null, textBody };
	}

	return { response, json: JSON.parse( textBody ), textBody };
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

test.describe( 'Tsubakuro site strategy', () => {
	test( 'admin site strategy page is accessible', async ( { page } ) => {
		await page.goto( '/wp-admin/admin.php?page=tsubakuro-site-strategy' );
		await expect( page.locator( '.tsubakuro-admin-wrap h1' ) ).toHaveText(
			'サイト方針'
		);
		await expect(
			page.locator( '#tsubakuro-site-strategy-purpose' )
		).toBeVisible();
	} );

	test( 'REST API site-strategy endpoint returns the expected fields', async () => {
		const strategy = await rest( {
			method: 'GET',
			path: 'tsubakuro/v1/site-strategy',
		} );

		expect( strategy ).toHaveProperty( 'purpose' );
		expect( strategy ).toHaveProperty( 'position' );
		expect( strategy ).toHaveProperty( 'direction' );
		expect( strategy ).toHaveProperty( 'audience' );
		expect( strategy ).toHaveProperty( 'value' );
	} );

	test( 'can update the site strategy via REST API and read it back', async () => {
		const marker = `E2E direction ${ Date.now() }`;

		const updated = await rest( {
			method: 'PUT',
			path: 'tsubakuro/v1/site-strategy',
			data: {
				direction: marker,
			},
		} );
		expect( updated.direction ).toBe( marker );

		const reread = await rest( {
			method: 'GET',
			path: 'tsubakuro/v1/site-strategy',
		} );
		expect( reread.direction ).toBe( marker );
	} );

	test( 'saving the strategy form persists values and restores them', async ( {
		page,
	} ) => {
		const fields = [
			'purpose',
			'position',
			'direction',
			'audience',
			'value',
		];
		const marker = Date.now();
		await page.goto( '/wp-admin/admin.php?page=tsubakuro-site-strategy' );
		const original = {};
		for ( const field of fields ) {
			original[ field ] = await page
				.locator( `#tsubakuro-site-strategy-${ field }` )
				.inputValue();
		}

		try {
			for ( const field of fields ) {
				await page.fill(
					`#tsubakuro-site-strategy-${ field }`,
					`E2E ${ field } ${ marker }`
				);
			}
			await page.getByRole( 'button', { name: '保存' } ).click();
			await expect( page.locator( '.notice-success' ) ).toBeVisible();

			await page.reload();
			for ( const field of fields ) {
				await expect(
					page.locator( `#tsubakuro-site-strategy-${ field }` )
				).toHaveValue( `E2E ${ field } ${ marker }` );
			}
		} finally {
			for ( const field of fields ) {
				await page.fill(
					`#tsubakuro-site-strategy-${ field }`,
					original[ field ]
				);
			}
			await page.getByRole( 'button', { name: '保存' } ).click();
			await expect( page.locator( '.notice-success' ) ).toBeVisible();
		}
	} );
} );
