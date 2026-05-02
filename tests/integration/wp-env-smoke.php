<?php
/**
 * Smoke tests executed inside wp-env with WordPress loaded.
 */
function tsubakuro_wp_env_assert( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: {$message}\n" );
		exit( 1 );
	}
}

tsubakuro_wp_env_assert( defined( 'TSUBAKURO_VERSION' ), 'Plugin constants are loaded.' );
tsubakuro_wp_env_assert( post_type_exists( 'tsubakuro_task' ), 'Task post type is registered.' );

global $wpdb;
$comments_table = $wpdb->prefix . 'tsubakuro_comments';
$table_exists   = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $comments_table ) );
tsubakuro_wp_env_assert( $comments_table === $table_exists, 'Comments table exists after activation.' );

do_action( 'rest_api_init' );
$routes = rest_get_server()->get_routes();
tsubakuro_wp_env_assert( isset( $routes['/tsubakuro/v1/tasks'] ), 'Tasks REST route is registered.' );
tsubakuro_wp_env_assert( isset( $routes['/tsubakuro/v1/mcp'] ), 'MCP REST route is registered.' );

$task_id = wp_insert_post(
	array(
		'post_type'    => 'tsubakuro_task',
		'post_title'   => 'wp-env smoke task',
		'post_content' => 'Created by the wp-env smoke test.',
		'post_status'  => 'publish',
	),
	true
);
tsubakuro_wp_env_assert( ! is_wp_error( $task_id ), 'Task can be inserted.' );

Tsubakuro_Post_Types::save_meta(
	$task_id,
	array(
		'status'        => 'in_progress',
		'assignee'      => 0,
		'related_pages' => array( 1, 2 ),
	)
);

$task = Tsubakuro_Post_Types::get_task( $task_id );
tsubakuro_wp_env_assert( is_array( $task ), 'Task can be loaded through plugin helper.' );
tsubakuro_wp_env_assert( 'in_progress' === $task['status'], 'Task status meta is persisted.' );
tsubakuro_wp_env_assert( array( 1, 2 ) === $task['related_pages'], 'Related pages meta is persisted.' );

$manifest = Tsubakuro_MCP::get_manifest();
$tools    = wp_list_pluck( $manifest['tools'], 'name' );
tsubakuro_wp_env_assert( in_array( 'tsubakuro_list_tasks', $tools, true ), 'MCP manifest exposes list task tool.' );

wp_delete_post( $task_id, true );
echo "wp-env smoke tests passed.\n";
