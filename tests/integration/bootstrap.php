<?php
/**
 * Bootstrap for WordPress integration tests running inside wp-env tests-cli.
 */

use Yoast\WPTestUtils\WPIntegration;

require_once dirname( __DIR__, 2 ) . '/vendor/yoast/wp-test-utils/src/WPIntegration/bootstrap-functions.php';

$_tests_dir = WPIntegration\get_path_to_wp_test_dir();

if ( false === $_tests_dir ) {
	echo PHP_EOL, 'ERROR: WordPress test bootstrap not found. Run integration tests via `npm run wp-env:test`, or set WP_TESTS_DIR/WP_DEVELOP_DIR.', PHP_EOL;
	exit( 1 );
}

require_once $_tests_dir . 'includes/functions.php';

/**
 * Load the plugin during the WordPress bootstrap sequence.
 */
function _manually_load_tsubakuro_plugin() {
	require_once dirname( __DIR__, 2 ) . '/tsubakuro.php';
}

tests_add_filter( 'muplugins_loaded', '_manually_load_tsubakuro_plugin' );

WPIntegration\bootstrap_it();

Tsubakuro_Activator::activate();
