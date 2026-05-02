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
	 * Create the custom database table for task comments on activation.
	 */
	public static function activate() {
		global $wpdb;

		$table_name      = $wpdb->prefix . 'tsubakuro_comments';
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
			id          BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			task_id     BIGINT(20) UNSIGNED NOT NULL,
			user_id     BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			comment     TEXT               NOT NULL,
			created_at  DATETIME           NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY task_id (task_id)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		add_option( 'tsubakuro_db_version', '1.0' );
	}

	/**
	 * Runs on plugin deactivation.
	 */
	public static function deactivate() {
		// Nothing to clean up on simple deactivation.
	}
}
