<?php
/**
 * Bootstrap for WordPress integration tests running inside wp-env tests-cli.
 */

$_tests_dir = getenv( 'WP_TESTS_DIR' ) ?: '/tmp/wordpress-tests-lib';

require_once $_tests_dir . '/includes/bootstrap.php';

require_once dirname( __DIR__, 2 ) . '/tsubakuro.php';
