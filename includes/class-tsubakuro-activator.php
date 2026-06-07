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

		if ( class_exists( 'Tsubakuro_Reminders' ) ) {
			Tsubakuro_Reminders::schedule_event();
		}
	}

	/**
	 * Runs on plugin deactivation.
	 */
	public static function deactivate() {
		if ( class_exists( 'Tsubakuro_Reminders' ) ) {
			Tsubakuro_Reminders::unschedule_event();
		}
	}
}
