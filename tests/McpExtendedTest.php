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

	private function dispatch( array $rpc ): ?array {
		$reflection = new ReflectionClass( 'Tsubakuro_MCP' );
		$method     = $reflection->getMethod( 'dispatch' );
		$method->setAccessible( true );
		return $method->invoke( null, $rpc );
	}

	private function make_post( int $id, string $title, string $content = '' ): object {
		return (object) array(
			'ID'            => $id,
			'post_type'     => 'tsubakuro_task',
			'post_title'    => $title,
			'post_content'  => $content,
			'post_date'     => '2026-05-01 10:00:00',
			'post_modified' => '2026-05-01 11:00:00',
			'post_author'   => 1,
		);
	}

	public function test_handle_get_returns_json_rpc_error_when_authorized(): void {
		$_SERVER['HTTP_AUTHORIZATION'] = 'Basic ' . base64_encode( 'admin:password' );

		$result = Tsubakuro_MCP::handle_get();

		$this->assertSame( '2.0', $result['jsonrpc'] );
		$this->assertSame( -32000, $result['error']['code'] );
		$this->assertStringContainsString( 'SSE stream is not available', $result['error']['message'] );
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

	public function test_handle_jsonrpc_rejects_batch_requests(): void {
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

		$this->assertSame( '2.0', $result['jsonrpc'] );
		$this->assertSame( -32600, $result['error']['code'] );
		$this->assertNull( $result['id'] );
	}

	public function test_handle_jsonrpc_accepts_json_rpc_response_messages(): void {
		$_SERVER['HTTP_AUTHORIZATION'] = 'Basic ' . base64_encode( 'admin:password' );
		$req                           = new WP_REST_Request(
			array(),
			array(
				'jsonrpc' => '2.0',
				'id'      => 1,
				'result'  => array(),
			)
		);
		$result                        = Tsubakuro_MCP::handle_jsonrpc( $req );

		$this->assertSame( '', $result );
	}

	public function test_initialize_returns_mcp_server_metadata(): void {
		$result = $this->dispatch(
			array(
				'jsonrpc' => '2.0',
				'id'      => 1,
				'method'  => 'initialize',
				'params'  => array(
					'protocolVersion' => '2025-11-25',
					'capabilities'    => array(),
					'clientInfo'      => array(
						'name'    => 'curl-test',
						'version' => '0.1.0',
					),
				),
			)
		);

		$this->assertSame( '2.0', $result['jsonrpc'] );
		$this->assertSame( '2025-11-25', $result['result']['protocolVersion'] );
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

	public function test_request_with_null_id_is_invalid(): void {
		$result = $this->dispatch(
			array(
				'jsonrpc' => '2.0',
				'id'      => null,
				'method'  => 'tools/list',
			)
		);

		$this->assertSame( -32600, $result['error']['code'] );
		$this->assertNull( $result['id'] );
	}

	public function test_standard_initialized_notification_returns_no_json_rpc_response(): void {
		$reflection = new ReflectionClass( 'Tsubakuro_MCP' );
		$method     = $reflection->getMethod( 'dispatch' );
		$method->setAccessible( true );
		$result     = $method->invoke(
			null,
			array(
				'jsonrpc' => '2.0',
				'method'  => 'notifications/initialized',
			)
		);

		$this->assertNull( $result );
	}

	public function test_handle_jsonrpc_returns_empty_response_for_standard_notification(): void {
		$_SERVER['HTTP_AUTHORIZATION'] = 'Basic ' . base64_encode( 'admin:password' );
		$req                           = new WP_REST_Request(
			array(),
			array(
				'jsonrpc' => '2.0',
				'method'  => 'notifications/initialized',
			)
		);
		$result                        = Tsubakuro_MCP::handle_jsonrpc( $req );

		$this->assertSame( '', $result );
	}

	public function test_unknown_notification_returns_no_json_rpc_response(): void {
		$result = $this->dispatch(
			array(
				'jsonrpc' => '2.0',
				'method'  => 'notifications/unknown',
			)
		);

		$this->assertNull( $result );
	}

	public function test_tools_list_returns_task_tools(): void {
		$result = $this->dispatch(
			array(
				'jsonrpc' => '2.0',
				'id'      => 2,
				'method'  => 'tools/list',
				'params'  => array(),
			)
		);

		$tools = array_column( $result['result']['tools'], 'name' );

		$this->assertSame( 'tsubakuro_list_tasks', $result['result']['tools'][0]['name'] );
		$this->assertSame( 'object', $result['result']['tools'][0]['inputSchema']['type'] );
		$this->assertNotContains( 'ping', $tools );
		$this->assertContains( 'tsubakuro_list_tasks', $tools );
		$this->assertContains( 'tsubakuro_update_task', $tools );
		$this->assertContains( 'tsubakuro_add_comment', $tools );
	}

	public function test_tools_call_list_tasks_returns_task_list(): void {
		$GLOBALS['tsubakuro_test']['posts'][1] = $this->make_post( 1, 'Alpha' );
		$GLOBALS['tsubakuro_test']['posts'][2] = $this->make_post( 2, 'Beta' );

		$result = $this->dispatch(
			array(
				'jsonrpc' => '2.0',
				'id'      => 10,
				'method'  => 'tools/call',
				'params'  => array(
					'name'      => 'tsubakuro_list_tasks',
					'arguments' => array(
						'per_page' => 10,
					),
				),
			)
		);

		$this->assertCount( 2, $result['result']['structuredContent']['tasks'] );
		$this->assertSame( 'Alpha', $result['result']['structuredContent']['tasks'][0]['title'] );
		$this->assertStringContainsString( 'Alpha', $result['result']['content'][0]['text'] );
	}

	public function test_tools_call_get_task_returns_task_with_comments(): void {
		$GLOBALS['tsubakuro_test']['posts'][101] = $this->make_post( 101, 'Task Z' );
		$GLOBALS['tsubakuro_test']['posts'][201] = (object) array(
			'ID'            => 201,
			'post_type'     => Tsubakuro_Post_Types::COMMENT_POST_TYPE,
			'post_author'   => 0,
			'post_content'  => 'Note',
			'post_date'     => '2026-05-01 10:00:00',
			'post_modified' => '2026-05-01 10:00:00',
		);
		$GLOBALS['tsubakuro_test']['post_meta'][201]['_tsubakuro_task_id'] = array( 101 );

		$result = $this->dispatch(
			array(
				'jsonrpc' => '2.0',
				'id'      => 11,
				'method'  => 'tools/call',
				'params'  => array(
					'name'      => 'tsubakuro_get_task',
					'arguments' => array(
						'id' => 101,
					),
				),
			)
		);

		$this->assertSame( 101, $result['result']['structuredContent']['task']['id'] );
		$this->assertCount( 1, $result['result']['structuredContent']['task']['comments'] );
	}

	public function test_tools_call_create_task_creates_and_returns_task(): void {
		$GLOBALS['tsubakuro_test']['posts'][123] = $this->make_post( 123, 'New Task' );

		$result = $this->dispatch(
			array(
				'jsonrpc' => '2.0',
				'id'      => 12,
				'method'  => 'tools/call',
				'params'  => array(
					'name'      => 'tsubakuro_create_task',
					'arguments' => array(
						'title'  => 'New Task',
						'status' => 'todo',
					),
				),
			)
		);

		$this->assertSame( 123, $result['result']['structuredContent']['task']['id'] );
	}

	public function test_tools_call_update_task_updates_meta_and_returns_task(): void {
		$GLOBALS['tsubakuro_test']['posts'][101] = $this->make_post( 101, 'Old' );

		$result = $this->dispatch(
			array(
				'jsonrpc' => '2.0',
				'id'      => 13,
				'method'  => 'tools/call',
				'params'  => array(
					'name'      => 'tsubakuro_update_task',
					'arguments' => array(
						'id'     => 101,
						'status' => 'completed',
					),
				),
			)
		);

		$this->assertSame( 101, $result['result']['structuredContent']['task']['id'] );
		$this->assertSame( array( 'completed' ), $GLOBALS['tsubakuro_test']['post_meta'][101]['_tsubakuro_status'] );
	}

	public function test_tools_call_delete_task_deletes_and_confirms(): void {
		$GLOBALS['tsubakuro_test']['posts'][101] = $this->make_post( 101, 'Task' );

		$result = $this->dispatch(
			array(
				'jsonrpc' => '2.0',
				'id'      => 14,
				'method'  => 'tools/call',
				'params'  => array(
					'name'      => 'tsubakuro_delete_task',
					'arguments' => array(
						'id' => 101,
					),
				),
			)
		);

		$this->assertTrue( $result['result']['structuredContent']['deleted'] );
		$this->assertSame( array( 101 ), $GLOBALS['tsubakuro_test']['deleted_posts'] );
	}

	public function test_tools_call_add_comment_inserts_and_returns_comment(): void {
		$GLOBALS['tsubakuro_test']['posts'][101] = $this->make_post( 101, 'Task' );
		$GLOBALS['tsubakuro_test']['users'][7]   = (object) array(
			'ID'           => 7,
			'display_name' => 'Bob',
		);

		$result = $this->dispatch(
			array(
				'jsonrpc' => '2.0',
				'id'      => 15,
				'method'  => 'tools/call',
				'params'  => array(
					'name'      => 'tsubakuro_add_comment',
					'arguments' => array(
						'id'      => 101,
						'comment' => 'Great work',
					),
				),
			)
		);

		$this->assertSame( 'Great work', $result['result']['structuredContent']['comment']['comment'] );
		$this->assertSame( 'Bob', $result['result']['structuredContent']['comment']['user_name'] );
	}

	public function test_tools_call_task_tool_returns_error_for_missing_required_argument(): void {
		$result = $this->dispatch(
			array(
				'jsonrpc' => '2.0',
				'id'      => 16,
				'method'  => 'tools/call',
				'params'  => array(
					'name'      => 'tsubakuro_get_task',
					'arguments' => array(),
				),
			)
		);

		$this->assertSame( -32602, $result['error']['code'] );
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

	public function test_resources_list_returns_mcp_guide_resource(): void {
		$resources = $this->dispatch(
			array(
				'jsonrpc' => '2.0',
				'id'      => 5,
				'method'  => 'resources/list',
			)
		);

		$this->assertSame( 'Tsubakuro MCP Guide', $resources['result']['resources'][0]['name'] );
		$this->assertSame( 'text/markdown', $resources['result']['resources'][0]['mimeType'] );
		$this->assertStringContainsString( 'page=tsubakuro-mcp-guide', $resources['result']['resources'][0]['uri'] );
	}

	public function test_resources_read_returns_mcp_guide_document(): void {
		$uri    = 'https://example.test/wp-admin/admin.php?page=tsubakuro-mcp-guide';
		$result = $this->dispatch(
			array(
				'jsonrpc' => '2.0',
				'id'      => 6,
				'method'  => 'resources/read',
				'params'  => array(
					'uri' => $uri,
				),
			)
		);

		$this->assertSame( $uri, $result['result']['contents'][0]['uri'] );
		$this->assertSame( 'text/markdown', $result['result']['contents'][0]['mimeType'] );
		$this->assertStringContainsString( 'page=tsubakuro-mcp-guide', $result['result']['contents'][0]['text'] );
		$this->assertStringContainsString( 'resources/read', $result['result']['contents'][0]['text'] );
	}

	public function test_resources_read_unknown_resource_returns_json_rpc_error(): void {
		$result = $this->dispatch(
			array(
				'jsonrpc' => '2.0',
				'id'      => 7,
				'method'  => 'resources/read',
				'params'  => array(
					'uri' => 'https://example.test/wp-admin/admin.php?page=missing',
				),
			)
		);

		$this->assertSame( -32602, $result['error']['code'] );
	}

	public function test_prompts_list_returns_empty_array(): void {
		$prompts   = $this->dispatch(
			array(
				'jsonrpc' => '2.0',
				'id'      => 8,
				'method'  => 'prompts/list',
			)
		);

		$this->assertSame( array(), $prompts['result']['prompts'] );
	}

	public function test_dispatch_returns_method_not_found_for_unknown_method(): void {
		$result = $this->dispatch(
			array(
				'jsonrpc' => '2.0',
				'id'      => 9,
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

	public function test_check_permission_returns_false_when_no_authorization_header(): void {
		$this->assertFalse( Tsubakuro_MCP::check_permission() );
	}

	public function test_check_permission_returns_false_for_bearer_token(): void {
		$_SERVER['HTTP_AUTHORIZATION'] = 'Bearer unsupported-token';

		$this->assertFalse( Tsubakuro_MCP::check_permission() );
	}

	public function test_check_permission_returns_false_when_user_cannot_edit(): void {
		$_SERVER['HTTP_AUTHORIZATION']                 = 'Basic ' . base64_encode( 'admin:password' );
		$GLOBALS['tsubakuro_test']['can']['edit_posts'] = false;

		$this->assertFalse( Tsubakuro_MCP::check_permission() );
	}
}
