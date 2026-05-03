<?php
/**
 * Plugin activation / deactivation handler.
 *
 * @package Tsubakuro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles plugin activation and deactivation lifecycle.
 */
class Tsubakuro_Activator {

	/**
	 * Run plugin activation tasks.
	 */
	public static function activate() {
		add_option( 'tsubakuro_db_version', '1.0' );
	}

	/**
	 * Runs on plugin deactivation.
	 */
	public static function deactivate() {
		// Nothing to clean up on simple deactivation.
	}
}
