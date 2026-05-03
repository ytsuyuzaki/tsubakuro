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
		$this->assertArrayHasKey( 'admin_post_tsubakuro_oauth_consent', $GLOBALS['tsubakuro_test']['actions'] );
		$this->assertArrayHasKey( 'admin_post_nopriv_tsubakuro_oauth_consent', $GLOBALS['tsubakuro_test']['actions'] );
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

	public function test_register_routes_creates_authorize_endpoint(): void {
		Tsubakuro_OAuth::register_routes();

		$this->assertArrayHasKey(
			'tsubakuro/v1/oauth/authorize',
			$GLOBALS['tsubakuro_test']['rest_routes']
		);
	}

	public function test_register_routes_creates_metadata_endpoint(): void {
		Tsubakuro_OAuth::register_routes();

		$this->assertArrayHasKey(
			'tsubakuro/v1/oauth/metadata',
			$GLOBALS['tsubakuro_test']['rest_routes']
		);
	}

	// -------------------------------------------------------------------------
	// handle_token() – token endpoint
	// -------------------------------------------------------------------------

	public function test_handle_token_returns_error_for_unsupported_grant_type(): void {
		$req    = $this->make_token_request(
			array( 'grant_type' => 'implicit', 'client_id' => 'x', 'client_secret' => 'y' )
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
	// handle_token() – authorization_code grant type
	// -------------------------------------------------------------------------

	public function test_handle_token_authorization_code_returns_error_when_code_missing(): void {
		$generated = Tsubakuro_OAuth::generate_client( 'AC Client', 1, 'https://example.com/callback' );

		$req    = $this->make_token_request(
			array(
				'grant_type'    => 'authorization_code',
				'client_id'     => $generated['client_id'],
				'client_secret' => $generated['client_secret'],
				'redirect_uri'  => 'https://example.com/callback',
			)
		);
		$result = Tsubakuro_OAuth::handle_token( $req );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'invalid_request', $result->get_error_code() );
	}

	public function test_handle_token_authorization_code_returns_error_when_redirect_uri_missing(): void {
		$generated = Tsubakuro_OAuth::generate_client( 'AC Client', 1, 'https://example.com/callback' );

		$req    = $this->make_token_request(
			array(
				'grant_type'    => 'authorization_code',
				'client_id'     => $generated['client_id'],
				'client_secret' => $generated['client_secret'],
				'code'          => 'somecode',
			)
		);
		$result = Tsubakuro_OAuth::handle_token( $req );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'invalid_request', $result->get_error_code() );
	}

	public function test_handle_token_authorization_code_returns_error_for_unknown_client(): void {
		$req    = $this->make_token_request(
			array(
				'grant_type'    => 'authorization_code',
				'client_id'     => 'no_such_client',
				'client_secret' => 'secret',
				'code'          => 'somecode',
				'redirect_uri'  => 'https://example.com/callback',
			)
		);
		$result = Tsubakuro_OAuth::handle_token( $req );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'invalid_client', $result->get_error_code() );
	}

	public function test_handle_token_authorization_code_returns_error_for_wrong_secret(): void {
		$generated = Tsubakuro_OAuth::generate_client( 'AC Client', 1, 'https://example.com/callback' );

		$req    = $this->make_token_request(
			array(
				'grant_type'    => 'authorization_code',
				'client_id'     => $generated['client_id'],
				'client_secret' => 'WRONG_SECRET',
				'code'          => 'somecode',
				'redirect_uri'  => 'https://example.com/callback',
			)
		);
		$result = Tsubakuro_OAuth::handle_token( $req );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'invalid_client', $result->get_error_code() );
	}

	public function test_handle_token_authorization_code_returns_error_for_invalid_code(): void {
		$generated = Tsubakuro_OAuth::generate_client( 'AC Client', 1, 'https://example.com/callback' );

		$req    = $this->make_token_request(
			array(
				'grant_type'    => 'authorization_code',
				'client_id'     => $generated['client_id'],
				'client_secret' => $generated['client_secret'],
				'code'          => 'bad_code_that_does_not_exist',
				'redirect_uri'  => 'https://example.com/callback',
			)
		);
		$result = Tsubakuro_OAuth::handle_token( $req );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'invalid_grant', $result->get_error_code() );
	}

	public function test_handle_token_authorization_code_issues_token_for_valid_code(): void {
		$generated    = Tsubakuro_OAuth::generate_client( 'AC Client', 9, 'https://claude.ai/callback' );
		$redirect_uri = 'https://claude.ai/callback';

		// Simulate a code that was issued after the user approved consent.
		$code_key = 'tsubakuro_code_' . hash( 'sha256', 'testcode123' );
		$GLOBALS['tsubakuro_test']['transients'][ $code_key ] = array(
			'client_id'    => $generated['client_id'],
			'user_id'      => 9,
			'redirect_uri' => $redirect_uri,
		);

		$req    = $this->make_token_request(
			array(
				'grant_type'    => 'authorization_code',
				'client_id'     => $generated['client_id'],
				'client_secret' => $generated['client_secret'],
				'code'          => 'testcode123',
				'redirect_uri'  => $redirect_uri,
			)
		);
		$result = Tsubakuro_OAuth::handle_token( $req );

		$this->assertIsArray( $result );
		$this->assertSame( 'Bearer', $result['token_type'] );
		$this->assertNotEmpty( $result['access_token'] );

		// Code must be consumed (one-time use).
		$this->assertArrayNotHasKey( $code_key, $GLOBALS['tsubakuro_test']['transients'] );
	}

	public function test_handle_token_authorization_code_returns_error_when_redirect_uri_mismatch(): void {
		$generated    = Tsubakuro_OAuth::generate_client( 'AC Client', 9, 'https://claude.ai/callback' );

		$code_key = 'tsubakuro_code_' . hash( 'sha256', 'testcode456' );
		$GLOBALS['tsubakuro_test']['transients'][ $code_key ] = array(
			'client_id'    => $generated['client_id'],
			'user_id'      => 9,
			'redirect_uri' => 'https://claude.ai/callback',
		);

		$req    = $this->make_token_request(
			array(
				'grant_type'    => 'authorization_code',
				'client_id'     => $generated['client_id'],
				'client_secret' => $generated['client_secret'],
				'code'          => 'testcode456',
				'redirect_uri'  => 'https://evil.example.com/callback', // Mismatch!
			)
		);
		$result = Tsubakuro_OAuth::handle_token( $req );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'invalid_grant', $result->get_error_code() );
	}

	public function test_handle_token_authorization_code_returns_error_when_client_mismatch(): void {
		$client_a = Tsubakuro_OAuth::generate_client( 'Client A', 1, 'https://a.example.com/callback' );
		$client_b = Tsubakuro_OAuth::generate_client( 'Client B', 2, 'https://b.example.com/callback' );

		// Code was issued for client_a but client_b is trying to use it.
		$code_key = 'tsubakuro_code_' . hash( 'sha256', 'testcodeX' );
		$GLOBALS['tsubakuro_test']['transients'][ $code_key ] = array(
			'client_id'    => $client_a['client_id'],
			'user_id'      => 1,
			'redirect_uri' => 'https://a.example.com/callback',
		);

		$req    = $this->make_token_request(
			array(
				'grant_type'    => 'authorization_code',
				'client_id'     => $client_b['client_id'],
				'client_secret' => $client_b['client_secret'],
				'code'          => 'testcodeX',
				'redirect_uri'  => 'https://b.example.com/callback',
			)
		);
		$result = Tsubakuro_OAuth::handle_token( $req );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'invalid_grant', $result->get_error_code() );
	}

	// -------------------------------------------------------------------------
	// handle_authorize() – authorization endpoint
	// -------------------------------------------------------------------------

	public function test_handle_authorize_returns_error_for_non_code_response_type(): void {
		$req    = new WP_REST_Request( array( 'response_type' => 'token', 'client_id' => 'x', 'redirect_uri' => 'https://example.com' ) );
		$result = Tsubakuro_OAuth::handle_authorize( $req );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'unsupported_response_type', $result->get_error_code() );
	}

	public function test_handle_authorize_returns_error_when_client_id_missing(): void {
		$req    = new WP_REST_Request( array( 'response_type' => 'code', 'redirect_uri' => 'https://example.com' ) );
		$result = Tsubakuro_OAuth::handle_authorize( $req );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'invalid_request', $result->get_error_code() );
	}

	public function test_handle_authorize_returns_error_for_unknown_client(): void {
		$req    = new WP_REST_Request( array( 'response_type' => 'code', 'client_id' => 'unknown', 'redirect_uri' => 'https://example.com' ) );
		$result = Tsubakuro_OAuth::handle_authorize( $req );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'invalid_client', $result->get_error_code() );
	}

	public function test_handle_authorize_returns_error_when_redirect_uri_missing(): void {
		$generated = Tsubakuro_OAuth::generate_client( 'Test App', 1, 'https://example.com/cb' );

		$req    = new WP_REST_Request( array( 'response_type' => 'code', 'client_id' => $generated['client_id'] ) );
		$result = Tsubakuro_OAuth::handle_authorize( $req );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'invalid_request', $result->get_error_code() );
	}

	public function test_handle_authorize_returns_error_for_mismatched_redirect_uri(): void {
		$generated = Tsubakuro_OAuth::generate_client( 'Test App', 1, 'https://allowed.example.com/cb' );

		$req    = new WP_REST_Request(
			array(
				'response_type' => 'code',
				'client_id'     => $generated['client_id'],
				'redirect_uri'  => 'https://evil.example.com/cb',
			)
		);
		$result = Tsubakuro_OAuth::handle_authorize( $req );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'invalid_redirect_uri', $result->get_error_code() );
	}

	// -------------------------------------------------------------------------
	// process_consent() – consent business logic
	// -------------------------------------------------------------------------

	public function test_process_consent_returns_error_on_invalid_nonce(): void {
		$generated = Tsubakuro_OAuth::generate_client( 'App', 1, 'https://example.com/cb' );

		$result = Tsubakuro_OAuth::process_consent(
			$generated['client_id'],
			'https://example.com/cb',
			'xyz',
			'approve',
			'bad_nonce'
		);

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'invalid_nonce', $result->get_error_code() );
	}

	public function test_process_consent_returns_error_when_not_logged_in(): void {
		$generated = Tsubakuro_OAuth::generate_client( 'App', 1, 'https://example.com/cb' );
		$GLOBALS['tsubakuro_test']['is_logged_in'] = false;

		$result = Tsubakuro_OAuth::process_consent(
			$generated['client_id'],
			'https://example.com/cb',
			'xyz',
			'approve',
			'nonce'
		);

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'not_logged_in', $result->get_error_code() );
	}

	public function test_process_consent_returns_redirect_with_error_when_denied(): void {
		$generated = Tsubakuro_OAuth::generate_client( 'App', 1, 'https://example.com/cb' );
		$GLOBALS['tsubakuro_test']['is_logged_in'] = true;

		$result = Tsubakuro_OAuth::process_consent(
			$generated['client_id'],
			'https://example.com/cb',
			'abc',
			'deny',
			'nonce'
		);

		$this->assertIsString( $result );
		$this->assertStringContainsString( 'error=access_denied', $result );
		$this->assertStringContainsString( 'state=abc', $result );
	}

	public function test_process_consent_returns_redirect_with_code_when_approved(): void {
		$generated = Tsubakuro_OAuth::generate_client( 'App', 7, 'https://claude.ai/callback' );
		$GLOBALS['tsubakuro_test']['is_logged_in'] = true;

		$result = Tsubakuro_OAuth::process_consent(
			$generated['client_id'],
			'https://claude.ai/callback',
			'mystate',
			'approve',
			'nonce'
		);

		$this->assertIsString( $result );
		$this->assertStringContainsString( 'code=', $result );
		$this->assertStringContainsString( 'state=mystate', $result );
		$this->assertStringContainsString( 'claude.ai', $result );
	}

	public function test_process_consent_returns_error_for_invalid_redirect_uri(): void {
		$generated = Tsubakuro_OAuth::generate_client( 'App', 1, 'https://example.com/cb' );
		$GLOBALS['tsubakuro_test']['is_logged_in'] = true;

		$result = Tsubakuro_OAuth::process_consent(
			$generated['client_id'],
			'https://evil.com/cb',    // Mismatched URI
			'',
			'approve',
			'nonce'
		);

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'invalid_redirect_uri', $result->get_error_code() );
	}

	// -------------------------------------------------------------------------
	// handle_metadata()
	// -------------------------------------------------------------------------

	public function test_handle_metadata_returns_required_fields(): void {
		$result = Tsubakuro_OAuth::handle_metadata();

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'issuer', $result );
		$this->assertArrayHasKey( 'authorization_endpoint', $result );
		$this->assertArrayHasKey( 'token_endpoint', $result );
		$this->assertArrayHasKey( 'response_types_supported', $result );
		$this->assertArrayHasKey( 'grant_types_supported', $result );
	}

	public function test_handle_metadata_endpoints_contain_correct_paths(): void {
		$result = Tsubakuro_OAuth::handle_metadata();

		$this->assertStringContainsString( 'oauth/authorize', $result['authorization_endpoint'] );
		$this->assertStringContainsString( 'oauth/token', $result['token_endpoint'] );
		$this->assertContains( 'code', $result['response_types_supported'] );
		$this->assertContains( 'authorization_code', $result['grant_types_supported'] );
		$this->assertContains( 'client_credentials', $result['grant_types_supported'] );
	}

	// -------------------------------------------------------------------------
	// generate_client() – redirect_uri support
	// -------------------------------------------------------------------------

	public function test_generate_client_stores_redirect_uri(): void {
		$result = Tsubakuro_OAuth::generate_client( 'Claude App', 1, 'https://claude.ai/callback' );

		$this->assertSame( 'https://claude.ai/callback', $result['redirect_uri'] );

		$clients = Tsubakuro_OAuth::get_clients();
		$this->assertSame( 'https://claude.ai/callback', $clients[0]['redirect_uri'] );
	}

	public function test_generate_client_stores_empty_redirect_uri_when_not_provided(): void {
		$result = Tsubakuro_OAuth::generate_client( 'CLI App', 1 );

		$this->assertSame( '', $result['redirect_uri'] );
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
	// has_valid_bearer_token()
	// -------------------------------------------------------------------------

	public function test_has_valid_bearer_token_returns_false_when_no_header(): void {
		$this->assertFalse( Tsubakuro_OAuth::has_valid_bearer_token() );
	}

	public function test_has_valid_bearer_token_returns_false_for_invalid_token(): void {
		$_SERVER['HTTP_AUTHORIZATION'] = 'Bearer invalidtoken';
		$this->assertFalse( Tsubakuro_OAuth::has_valid_bearer_token() );
	}

	public function test_has_valid_bearer_token_returns_false_for_basic_auth(): void {
		$_SERVER['HTTP_AUTHORIZATION'] = 'Basic ' . base64_encode( 'admin:password' );
		$this->assertFalse( Tsubakuro_OAuth::has_valid_bearer_token() );
	}

	public function test_has_valid_bearer_token_returns_true_for_valid_token(): void {
		$generated = Tsubakuro_OAuth::generate_client( 'Valid Client', 1 );
		$req       = $this->make_token_request(
			array(
				'grant_type'    => 'client_credentials',
				'client_id'     => $generated['client_id'],
				'client_secret' => $generated['client_secret'],
			)
		);
		$token_response                = Tsubakuro_OAuth::handle_token( $req );
		$_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $token_response['access_token'];

		$this->assertTrue( Tsubakuro_OAuth::has_valid_bearer_token() );
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
