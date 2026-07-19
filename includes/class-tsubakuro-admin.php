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
		add_action( 'admin_menu', array( __CLASS__, 'reorder_submenu' ), 99 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_scripts' ) );
		add_action( 'admin_post_tsubakuro_save_task', array( __CLASS__, 'handle_save_task' ) );
		add_action( 'admin_post_tsubakuro_bulk_tasks', array( __CLASS__, 'handle_bulk_tasks' ) );
		add_action( 'wp_ajax_tsubakuro_delete_task', array( __CLASS__, 'ajax_delete_task' ) );
		add_action( 'wp_ajax_tsubakuro_add_comment', array( __CLASS__, 'ajax_add_comment' ) );
		add_action( 'wp_ajax_tsubakuro_get_comments', array( __CLASS__, 'ajax_get_comments' ) );
		add_action( 'wp_ajax_tsubakuro_search_posts', array( __CLASS__, 'ajax_search_posts' ) );
		add_action( 'wp_ajax_tsubakuro_search_tasks', array( __CLASS__, 'ajax_search_tasks' ) );
		add_action( 'pre_get_comments', array( __CLASS__, 'exclude_task_comments_from_list' ) );
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_related_tasks_meta_boxes' ) );
	}

	// -------------------------------------------------------------------------
	// Menu
	// -------------------------------------------------------------------------

	/**
	 * Register top-level and sub-menu pages in the WordPress admin.
	 */
	public static function add_menu() {
		add_menu_page(
			'tsubakuro',
			'tsubakuro',
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
			'タスクを追加',
			'タスクを追加',
			'edit_posts',
			'tsubakuro-task-form',
			array( __CLASS__, 'render_task_form' )
		);

		add_submenu_page(
			'tsubakuro-tasks',
			'tsubakuroについて',
			'tsubakuroについて',
			'edit_posts',
			'tsubakuro-about',
			array( __CLASS__, 'render_about' )
		);
	}

	/**
	 * Reorder submenu entries into the intended workflow order.
	 */
	public static function reorder_submenu() {
		global $submenu;

		if ( empty( $submenu['tsubakuro-tasks'] ) || ! is_array( $submenu['tsubakuro-tasks'] ) ) {
			return;
		}

		$order = array(
			'tsubakuro-site-strategy',
			'tsubakuro-tasks',
			'tsubakuro-task-form',
			'tsubakuro-evaluations',
			'tsubakuro-evaluation-form',
			'tsubakuro-insights',
			'tsubakuro-insight-form',
			'tsubakuro-about',
		);

		$positions = array_flip( $order );
		usort(
			$submenu['tsubakuro-tasks'],
			static function ( $a, $b ) use ( $positions ) {
				$a_slug = $a[2] ?? '';
				$b_slug = $b[2] ?? '';
				$a_pos  = $positions[ $a_slug ] ?? PHP_INT_MAX;
				$b_pos  = $positions[ $b_slug ] ?? PHP_INT_MAX;

				return $a_pos <=> $b_pos;
			}
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
				'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
				'nonce'      => wp_create_nonce( 'tsubakuro_admin' ),
				'statuses'   => Tsubakuro_Post_Types::STATUSES,
				'priorities' => Tsubakuro_Post_Types::PRIORITIES,
				'users'      => self::get_users_list(),
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

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- task_id and parent_id are display params, not form data.
		$task_id = absint( $_GET['task_id'] ?? 0 );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- parent_id is a display param, not form data.
		$default_parent_id    = absint( $_GET['parent_id'] ?? 0 );
		$task                 = null;
		$comments             = array();
		$related_page_objects = array();
		$parent_task          = null;
		$child_tasks          = array();
		$task_defaults        = array(
			'title'         => '',
			'content'       => '',
			'related_pages' => array(),
		);

		if ( ! $task_id ) {
			// phpcs:disable WordPress.Security.NonceVerification.Recommended -- display defaults for the new-task form.
			$task_defaults['title']   = isset( $_GET['title'] ) ? sanitize_text_field( wp_unslash( $_GET['title'] ) ) : '';
			$task_defaults['content'] = isset( $_GET['content'] ) ? sanitize_textarea_field( wp_unslash( $_GET['content'] ) ) : '';
			$related_page             = isset( $_GET['related_page'] ) ? absint( wp_unslash( $_GET['related_page'] ) ) : 0;
			// phpcs:enable WordPress.Security.NonceVerification.Recommended
			if ( $related_page ) {
				$task_defaults['related_pages'] = array( $related_page );
			}
		}

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
				if ( $task['parent_id'] ) {
					$parent_task = Tsubakuro_Post_Types::get_task( $task['parent_id'] );
				}
				$child_tasks = Tsubakuro_Post_Types::get_tasks( array( 'parent_id' => $task_id ) );
			}
		} elseif ( $default_parent_id ) {
			$parent_task = Tsubakuro_Post_Types::get_task( $default_parent_id );
		}

		if ( ! $task_id && ! empty( $task_defaults['related_pages'] ) ) {
			foreach ( $task_defaults['related_pages'] as $page_id ) {
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

		include TSUBAKURO_PLUGIN_DIR . 'templates/admin/task-form.php';
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
	 * Build task list query args from display-only request parameters.
	 *
	 * @return array
	 */
	public static function get_task_list_args_from_request() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- display-only list table filters.
		$args = array(
			'status'   => isset( $_GET['status'] ) ? sanitize_text_field( wp_unslash( $_GET['status'] ) ) : 'todo',
			'priority' => isset( $_GET['priority'] ) ? sanitize_text_field( wp_unslash( $_GET['priority'] ) ) : '',
			'assignee' => isset( $_GET['assignee'] ) ? absint( wp_unslash( $_GET['assignee'] ) ) : 0,
			's'        => isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '',
			'orderby'  => isset( $_GET['orderby'] ) ? sanitize_key( wp_unslash( $_GET['orderby'] ) ) : 'date',
			'order'    => isset( $_GET['order'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_GET['order'] ) ) ) : 'DESC',
		);
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		if ( 'all' !== $args['status'] && ! array_key_exists( $args['status'], Tsubakuro_Post_Types::STATUSES ) ) {
			$args['status'] = 'todo';
		}

		if ( ! array_key_exists( $args['priority'], Tsubakuro_Post_Types::PRIORITIES ) ) {
			$args['priority'] = '';
		}

		if ( ! in_array( $args['orderby'], array( 'id', 'title', 'status', 'priority', 'assignee', 'date' ), true ) ) {
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
			if ( Tsubakuro_Post_Types::get_task( $task_id ) && wp_delete_post( $task_id, true ) ) {
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
				'label'       => 'タスクを追加',
				'description' => '課題、依頼、改善案を登録する',
				'url'         => admin_url( 'admin.php?page=tsubakuro-task-form' ),
			),
			array(
				'label'       => 'tsubakuroについて',
				'description' => 'ツバクロの設計思想と運用の背景を確認する',
				'url'         => admin_url( 'admin.php?page=tsubakuro-about' ),
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

		$task_id   = absint( $_POST['task_id'] ?? 0 );
		$title     = sanitize_text_field( wp_unslash( $_POST['title'] ?? '' ) );
		$content   = wp_kses_post( wp_unslash( $_POST['content'] ?? '' ) );
		$parent_id = absint( $_POST['parent_id'] ?? 0 );

		if ( ! $title ) {
			$back = admin_url( 'admin.php?page=tsubakuro-task-form' );
			if ( $task_id ) {
				$back .= '&task_id=' . $task_id;
			}
			wp_safe_redirect( add_query_arg( 'error', rawurlencode( 'タイトルは必須です。' ), $back ) );
			exit;
		}

		if ( $task_id ) {
			if ( ! Tsubakuro_Post_Types::get_task( $task_id ) ) {
				$back = admin_url( 'admin.php?page=tsubakuro-task-form&task_id=' . $task_id );
				wp_safe_redirect( add_query_arg( 'error', rawurlencode( 'タスクが見つかりません。' ), $back ) );
				exit;
			}

			wp_update_post(
				array(
					'ID'           => $task_id,
					'post_title'   => $title,
					'post_content' => $content,
					'post_parent'  => $parent_id,
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
					'post_parent'  => $parent_id,
				),
				true
			);

			if ( is_wp_error( $task_id ) ) {
				$back = admin_url( 'admin.php?page=tsubakuro-task-form' );
				wp_safe_redirect( add_query_arg( 'error', rawurlencode( $task_id->get_error_message() ), $back ) );
				exit;
			}

			$meta_input = $_POST;
			if ( empty( $meta_input['status'] ) || ! array_key_exists( $meta_input['status'], Tsubakuro_Post_Types::STATUSES ) ) {
				$meta_input['status'] = 'todo';
			}

			Tsubakuro_Post_Types::save_meta( $task_id, $meta_input );
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

		if ( ! Tsubakuro_Post_Types::get_task( $task_id ) ) {
			wp_send_json_error( array( 'message' => 'タスクが見つかりません。' ), 404 );
		}

		wp_delete_post( $task_id, true );
		wp_send_json_success( array( 'deleted' => true ) );
	}

	/**
	 * AJAX handler: add a comment to a task/evaluation/insight.
	 */
	public static function ajax_add_comment() {
		check_ajax_referer( 'tsubakuro_admin', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => '権限がありません。' ), 403 );
		}

		$post_id = absint( $_POST['post_id'] ?? 0 );
		if ( ! $post_id ) {
			$post_id = absint( $_POST['task_id'] ?? 0 );
		}
		$comment = sanitize_textarea_field( wp_unslash( $_POST['comment'] ?? '' ) );

		if ( ! $post_id || ! $comment ) {
			wp_send_json_error( array( 'message' => '対象IDとコメントは必須です。' ), 400 );
		}

		if ( ! self::is_comment_target_post_id( $post_id ) ) {
			wp_send_json_error( array( 'message' => 'コメント対象が見つかりません。' ), 404 );
		}

		$result = self::insert_comment( $post_id, get_current_user_id(), $comment );
		if ( false === $result ) {
			wp_send_json_error( array( 'message' => 'コメントの保存に失敗しました。' ), 500 );
		}

		wp_send_json_success( self::get_comment( $result ) );
	}

	/**
	 * AJAX handler: retrieve all comments for a task/evaluation/insight.
	 */
	public static function ajax_get_comments() {
		check_ajax_referer( 'tsubakuro_admin', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => '権限がありません。' ), 403 );
		}

		$post_id = absint( $_GET['post_id'] ?? 0 );
		if ( ! $post_id ) {
			$post_id = absint( $_GET['task_id'] ?? 0 );
		}

		if ( ! $post_id ) {
			wp_send_json_error( array( 'message' => '対象IDが必要です。' ), 400 );
		}

		if ( ! self::is_comment_target_post_id( $post_id ) ) {
			wp_send_json_error( array( 'message' => 'コメント対象が見つかりません。' ), 404 );
		}

		wp_send_json_success( self::get_task_comments( $post_id ) );
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

	/**
	 * AJAX handler: search tasks by keyword (used for parent task selector).
	 */
	public static function ajax_search_tasks() {
		check_ajax_referer( 'tsubakuro_admin', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => '権限がありません。' ), 403 );
		}

		$keyword = sanitize_text_field( wp_unslash( $_GET['keyword'] ?? '' ) );
		$tasks   = Tsubakuro_Post_Types::get_tasks(
			array(
				's'              => $keyword,
				'posts_per_page' => 10,
			)
		);
		$results = array();
		foreach ( $tasks as $task ) {
			$results[] = array(
				'id'    => $task['id'],
				'title' => $task['title'],
			);
		}

		wp_send_json_success( $results );
	}

	// -------------------------------------------------------------------------
	// Comment helpers
	// -------------------------------------------------------------------------

	/**
	 * Exclude task comments from all standard WordPress comment queries.
	 *
	 * Fires on the `pre_get_comments` action so that comments stored with the
	 * plugin's custom comment type do not appear in the wp-admin Comments page,
	 * the admin-dashboard Recent Comments widget, or any frontend comment list.
	 * Queries issued by the plugin itself (which already set `type` to the
	 * plugin's comment type) are left untouched.
	 *
	 * @param WP_Comment_Query $query The comment query object.
	 */
	public static function exclude_task_comments_from_list( $query ) {
		// Skip queries that explicitly target the plugin's own comment type.
		if ( self::COMMENT_TYPE === ( $query->query_vars['type'] ?? '' ) ) {
			return;
		}

		$not_in = $query->query_vars['type__not_in'] ?? array();

		if ( ! is_array( $not_in ) ) {
			$not_in = array( $not_in );
		}

		if ( ! in_array( self::COMMENT_TYPE, $not_in, true ) ) {
			$not_in[] = self::COMMENT_TYPE;
			$query->set( 'type__not_in', $not_in );
		}
	}

	/**
	 * Insert a task comment as a dedicated internal post.
	 *
	 * @param int    $task_id  ID of the task.
	 * @param int    $user_id  ID of the commenter.
	 * @param string $comment  Comment text (already sanitized).
	 * @return int|false Inserted comment ID, or false on failure.
	 */
	public static function insert_comment( $task_id, $user_id, $comment ) {
		$comment_id = wp_insert_post(
			array(
				'post_type'    => Tsubakuro_Post_Types::COMMENT_POST_TYPE,
				'post_status'  => 'publish',
				'post_title'   => '',
				'post_content' => $comment,
				'post_author'  => (int) $user_id,
				'post_parent'  => (int) $task_id,
			),
			true
		);

		if ( is_wp_error( $comment_id ) || ! $comment_id ) {
			return false;
		}

		return (int) $comment_id;
	}

	/**
	 * Retrieve a single comment by its ID.
	 *
	 * @param int $comment_id Comment ID.
	 * @return array|null
	 */
	public static function get_comment( $comment_id ) {
		$comment = get_post( $comment_id );

		if ( ! $comment || Tsubakuro_Post_Types::COMMENT_POST_TYPE !== $comment->post_type ) {
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
		$query           = new WP_Query(
			array(
				'post_type'      => Tsubakuro_Post_Types::COMMENT_POST_TYPE,
				'post_status'    => 'publish',
				'post_parent'    => (int) $task_id,
				'posts_per_page' => 100,
				'orderby'        => 'date',
				'order'          => 'ASC',
			)
		);
		$comment_objects = $query->posts;

		$comments = array();
		foreach ( $comment_objects as $comment ) {
			$comments[] = self::format_comment( $comment );
		}

		return $comments;
	}

	/**
	 * Format a task comment post for REST, AJAX, and MCP responses.
	 *
	 * @param WP_Post|object $comment Task comment post object.
	 * @return array
	 */
	private static function format_comment( $comment ) {
		$user_id = (int) $comment->post_author;
		$user    = get_user_by( 'id', $user_id );
		$task_id = (int) $comment->post_parent;

		return array(
			'id'         => (int) $comment->ID,
			'post_id'    => $task_id,
			'task_id'    => $task_id,
			'user_id'    => $user_id,
			'user_name'  => $user ? $user->display_name : '不明',
			'comment'    => $comment->post_content,
			'created_at' => $comment->post_date,
		);
	}

	/**
	 * Whether the given post ID can accept plugin comments.
	 *
	 * @param int $post_id Parent post ID.
	 * @return bool
	 */
	public static function is_comment_target_post_id( $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return false;
		}

		return in_array(
			$post->post_type,
			array(
				Tsubakuro_Post_Types::TASK_POST_TYPE,
				Tsubakuro_Evaluations::POST_TYPE,
				Tsubakuro_Insights::POST_TYPE,
			),
			true
		);
	}

	// -------------------------------------------------------------------------
	// Meta boxes
	// -------------------------------------------------------------------------

	/**
	 * Register a "関連タスク" meta box for all public post types (admin-only).
	 */
	public static function add_related_tasks_meta_boxes() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$post_types = array_values(
			array_diff(
				get_post_types( array( 'public' => true ), 'names' ),
				array( Tsubakuro_Post_Types::TASK_POST_TYPE, Tsubakuro_Post_Types::COMMENT_POST_TYPE )
			)
		);

		foreach ( $post_types as $post_type ) {
			add_meta_box(
				'tsubakuro-related-tasks',
				__( '関連タスク (Tsubakuro)', 'tsubakuro' ),
				array( __CLASS__, 'render_related_tasks_meta_box' ),
				$post_type,
				'side',
				'default'
			);
		}
	}

	/**
	 * Render the "関連タスク" meta box content.
	 *
	 * @param WP_Post $post The current post being edited.
	 */
	public static function render_related_tasks_meta_box( $post ) {
		$tasks = Tsubakuro_Post_Types::get_tasks(
			array(
				'related_page' => $post->ID,
				'status'       => 'all',
			)
		);
		include TSUBAKURO_PLUGIN_DIR . 'templates/admin/related-tasks-meta-box.php';
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
				'capability' => 'edit_posts',
				'fields'     => array( 'ID', 'display_name' ),
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
