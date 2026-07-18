<?php
/**
 * Admin UI for the site strategy singleton.
 *
 * Registers a single "サイト方針" page under the Tsubakuro top-level menu and
 * handles its form submission. There is no list or delete screen because the
 * strategy is a single site-wide record. Shares the CSS/JS enqueued by
 * Tsubakuro_Admin (its hook guard matches any `tsubakuro` admin page).
 *
 * @package Tsubakuro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manages the site strategy admin page and its handler.
 */
class Tsubakuro_Site_Strategy_Admin {


	const PAGE_SLUG = 'tsubakuro-site-strategy';

	/**
	 * Register WordPress action hooks.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'add_menu' ), 15 );
		add_action( 'admin_post_tsubakuro_save_site_strategy', array( __CLASS__, 'handle_save' ) );
	}

	// -------------------------------------------------------------------------
	// Menu
	// -------------------------------------------------------------------------

	/**
	 * Register the site strategy submenu page.
	 *
	 * Priority 15 places it after the core task pages and before the evaluation
	 * / insight pages (registered at priority 20).
	 */
	public static function add_menu() {
		add_submenu_page(
			'tsubakuro-tasks',
			'サイト方針',
			'サイト方針',
			'edit_posts',
			self::PAGE_SLUG,
			array( __CLASS__, 'render_page' )
		);
	}

	// -------------------------------------------------------------------------
	// Screen
	// -------------------------------------------------------------------------

	/**
	 * Render the site strategy edit page.
	 */
	public static function render_page() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( '権限がありません。' );
		}

		$strategy = Tsubakuro_Site_Strategy::get_strategy();
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display-only notice key.
		$message = isset( $_GET['message'] ) ? sanitize_key( wp_unslash( $_GET['message'] ) ) : '';

		include TSUBAKURO_PLUGIN_DIR . 'templates/admin/site-strategy-form.php';
	}

	/**
	 * Handle the site strategy form submission.
	 */
	public static function handle_save() {
		check_admin_referer( 'tsubakuro_save_site_strategy', 'tsubakuro_site_strategy_nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( '権限がありません。' );
		}

		$data = array();
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- nonce verified above.
		foreach ( array_keys( Tsubakuro_Site_Strategy::FIELDS ) as $field ) {
			$data[ $field ] = sanitize_textarea_field( wp_unslash( $_POST[ $field ] ?? '' ) );
		}
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		Tsubakuro_Site_Strategy::save_strategy( $data );

		wp_safe_redirect( admin_url( 'admin.php?page=' . self::PAGE_SLUG . '&message=saved' ) );
		exit;
	}
}
