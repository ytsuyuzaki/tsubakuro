<?php
/**
 * Frontend overlay – shows a floating task panel to logged-in admins
 * on any public-facing page, allowing them to create tasks, view tasks
 * related to the current page, add comments and change status.
 *
 * @package Tsubakuro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Frontend overlay for logged-in admin users.
 */
class Tsubakuro_Frontend {

	/**
	 * Register WordPress action hooks.
	 */
	public static function init() {
		// Only run on the frontend, not in admin.
		if ( is_admin() ) {
			return;
		}

		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_scripts' ) );
		add_action( 'wp_footer', array( __CLASS__, 'render_popup' ) );
	}

	/**
	 * Enqueue public-facing CSS and JS assets.
	 */
	public static function enqueue_scripts() {
		if ( ! self::should_show() ) {
			return;
		}

		wp_enqueue_style(
			'tsubakuro-public',
			TSUBAKURO_PLUGIN_URL . 'public/css/tsubakuro-public.css',
			array(),
			TSUBAKURO_VERSION
		);

		wp_enqueue_script(
			'tsubakuro-public',
			TSUBAKURO_PLUGIN_URL . 'public/js/tsubakuro-public.js',
			array( 'jquery' ),
			TSUBAKURO_VERSION,
			true
		);

		wp_localize_script(
			'tsubakuro-public',
			'tsubakuroPublic',
			array(
				'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
				'restUrl'     => rest_url( Tsubakuro_REST_API::NAMESPACE ),
				'restNonce'   => wp_create_nonce( 'wp_rest' ),
				'nonce'       => wp_create_nonce( 'tsubakuro_admin' ),
				'currentPage' => get_queried_object_id(),
				'statuses'    => Tsubakuro_Post_Types::STATUSES,
				'users'       => Tsubakuro_Admin::get_users_list(),
			)
		);
	}

	/**
	 * Output the task popup HTML in the page footer.
	 */
	public static function render_popup() {
		if ( ! self::should_show() ) {
			return;
		}

		include TSUBAKURO_PLUGIN_DIR . 'templates/public/task-popup.php';
	}

	/**
	 * Whether the frontend overlay should be rendered for the current user.
	 *
	 * @return bool
	 */
	private static function should_show() {
		return is_user_logged_in() && current_user_can( 'edit_posts' );
	}
}
