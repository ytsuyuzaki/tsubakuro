<?php

use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the minimal MCP JSON-RPC endpoint.
 */
class McpExtendedTest extends TestCase
{

	protected function setUp(): void
	{
		tsubakuro_test_reset();
		unset($_SERVER['HTTP_AUTHORIZATION'], $_SERVER['REDIRECT_HTTP_AUTHORIZATION'], $_SERVER['HTTP_MCP_PROTOCOL_VERSION']);
	}

	protected function tearDown(): void
	{
		unset($_SERVER['HTTP_AUTHORIZATION'], $_SERVER['REDIRECT_HTTP_AUTHORIZATION'], $_SERVER['HTTP_MCP_PROTOCOL_VERSION']);
	}

	private function dispatch(array $rpc): ?array
	{
		$reflection = new ReflectionClass('Tsubakuro_MCP');
		$method     = $reflection->getMethod('dispatch');
		$method->setAccessible(true);
		return $method->invoke(null, $rpc);
	}

	private function make_post(int $id, string $title, string $content = ''): object
	{
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

	public function test_handle_get_returns_json_rpc_error_when_authorized(): void
	{
		$_SERVER['HTTP_AUTHORIZATION'] = 'Basic ' . base64_encode('admin:password');

		$result = Tsubakuro_MCP::handle_get();

		$this->assertSame('2.0', $result['jsonrpc']);
		$this->assertSame(-32000, $result['error']['code']);
		$this->assertStringContainsString('SSE stream is not available', $result['error']['message']);
	}

	public function test_handle_jsonrpc_returns_401_when_unauthorized(): void
	{
		$req    = new WP_REST_Request(
			array(),
			array(
				'jsonrpc' => '2.0',
				'id'      => 1,
				'method'  => 'initialize',
			)
		);
		$result = Tsubakuro_MCP::handle_jsonrpc($req);

		$this->assertSame(-32001, $result['error']['code']);
	}

	public function test_handle_jsonrpc_returns_parse_error_for_empty_body(): void
	{
		$_SERVER['HTTP_AUTHORIZATION'] = 'Basic ' . base64_encode('admin:password');
		$req                           = new WP_REST_Request(array(), null);
		$result                        = Tsubakuro_MCP::handle_jsonrpc($req);

		$this->assertSame(-32700, $result['error']['code']);
	}

	public function test_handle_jsonrpc_rejects_batch_requests(): void
	{
		$_SERVER['HTTP_AUTHORIZATION'] = 'Basic ' . base64_encode('admin:password');
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
					'method'  => 'tools/list',
				),
			)
		);
		$result                        = Tsubakuro_MCP::handle_jsonrpc($req);

		$this->assertSame('2.0', $result['jsonrpc']);
		$this->assertSame(-32600, $result['error']['code']);
		$this->assertNull($result['id']);
	}

	public function test_handle_jsonrpc_accepts_json_rpc_response_messages(): void
	{
		$_SERVER['HTTP_AUTHORIZATION'] = 'Basic ' . base64_encode('admin:password');
		$req                           = new WP_REST_Request(
			array(),
			array(
				'jsonrpc' => '2.0',
				'id'      => 1,
				'result'  => array(),
			)
		);
		$result                        = Tsubakuro_MCP::handle_jsonrpc($req);

		$this->assertSame('', $result);
	}

	public function test_initialize_returns_mcp_server_metadata(): void
	{
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

		$this->assertSame('2.0', $result['jsonrpc']);
		$this->assertSame('2025-11-25', $result['result']['protocolVersion']);
		$this->assertSame('tsubakuro-wordpress-mcp', $result['result']['serverInfo']['name']);
		$this->assertArrayHasKey('tools', $result['result']['capabilities']);
		$this->assertArrayNotHasKey('resources', $result['result']['capabilities']);
	}

	public function test_initialized_method_returns_method_not_found(): void
	{
		$reflection = new ReflectionClass('Tsubakuro_MCP');
		$method     = $reflection->getMethod('dispatch');
		$method->setAccessible(true);
		$result = $method->invoke(
			null,
			array(
				'jsonrpc' => '2.0',
				'id'      => 99,
				'method'  => 'initialized',
			)
		);

		$this->assertSame(-32601, $result['error']['code']);
		$this->assertSame(99, $result['id']);
	}

	public function test_request_with_null_id_is_invalid(): void
	{
		$result = $this->dispatch(
			array(
				'jsonrpc' => '2.0',
				'id'      => null,
				'method'  => 'tools/list',
			)
		);

		$this->assertSame(-32600, $result['error']['code']);
		$this->assertNull($result['id']);
	}

	public function test_standard_initialized_notification_returns_no_json_rpc_response(): void
	{
		$reflection = new ReflectionClass('Tsubakuro_MCP');
		$method     = $reflection->getMethod('dispatch');
		$method->setAccessible(true);
		$result     = $method->invoke(
			null,
			array(
				'jsonrpc' => '2.0',
				'method'  => 'notifications/initialized',
			)
		);

		$this->assertNull($result);
	}

	public function test_handle_jsonrpc_returns_empty_response_for_standard_notification(): void
	{
		$_SERVER['HTTP_AUTHORIZATION'] = 'Basic ' . base64_encode('admin:password');
		$req                           = new WP_REST_Request(
			array(),
			array(
				'jsonrpc' => '2.0',
				'method'  => 'notifications/initialized',
			)
		);
		$result                        = Tsubakuro_MCP::handle_jsonrpc($req);

		$this->assertSame('', $result);
	}

	public function test_unknown_notification_returns_no_json_rpc_response(): void
	{
		$result = $this->dispatch(
			array(
				'jsonrpc' => '2.0',
				'method'  => 'notifications/unknown',
			)
		);

		$this->assertNull($result);
	}

	public function test_tools_list_returns_task_tools(): void
	{
		$result = $this->dispatch(
			array(
				'jsonrpc' => '2.0',
				'id'      => 2,
				'method'  => 'tools/list',
				'params'  => array(),
			)
		);

		$tools = array_column($result['result']['tools'], 'name');

		$this->assertSame('tsubakuro_list_tasks', $result['result']['tools'][0]['name']);
		$this->assertSame('object', $result['result']['tools'][0]['inputSchema']['type']);
		$this->assertNotContains('ping', $tools);
		$this->assertContains('tsubakuro_list_tasks', $tools);
		$this->assertContains('tsubakuro_update_task', $tools);
		$this->assertContains('tsubakuro_add_comment', $tools);
	}

	public function test_tools_call_list_tasks_returns_task_list(): void
	{
		$GLOBALS['tsubakuro_test']['posts'][1] = $this->make_post(1, 'Alpha');
		$GLOBALS['tsubakuro_test']['posts'][2] = $this->make_post(2, 'Beta');

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

		$this->assertCount(2, $result['result']['structuredContent']['tasks']);
		$this->assertSame('Alpha', $result['result']['structuredContent']['tasks'][0]['title']);
		$this->assertStringContainsString('Alpha', $result['result']['content'][0]['text']);
	}

	public function test_tools_call_get_task_returns_task_with_comments(): void
	{
		$GLOBALS['tsubakuro_test']['posts'][101] = $this->make_post(101, 'Task Z');
		$GLOBALS['tsubakuro_test']['posts'][201] = (object) array(
			'ID'            => 201,
			'post_type'     => Tsubakuro_Post_Types::COMMENT_POST_TYPE,
			'post_author'   => 0,
			'post_parent'   => 101,
			'post_content'  => 'Note',
			'post_date'     => '2026-05-01 10:00:00',
			'post_modified' => '2026-05-01 10:00:00',
		);

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

		$this->assertSame(101, $result['result']['structuredContent']['task']['id']);
		$this->assertCount(1, $result['result']['structuredContent']['task']['comments']);
	}

	public function test_tools_call_create_task_creates_and_returns_task(): void
	{
		$GLOBALS['tsubakuro_test']['posts'][123] = $this->make_post(123, 'New Task');

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

		$this->assertSame(123, $result['result']['structuredContent']['task']['id']);
	}

	public function test_tools_call_create_task_defaults_status_to_todo_when_missing(): void
	{
		$GLOBALS['tsubakuro_test']['posts'][123] = $this->make_post(123, 'New Task');

		$result = $this->dispatch(
			array(
				'jsonrpc' => '2.0',
				'id'      => 121,
				'method'  => 'tools/call',
				'params'  => array(
					'name'      => 'tsubakuro_create_task',
					'arguments' => array(
						'title' => 'New Task',
					),
				),
			)
		);

		$this->assertSame(123, $result['result']['structuredContent']['task']['id']);
		$this->assertSame('todo', $result['result']['structuredContent']['task']['status']);
		$this->assertSame(
			array('todo'),
			$GLOBALS['tsubakuro_test']['post_meta'][123]['_tsubakuro_status']
		);
	}

	public function test_tools_call_create_task_stores_reminder_fields(): void
	{
		$GLOBALS['tsubakuro_test']['posts'][123] = $this->make_post(123, 'New Task');

		$result = $this->dispatch(
			array(
				'jsonrpc' => '2.0',
				'id'      => 122,
				'method'  => 'tools/call',
				'params'  => array(
					'name'      => 'tsubakuro_create_task',
					'arguments' => array(
						'title'           => 'New Task',
						'start_remind_at' => '2026-06-08 09:00:00',
						'due_remind_at'   => '2026-06-09 19:00:00',
					),
				),
			)
		);

		$this->assertSame(123, $result['result']['structuredContent']['task']['id']);
		$this->assertSame(
			array('2026-06-08 09:00:00'),
			$GLOBALS['tsubakuro_test']['post_meta'][123]['_tsubakuro_start_remind_at']
		);
		$this->assertSame(
			array('2026-06-09 19:00:00'),
			$GLOBALS['tsubakuro_test']['post_meta'][123]['_tsubakuro_due_remind_at']
		);
	}

	public function test_tools_call_update_task_updates_meta_and_returns_task(): void
	{
		$GLOBALS['tsubakuro_test']['posts'][101] = $this->make_post(101, 'Old');

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

		$this->assertSame(101, $result['result']['structuredContent']['task']['id']);
		$this->assertSame(array('completed'), $GLOBALS['tsubakuro_test']['post_meta'][101]['_tsubakuro_status']);
	}

	public function test_tools_call_delete_task_deletes_and_confirms(): void
	{
		$GLOBALS['tsubakuro_test']['posts'][101] = $this->make_post(101, 'Task');

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

		$this->assertTrue($result['result']['structuredContent']['deleted']);
		$this->assertSame(array(101), $GLOBALS['tsubakuro_test']['deleted_posts']);
	}

	public function test_tools_call_add_comment_inserts_and_returns_comment(): void
	{
		$GLOBALS['tsubakuro_test']['posts'][101] = $this->make_post(101, 'Task');
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

		$this->assertSame('Great work', $result['result']['structuredContent']['comment']['comment']);
		$this->assertSame('Bob', $result['result']['structuredContent']['comment']['user_name']);
	}

	public function test_tools_call_task_tool_returns_error_for_missing_required_argument(): void
	{
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

		$this->assertSame(-32602, $result['error']['code']);
	}

	public function test_tools_call_unknown_tool_returns_json_rpc_error(): void
	{
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

		$this->assertSame(-32602, $result['error']['code']);
		$this->assertStringContainsString('missing', $result['error']['message']);
	}

	public function test_dispatch_returns_method_not_found_for_unknown_method(): void
	{
		$result = $this->dispatch(
			array(
				'jsonrpc' => '2.0',
				'id'      => 8,
				'method'  => 'totally_unknown',
				'params'  => array(),
			)
		);

		$this->assertSame(-32601, $result['error']['code']);
		$this->assertStringContainsString('totally_unknown', $result['error']['message']);
	}

	public function test_non_initialize_request_rejects_unsupported_protocol_header(): void
	{
		$_SERVER['HTTP_AUTHORIZATION'] = 'Basic ' . base64_encode('admin:password');
		$req                           = new WP_REST_Request(
			array(),
			array(
				'jsonrpc' => '2.0',
				'id'      => 17,
				'method'  => 'tools/list',
				'params'  => array(),
			)
		);
		$_SERVER['HTTP_MCP_PROTOCOL_VERSION'] = '2024-11-05';
		$result = Tsubakuro_MCP::handle_jsonrpc($req);

		$this->assertSame(-32600, $result['error']['code']);
		$this->assertStringContainsString('Unsupported protocol version', $result['error']['message']);
	}

	public function test_initialize_accepts_unsupported_protocol_header_value(): void
	{
		$_SERVER['HTTP_AUTHORIZATION']       = 'Basic ' . base64_encode('admin:password');
		$_SERVER['HTTP_MCP_PROTOCOL_VERSION'] = '2024-11-05';
		$req                                 = new WP_REST_Request(
			array(),
			array(
				'jsonrpc' => '2.0',
				'id'      => 18,
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
		$result                              = Tsubakuro_MCP::handle_jsonrpc($req);

		$this->assertArrayHasKey('result', $result);
		$this->assertSame('2025-11-25', $result['result']['protocolVersion']);
	}

	public function test_check_permission_returns_true_for_basic_auth_when_user_can_edit(): void
	{
		$_SERVER['HTTP_AUTHORIZATION'] = 'Basic ' . base64_encode('admin:password');

		$this->assertTrue(Tsubakuro_MCP::check_permission());
	}

	public function test_check_permission_returns_false_when_no_authorization_header(): void
	{
		$this->assertFalse(Tsubakuro_MCP::check_permission());
	}

	public function test_check_permission_returns_false_for_bearer_token(): void
	{
		$_SERVER['HTTP_AUTHORIZATION'] = 'Bearer unsupported-token';

		$this->assertFalse(Tsubakuro_MCP::check_permission());
	}

	public function test_check_permission_returns_false_when_user_cannot_edit(): void
	{
		$_SERVER['HTTP_AUTHORIZATION']                 = 'Basic ' . base64_encode('admin:password');
		$GLOBALS['tsubakuro_test']['can']['edit_posts'] = false;

		$this->assertFalse(Tsubakuro_MCP::check_permission());
	}

	// -------------------------------------------------------------------------
	// Parent / child task support
	// -------------------------------------------------------------------------

	public function test_tools_call_create_task_with_parent_id(): void
	{
		$parent = $this->make_post(50, 'Parent Task');
		$child  = $this->make_post(123, 'Child Task');
		$child->post_parent = 50;
		$GLOBALS['tsubakuro_test']['posts'][50]  = $parent;
		$GLOBALS['tsubakuro_test']['posts'][123] = $child;

		$result = $this->dispatch(
			array(
				'jsonrpc' => '2.0',
				'id'      => 50,
				'method'  => 'tools/call',
				'params'  => array(
					'name'      => 'tsubakuro_create_task',
					'arguments' => array(
						'title'     => 'Child Task',
						'parent_id' => 50,
					),
				),
			)
		);

		$this->assertArrayHasKey('result', $result);
		$task = $result['result']['structuredContent']['task'];
		$this->assertSame(123, $task['id']);
		$this->assertSame(50, $task['parent_id']);
	}

	public function test_tools_call_list_tasks_with_parent_id_filter(): void
	{
		$child1 = $this->make_post(11, 'Child 1');
		$child2 = $this->make_post(12, 'Child 2');
		$child1->post_parent = 10;
		$child2->post_parent = 10;
		$GLOBALS['tsubakuro_test']['posts'][11] = $child1;
		$GLOBALS['tsubakuro_test']['posts'][12] = $child2;

		$result = $this->dispatch(
			array(
				'jsonrpc' => '2.0',
				'id'      => 51,
				'method'  => 'tools/call',
				'params'  => array(
					'name'      => 'tsubakuro_list_tasks',
					'arguments' => array(
						'parent_id' => 10,
					),
				),
			)
		);

		$this->assertArrayHasKey('result', $result);
		$tasks = $result['result']['structuredContent']['tasks'];
		$this->assertCount(2, $tasks);
		$this->assertSame(array(11, 12), array_column($tasks, 'id'));
	}

	public function test_tools_call_update_task_with_parent_id(): void
	{
		$parent = $this->make_post(20, 'Parent');
		$child  = $this->make_post(30, 'Child');
		$child->post_parent = 20;
		$GLOBALS['tsubakuro_test']['posts'][20] = $parent;
		$GLOBALS['tsubakuro_test']['posts'][30] = $child;

		$result = $this->dispatch(
			array(
				'jsonrpc' => '2.0',
				'id'      => 52,
				'method'  => 'tools/call',
				'params'  => array(
					'name'      => 'tsubakuro_update_task',
					'arguments' => array(
						'id'        => 30,
						'parent_id' => 20,
					),
				),
			)
		);

		$this->assertArrayHasKey('result', $result);
		$task = $result['result']['structuredContent']['task'];
		$this->assertSame(30, $task['id']);
		$this->assertSame(20, $task['parent_id']);
	}

	// -------------------------------------------------------------------------
	// Evaluation / insight tools
	// -------------------------------------------------------------------------

	private function make_eval_post(int $id, string $title = 'Eval'): object
	{
		return (object) array(
			'ID'            => $id,
			'post_type'     => 'tsubakuro_evaluation',
			'post_title'    => $title,
			'post_content'  => '',
			'post_date'     => '2026-05-01 10:00:00',
			'post_modified' => '2026-05-01 11:00:00',
			'post_author'   => 1,
		);
	}

	private function make_insight_post(int $id, string $title = 'Insight'): object
	{
		return (object) array(
			'ID'            => $id,
			'post_type'     => 'tsubakuro_insight',
			'post_title'    => $title,
			'post_content'  => '',
			'post_date'     => '2026-05-01 10:00:00',
			'post_modified' => '2026-05-01 11:00:00',
			'post_author'   => 1,
		);
	}

	public function test_tools_list_includes_evaluation_and_insight_tools(): void
	{
		$result = $this->dispatch(
			array(
				'jsonrpc' => '2.0',
				'id'      => 2,
				'method'  => 'tools/list',
				'params'  => array(),
			)
		);

		$tools = array_column($result['result']['tools'], 'name');

		$this->assertContains('tsubakuro_list_evaluations', $tools);
		$this->assertContains('tsubakuro_create_evaluation', $tools);
		$this->assertContains('tsubakuro_list_insights', $tools);
		$this->assertContains('tsubakuro_create_insight', $tools);
		$this->assertContains('tsubakuro_link_evaluation_to_insight', $tools);
	}

	public function test_ability_definitions_and_json_rpc_tools_stay_in_sync(): void
	{
		$reflection = new ReflectionClass('Tsubakuro_MCP');

		$abilities_method = $reflection->getMethod('get_ability_definitions');
		$abilities_method->setAccessible(true);
		$ability_names = array_map(
			static function ($name) {
				return str_replace(array('tsubakuro/', '-'), array('tsubakuro_', '_'), $name);
			},
			array_keys($abilities_method->invoke(null))
		);

		$tools_method = $reflection->getMethod('get_tools');
		$tools_method->setAccessible(true);
		$tool_names = array_column($tools_method->invoke(null), 'name');

		// The abilities surface uses "link-evaluation-to-insight" while the
		// JSON-RPC tool name is fully snake-cased; normalise the one exception.
		sort($ability_names);
		sort($tool_names);

		$this->assertSame($ability_names, $tool_names, 'Ability and JSON-RPC tool names must match.');
	}

	public function test_tools_call_create_evaluation_saves_meta(): void
	{
		$GLOBALS['tsubakuro_test']['posts'][123] = $this->make_eval_post(123, 'New Eval');

		$result = $this->dispatch(
			array(
				'jsonrpc' => '2.0',
				'id'      => 60,
				'method'  => 'tools/call',
				'params'  => array(
					'name'      => 'tsubakuro_create_evaluation',
					'arguments' => array(
						'title'       => 'New Eval',
						'change_item' => 'comparison',
						'judgment'    => 'success',
					),
				),
			)
		);

		$evaluation = $result['result']['structuredContent']['evaluation'];
		$this->assertSame(123, $evaluation['id']);
		$this->assertSame('success', $evaluation['judgment']);
	}

	public function test_tools_call_create_evaluation_allows_missing_title(): void
	{
		$GLOBALS['tsubakuro_test']['posts'][123] = $this->make_eval_post(123, '本文だけの評価');

		$result = $this->dispatch(
			array(
				'jsonrpc' => '2.0',
				'id'      => 601,
				'method'  => 'tools/call',
				'params'  => array(
					'name'      => 'tsubakuro_create_evaluation',
					'arguments' => array(
						'change_detail' => '本文だけの評価',
					),
				),
			)
		);

		$evaluation = $result['result']['structuredContent']['evaluation'];
		$this->assertSame(123, $evaluation['id']);
	}

	public function test_tools_call_create_insight_allows_missing_title_and_uses_detail(): void
	{
		$GLOBALS['tsubakuro_test']['posts'][123] = (object) array(
			'ID'            => 123,
			'post_type'     => 'tsubakuro_insight',
			'post_title'    => '知見本文',
			'post_content'  => '知見本文',
			'post_date'     => '2026-05-01 10:00:00',
			'post_modified' => '2026-05-03 11:00:00',
			'post_author'   => 7,
		);

		$result = $this->dispatch(
			array(
				'jsonrpc' => '2.0',
				'id'      => 602,
				'method'  => 'tools/call',
				'params'  => array(
					'name'      => 'tsubakuro_create_insight',
					'arguments' => array(
						'detail' => '知見本文',
					),
				),
			)
		);

		$insight = $result['result']['structuredContent']['insight'];
		$this->assertSame(123, $insight['id']);
		$this->assertSame('知見本文', $insight['detail']);
	}

	public function test_tools_call_list_evaluations_returns_list(): void
	{
		$GLOBALS['tsubakuro_test']['posts'][1] = $this->make_eval_post(1, 'E1');
		$GLOBALS['tsubakuro_test']['posts'][2] = $this->make_eval_post(2, 'E2');

		$result = $this->dispatch(
			array(
				'jsonrpc' => '2.0',
				'id'      => 61,
				'method'  => 'tools/call',
				'params'  => array(
					'name'      => 'tsubakuro_list_evaluations',
					'arguments' => array(),
				),
			)
		);

		$this->assertCount(2, $result['result']['structuredContent']['evaluations']);
	}

	public function test_tools_call_link_evaluation_to_insight(): void
	{
		$GLOBALS['tsubakuro_test']['posts'][70] = $this->make_insight_post(70, 'Insight');
		$GLOBALS['tsubakuro_test']['posts'][71] = $this->make_eval_post(71, 'Eval');

		$result = $this->dispatch(
			array(
				'jsonrpc' => '2.0',
				'id'      => 62,
				'method'  => 'tools/call',
				'params'  => array(
					'name'      => 'tsubakuro_link_evaluation_to_insight',
					'arguments' => array(
						'insight_id'    => 70,
						'evaluation_id' => 71,
					),
				),
			)
		);

		$insight = $result['result']['structuredContent']['insight'];
		$this->assertContains(71, $insight['evaluation_ids']);
	}

	public function test_tools_call_link_evaluation_is_idempotent(): void
	{
		$GLOBALS['tsubakuro_test']['posts'][72] = $this->make_insight_post(72, 'Insight');
		$GLOBALS['tsubakuro_test']['posts'][73] = $this->make_eval_post(73, 'Eval');

		$call = array(
			'jsonrpc' => '2.0',
			'id'      => 64,
			'method'  => 'tools/call',
			'params'  => array(
				'name'      => 'tsubakuro_link_evaluation_to_insight',
				'arguments' => array(
					'insight_id'    => 72,
					'evaluation_id' => 73,
				),
			),
		);

		$this->dispatch($call);
		$result = $this->dispatch($call);

		$insight = $result['result']['structuredContent']['insight'];
		$this->assertSame(array(73), $insight['evaluation_ids']);
	}

	public function test_tools_call_delete_insight_requires_permission(): void
	{
		$GLOBALS['tsubakuro_test']['can']['delete_posts'] = false;
		$GLOBALS['tsubakuro_test']['posts'][80]           = $this->make_insight_post(80);

		$result = $this->dispatch(
			array(
				'jsonrpc' => '2.0',
				'id'      => 63,
				'method'  => 'tools/call',
				'params'  => array(
					'name'      => 'tsubakuro_delete_insight',
					'arguments' => array( 'id' => 80 ),
				),
			)
		);

		$this->assertSame(-32003, $result['error']['code']);
	}

	// -------------------------------------------------------------------------
	// Site strategy tools
	// -------------------------------------------------------------------------

	public function test_tools_list_includes_site_strategy_tools(): void
	{
		$result = $this->dispatch(
			array(
				'jsonrpc' => '2.0',
				'id'      => 70,
				'method'  => 'tools/list',
				'params'  => array(),
			)
		);

		$tools = array_column($result['result']['tools'], 'name');

		$this->assertContains('tsubakuro_get_site_strategy', $tools);
		$this->assertContains('tsubakuro_update_site_strategy', $tools);
	}

	public function test_tools_call_get_site_strategy_returns_stored_values(): void
	{
		Tsubakuro_Site_Strategy::save_strategy(array( 'purpose' => 'MCP 目的' ));

		$result = $this->dispatch(
			array(
				'jsonrpc' => '2.0',
				'id'      => 71,
				'method'  => 'tools/call',
				'params'  => array(
					'name'      => 'tsubakuro_get_site_strategy',
					'arguments' => array(),
				),
			)
		);

		$this->assertSame('MCP 目的', $result['result']['structuredContent']['site_strategy']['purpose']);
	}

	public function test_tools_call_update_site_strategy_saves_provided_fields(): void
	{
		$result = $this->dispatch(
			array(
				'jsonrpc' => '2.0',
				'id'      => 72,
				'method'  => 'tools/call',
				'params'  => array(
					'name'      => 'tsubakuro_update_site_strategy',
					'arguments' => array( 'direction' => 'MCP 方向性' ),
				),
			)
		);

		$this->assertSame('MCP 方向性', $result['result']['structuredContent']['site_strategy']['direction']);
		$this->assertSame('MCP 方向性', $GLOBALS['tsubakuro_test']['options'][Tsubakuro_Site_Strategy::OPTION]['direction']);
	}
}
