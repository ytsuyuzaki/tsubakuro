<?php

use PHPUnit\Framework\TestCase;

class TsubakuroTest extends TestCase
{

	protected function setUp(): void
	{
		tsubakuro_test_reset();
	}

	private function make_post(int $id, string $title, string $content, string $type = 'tsubakuro_task'): object
	{
		return (object) array(
			'ID'            => $id,
			'post_type'     => $type,
			'post_title'    => $title,
			'post_content'  => $content,
			'post_date'     => '2026-05-01 10:00:00',
			'post_modified' => '2026-05-01 11:00:00',
			'post_author'   => 7,
		);
	}

	public function test_plugin_bootstrap_registers_hooks(): void
	{
		$bootstrap_state = $GLOBALS['tsubakuro_test_bootstrap_state'];

		$this->assertArrayHasKey('plugins_loaded', $bootstrap_state['actions']);
		$this->assertSame('tsubakuro_init', $bootstrap_state['actions']['plugins_loaded'][0]);
		$this->assertSame(array('Tsubakuro_Activator', 'activate'), $bootstrap_state['activation_hooks'][0][1]);
		$this->assertSame('0.0.1', TSUBAKURO_VERSION);
	}

	public function test_init_methods_register_wordpress_hooks_and_routes(): void
	{
		tsubakuro_init();

		$this->assertArrayHasKey('init', $GLOBALS['tsubakuro_test']['actions']);
		$this->assertArrayHasKey('admin_menu', $GLOBALS['tsubakuro_test']['actions']);
		$this->assertArrayHasKey('rest_api_init', $GLOBALS['tsubakuro_test']['actions']);
		$this->assertArrayHasKey('wp_abilities_api_categories_init', $GLOBALS['tsubakuro_test']['actions']);
		$this->assertArrayHasKey('wp_abilities_api_init', $GLOBALS['tsubakuro_test']['actions']);
		$this->assertArrayHasKey('mcp_adapter_init', $GLOBALS['tsubakuro_test']['actions']);
		$this->assertArrayHasKey('wp_enqueue_scripts', $GLOBALS['tsubakuro_test']['actions']);

		Tsubakuro_Post_Types::register_post_type();
		Tsubakuro_REST_API::register_routes();

		$this->assertArrayHasKey('tsubakuro_task', $GLOBALS['tsubakuro_test']['post_types']);
		$this->assertTrue($GLOBALS['tsubakuro_test']['post_types']['tsubakuro_task']['show_in_rest']);
		$this->assertArrayHasKey('tsubakuro/v1/tasks', $GLOBALS['tsubakuro_test']['rest_routes']);
	}

	public function test_mcp_register_abilities_registers_expected_tool_abilities(): void
	{
		Tsubakuro_MCP::register_abilities();

		$this->assertArrayHasKey('tsubakuro/list-tasks', $GLOBALS['tsubakuro_test']['abilities']);
		$this->assertArrayHasKey('tsubakuro/get-task', $GLOBALS['tsubakuro_test']['abilities']);
		$this->assertArrayHasKey('tsubakuro/create-task', $GLOBALS['tsubakuro_test']['abilities']);
		$this->assertArrayHasKey('tsubakuro/update-task', $GLOBALS['tsubakuro_test']['abilities']);
		$this->assertArrayHasKey('tsubakuro/delete-task', $GLOBALS['tsubakuro_test']['abilities']);
		$this->assertArrayHasKey('tsubakuro/add-comment', $GLOBALS['tsubakuro_test']['abilities']);
		$this->assertTrue($GLOBALS['tsubakuro_test']['abilities']['tsubakuro/list-tasks']['meta']['mcp']['public']);
	}

	public function test_mcp_register_mcp_server_creates_adapter_server(): void
	{
		$adapter = new class() {
			public $calls = array();

			public function create_server(...$args)
			{
				$this->calls[] = $args;
				return true;
			}
		};

		Tsubakuro_MCP::register_mcp_server($adapter);

		$this->assertNotEmpty($adapter->calls);
		$this->assertSame('tsubakuro-server', $adapter->calls[0][0]);
		$this->assertSame('tsubakuro/v1', $adapter->calls[0][1]);
		$this->assertSame('mcp', $adapter->calls[0][2]);
	}

	public function test_save_meta_sanitizes_supported_task_metadata(): void
	{
		Tsubakuro_Post_Types::save_meta(
			101,
			array(
				'status'        => 'completed',
				'assignee'      => '-9',
				'related_pages' => array('5', '5', '0', '-3', 'abc'),
			)
		);

		$this->assertSame(array('completed'), $GLOBALS['tsubakuro_test']['post_meta'][101]['_tsubakuro_status']);
		$this->assertSame(array(9), $GLOBALS['tsubakuro_test']['post_meta'][101]['_tsubakuro_assignee']);
		$this->assertSame(array(5, 3), $GLOBALS['tsubakuro_test']['post_meta'][101]['_tsubakuro_related_page']);
	}

	public function test_format_task_returns_structured_task_data(): void
	{
		$GLOBALS['tsubakuro_test']['users'][7]       = (object) array(
			'ID'           => 7,
			'display_name' => 'Editor User',
		);
		$GLOBALS['tsubakuro_test']['post_meta'][101] = array(
			'_tsubakuro_status'       => array('in_progress'),
			'_tsubakuro_assignee'     => array(7),
			'_tsubakuro_related_page' => array(11, 12),
		);

		$task = Tsubakuro_Post_Types::format_task($this->make_post(101, 'Task title', 'Task body'));

		$this->assertSame(101, $task['id']);
		$this->assertSame('Task title', $task['title']);
		$this->assertSame('in_progress', $task['status']);
		$this->assertSame('実行中', $task['status_label']);
		$this->assertSame(array(11, 12), $task['related_pages']);
		$this->assertSame(
			array(
				'id'           => 7,
				'display_name' => 'Editor User',
			),
			$task['assignee']
		);
	}

	public function test_get_tasks_builds_query_filters(): void
	{
		$GLOBALS['tsubakuro_test']['posts'][101] = $this->make_post(101, 'Task title', 'Task body');
		$GLOBALS['tsubakuro_test']['post_meta'][101] = array(
			'_tsubakuro_status'       => array('todo'),
			'_tsubakuro_related_page' => array(22),
		);

		$tasks = Tsubakuro_Post_Types::get_tasks(
			array(
				'status'       => 'todo',
				'related_page' => 22,
				'per_page'     => 1,
			)
		);

		$this->assertCount(1, $tasks);
		$this->assertSame('tsubakuro_task', $GLOBALS['tsubakuro_test']['last_query_args']['post_type']);
		$this->assertSame(1, $GLOBALS['tsubakuro_test']['last_query_args']['per_page']);
		$this->assertSame('_tsubakuro_related_page', $GLOBALS['tsubakuro_test']['last_query_args']['meta_query'][0]['key']);
	}

	public function test_mcp_manifest_exposes_expected_tools(): void
	{
		$manifest = Tsubakuro_MCP::get_manifest();

		$this->assertSame('2025-11-25', $manifest['protocolVersion']);
		$this->assertSame('streamable-http', $manifest['transport']);
		$this->assertSame('tsubakuro-wordpress-mcp', $manifest['serverInfo']['name']);
	}

	public function test_mcp_dispatcher_returns_json_rpc_errors_for_invalid_requests(): void
	{
		$reflection = new ReflectionClass('Tsubakuro_MCP');
		$dispatch   = $reflection->getMethod('dispatch');
		$dispatch->setAccessible(true);

		$invalid = $dispatch->invoke(null, array('id' => 1));
		$missing = $dispatch->invoke(
			null,
			array(
				'jsonrpc' => '2.0',
				'id'      => 2,
				'method'  => 'unknown/method',
				'params'  => array(),
			)
		);

		$this->assertSame(-32600, $invalid['error']['code']);
		$this->assertSame(-32601, $missing['error']['code']);
	}
}
