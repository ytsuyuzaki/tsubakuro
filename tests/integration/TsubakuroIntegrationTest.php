<?php
/**
 * Integration tests for the Tsubakuro plugin running inside the wp-env
 * tests-cli container with a real WordPress and database environment.
 */

class TsubakuroIntegrationTest extends WP_UnitTestCase {

	public function test_plugin_constants_are_loaded(): void {
		$this->assertTrue( defined( 'TSUBAKURO_VERSION' ), 'Plugin constants are loaded.' );
	}

	public function test_task_post_type_is_registered(): void {
		$this->assertTrue( post_type_exists( 'tsubakuro_task' ), 'Task post type is registered.' );
	}

	public function test_comments_table_exists_after_activation(): void {
		global $wpdb;
		$comments_table = $wpdb->prefix . 'tsubakuro_comments';
		$table_exists   = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $comments_table ) );
		$this->assertSame( $comments_table, $table_exists, 'Comments table exists after activation.' );
	}

	public function test_rest_routes_are_registered(): void {
		do_action( 'rest_api_init' );
		$routes = rest_get_server()->get_routes();
		$this->assertArrayHasKey( '/tsubakuro/v1/tasks', $routes, 'Tasks REST route is registered.' );
		$this->assertArrayHasKey( '/tsubakuro/v1/mcp', $routes, 'MCP REST route is registered.' );
	}

	public function test_task_crud_and_meta_persistence(): void {
		$task_id = wp_insert_post(
			array(
				'post_type'    => 'tsubakuro_task',
				'post_title'   => 'wp-env smoke task',
				'post_content' => 'Created by the integration test.',
				'post_status'  => 'publish',
			),
			true
		);
		$this->assertFalse( is_wp_error( $task_id ), 'Task can be inserted.' );

		Tsubakuro_Post_Types::save_meta(
			$task_id,
			array(
				'status'        => 'in_progress',
				'assignee'      => 0,
				'related_pages' => array( 1, 2 ),
			)
		);

		$task = Tsubakuro_Post_Types::get_task( $task_id );
		$this->assertIsArray( $task, 'Task can be loaded through plugin helper.' );
		$this->assertSame( 'in_progress', $task['status'], 'Task status meta is persisted.' );
		$this->assertSame( array( 1, 2 ), $task['related_pages'], 'Related pages meta is persisted.' );
	}

	public function test_mcp_manifest_exposes_list_task_tool(): void {
		$manifest = Tsubakuro_MCP::get_manifest();
		$tools    = wp_list_pluck( $manifest['tools'], 'name' );
		$this->assertContains( 'tsubakuro_list_tasks', $tools, 'MCP manifest exposes list task tool.' );
	}
}
