<?php

/**
 * Bootstrap for WordPress integration tests running inside wp-env tests-cli.
 */

use Yoast\WPTestUtils\WPIntegration;

require_once dirname(__DIR__, 2) . '/vendor/yoast/wp-test-utils/src/WPIntegration/bootstrap-functions.php';

$_tests_dir = WPIntegration\get_path_to_wp_test_dir();

if (false === $_tests_dir) {
	echo PHP_EOL, 'ERROR: WordPress test bootstrap not found. Run tests via `npm run wp-env:test`, or set WP_TESTS_DIR/WP_DEVELOP_DIR.', PHP_EOL;
	exit(1);
}

require_once $_tests_dir . 'includes/functions.php';

/**
 * Load the plugin during the WordPress bootstrap sequence.
 */
function _manually_load_tsubakuro_plugin()
{
	require_once dirname(__DIR__, 2) . '/tsubakuro.php';
}

tests_add_filter('muplugins_loaded', '_manually_load_tsubakuro_plugin');

WPIntegration\bootstrap_it();

$adapter_plugin_file = dirname(__DIR__, 3) . '/mcp-adapter/mcp-adapter.php';
if (file_exists($adapter_plugin_file)) {
	require_once $adapter_plugin_file;

	if (class_exists('Tsubakuro_MCP')) {
		add_action('wp_abilities_api_init', array('Tsubakuro_MCP', 'register_abilities'));
		add_action('mcp_adapter_init', array('Tsubakuro_MCP', 'register_mcp_server'));
	}

	do_action('wp_abilities_api_init');

	if (class_exists('\\WP\\MCP\\Core\\McpAdapter')) {
		$adapter = \WP\MCP\Core\McpAdapter::instance();
		$adapter->init();
	}
}

Tsubakuro_Activator::activate();
