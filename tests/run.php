<?php

require_once __DIR__ . '/bootstrap.php';

$failures = 0;

function test( $name, $callback ) {
	global $failures;

	tsubakuro_test_reset();

	try {
		$callback();
		echo "PASS {$name}\n";
	} catch ( Exception $exception ) {
		$failures++;
		echo "FAIL {$name}\n";
		echo '  ' . $exception->getMessage() . "\n";
	}
}

function assert_same( $expected, $actual, $message = '' ) {
	if ( $expected !== $actual ) {
		throw new Exception(
			( $message ? $message . ' - ' : '' ) .
			'expected ' . var_export( $expected, true ) . ', got ' . var_export( $actual, true )
		);
	}
}

function assert_true( $actual, $message = '' ) {
	assert_same( true, (bool) $actual, $message );
}

function assert_array_has_key_test( $key, $array, $message = '' ) {
	if ( ! array_key_exists( $key, $array ) ) {
		throw new Exception( ( $message ? $message . ' - ' : '' ) . "missing key {$key}" );
	}
}

function make_post( $id, $title, $content, $type = 'tsubakuro_task' ) {
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

test(
	'plugin bootstrap registers hooks',
	function () {
		$bootstrap_state = $GLOBALS['tsubakuro_test_bootstrap_state'];

		assert_array_has_key_test( 'plugins_loaded', $bootstrap_state['actions'] );
		assert_same( 'tsubakuro_init', $bootstrap_state['actions']['plugins_loaded'][0] );
		assert_same( array( 'Tsubakuro_Activator', 'activate' ), $bootstrap_state['activation_hooks'][0][1] );
		assert_same( '1.0.0', TSUBAKURO_VERSION );
	}
);

test(
	'init methods register WordPress hooks and routes',
	function () {
		tsubakuro_init();

		assert_array_has_key_test( 'init', $GLOBALS['tsubakuro_test']['actions'] );
		assert_array_has_key_test( 'admin_menu', $GLOBALS['tsubakuro_test']['actions'] );
		assert_array_has_key_test( 'rest_api_init', $GLOBALS['tsubakuro_test']['actions'] );
		assert_array_has_key_test( 'wp_enqueue_scripts', $GLOBALS['tsubakuro_test']['actions'] );

		Tsubakuro_Post_Types::register_post_type();
		Tsubakuro_REST_API::register_routes();
		Tsubakuro_MCP::register_routes();

		assert_array_has_key_test( 'tsubakuro_task', $GLOBALS['tsubakuro_test']['post_types'] );
		assert_true( $GLOBALS['tsubakuro_test']['post_types']['tsubakuro_task']['show_in_rest'] );
		assert_array_has_key_test( 'tsubakuro/v1/tasks', $GLOBALS['tsubakuro_test']['rest_routes'] );
		assert_array_has_key_test( 'tsubakuro/v1/mcp', $GLOBALS['tsubakuro_test']['rest_routes'] );
	}
);

test(
	'save_meta sanitizes supported task metadata',
	function () {
		Tsubakuro_Post_Types::save_meta(
			101,
			array(
				'status'        => 'completed',
				'assignee'      => '-9',
				'related_pages' => array( '5', '5', '0', '-3', 'abc' ),
			)
		);

		assert_same( array( 'completed' ), $GLOBALS['tsubakuro_test']['post_meta'][101]['_tsubakuro_status'] );
		assert_same( array( 9 ), $GLOBALS['tsubakuro_test']['post_meta'][101]['_tsubakuro_assignee'] );
		assert_same( array( 5, 3 ), $GLOBALS['tsubakuro_test']['post_meta'][101]['_tsubakuro_related_page'] );
	}
);

test(
	'format_task returns structured task data',
	function () {
		$GLOBALS['tsubakuro_test']['users'][7] = (object) array(
			'ID'           => 7,
			'display_name' => 'Editor User',
		);
		$GLOBALS['tsubakuro_test']['post_meta'][101] = array(
			'_tsubakuro_status'       => array( 'in_progress' ),
			'_tsubakuro_assignee'     => array( 7 ),
			'_tsubakuro_related_page' => array( 11, 12 ),
		);

		$task = Tsubakuro_Post_Types::format_task( make_post( 101, 'Task title', 'Task body' ) );

		assert_same( 101, $task['id'] );
		assert_same( 'Task title', $task['title'] );
		assert_same( 'in_progress', $task['status'] );
		assert_same( '実行中', $task['status_label'] );
		assert_same( array( 11, 12 ), $task['related_pages'] );
		assert_same( array( 'id' => 7, 'display_name' => 'Editor User' ), $task['assignee'] );
	}
);

test(
	'get_tasks builds query filters',
	function () {
		$GLOBALS['tsubakuro_test']['posts'][101] = make_post( 101, 'Task title', 'Task body' );

		$tasks = Tsubakuro_Post_Types::get_tasks(
			array(
				'status'       => 'todo',
				'related_page' => 22,
				'per_page'     => 1,
			)
		);

		assert_same( 1, count( $tasks ) );
		assert_same( 'tsubakuro_task', $GLOBALS['tsubakuro_test']['last_query_args']['post_type'] );
		assert_same( 1, $GLOBALS['tsubakuro_test']['last_query_args']['per_page'] );
		assert_same( '_tsubakuro_status', $GLOBALS['tsubakuro_test']['last_query_args']['meta_query'][0]['key'] );
		assert_same( '_tsubakuro_related_page', $GLOBALS['tsubakuro_test']['last_query_args']['meta_query'][1]['key'] );
	}
);

test(
	'MCP manifest exposes expected tools',
	function () {
		$manifest = Tsubakuro_MCP::get_manifest();
		$tools    = array_column( $manifest['tools'], 'name' );

		assert_same( '2024-11-05', $manifest['schema_version'] );
		assert_true( in_array( 'tsubakuro_list_tasks', $tools, true ) );
		assert_true( in_array( 'tsubakuro_add_comment', $tools, true ) );
	}
);

test(
	'MCP dispatcher returns JSON-RPC errors for invalid requests',
	function () {
		$reflection = new ReflectionClass( 'Tsubakuro_MCP' );
		$dispatch   = $reflection->getMethod( 'dispatch' );
		$dispatch->setAccessible( true );

		$invalid = $dispatch->invoke( null, array( 'id' => 1 ) );
		$missing = $dispatch->invoke(
			null,
			array(
				'id'     => 2,
				'method' => 'tsubakuro_get_task',
				'params' => array(),
			)
		);

		assert_same( -32600, $invalid['error']['code'] );
		assert_same( -32602, $missing['error']['code'] );
	}
);

if ( $failures > 0 ) {
	exit( 1 );
}

echo "All tests passed.\n";
