<?php
/**
 * REST API endpoints for tasks and comments.
 *
 * Base route: /wp-json/tsubakuro/v1/
 *
 * Endpoints:
 *   GET    /tasks
 *   POST   /tasks
 *   GET    /tasks/{id}
 *   PUT    /tasks/{id}
 *   DELETE /tasks/{id}
 *   GET    /tasks/{id}/comments
 *   POST   /tasks/{id}/comments
 *   GET    /tasks/{id}/subtasks
 *
 * @package Tsubakuro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provides the REST API endpoints for tasks and comments.
 */
class Tsubakuro_REST_API {



	const NAMESPACE = 'tsubakuro/v1';

	/**
	 * Register WordPress hooks.
	 */
	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	/**
	 * Register all REST routes for this plugin.
	 */
	public static function register_routes() {
		// Tasks collection.
		register_rest_route(
			self::NAMESPACE,
			'/tasks',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( __CLASS__, 'get_tasks' ),
					'permission_callback' => array( __CLASS__, 'check_read_permission' ),
					'args'                => array(
						'status'       => array( 'type' => 'string' ),
						'priority'     => array( 'type' => 'string' ),
						'related_page' => array( 'type' => 'integer' ),
						'parent_id'    => array( 'type' => 'integer' ),
						'per_page'     => array(
							'type'    => 'integer',
							'default' => 50,
							'minimum' => 1,
							'maximum' => 100,
						),
					),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( __CLASS__, 'create_task' ),
					'permission_callback' => array( __CLASS__, 'check_write_permission' ),
					'args'                => array(
						'title'           => array(
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'content'         => array(
							'type'    => 'string',
							'default' => '',
						),
						'status'          => array(
							'type'    => 'string',
							'default' => 'todo',
						),
						'priority'        => array(
							'type'    => 'string',
							'default' => 'medium',
						),
						'assignee'        => array(
							'type'    => 'integer',
							'default' => 0,
						),
						'related_pages'   => array(
							'type'    => 'array',
							'items'   => array( 'type' => 'integer' ),
							'default' => array(),
						),
						'start_remind_at' => array(
							'type' => 'string',
						),
						'due_remind_at'   => array(
							'type' => 'string',
						),
						'parent_id'       => array(
							'type'    => 'integer',
							'default' => 0,
						),
					),
				),
			)
		);

		// Single task.
		register_rest_route(
			self::NAMESPACE,
			'/tasks/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( __CLASS__, 'get_task' ),
					'permission_callback' => array( __CLASS__, 'check_read_permission' ),
					'args'                => array(
						'id' => array(
							'required' => true,
							'type'     => 'integer',
						),
					),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( __CLASS__, 'update_task' ),
					'permission_callback' => array( __CLASS__, 'check_write_permission' ),
					'args'                => array(
						'id'              => array(
							'required' => true,
							'type'     => 'integer',
						),
						'title'           => array(
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'content'         => array( 'type' => 'string' ),
						'status'          => array( 'type' => 'string' ),
						'priority'        => array( 'type' => 'string' ),
						'assignee'        => array( 'type' => 'integer' ),
						'related_pages'   => array(
							'type'  => 'array',
							'items' => array( 'type' => 'integer' ),
						),
						'start_remind_at' => array( 'type' => 'string' ),
						'due_remind_at'   => array( 'type' => 'string' ),
						'parent_id'       => array( 'type' => 'integer' ),
					),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( __CLASS__, 'delete_task' ),
					'permission_callback' => array( __CLASS__, 'check_delete_permission' ),
					'args'                => array(
						'id' => array(
							'required' => true,
							'type'     => 'integer',
						),
					),
				),
			)
		);

		// Task comments.
		register_rest_route(
			self::NAMESPACE,
			'/tasks/(?P<id>\d+)/comments',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( __CLASS__, 'get_comments' ),
					'permission_callback' => array( __CLASS__, 'check_read_permission' ),
					'args'                => array(
						'id' => array(
							'required' => true,
							'type'     => 'integer',
						),
					),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( __CLASS__, 'add_comment' ),
					'permission_callback' => array( __CLASS__, 'check_write_permission' ),
					'args'                => array(
						'id'      => array(
							'required' => true,
							'type'     => 'integer',
						),
						'comment' => array(
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_textarea_field',
						),
					),
				),
			)
		);

		// Task subtasks.
		register_rest_route(
			self::NAMESPACE,
			'/tasks/(?P<id>\d+)/subtasks',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( __CLASS__, 'get_subtasks' ),
					'permission_callback' => array( __CLASS__, 'check_read_permission' ),
					'args'                => array(
						'id' => array(
							'required' => true,
							'type'     => 'integer',
						),
					),
				),
			)
		);
	}

	// -------------------------------------------------------------------------
	// Handlers
	// -------------------------------------------------------------------------

	/**
	 * GET /tasks – Return a list of tasks with optional filters.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public static function get_tasks( $request ) {
		$args = array();

		if ( $request->get_param( 'status' ) ) {
			$args['status'] = $request->get_param( 'status' );
		}

		if ( $request->get_param( 'priority' ) ) {
			$args['priority'] = $request->get_param( 'priority' );
		}

		if ( $request->get_param( 'related_page' ) ) {
			$args['related_page'] = $request->get_param( 'related_page' );
		}

		if ( $request->get_param( 'per_page' ) ) {
			$args['posts_per_page'] = $request->get_param( 'per_page' );
		}

		if ( null !== $request->get_param( 'parent_id' ) ) {
			$args['parent_id'] = $request->get_param( 'parent_id' );
		}

		return rest_ensure_response( Tsubakuro_Post_Types::get_tasks( $args ) );
	}

	/**
	 * POST /tasks – Create a new task.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function create_task( $request ) {
		$task_id = wp_insert_post(
			array(
				'post_type'    => 'tsubakuro_task',
				'post_title'   => $request->get_param( 'title' ),
				'post_content' => wp_kses_post( $request->get_param( 'content' ) ),
				'post_status'  => 'publish',
				'post_parent'  => absint( $request->get_param( 'parent_id' ) ),
			),
			true
		);

		if ( is_wp_error( $task_id ) ) {
			return new WP_Error( 'create_failed', $task_id->get_error_message(), array( 'status' => 500 ) );
		}

		$status = $request->get_param( 'status' );
		if ( ! is_string( $status ) || ! array_key_exists( $status, Tsubakuro_Post_Types::STATUSES ) ) {
			$status = 'todo';
		}

		Tsubakuro_Post_Types::save_meta(
			$task_id,
			array(
				'status'          => $status,
				'priority'        => $request->get_param( 'priority' ),
				'assignee'        => $request->get_param( 'assignee' ),
				'related_pages'   => $request->get_param( 'related_pages' ),
				'start_remind_at' => $request->get_param( 'start_remind_at' ),
				'due_remind_at'   => $request->get_param( 'due_remind_at' ),
			)
		);

		return rest_ensure_response( Tsubakuro_Post_Types::get_task( $task_id ) );
	}

	/**
	 * GET /tasks/{id} – Return a single task with its comments.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function get_task( $request ) {
		$task = Tsubakuro_Post_Types::get_task( (int) $request['id'] );

		if ( ! $task ) {
			return new WP_Error( 'not_found', 'タスクが見つかりません。', array( 'status' => 404 ) );
		}

		$task['comments'] = Tsubakuro_Admin::get_task_comments( $task['id'] );
		$task['children'] = Tsubakuro_Post_Types::get_tasks( array( 'parent_id' => $task['id'] ) );

		return rest_ensure_response( $task );
	}

	/**
	 * PUT /tasks/{id} – Update an existing task.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function update_task( $request ) {
		$task_id = (int) $request['id'];
		$task    = Tsubakuro_Post_Types::get_task( $task_id );

		if ( ! $task ) {
			return new WP_Error( 'not_found', 'タスクが見つかりません。', array( 'status' => 404 ) );
		}

		$update = array( 'ID' => $task_id );

		if ( null !== $request->get_param( 'title' ) ) {
			$update['post_title'] = $request->get_param( 'title' );
		}

		if ( null !== $request->get_param( 'content' ) ) {
			$update['post_content'] = wp_kses_post( $request->get_param( 'content' ) );
		}

		if ( null !== $request->get_param( 'parent_id' ) ) {
			$update['post_parent'] = absint( $request->get_param( 'parent_id' ) );
		}

		wp_update_post( $update );

		$meta = array();
		foreach ( array( 'status', 'priority', 'assignee', 'related_pages', 'start_remind_at', 'due_remind_at' ) as $field ) {
			if ( null !== $request->get_param( $field ) ) {
				$meta[ $field ] = $request->get_param( $field );
			}
		}
		if ( ! empty( $meta ) ) {
			Tsubakuro_Post_Types::save_meta( $task_id, $meta );
		}

		return rest_ensure_response( Tsubakuro_Post_Types::get_task( $task_id ) );
	}

	/**
	 * DELETE /tasks/{id} – Delete a task.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function delete_task( $request ) {
		$task_id = (int) $request['id'];
		$task    = Tsubakuro_Post_Types::get_task( $task_id );

		if ( ! $task ) {
			return new WP_Error( 'not_found', 'タスクが見つかりません。', array( 'status' => 404 ) );
		}

		wp_delete_post( $task_id, true );

		return rest_ensure_response(
			array(
				'deleted' => true,
				'id'      => $task_id,
			)
		);
	}

	/**
	 * GET /tasks/{id}/comments – Return all comments for a task.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function get_comments( $request ) {
		$task_id = (int) $request['id'];
		$task    = Tsubakuro_Post_Types::get_task( $task_id );

		if ( ! $task ) {
			return new WP_Error( 'not_found', 'タスクが見つかりません。', array( 'status' => 404 ) );
		}

		return rest_ensure_response( Tsubakuro_Admin::get_task_comments( $task_id ) );
	}

	/**
	 * POST /tasks/{id}/comments – Add a comment to a task.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function add_comment( $request ) {
		$task_id = (int) $request['id'];
		$task    = Tsubakuro_Post_Types::get_task( $task_id );

		if ( ! $task ) {
			return new WP_Error( 'not_found', 'タスクが見つかりません。', array( 'status' => 404 ) );
		}

		$comment_id = Tsubakuro_Admin::insert_comment(
			$task_id,
			get_current_user_id(),
			$request->get_param( 'comment' )
		);

		if ( false === $comment_id ) {
			return new WP_Error( 'insert_failed', 'コメントの保存に失敗しました。', array( 'status' => 500 ) );
		}

		return rest_ensure_response( Tsubakuro_Admin::get_comment( $comment_id ) );
	}

	/**
	 * GET /tasks/{id}/subtasks – Return all direct child tasks.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function get_subtasks( $request ) {
		$task_id = (int) $request['id'];
		$task    = Tsubakuro_Post_Types::get_task( $task_id );

		if ( ! $task ) {
			return new WP_Error( 'not_found', 'タスクが見つかりません。', array( 'status' => 404 ) );
		}

		return rest_ensure_response( Tsubakuro_Post_Types::get_tasks( array( 'parent_id' => $task_id ) ) );
	}

	// -------------------------------------------------------------------------
	// Permissions
	// -------------------------------------------------------------------------

	/**
	 * Permission callback: require edit_posts capability.
	 *
	 * @return bool
	 */
	public static function check_read_permission() {
		return current_user_can( 'edit_posts' );
	}

	/**
	 * Permission callback: require edit_posts capability for write operations.
	 *
	 * @return bool
	 */
	public static function check_write_permission() {
		return current_user_can( 'edit_posts' );
	}

	/**
	 * Permission callback: require delete_posts capability.
	 *
	 * @return bool
	 */
	public static function check_delete_permission() {
		return current_user_can( 'delete_posts' );
	}
}
