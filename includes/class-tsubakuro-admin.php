<?php
/**
 * Admin UI – task list and task detail pages.
 *
 * @package Tsubakuro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Tsubakuro_Admin {

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'add_menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_scripts' ) );
		add_action( 'wp_ajax_tsubakuro_create_task', array( __CLASS__, 'ajax_create_task' ) );
		add_action( 'wp_ajax_tsubakuro_update_task', array( __CLASS__, 'ajax_update_task' ) );
		add_action( 'wp_ajax_tsubakuro_delete_task', array( __CLASS__, 'ajax_delete_task' ) );
		add_action( 'wp_ajax_tsubakuro_add_comment', array( __CLASS__, 'ajax_add_comment' ) );
		add_action( 'wp_ajax_tsubakuro_get_comments', array( __CLASS__, 'ajax_get_comments' ) );
		add_action( 'wp_ajax_tsubakuro_get_task', array( __CLASS__, 'ajax_get_task' ) );
	}

	// -------------------------------------------------------------------------
	// Menu
	// -------------------------------------------------------------------------

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
	}

	// -------------------------------------------------------------------------
	// Assets
	// -------------------------------------------------------------------------

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

	public static function render_task_list() {
		$status_filter = isset( $_GET['status'] ) ? sanitize_text_field( $_GET['status'] ) : '';
		$tasks         = Tsubakuro_Post_Types::get_tasks( $status_filter ? array( 'status' => $status_filter ) : array() );
		include TSUBAKURO_PLUGIN_DIR . 'templates/admin/task-list.php';
	}

	// -------------------------------------------------------------------------
	// AJAX handlers
	// -------------------------------------------------------------------------

	public static function ajax_create_task() {
		check_ajax_referer( 'tsubakuro_admin', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => '権限がありません。' ), 403 );
		}

		$title   = sanitize_text_field( wp_unslash( $_POST['title'] ?? '' ) );
		$content = wp_kses_post( wp_unslash( $_POST['content'] ?? '' ) );

		if ( ! $title ) {
			wp_send_json_error( array( 'message' => 'タイトルは必須です。' ), 400 );
		}

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
			wp_send_json_error( array( 'message' => $task_id->get_error_message() ), 500 );
		}

		Tsubakuro_Post_Types::save_meta( $task_id, $_POST );

		wp_send_json_success( Tsubakuro_Post_Types::get_task( $task_id ) );
	}

	public static function ajax_update_task() {
		check_ajax_referer( 'tsubakuro_admin', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => '権限がありません。' ), 403 );
		}

		$task_id = absint( $_POST['task_id'] ?? 0 );
		if ( ! $task_id ) {
			wp_send_json_error( array( 'message' => 'タスクIDが必要です。' ), 400 );
		}

		$update = array( 'ID' => $task_id );

		if ( ! empty( $_POST['title'] ) ) {
			$update['post_title'] = sanitize_text_field( wp_unslash( $_POST['title'] ) );
		}

		if ( isset( $_POST['content'] ) ) {
			$update['post_content'] = wp_kses_post( wp_unslash( $_POST['content'] ) );
		}

		wp_update_post( $update );
		Tsubakuro_Post_Types::save_meta( $task_id, $_POST );

		wp_send_json_success( Tsubakuro_Post_Types::get_task( $task_id ) );
	}

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

	public static function ajax_get_comments() {
		check_ajax_referer( 'tsubakuro_admin', 'nonce' );

		$task_id = absint( $_GET['task_id'] ?? 0 );
		if ( ! $task_id ) {
			wp_send_json_error( array( 'message' => 'タスクIDが必要です。' ), 400 );
		}

		wp_send_json_success( self::get_task_comments( $task_id ) );
	}

	public static function ajax_get_task() {
		check_ajax_referer( 'tsubakuro_admin', 'nonce' );

		$task_id = absint( $_GET['task_id'] ?? 0 );
		if ( ! $task_id ) {
			wp_send_json_error( array( 'message' => 'タスクIDが必要です。' ), 400 );
		}

		$task = Tsubakuro_Post_Types::get_task( $task_id );
		if ( ! $task ) {
			wp_send_json_error( array( 'message' => 'タスクが見つかりません。' ), 404 );
		}

		$task['comments'] = self::get_task_comments( $task_id );
		wp_send_json_success( $task );
	}

	// -------------------------------------------------------------------------
	// Comment helpers
	// -------------------------------------------------------------------------

	public static function insert_comment( $task_id, $user_id, $comment ) {
		global $wpdb;

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

	public static function get_comment( $comment_id ) {
		global $wpdb;

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
			'id'           => (int) $row['id'],
			'task_id'      => (int) $row['task_id'],
			'user_id'      => (int) $row['user_id'],
			'user_name'    => $user ? $user->display_name : '不明',
			'comment'      => $row['comment'],
			'created_at'   => $row['created_at'],
		);
	}

	public static function get_task_comments( $task_id ) {
		global $wpdb;

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

	public static function get_users_list() {
		$users = get_users( array( 'who' => 'authors', 'fields' => array( 'ID', 'display_name' ) ) );
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
