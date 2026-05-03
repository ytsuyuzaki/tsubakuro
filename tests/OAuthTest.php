<?php

use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Tsubakuro_OAuth.
 */
class OAuthTest extends TestCase {

	protected function setUp(): void {
		tsubakuro_test_reset();
		unset( $_SERVER['HTTP_AUTHORIZATION'], $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] );
	}

	protected function tearDown(): void {
		unset( $_SERVER['HTTP_AUTHORIZATION'], $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] );
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	private function make_token_request( array $params ): WP_REST_Request {
		return new WP_REST_Request( array(), $params );
	}

	// -------------------------------------------------------------------------
	// init() – hook registration
	// -------------------------------------------------------------------------

	public function test_init_registers_rest_route_and_ajax_actions(): void {
		Tsubakuro_OAuth::init();

		$this->assertArrayHasKey( 'rest_api_init', $GLOBALS['tsubakuro_test']['actions'] );
		$this->assertArrayHasKey( 'determine_current_user', $GLOBALS['tsubakuro_test']['filters'] );
		$this->assertArrayHasKey( 'wp_ajax_tsubakuro_generate_oauth_client', $GLOBALS['tsubakuro_test']['actions'] );
		$this->assertArrayHasKey( 'wp_ajax_tsubakuro_revoke_oauth_client', $GLOBALS['tsubakuro_test']['actions'] );
	}

	// -------------------------------------------------------------------------
	// register_routes()
	// -------------------------------------------------------------------------

	public function test_register_routes_creates_token_endpoint(): void {
		Tsubakuro_OAuth::register_routes();

		$this->assertArrayHasKey(
			'tsubakuro/v1/oauth/token',
			$GLOBALS['tsubakuro_test']['rest_routes']
		);
	}

	// -------------------------------------------------------------------------
	// handle_token() – token endpoint
	// -------------------------------------------------------------------------

	public function test_handle_token_returns_error_for_unsupported_grant_type(): void {
		$req    = $this->make_token_request(
			array( 'grant_type' => 'authorization_code', 'client_id' => 'x', 'client_secret' => 'y' )
		);
		$result = Tsubakuro_OAuth::handle_token( $req );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'unsupported_grant_type', $result->get_error_code() );
	}

	public function test_handle_token_returns_error_when_credentials_missing(): void {
		$req = $this->make_token_request( array( 'grant_type' => 'client_credentials' ) );
		$result = Tsubakuro_OAuth::handle_token( $req );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'invalid_request', $result->get_error_code() );
	}

	public function test_handle_token_returns_error_for_unknown_client(): void {
		$req = $this->make_token_request(
			array( 'grant_type' => 'client_credentials', 'client_id' => 'no_such', 'client_secret' => 'sec' )
		);
		$result = Tsubakuro_OAuth::handle_token( $req );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'invalid_client', $result->get_error_code() );
	}

	public function test_handle_token_returns_error_for_wrong_secret(): void {
		Tsubakuro_OAuth::generate_client( 'Test Client', 1 );
		$clients   = Tsubakuro_OAuth::get_clients();
		$client_id = $clients[0]['client_id'];

		$req = $this->make_token_request(
			array( 'grant_type' => 'client_credentials', 'client_id' => $client_id, 'client_secret' => 'WRONG' )
		);
		$result = Tsubakuro_OAuth::handle_token( $req );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'invalid_client', $result->get_error_code() );
	}

	public function test_handle_token_issues_bearer_token_for_valid_credentials(): void {
		$generated = Tsubakuro_OAuth::generate_client( 'Test Client', 5 );
		$client_id = $generated['client_id'];
		$client_secret = $generated['client_secret'];

		$req = $this->make_token_request(
			array(
				'grant_type'    => 'client_credentials',
				'client_id'     => $client_id,
				'client_secret' => $client_secret,
			)
		);
		$result = Tsubakuro_OAuth::handle_token( $req );

		$this->assertIsArray( $result );
		$this->assertSame( 'Bearer', $result['token_type'] );
		$this->assertSame( Tsubakuro_OAuth::TOKEN_EXPIRY, $result['expires_in'] );
		$this->assertNotEmpty( $result['access_token'] );
	}

	public function test_handle_token_accepts_json_params(): void {
		$generated = Tsubakuro_OAuth::generate_client( 'JSON Client', 1 );

		// WP_REST_Request stub returns json_params when get_json_params() is called.
		$req = new WP_REST_Request(
			array(),
			array(
				'grant_type'    => 'client_credentials',
				'client_id'     => $generated['client_id'],
				'client_secret' => $generated['client_secret'],
			)
		);
		$result = Tsubakuro_OAuth::handle_token( $req );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'access_token', $result );
	}

	// -------------------------------------------------------------------------
	// authenticate_bearer_token()
	// -------------------------------------------------------------------------

	public function test_authenticate_bearer_token_returns_existing_user_id_unchanged(): void {
		// When WordPress already determined a user, we must not override it.
		$result = Tsubakuro_OAuth::authenticate_bearer_token( 99 );
		$this->assertSame( 99, $result );
	}

	public function test_authenticate_bearer_token_returns_false_when_no_header(): void {
		$result = Tsubakuro_OAuth::authenticate_bearer_token( false );
		$this->assertFalse( $result );
	}

	public function test_authenticate_bearer_token_returns_false_for_invalid_token(): void {
		$_SERVER['HTTP_AUTHORIZATION'] = 'Bearer invalidtoken';
		$result = Tsubakuro_OAuth::authenticate_bearer_token( false );
		$this->assertFalse( $result );
	}

	public function test_authenticate_bearer_token_returns_user_id_for_valid_token(): void {
		// Issue a token for user 7.
		$generated = Tsubakuro_OAuth::generate_client( 'My Client', 7 );
		$req       = $this->make_token_request(
			array(
				'grant_type'    => 'client_credentials',
				'client_id'     => $generated['client_id'],
				'client_secret' => $generated['client_secret'],
			)
		);
		$token_response = Tsubakuro_OAuth::handle_token( $req );
		$token          = $token_response['access_token'];

		$_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $token;
		$result = Tsubakuro_OAuth::authenticate_bearer_token( false );

		$this->assertSame( 7, $result );
	}

	public function test_authenticate_bearer_token_uses_redirect_header_fallback(): void {
		$generated = Tsubakuro_OAuth::generate_client( 'Redirect Client', 3 );
		$req       = $this->make_token_request(
			array(
				'grant_type'    => 'client_credentials',
				'client_id'     => $generated['client_id'],
				'client_secret' => $generated['client_secret'],
			)
		);
		$token_response = Tsubakuro_OAuth::handle_token( $req );
		$token          = $token_response['access_token'];

		$_SERVER['REDIRECT_HTTP_AUTHORIZATION'] = 'Bearer ' . $token;
		$result = Tsubakuro_OAuth::authenticate_bearer_token( false );

		$this->assertSame( 3, $result );
	}

	// -------------------------------------------------------------------------
	// generate_client()
	// -------------------------------------------------------------------------

	public function test_generate_client_stores_and_returns_new_client(): void {
		$result = Tsubakuro_OAuth::generate_client( 'My App', 1 );

		$this->assertNotEmpty( $result['client_id'] );
		$this->assertNotEmpty( $result['client_secret'] );
		$this->assertSame( 'My App', $result['name'] );
		$this->assertSame( 1, $result['user_id'] );

		$clients = Tsubakuro_OAuth::get_clients();
		$this->assertCount( 1, $clients );
		$this->assertSame( $result['client_id'], $clients[0]['client_id'] );
		// Secret hash must not equal plain text.
		$this->assertNotSame( $result['client_secret'], $clients[0]['secret_hash'] );
	}

	public function test_generate_client_accumulates_multiple_clients(): void {
		Tsubakuro_OAuth::generate_client( 'Client A', 1 );
		Tsubakuro_OAuth::generate_client( 'Client B', 2 );

		$this->assertCount( 2, Tsubakuro_OAuth::get_clients() );
	}

	// -------------------------------------------------------------------------
	// revoke_client()
	// -------------------------------------------------------------------------

	public function test_revoke_client_removes_matching_client(): void {
		$c1 = Tsubakuro_OAuth::generate_client( 'A', 1 );
		$c2 = Tsubakuro_OAuth::generate_client( 'B', 1 );

		Tsubakuro_OAuth::revoke_client( $c1['client_id'] );

		$clients = Tsubakuro_OAuth::get_clients();
		$this->assertCount( 1, $clients );
		$this->assertSame( $c2['client_id'], $clients[0]['client_id'] );
	}

	public function test_revoke_client_is_no_op_for_unknown_id(): void {
		Tsubakuro_OAuth::generate_client( 'A', 1 );
		Tsubakuro_OAuth::revoke_client( 'nonexistent' );

		$this->assertCount( 1, Tsubakuro_OAuth::get_clients() );
	}

	// -------------------------------------------------------------------------
	// ajax_generate_client()
	// -------------------------------------------------------------------------

	public function test_ajax_generate_client_requires_manage_options(): void {
		$GLOBALS['tsubakuro_test']['can']['manage_options'] = false;
		$_POST = array( 'nonce' => 'nonce', 'name' => 'Test' );

		$this->expectNotToPerformAssertions();
		// Should call wp_send_json_error but not throw.
		Tsubakuro_OAuth::ajax_generate_client();
	}

	// -------------------------------------------------------------------------
	// ajax_revoke_client()
	// -------------------------------------------------------------------------

	public function test_ajax_revoke_client_requires_manage_options(): void {
		$GLOBALS['tsubakuro_test']['can']['manage_options'] = false;
		$_POST = array( 'nonce' => 'nonce', 'client_id' => 'xyz' );

		$this->expectNotToPerformAssertions();
		Tsubakuro_OAuth::ajax_revoke_client();
	}
}
