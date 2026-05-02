<?php
/**
 * Admin UI – task list and task detail pages.
 *
 * @package Tsubakuro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manages admin menu pages and AJAX handlers.
 */
class Tsubakuro_Admin {

	/**
	 * Register WordPress action hooks.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'add_menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_scripts' ) );
		add_action( 'admin_post_tsubakuro_save_task', array( __CLASS__, 'handle_save_task' ) );
		add_action( 'wp_ajax_tsubakuro_delete_task', array( __CLASS__, 'ajax_delete_task' ) );
		add_action( 'wp_ajax_tsubakuro_add_comment', array( __CLASS__, 'ajax_add_comment' ) );
		add_action( 'wp_ajax_tsubakuro_get_comments', array( __CLASS__, 'ajax_get_comments' ) );
		add_action( 'wp_ajax_tsubakuro_search_posts', array( __CLASS__, 'ajax_search_posts' ) );
	}

	// -------------------------------------------------------------------------
	// Menu
	// -------------------------------------------------------------------------

	/**
	 * Register top-level and sub-menu pages in the WordPress admin.
	 */
	public static function add_menu() {
		add_menu_page(
			'Tsubakuro タスク管理',
			'タスク管理',
			'edit_posts',
			'tsubakuro-tasks',
			array( __CLASS__, 'render_task_list' ),
			'dashicons-list-view',
			30
		);

		add_submenu_page(
			'tsubakuro-tasks',
			'タスク一覧',
			'タスク一覧',
			'edit_posts',
			'tsubakuro-tasks',
			array( __CLASS__, 'render_task_list' )
		);

		add_submenu_page(
			'tsubakuro-tasks',
			'新規タスク追加',
			'新規タスク追加',
			'edit_posts',
			'tsubakuro-task-form',
			array( __CLASS__, 'render_task_form' )
		);

		add_submenu_page(
			'tsubakuro-tasks',
			'Tsubakuro 設定',
			'設定',
			'manage_options',
			'tsubakuro-settings',
			array( __CLASS__, 'render_settings' )
		);
	}

	// -------------------------------------------------------------------------
	// Assets
	// -------------------------------------------------------------------------

	/**
	 * Enqueue admin CSS and JS on Tsubakuro admin pages.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public static function enqueue_scripts( $hook ) {
		if ( strpos( $hook, 'tsubakuro' ) === false ) {
			return;
		}

		wp_enqueue_style(
			'tsubakuro-admin',
			TSUBAKURO_PLUGIN_URL . 'admin/css/tsubakuro-admin.css',
			array(),
			TSUBAKURO_VERSION
		);

		wp_enqueue_script(
			'tsubakuro-admin',
			TSUBAKURO_PLUGIN_URL . 'admin/js/tsubakuro-admin.js',
			array( 'jquery' ),
			TSUBAKURO_VERSION,
			true
		);

		wp_localize_script(
			'tsubakuro-admin',
			'tsubakuroAdmin',
			array(
				'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'tsubakuro_admin' ),
				'statuses' => Tsubakuro_Post_Types::STATUSES,
				'users'    => self::get_users_list(),
			)
		);
	}

	// -------------------------------------------------------------------------
	// Render pages
	// -------------------------------------------------------------------------

	/**
	 * Render the task list admin page.
	 */
	public static function render_task_list() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display-only URL params set by the plugin.
		$status_filter = isset( $_GET['status'] ) ? sanitize_text_field( wp_unslash( $_GET['status'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display-only URL params set by the plugin.
		$message = isset( $_GET['message'] ) ? sanitize_text_field( wp_unslash( $_GET['message'] ) ) : '';
		$tasks   = Tsubakuro_Post_Types::get_tasks( $status_filter ? array( 'status' => $status_filter ) : array() );
		include TSUBAKURO_PLUGIN_DIR . 'templates/admin/task-list.php';
	}

	/**
	 * Render the task add/edit form admin page.
	 */
	public static function render_task_form() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( '権限がありません。' );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- task_id is a display param, not form data.
		$task_id              = absint( $_GET['task_id'] ?? 0 );
		$task                 = null;
		$comments             = array();
		$related_page_objects = array();

		if ( $task_id ) {
			$task = Tsubakuro_Post_Types::get_task( $task_id );
			if ( $task ) {
				$comments = self::get_task_comments( $task_id );
				foreach ( $task['related_pages'] as $page_id ) {
					$post = get_post( $page_id );
					if ( $post ) {
						$related_page_objects[] = array(
							'id'    => $post->ID,
							'title' => $post->post_title ?: sprintf( '(ID: %d)', $post->ID ),
							'url'   => (string) get_permalink( $post->ID ),
						);
					}
				}
			}
		}

		include TSUBAKURO_PLUGIN_DIR . 'templates/admin/task-form.php';
	}

	/**
	 * Render the plugin settings admin page.
	 */
	public static function render_settings() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( '権限がありません。' );
		}

		$mcp_url = rest_url( Tsubakuro_REST_API::NAMESPACE . '/mcp' );

		include TSUBAKURO_PLUGIN_DIR . 'templates/admin/settings.php';
	}

	// -------------------------------------------------------------------------
	// -------------------------------------------------------------------------

	/**
	 * Handle the task create/update form submission (admin-post action).
	 */
	public static function handle_save_task() {
		check_admin_referer( 'tsubakuro_save_task', 'tsubakuro_nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( '権限がありません。' );
		}

		$task_id = absint( $_POST['task_id'] ?? 0 );
		$title   = sanitize_text_field( wp_unslash( $_POST['title'] ?? '' ) );
		$content = wp_kses_post( wp_unslash( $_POST['content'] ?? '' ) );

		if ( ! $title ) {
			$back = admin_url( 'admin.php?page=tsubakuro-task-form' );
			if ( $task_id ) {
				$back .= '&task_id=' . $task_id;
			}
			wp_safe_redirect( add_query_arg( 'error', rawurlencode( 'タイトルは必須です。' ), $back ) );
			exit;
		}

		if ( $task_id ) {
			wp_update_post(
				array(
					'ID'           => $task_id,
					'post_title'   => $title,
					'post_content' => $content,
				)
			);
			Tsubakuro_Post_Types::save_meta( $task_id, $_POST );
		} else {
			$task_id = wp_insert_post(
				array(
					'post_type'    => 'tsubakuro_task',
					'post_title'   => $title,
					'post_content' => $content,
					'post_status'  => 'publish',
				),
				true
			);

			if ( is_wp_error( $task_id ) ) {
				$back = admin_url( 'admin.php?page=tsubakuro-task-form' );
				wp_safe_redirect( add_query_arg( 'error', rawurlencode( $task_id->get_error_message() ), $back ) );
				exit;
			}

			Tsubakuro_Post_Types::save_meta( $task_id, $_POST );
		}

		wp_safe_redirect( admin_url( 'admin.php?page=tsubakuro-tasks&message=saved' ) );
		exit;
	}

	// -------------------------------------------------------------------------
	// AJAX handlers
	// -------------------------------------------------------------------------

	/**
	 * AJAX handler: delete a task by ID.
	 */
	public static function ajax_delete_task() {
		check_ajax_referer( 'tsubakuro_admin', 'nonce' );

		if ( ! current_user_can( 'delete_posts' ) ) {
			wp_send_json_error( array( 'message' => '権限がありません。' ), 403 );
		}

		$task_id = absint( $_POST['task_id'] ?? 0 );
		if ( ! $task_id ) {
			wp_send_json_error( array( 'message' => 'タスクIDが必要です。' ), 400 );
		}

		wp_delete_post( $task_id, true );
		wp_send_json_success( array( 'deleted' => true ) );
	}

	/**
	 * AJAX handler: add a comment to a task.
	 */
	public static function ajax_add_comment() {
		check_ajax_referer( 'tsubakuro_admin', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => '権限がありません。' ), 403 );
		}

		$task_id = absint( $_POST['task_id'] ?? 0 );
		$comment = sanitize_textarea_field( wp_unslash( $_POST['comment'] ?? '' ) );

		if ( ! $task_id || ! $comment ) {
			wp_send_json_error( array( 'message' => 'タスクIDとコメントは必須です。' ), 400 );
		}

		$result = self::insert_comment( $task_id, get_current_user_id(), $comment );
		if ( false === $result ) {
			wp_send_json_error( array( 'message' => 'コメントの保存に失敗しました。' ), 500 );
		}

		wp_send_json_success( self::get_comment( $result ) );
	}

	/**
	 * AJAX handler: retrieve all comments for a task.
	 */
	public static function ajax_get_comments() {
		check_ajax_referer( 'tsubakuro_admin', 'nonce' );

		$task_id = absint( $_GET['task_id'] ?? 0 );
		if ( ! $task_id ) {
			wp_send_json_error( array( 'message' => 'タスクIDが必要です。' ), 400 );
		}

		wp_send_json_success( self::get_task_comments( $task_id ) );
	}

	/**
	 * AJAX handler: search published posts/pages by keyword.
	 */
	public static function ajax_search_posts() {
		check_ajax_referer( 'tsubakuro_admin', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => '権限がありません。' ), 403 );
		}

		$keyword    = sanitize_text_field( wp_unslash( $_GET['keyword'] ?? '' ) );
		$post_types = array_values(
			array_diff(
				get_post_types( array( 'public' => true ), 'names' ),
				array( 'tsubakuro_task' )
			)
		);

		// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_s -- keyword search required for post lookup.
		$query = new WP_Query(
			array(
				'post_type'      => $post_types,
				'post_status'    => 'publish',
				's'              => $keyword,
				'posts_per_page' => 10,
				'no_found_rows'  => true,
			)
		);

		$results = array();
		foreach ( $query->posts as $post ) {
			$results[] = array(
				'id'    => $post->ID,
				'title' => $post->post_title,
				'url'   => (string) get_permalink( $post->ID ),
			);
		}

		wp_send_json_success( $results );
	}

	// -------------------------------------------------------------------------
	// Comment helpers
	// -------------------------------------------------------------------------

	/**
	 * Insert a comment row into the custom comments table.
	 *
	 * @param int    $task_id  ID of the task.
	 * @param int    $user_id  ID of the commenter.
	 * @param string $comment  Comment text (already sanitized).
	 * @return int|false Inserted comment ID, or false on failure.
	 */
	public static function insert_comment( $task_id, $user_id, $comment ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- inserting into a custom table.
		return $wpdb->insert(
			$wpdb->prefix . 'tsubakuro_comments',
			array(
				'task_id'    => (int) $task_id,
				'user_id'    => (int) $user_id,
				'comment'    => $comment,
				'created_at' => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%s', '%s' )
		) ? $wpdb->insert_id : false;
	}

	/**
	 * Retrieve a single comment by its ID.
	 *
	 * @param int $comment_id Comment ID.
	 * @return array|null
	 */
	public static function get_comment( $comment_id ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- custom table.
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}tsubakuro_comments WHERE id = %d",
				$comment_id
			),
			ARRAY_A
		);

		if ( ! $row ) {
			return null;
		}

		$user = get_user_by( 'id', $row['user_id'] );

		return array(
			'id'         => (int) $row['id'],
			'task_id'    => (int) $row['task_id'],
			'user_id'    => (int) $row['user_id'],
			'user_name'  => $user ? $user->display_name : '不明',
			'comment'    => $row['comment'],
			'created_at' => $row['created_at'],
		);
	}

	/**
	 * Retrieve all comments for a task, ordered chronologically.
	 *
	 * @param int $task_id Task post ID.
	 * @return array
	 */
	public static function get_task_comments( $task_id ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- custom table.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}tsubakuro_comments WHERE task_id = %d ORDER BY created_at ASC",
				$task_id
			),
			ARRAY_A
		);

		$comments = array();
		foreach ( $rows as $row ) {
			$user       = get_user_by( 'id', $row['user_id'] );
			$comments[] = array(
				'id'         => (int) $row['id'],
				'task_id'    => (int) $row['task_id'],
				'user_id'    => (int) $row['user_id'],
				'user_name'  => $user ? $user->display_name : '不明',
				'comment'    => $row['comment'],
				'created_at' => $row['created_at'],
			);
		}

		return $comments;
	}

	// -------------------------------------------------------------------------
	// Utility
	// -------------------------------------------------------------------------

	/**
	 * Return a simplified list of all author users for dropdowns.
	 *
	 * @return array[]
	 */
	public static function get_users_list() {
		$users = get_users(
			array(
				'who'    => 'authors',
				'fields' => array( 'ID', 'display_name' ),
			)
		);
		$list  = array();
		foreach ( $users as $user ) {
			$list[] = array(
				'id'   => (int) $user->ID,
				'name' => $user->display_name,
			);
		}
		return $list;
	}
}
