<?php

/**
 * Integration tests for the Tsubakuro plugin running inside the wp-env
 * tests-cli container with a real WordPress and database environment.
 */

class TsubakuroIntegrationTest extends WP_UnitTestCase
{

	public function test_plugin_constants_are_loaded(): void
	{
		$this->assertTrue(defined('TSUBAKURO_VERSION'), 'Plugin constants are loaded.');
	}

	public function test_task_post_type_is_registered(): void
	{
		$this->assertTrue(post_type_exists('tsubakuro_task'), 'Task post type is registered.');
	}

	public function test_task_comment_post_type_is_registered(): void
	{
		$this->assertTrue(post_type_exists('tsubakuro_comment'), 'Task comment post type is registered.');
	}

	public function test_activation_records_db_version(): void
	{
		$this->assertSame('1.0', get_option('tsubakuro_db_version'), 'DB version option is recorded after activation.');
	}

	public function test_task_comments_use_dedicated_comment_post_type(): void
	{
		$task_id = wp_insert_post(
			array(
				'post_type'   => 'tsubakuro_task',
				'post_title'  => 'comment storage task',
				'post_status' => 'publish',
			),
			true
		);
		$this->assertFalse(is_wp_error($task_id), 'Task can be inserted.');

		$comment_id = Tsubakuro_Admin::insert_comment($task_id, get_current_user_id(), 'Stored in comment posts');
		$this->assertIsInt($comment_id, 'Comment can be inserted.');

		$comment_post = get_post($comment_id);
		$this->assertSame('tsubakuro_comment', $comment_post->post_type, 'Comment post type identifies Tsubakuro task comments.');

		$comments = Tsubakuro_Admin::get_task_comments($task_id);
		$this->assertCount(1, $comments, 'Comment can be loaded through plugin helper.');
		$this->assertSame('Stored in comment posts', $comments[0]['comment']);
	}

	public function test_rest_routes_are_registered(): void
	{
		do_action('rest_api_init');
		$routes = rest_get_server()->get_routes();
		$this->assertArrayHasKey('/tsubakuro/v1/tasks', $routes, 'Tasks REST route is registered.');
		$this->assertArrayHasKey('/tsubakuro/v1/mcp', $routes, 'MCP REST route is registered.');
	}

	public function test_task_crud_and_meta_persistence(): void
	{
		$task_id = wp_insert_post(
			array(
				'post_type'    => 'tsubakuro_task',
				'post_title'   => 'wp-env smoke task',
				'post_content' => 'Created by the integration test.',
				'post_status'  => 'publish',
			),
			true
		);
		$this->assertFalse(is_wp_error($task_id), 'Task can be inserted.');

		Tsubakuro_Post_Types::save_meta(
			$task_id,
			array(
				'status'        => 'in_progress',
				'assignee'      => 0,
				'related_pages' => array(1, 2),
			)
		);

		$task = Tsubakuro_Post_Types::get_task($task_id);
		$this->assertIsArray($task, 'Task can be loaded through plugin helper.');
		$this->assertSame('in_progress', $task['status'], 'Task status meta is persisted.');
		$this->assertSame(array(1, 2), $task['related_pages'], 'Related pages meta is persisted.');
	}

	public function test_mcp_manifest_describes_streamable_http_endpoint(): void
	{
		$manifest = Tsubakuro_MCP::get_manifest();

		$this->assertSame('2025-11-25', $manifest['protocolVersion'], 'MCP protocol version is declared.');
		$this->assertSame('streamable-http', $manifest['transport'], 'MCP transport is declared.');
		$this->assertSame('tsubakuro-wordpress-mcp', $manifest['serverInfo']['name'], 'MCP server name is declared.');
	}
}
