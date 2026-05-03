<?php

use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the minimal MCP JSON-RPC endpoint.
 */
class McpExtendedTest extends TestCase {

	protected function setUp(): void {
		tsubakuro_test_reset();
		unset( $_SERVER['HTTP_AUTHORIZATION'], $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] );
	}

	protected function tearDown(): void {
		unset( $_SERVER['HTTP_AUTHORIZATION'], $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] );
	}

	private function dispatch( array $rpc ): array {
		$reflection = new ReflectionClass( 'Tsubakuro_MCP' );
		$method     = $reflection->getMethod( 'dispatch' );
		$method->setAccessible( true );
		return $method->invoke( null, $rpc );
	}

	private function set_valid_bearer_header(): void {
		$generated = Tsubakuro_OAuth::generate_client( 'Test Client', 1 );
		$req       = new WP_REST_Request(
			array(),
			array(
				'grant_type'    => 'client_credentials',
				'client_id'     => $generated['client_id'],
				'client_secret' => $generated['client_secret'],
			)
		);
		$token_response                = Tsubakuro_OAuth::handle_token( $req );
		$_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $token_response['access_token'];
	}

	public function test_handle_get_returns_manifest_when_authorized(): void {
		$_SERVER['HTTP_AUTHORIZATION'] = 'Basic ' . base64_encode( 'admin:password' );

		$result = Tsubakuro_MCP::handle_get();

		$this->assertSame( '2024-11-05', $result['protocolVersion'] );
		$this->assertSame( 'streamable-http', $result['transport'] );
	}

	public function test_handle_jsonrpc_returns_401_when_unauthorized(): void {
		$req    = new WP_REST_Request(
			array(),
			array(
				'jsonrpc' => '2.0',
				'id'      => 1,
				'method'  => 'initialize',
			)
		);
		$result = Tsubakuro_MCP::handle_jsonrpc( $req );

		$this->assertSame( -32001, $result['error']['code'] );
	}

	public function test_handle_jsonrpc_returns_parse_error_for_empty_body(): void {
		$_SERVER['HTTP_AUTHORIZATION'] = 'Basic ' . base64_encode( 'admin:password' );
		$req                           = new WP_REST_Request( array(), null );
		$result                        = Tsubakuro_MCP::handle_jsonrpc( $req );

		$this->assertSame( -32700, $result['error']['code'] );
	}

	public function test_handle_jsonrpc_dispatches_batch_requests(): void {
		$_SERVER['HTTP_AUTHORIZATION'] = 'Basic ' . base64_encode( 'admin:password' );
		$req                           = new WP_REST_Request(
			array(),
			array(
				array(
					'jsonrpc' => '2.0',
					'id'      => 1,
					'method'  => 'tools/list',
				),
				array(
					'jsonrpc' => '2.0',
					'id'      => 2,
					'method'  => 'resources/list',
				),
			)
		);
		$result                        = Tsubakuro_MCP::handle_jsonrpc( $req );

		$this->assertCount( 2, $result );
		$this->assertSame( 1, $result[0]['id'] );
		$this->assertSame( 2, $result[1]['id'] );
	}

	public function test_initialize_returns_mcp_server_metadata(): void {
		$result = $this->dispatch(
			array(
				'jsonrpc' => '2.0',
				'id'      => 1,
				'method'  => 'initialize',
				'params'  => array(
					'protocolVersion' => '2024-11-05',
					'capabilities'    => array(),
					'clientInfo'      => array(
						'name'    => 'curl-test',
						'version' => '0.1.0',
					),
				),
			)
		);

		$this->assertSame( '2.0', $result['jsonrpc'] );
		$this->assertSame( '2024-11-05', $result['result']['protocolVersion'] );
		$this->assertSame( 'tsubakuro-wordpress-mcp', $result['result']['serverInfo']['name'] );
	}

	public function test_initialized_notification_returns_no_json_rpc_response(): void {
		$reflection = new ReflectionClass( 'Tsubakuro_MCP' );
		$method     = $reflection->getMethod( 'dispatch' );
		$method->setAccessible( true );
		$result = $method->invoke(
			null,
			array(
				'jsonrpc' => '2.0',
				'method'  => 'initialized',
			)
		);

		$this->assertNull( $result );
	}

	public function test_tools_list_returns_ping_tool(): void {
		$result = $this->dispatch(
			array(
				'jsonrpc' => '2.0',
				'id'      => 2,
				'method'  => 'tools/list',
				'params'  => array(),
			)
		);

		$this->assertSame( 'ping', $result['result']['tools'][0]['name'] );
		$this->assertSame( 'object', $result['result']['tools'][0]['inputSchema']['type'] );
	}

	public function test_tools_call_ping_returns_pong(): void {
		$result = $this->dispatch(
			array(
				'jsonrpc' => '2.0',
				'id'      => 3,
				'method'  => 'tools/call',
				'params'  => array(
					'name'      => 'ping',
					'arguments' => array(),
				),
			)
		);

		$this->assertSame( 'pong', $result['result']['content'][0]['text'] );
	}

	public function test_tools_call_unknown_tool_returns_json_rpc_error(): void {
		$result = $this->dispatch(
			array(
				'jsonrpc' => '2.0',
				'id'      => 4,
				'method'  => 'tools/call',
				'params'  => array(
					'name' => 'missing',
				),
			)
		);

		$this->assertSame( -32602, $result['error']['code'] );
		$this->assertStringContainsString( 'missing', $result['error']['message'] );
	}

	public function test_resources_and_prompts_list_return_empty_arrays(): void {
		$resources = $this->dispatch(
			array(
				'jsonrpc' => '2.0',
				'id'      => 5,
				'method'  => 'resources/list',
			)
		);
		$prompts   = $this->dispatch(
			array(
				'jsonrpc' => '2.0',
				'id'      => 6,
				'method'  => 'prompts/list',
			)
		);

		$this->assertSame( array(), $resources['result']['resources'] );
		$this->assertSame( array(), $prompts['result']['prompts'] );
	}

	public function test_dispatch_returns_method_not_found_for_unknown_method(): void {
		$result = $this->dispatch(
			array(
				'jsonrpc' => '2.0',
				'id'      => 7,
				'method'  => 'totally_unknown',
				'params'  => array(),
			)
		);

		$this->assertSame( -32601, $result['error']['code'] );
		$this->assertStringContainsString( 'totally_unknown', $result['error']['message'] );
	}

	public function test_check_permission_returns_true_for_basic_auth_when_user_can_edit(): void {
		$_SERVER['HTTP_AUTHORIZATION'] = 'Basic ' . base64_encode( 'admin:password' );

		$this->assertTrue( Tsubakuro_MCP::check_permission() );
	}

	public function test_check_permission_returns_true_for_valid_bearer_token(): void {
		$this->set_valid_bearer_header();

		$this->assertTrue( Tsubakuro_MCP::check_permission() );
	}

	public function test_check_permission_returns_false_when_no_authorization_header(): void {
		$this->assertFalse( Tsubakuro_MCP::check_permission() );
	}

	public function test_check_permission_returns_false_for_invalid_bearer_token(): void {
		$_SERVER['HTTP_AUTHORIZATION'] = 'Bearer invalidtoken';

		$this->assertFalse( Tsubakuro_MCP::check_permission() );
	}

	public function test_check_permission_returns_false_when_user_cannot_edit(): void {
		$_SERVER['HTTP_AUTHORIZATION']                 = 'Basic ' . base64_encode( 'admin:password' );
		$GLOBALS['tsubakuro_test']['can']['edit_posts'] = false;

		$this->assertFalse( Tsubakuro_MCP::check_permission() );
	}
}
