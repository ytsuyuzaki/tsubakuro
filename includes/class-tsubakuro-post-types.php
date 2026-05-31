<?php
/**
 * Register the tsubakuro_task custom post type and its taxonomies/meta.
 *
 * Task meta fields:
 *   _tsubakuro_status        – todo | in_progress | completed
 *   _tsubakuro_priority      – low | medium | high
 *   _tsubakuro_assignee      – WordPress user ID
 *   _tsubakuro_related_pages – comma-separated post/page IDs
 *
 * @package Tsubakuro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manages the tsubakuro_task custom post type.
 */
class Tsubakuro_Post_Types {


	const TASK_POST_TYPE    = 'tsubakuro_task';
	const COMMENT_POST_TYPE = 'tsubakuro_comment';

	/** All valid task statuses. */
	const STATUSES = array(
		'todo'        => 'ToDo',
		'in_progress' => '実行中',
		'completed'   => '実行完了',
	);

	/** All valid task priorities. */
	const PRIORITIES = array(
		'low'    => '低',
		'medium' => '中',
		'high'   => '高',
	);

	/**
	 * Register WordPress hooks.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_post_type' ) );
	}

	/**
	 * Register plugin custom post types.
	 */
	public static function register_post_type() {
		$labels = array(
			'name'               => 'タスク',
			'singular_name'      => 'タスク',
			'add_new'            => '新規追加',
			'add_new_item'       => '新しいタスクを追加',
			'edit_item'          => 'タスクを編集',
			'new_item'           => '新しいタスク',
			'view_item'          => 'タスクを表示',
			'search_items'       => 'タスクを検索',
			'not_found'          => 'タスクが見つかりません',
			'not_found_in_trash' => 'ゴミ箱にタスクはありません',
		);

		$args = array(
			'labels'             => $labels,
			'public'             => false,
			'publicly_queryable' => false,
			'show_ui'            => false, // We build our own admin UI.
			'show_in_menu'       => false,
			'query_var'          => false,
			'rewrite'            => false,
			'capability_type'    => 'post',
			'has_archive'        => false,
			'hierarchical'       => false,
			'supports'           => array( 'title', 'editor', 'author' ),
			'show_in_rest'       => true,
			'rest_base'          => 'tsubakuro-tasks',
		);

		register_post_type( self::TASK_POST_TYPE, $args );

		$comment_labels = array(
			'name'          => 'タスクコメント',
			'singular_name' => 'タスクコメント',
		);

		$comment_args = array(
			'labels'             => $comment_labels,
			'public'             => false,
			'publicly_queryable' => false,
			'show_ui'            => false,
			'show_in_menu'       => false,
			'query_var'          => false,
			'rewrite'            => false,
			'capability_type'    => 'post',
			'has_archive'        => false,
			'hierarchical'       => false,
			'supports'           => array( 'editor', 'author' ),
		);

		register_post_type( self::COMMENT_POST_TYPE, $comment_args );
	}

	/**
	 * Save task meta from an array of data.
	 *
	 * @param int   $task_id Post ID.
	 * @param array $data    Assoc array with keys: status, assignee, related_pages.
	 */
	public static function save_meta( $task_id, $data ) {
		if ( isset( $data['status'] ) && array_key_exists( $data['status'], self::STATUSES ) ) {
			update_post_meta( $task_id, '_tsubakuro_status', sanitize_text_field( $data['status'] ) );
		}

		if ( isset( $data['priority'] ) && array_key_exists( $data['priority'], self::PRIORITIES ) ) {
			update_post_meta( $task_id, '_tsubakuro_priority', sanitize_text_field( $data['priority'] ) );
		}

		if ( isset( $data['assignee'] ) ) {
			update_post_meta( $task_id, '_tsubakuro_assignee', absint( $data['assignee'] ) );
		}

		if ( isset( $data['related_pages'] ) ) {
			// Accept array or comma-separated string.
			$pages = is_array( $data['related_pages'] )
				? array_map( 'absint', $data['related_pages'] )
				: array_map( 'absint', explode( ',', $data['related_pages'] ) );
			$pages = array_unique( array_filter( $pages ) );

			// Store each related page as a separate meta row for accurate querying.
			delete_post_meta( $task_id, '_tsubakuro_related_page' );
			foreach ( $pages as $page_id ) {
				add_post_meta( $task_id, '_tsubakuro_related_page', $page_id );
			}
		}
	}

	/**
	 * Get full task data including meta.
	 *
	 * @param int $task_id Post ID.
	 * @return array|null
	 */
	public static function get_task( $task_id ) {
		$post = get_post( $task_id );
		if ( ! $post || self::TASK_POST_TYPE !== $post->post_type ) {
			return null;
		}

		return self::format_task( $post );
	}

	/**
	 * Format a WP_Post into a structured task array.
	 *
	 * @param WP_Post $post The post object to format.
	 * @return array
	 */
	public static function format_task( $post ) {
		$status_raw    = get_post_meta( $post->ID, '_tsubakuro_status', true );
		$status        = $status_raw ? $status_raw : 'todo';
		$priority_raw  = get_post_meta( $post->ID, '_tsubakuro_priority', true );
		$priority      = $priority_raw ? $priority_raw : 'medium';
		$assignee_id   = (int) get_post_meta( $post->ID, '_tsubakuro_assignee', true );
		$related_pages = array_map( 'intval', get_post_meta( $post->ID, '_tsubakuro_related_page', false ) );

		$assignee = null;
		if ( $assignee_id ) {
			$user = get_user_by( 'id', $assignee_id );
			if ( $user ) {
				$assignee = array(
					'id'           => $user->ID,
					'display_name' => $user->display_name,
				);
			}
		}

		return array(
			'id'             => $post->ID,
			'title'          => $post->post_title,
			'content'        => $post->post_content,
			'status'         => $status,
			'status_label'   => self::STATUSES[ $status ] ?? $status,
			'priority'       => $priority,
			'priority_label' => self::PRIORITIES[ $priority ] ?? $priority,
			'assignee'       => $assignee,
			'related_pages'  => $related_pages,
			'created_at'     => $post->post_date,
			'updated_at'     => $post->post_modified,
			'author_id'      => (int) $post->post_author,
		);
	}

	/**
	 * Query tasks with optional filters.
	 *
	 * @param array $args Optional WP_Query compatible args.
	 * @return array
	 */
	public static function get_tasks( $args = array() ) {
		$defaults = array(
			'post_type'      => self::TASK_POST_TYPE,
			'post_status'    => 'publish',
			'posts_per_page' => 50,
			'orderby'        => 'date',
			'order'          => 'DESC',
		);

		$meta_query = array();

		if ( ! empty( $args['status'] ) && 'all' !== $args['status'] ) {
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- meta_query required for status filtering.
			$meta_query[] = array(
				'key'   => '_tsubakuro_status',
				'value' => sanitize_text_field( $args['status'] ),
			);
		}
		// status is always unset because it is not a valid WP_Query parameter.
		unset( $args['status'] );

		if ( ! empty( $args['priority'] ) ) {
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- meta_query required for priority filtering.
			$meta_query[] = array(
				'key'   => '_tsubakuro_priority',
				'value' => sanitize_text_field( $args['priority'] ),
			);
			unset( $args['priority'] );
		}

		if ( ! empty( $args['assignee'] ) ) {
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- meta_query required for assignee filtering.
			$meta_query[] = array(
				'key'     => '_tsubakuro_assignee',
				'value'   => absint( $args['assignee'] ),
				'compare' => '=',
				'type'    => 'NUMERIC',
			);
			unset( $args['assignee'] );
		}

		if ( ! empty( $args['related_page'] ) ) {
			$page_id = absint( $args['related_page'] );
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- meta_query required for page filtering.
			$meta_query[] = array(
				'key'     => '_tsubakuro_related_page',
				'value'   => $page_id,
				'compare' => '=',
				'type'    => 'NUMERIC',
			);
			unset( $args['related_page'] );
		}

		if ( ! empty( $meta_query ) ) {
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- meta_query is required for task list filters.
			$defaults['meta_query'] = $meta_query;
		}

		if ( ! empty( $args['s'] ) ) {
			$defaults['s'] = sanitize_text_field( $args['s'] );
			unset( $args['s'] );
		}

		if ( ! empty( $args['orderby'] ) ) {
			$orderby_map = array(
				'id'       => 'ID',
				'title'    => 'title',
				'date'     => 'date',
				'status'   => 'meta_value',
				'priority' => 'meta_value',
				'assignee' => 'meta_value_num',
			);
			$orderby     = sanitize_key( $args['orderby'] );
			if ( isset( $orderby_map[ $orderby ] ) ) {
				$defaults['orderby'] = $orderby_map[ $orderby ];
				if ( 'status' === $orderby ) {
					$defaults['meta_key'] = '_tsubakuro_status'; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- needed for list table sorting.
				}
				if ( 'priority' === $orderby ) {
					$defaults['meta_key'] = '_tsubakuro_priority'; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- needed for list table sorting.
				}
				if ( 'assignee' === $orderby ) {
					$defaults['meta_key'] = '_tsubakuro_assignee'; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- needed for list table sorting.
				}
			}
			unset( $args['orderby'] );
		}

		if ( ! empty( $args['order'] ) ) {
			$order = strtoupper( sanitize_text_field( $args['order'] ) );
			if ( in_array( $order, array( 'ASC', 'DESC' ), true ) ) {
				$defaults['order'] = $order;
			}
			unset( $args['order'] );
		}

		$query_args = array_merge( $defaults, $args );
		$query      = new WP_Query( $query_args );

		$tasks = array();
		foreach ( $query->posts as $post ) {
			$tasks[] = self::format_task( $post );
		}

		return $tasks;
	}
}
