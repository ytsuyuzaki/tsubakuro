<?php

use PHPUnit\Framework\TestCase;

/**
 * Extended unit tests for Tsubakuro_MCP tool implementations and dispatch
 * paths not covered by TsubakuroTest.
 */
class McpExtendedTest extends TestCase {

	protected function setUp(): void {
		tsubakuro_test_reset();
	}

	// -------------------------------------------------------------------------
	// Helper
	// -------------------------------------------------------------------------

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

	private function dispatch( array $rpc ): array {
		$reflection = new ReflectionClass( 'Tsubakuro_MCP' );
		$method     = $reflection->getMethod( 'dispatch' );
		$method->setAccessible( true );
		return $method->invoke( null, $rpc );
	}

	// -------------------------------------------------------------------------
	// handle_manifest()
	// -------------------------------------------------------------------------

	public function test_handle_manifest_returns_manifest_array(): void {
		$result = Tsubakuro_MCP::handle_manifest();

		$this->assertSame( '2024-11-05', $result['schema_version'] );
		$this->assertArrayHasKey( 'tools', $result );
	}

	// -------------------------------------------------------------------------
	// handle_jsonrpc() – top-level parsing
	// -------------------------------------------------------------------------

	public function test_handle_jsonrpc_returns_parse_error_for_empty_body(): void {
		$req    = new WP_REST_Request( array(), null );
		$result = Tsubakuro_MCP::handle_jsonrpc( $req );

		$this->assertSame( -32700, $result['error']['code'] );
	}

	public function test_handle_jsonrpc_dispatches_single_request(): void {
		$req    = new WP_REST_Request(
			array(),
			array( 'id' => 1, 'method' => 'tsubakuro_list_tasks', 'params' => array() )
		);
		$result = Tsubakuro_MCP::handle_jsonrpc( $req );

		$this->assertSame( '2.0', $result['jsonrpc'] );
		$this->assertSame( 1, $result['id'] );
	}

	public function test_handle_jsonrpc_dispatches_batch_requests(): void {
		$req = new WP_REST_Request(
			array(),
			array(
				array( 'id' => 1, 'method' => 'tsubakuro_list_tasks', 'params' => array() ),
				array( 'id' => 2, 'method' => 'tsubakuro_list_tasks', 'params' => array() ),
			)
		);
		$result = Tsubakuro_MCP::handle_jsonrpc( $req );

		$this->assertCount( 2, $result );
		$this->assertSame( 1, $result[0]['id'] );
		$this->assertSame( 2, $result[1]['id'] );
	}

	// -------------------------------------------------------------------------
	// dispatch() – routing
	// -------------------------------------------------------------------------

	public function test_dispatch_returns_method_not_found_for_unknown_method(): void {
		$result = $this->dispatch(
			array( 'id' => 5, 'method' => 'totally_unknown', 'params' => array() )
		);

		$this->assertSame( -32601, $result['error']['code'] );
		$this->assertStringContainsString( 'totally_unknown', $result['error']['message'] );
	}

	// -------------------------------------------------------------------------
	// tsubakuro_list_tasks
	// -------------------------------------------------------------------------

	public function test_tool_list_tasks_returns_task_list(): void {
		$GLOBALS['tsubakuro_test']['posts'][1] = $this->make_post( 1, 'Alpha' );
		$GLOBALS['tsubakuro_test']['posts'][2] = $this->make_post( 2, 'Beta' );

		$result = $this->dispatch(
			array( 'id' => 1, 'method' => 'tsubakuro_list_tasks', 'params' => array() )
		);

		$this->assertArrayHasKey( 'result', $result );
		$this->assertCount( 2, $result['result'] );
	}

	public function test_tool_list_tasks_applies_status_filter(): void {
		$GLOBALS['tsubakuro_test']['posts'][1] = $this->make_post( 1, 'T' );

		$this->dispatch(
			array(
				'id'     => 1,
				'method' => 'tsubakuro_list_tasks',
				'params' => array( 'status' => 'in_progress', 'per_page' => 5 ),
			)
		);

		$args = $GLOBALS['tsubakuro_test']['last_query_args'];
		$this->assertSame( 'in_progress', $args['meta_query'][0]['value'] );
		$this->assertSame( 5, $args['posts_per_page'] );
	}

	// -------------------------------------------------------------------------
	// tsubakuro_get_task
	// -------------------------------------------------------------------------

	public function test_tool_get_task_returns_error_when_id_missing(): void {
		$result = $this->dispatch(
			array( 'id' => 1, 'method' => 'tsubakuro_get_task', 'params' => array() )
		);

		$this->assertSame( -32602, $result['error']['code'] );
	}

	public function test_tool_get_task_returns_404_when_task_not_found(): void {
		$result = $this->dispatch(
			array( 'id' => 1, 'method' => 'tsubakuro_get_task', 'params' => array( 'id' => 999 ) )
		);

		$this->assertSame( 404, $result['error']['code'] );
	}

	public function test_tool_get_task_returns_task_with_comments(): void {
		$GLOBALS['tsubakuro_test']['posts'][101] = $this->make_post( 101, 'Task Z' );
		$GLOBALS['tsubakuro_test']['comments'][1] = (object) array(
			'comment_ID'       => 1,
			'comment_post_ID'  => 101,
			'user_id'          => 0,
			'comment_content'  => 'Note',
			'comment_type'     => Tsubakuro_Admin::COMMENT_TYPE,
			'comment_approved' => 1,
			'comment_date'     => '2026-05-01 10:00:00',
		);

		$result = $this->dispatch(
			array( 'id' => 1, 'method' => 'tsubakuro_get_task', 'params' => array( 'id' => 101 ) )
		);

		$this->assertSame( 101, $result['result']['id'] );
		$this->assertCount( 1, $result['result']['comments'] );
	}

	// -------------------------------------------------------------------------
	// tsubakuro_create_task
	// -------------------------------------------------------------------------

	public function test_tool_create_task_returns_error_when_title_missing(): void {
		$result = $this->dispatch(
			array( 'id' => 1, 'method' => 'tsubakuro_create_task', 'params' => array() )
		);

		$this->assertSame( -32602, $result['error']['code'] );
	}

	public function test_tool_create_task_creates_and_returns_task(): void {
		// Pre-seed post 123 (the ID returned by the wp_insert_post stub).
		$GLOBALS['tsubakuro_test']['posts'][123] = $this->make_post( 123, 'New Task' );

		$result = $this->dispatch(
			array(
				'id'     => 1,
				'method' => 'tsubakuro_create_task',
				'params' => array( 'title' => 'New Task', 'status' => 'todo' ),
			)
		);

		$this->assertArrayHasKey( 'result', $result );
		$this->assertSame( 123, $result['result']['id'] );
	}

	// -------------------------------------------------------------------------
	// tsubakuro_update_task
	// -------------------------------------------------------------------------

	public function test_tool_update_task_returns_error_when_id_missing(): void {
		$result = $this->dispatch(
			array( 'id' => 1, 'method' => 'tsubakuro_update_task', 'params' => array() )
		);

		$this->assertSame( -32602, $result['error']['code'] );
	}

	public function test_tool_update_task_returns_404_when_task_not_found(): void {
		$result = $this->dispatch(
			array(
				'id'     => 1,
				'method' => 'tsubakuro_update_task',
				'params' => array( 'id' => 999, 'title' => 'X' ),
			)
		);

		$this->assertSame( 404, $result['error']['code'] );
	}

	public function test_tool_update_task_updates_and_returns_task(): void {
		$GLOBALS['tsubakuro_test']['posts'][101] = $this->make_post( 101, 'Old' );

		$result = $this->dispatch(
			array(
				'id'     => 1,
				'method' => 'tsubakuro_update_task',
				'params' => array( 'id' => 101, 'status' => 'completed' ),
			)
		);

		$this->assertArrayHasKey( 'result', $result );
		$this->assertSame( 101, $result['result']['id'] );
		$this->assertSame(
			array( 'completed' ),
			$GLOBALS['tsubakuro_test']['post_meta'][101]['_tsubakuro_status']
		);
	}

	// -------------------------------------------------------------------------
	// tsubakuro_delete_task
	// -------------------------------------------------------------------------

	public function test_tool_delete_task_returns_error_when_id_missing(): void {
		$result = $this->dispatch(
			array( 'id' => 1, 'method' => 'tsubakuro_delete_task', 'params' => array() )
		);

		$this->assertSame( -32602, $result['error']['code'] );
	}

	public function test_tool_delete_task_returns_404_when_task_not_found(): void {
		$result = $this->dispatch(
			array(
				'id'     => 1,
				'method' => 'tsubakuro_delete_task',
				'params' => array( 'id' => 999 ),
			)
		);

		$this->assertSame( 404, $result['error']['code'] );
	}

	public function test_tool_delete_task_deletes_and_confirms(): void {
		$GLOBALS['tsubakuro_test']['posts'][101] = $this->make_post( 101, 'Task' );

		$result = $this->dispatch(
			array(
				'id'     => 1,
				'method' => 'tsubakuro_delete_task',
				'params' => array( 'id' => 101 ),
			)
		);

		$this->assertTrue( $result['result']['deleted'] );
		$this->assertSame( 101, $result['result']['id'] );
	}

	// -------------------------------------------------------------------------
	// tsubakuro_add_comment
	// -------------------------------------------------------------------------

	public function test_tool_add_comment_returns_error_when_params_missing(): void {
		// Missing both id and comment.
		$result = $this->dispatch(
			array( 'id' => 1, 'method' => 'tsubakuro_add_comment', 'params' => array() )
		);
		$this->assertSame( -32602, $result['error']['code'] );

		// Only id provided – comment missing.
		tsubakuro_test_reset();
		$result = $this->dispatch(
			array(
				'id'     => 1,
				'method' => 'tsubakuro_add_comment',
				'params' => array( 'id' => 101 ),
			)
		);
		$this->assertSame( -32602, $result['error']['code'] );
	}

	public function test_tool_add_comment_returns_404_when_task_not_found(): void {
		$result = $this->dispatch(
			array(
				'id'     => 1,
				'method' => 'tsubakuro_add_comment',
				'params' => array( 'id' => 999, 'comment' => 'Hi' ),
			)
		);

		$this->assertSame( 404, $result['error']['code'] );
	}

	public function test_tool_add_comment_returns_500_when_insert_fails(): void {
		$GLOBALS['tsubakuro_test']['posts'][101]       = $this->make_post( 101, 'Task' );
		$GLOBALS['tsubakuro_test']['wpdb_insert_fail'] = true;

		$result = $this->dispatch(
			array(
				'id'     => 1,
				'method' => 'tsubakuro_add_comment',
				'params' => array( 'id' => 101, 'comment' => 'Hi' ),
			)
		);

		$this->assertSame( 500, $result['error']['code'] );
	}

	public function test_tool_add_comment_inserts_and_returns_comment(): void {
		$GLOBALS['tsubakuro_test']['posts'][101] = $this->make_post( 101, 'Task' );
		$GLOBALS['tsubakuro_test']['users'][7]   = (object) array(
			'ID'           => 7,
			'display_name' => 'Bob',
		);
		$result = $this->dispatch(
			array(
				'id'     => 1,
				'method' => 'tsubakuro_add_comment',
				'params' => array( 'id' => 101, 'comment' => 'Great work' ),
			)
		);

		$this->assertArrayHasKey( 'result', $result );
		$this->assertSame( 'Great work', $result['result']['comment'] );
		$this->assertSame( 'Bob', $result['result']['user_name'] );
	}

	// -------------------------------------------------------------------------
	// check_permission()
	// -------------------------------------------------------------------------

	public function test_check_permission_returns_true_when_user_can_edit(): void {
		$this->assertTrue( Tsubakuro_MCP::check_permission() );
	}

	public function test_check_permission_returns_false_when_user_cannot(): void {
		$GLOBALS['tsubakuro_test']['can']['edit_posts'] = false;
		$this->assertFalse( Tsubakuro_MCP::check_permission() );
	}
}
