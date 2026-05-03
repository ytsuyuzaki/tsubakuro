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

	const COMMENT_TYPE = 'tsubakuro_task';

	/**
	 * Register WordPress action hooks.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'add_menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_scripts' ) );
		add_action( 'admin_post_tsubakuro_save_task', array( __CLASS__, 'handle_save_task' ) );
		add_action( 'admin_post_tsubakuro_bulk_tasks', array( __CLASS__, 'handle_bulk_tasks' ) );
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
			'MCP ガイド',
			'MCP ガイド',
			'edit_posts',
			'tsubakuro-mcp-guide',
			array( __CLASS__, 'render_mcp_guide' )
		);

		add_submenu_page(
			'tsubakuro-tasks',
			'ツバクロについて',
			'ツバクロについて',
			'edit_posts',
			'tsubakuro-about',
			array( __CLASS__, 'render_about' )
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
		$list_args = self::get_task_list_args_from_request();
		$message   = self::get_admin_message_from_request();
		$tasks     = Tsubakuro_Post_Types::get_tasks( $list_args );
		$users     = self::get_users_list();
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
							'title' => $post->post_title ? $post->post_title : sprintf( '(ID: %d)', $post->ID ),
							'url'   => (string) get_permalink( $post->ID ),
						);
					}
				}
			}
		}

		include TSUBAKURO_PLUGIN_DIR . 'templates/admin/task-form.php';
	}

	/**
	 * Render the MCP guide admin page.
	 */
	public static function render_mcp_guide() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( '権限がありません。', 'tsubakuro' ) );
		}

		$mcp_url = rest_url( Tsubakuro_REST_API::NAMESPACE . '/mcp' );

		include TSUBAKURO_PLUGIN_DIR . 'templates/admin/mcp-guide.php';
	}

	/**
	 * Render the plugin about page.
	 */
	public static function render_about() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( '権限がありません。' );
		}

		$story_items     = self::get_about_story_items();
		$value_points    = self::get_about_value_points();
		$reference_links = self::get_about_reference_links();

		include TSUBAKURO_PLUGIN_DIR . 'templates/admin/about.php';
	}

	/**
	 * Render the plugin settings admin page.
	 */
	public static function render_settings() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( '権限がありません。' );
		}

		$mcp_url       = rest_url( Tsubakuro_REST_API::NAMESPACE . '/mcp' );
		$token_url     = rest_url( Tsubakuro_REST_API::NAMESPACE . '/oauth/token' );
		$oauth_clients = Tsubakuro_OAuth::get_clients();

		include TSUBAKURO_PLUGIN_DIR . 'templates/admin/settings.php';
	}

	/**
	 * Build task list query args from display-only request parameters.
	 *
	 * @return array
	 */
	public static function get_task_list_args_from_request() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- display-only list table filters.
		$args = array(
			'status'   => isset( $_GET['status'] ) ? sanitize_text_field( wp_unslash( $_GET['status'] ) ) : '',
			'assignee' => isset( $_GET['assignee'] ) ? absint( wp_unslash( $_GET['assignee'] ) ) : 0,
			's'        => isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '',
			'orderby'  => isset( $_GET['orderby'] ) ? sanitize_key( wp_unslash( $_GET['orderby'] ) ) : 'date',
			'order'    => isset( $_GET['order'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_GET['order'] ) ) ) : 'DESC',
		);
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		if ( ! array_key_exists( $args['status'], Tsubakuro_Post_Types::STATUSES ) ) {
			$args['status'] = '';
		}

		if ( ! in_array( $args['orderby'], array( 'id', 'title', 'status', 'assignee', 'date' ), true ) ) {
			$args['orderby'] = 'date';
		}

		if ( ! in_array( $args['order'], array( 'ASC', 'DESC' ), true ) ) {
			$args['order'] = 'DESC';
		}

		return array_filter(
			$args,
			static function ( $value ) {
				return '' !== $value && 0 !== $value;
			}
		);
	}

	/**
	 * Return a sanitized admin message key from the current request.
	 *
	 * @return string
	 */
	public static function get_admin_message_from_request() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display-only notice parameter.
		return isset( $_GET['message'] ) ? sanitize_key( wp_unslash( $_GET['message'] ) ) : '';
	}

	/**
	 * Build a task list URL while preserving active filters.
	 *
	 * @param array $overrides Query parameters to add, replace, or remove by passing null/empty string.
	 * @return string
	 */
	public static function get_task_list_url( $overrides = array() ) {
		$params = array_merge(
			array( 'page' => 'tsubakuro-tasks' ),
			self::get_task_list_args_from_request(),
			$overrides
		);

		foreach ( $params as $key => $value ) {
			if ( '' === $value || null === $value || 0 === $value ) {
				unset( $params[ $key ] );
			}
		}

		return add_query_arg( $params, admin_url( 'admin.php' ) );
	}

	/**
	 * Return the next sort order for a column.
	 *
	 * @param string $column Column key.
	 * @return string
	 */
	public static function get_next_task_list_order( $column ) {
		$args = self::get_task_list_args_from_request();
		if ( ( $args['orderby'] ?? 'date' ) === $column && 'ASC' === ( $args['order'] ?? 'DESC' ) ) {
			return 'DESC';
		}

		return 'ASC';
	}

	/**
	 * Sanitize selected task IDs from a submitted bulk action.
	 *
	 * @param array $data Submitted request data.
	 * @return array
	 */
	public static function get_selected_task_ids( $data ) {
		$ids = $data['task_ids'] ?? array();
		if ( ! is_array( $ids ) ) {
			$ids = array( $ids );
		}

		return array_values( array_unique( array_filter( array_map( 'absint', $ids ) ) ) );
	}

	/**
	 * Delete selected tasks and return the number of deleted records.
	 *
	 * @param array $task_ids Task IDs.
	 * @return int
	 */
	public static function delete_tasks( $task_ids ) {
		$deleted = 0;
		foreach ( self::get_selected_task_ids( array( 'task_ids' => $task_ids ) ) as $task_id ) {
			if ( wp_delete_post( $task_id, true ) ) {
				++$deleted;
			}
		}

		return $deleted;
	}

	/**
	 * Return the name-story points shown on the about page.
	 *
	 * @return array
	 */
	public static function get_about_story_items() {
		$items = array(
			array(
				'title'       => '巣作り',
				'description' => 'ツバメが少しずつ素材を集めて巣を完成させるように、issue を積み上げてプロジェクトの全体像を形にします。',
			),
			array(
				'title'       => '往復して運ぶ',
				'description' => '餌や泥を何度も運ぶ姿を、タスクを回し続け、対応を前に進める流れに重ねています。',
			),
			array(
				'title'       => '帰ってくる',
				'description' => '同じ場所へ戻る習性は、issue の履歴、継続対応、再発対応を WordPress 側に残す考え方につながります。',
			),
			array(
				'title'       => '異変に敏感',
				'description' => '環境の変化に影響を受けやすい性質を、問題の早期発見や状態変化の把握になぞらえています。',
			),
			array(
				'title'       => '群れで動く',
				'description' => '複数で活動する姿から、チームで issue を共有し、分担して処理するイメージを持たせています。',
			),
		);

		return apply_filters( 'tsubakuro_about_story_items', $items );
	}

	/**
	 * Return the value points shown on the about page.
	 *
	 * @return array
	 */
	public static function get_about_value_points() {
		$points = array(
			'サイト運用者が見ている WordPress 管理画面の中で issue を管理できること。',
			'投稿、固定ページ、プラグイン、テーマなど、WordPress 文脈と課題を紐づけられること。',
			'AI、手動、外部ツール実行など、課題ごとに実行先を選べること。',
			'実行履歴や判断材料を WordPress 側に残し、後から参照できること。',
		);

		return apply_filters( 'tsubakuro_about_value_points', $points );
	}

	/**
	 * Return reference links shown on the about page.
	 *
	 * @return array
	 */
	public static function get_about_reference_links() {
		$links = array(
			array(
				'label'       => 'タスク一覧',
				'description' => 'WordPress 内の課題を確認する',
				'url'         => admin_url( 'admin.php?page=tsubakuro-tasks' ),
			),
			array(
				'label'       => '新規タスク追加',
				'description' => '課題、依頼、改善案を登録する',
				'url'         => admin_url( 'admin.php?page=tsubakuro-task-form' ),
			),
			array(
				'label'       => 'MCP 設定',
				'description' => 'AI エージェントや外部ツールに渡す入口を確認する',
				'url'         => admin_url( 'admin.php?page=tsubakuro-settings' ),
			),
		);

		return apply_filters( 'tsubakuro_about_reference_links', $links );
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

	/**
	 * Handle task list bulk actions.
	 */
	public static function handle_bulk_tasks() {
		check_admin_referer( 'tsubakuro_bulk_tasks', 'tsubakuro_bulk_nonce' );

		if ( ! current_user_can( 'delete_posts' ) ) {
			wp_die( '権限がありません。' );
		}

		$bulk_action = sanitize_key( wp_unslash( $_POST['bulk_action'] ?? '' ) );
		if ( '' === $bulk_action ) {
			$bulk_action = sanitize_key( wp_unslash( $_POST['bulk_action_bottom'] ?? '' ) );
		}
		$deleted = 0;
		$message = '';

		if ( 'delete' === $bulk_action ) {
			$deleted = self::delete_tasks( self::get_selected_task_ids( wp_unslash( $_POST ) ) );
			$message = $deleted ? 'bulk_deleted' : 'no_tasks_selected';
		}

		$redirect_args = array(
			'page' => 'tsubakuro-tasks',
		);

		foreach ( array( 'status', 'assignee', 's', 'orderby', 'order' ) as $key ) {
			if ( isset( $_POST[ $key ] ) && '' !== $_POST[ $key ] ) {
				$redirect_args[ $key ] = sanitize_text_field( wp_unslash( $_POST[ $key ] ) );
			}
		}

		if ( $message ) {
			$redirect_args['message'] = $message;
		}

		if ( $deleted ) {
			$redirect_args['deleted_count'] = $deleted;
		}

		wp_safe_redirect( add_query_arg( $redirect_args, admin_url( 'admin.php' ) ) );
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
	 * Insert a task comment using WordPress core comments.
	 *
	 * @param int    $task_id  ID of the task.
	 * @param int    $user_id  ID of the commenter.
	 * @param string $comment  Comment text (already sanitized).
	 * @return int|false Inserted comment ID, or false on failure.
	 */
	public static function insert_comment( $task_id, $user_id, $comment ) {
		$comment_id = wp_insert_comment(
			array(
				'comment_post_ID'  => (int) $task_id,
				'user_id'          => (int) $user_id,
				'comment_content'  => $comment,
				'comment_type'     => self::COMMENT_TYPE,
				'comment_approved' => 1,
			)
		);

		return $comment_id ? (int) $comment_id : false;
	}

	/**
	 * Retrieve a single comment by its ID.
	 *
	 * @param int $comment_id Comment ID.
	 * @return array|null
	 */
	public static function get_comment( $comment_id ) {
		$comment = get_comment( $comment_id );

		if ( ! $comment || self::COMMENT_TYPE !== $comment->comment_type ) {
			return null;
		}

		return self::format_comment( $comment );
	}

	/**
	 * Retrieve all comments for a task, ordered chronologically.
	 *
	 * @param int $task_id Task post ID.
	 * @return array
	 */
	public static function get_task_comments( $task_id ) {
		$comment_objects = get_comments(
			array(
				'post_id' => (int) $task_id,
				'status'  => 'approve',
				'type'    => self::COMMENT_TYPE,
				'orderby' => 'comment_date',
				'order'   => 'ASC',
			)
		);

		$comments = array();
		foreach ( $comment_objects as $comment ) {
			$comments[] = self::format_comment( $comment );
		}

		return $comments;
	}

	/**
	 * Format a WordPress comment for REST, AJAX, and MCP responses.
	 *
	 * @param WP_Comment|object $comment WordPress comment object.
	 * @return array
	 */
	private static function format_comment( $comment ) {
		$user_id = (int) $comment->user_id;
		$user    = get_user_by( 'id', $user_id );

		return array(
			'id'         => (int) $comment->comment_ID,
			'task_id'    => (int) $comment->comment_post_ID,
			'user_id'    => $user_id,
			'user_name'  => $user ? $user->display_name : '不明',
			'comment'    => $comment->comment_content,
			'created_at' => $comment->comment_date,
		);
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
