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

		self::register_evaluation_routes();
		self::register_insight_routes();
	}

	/**
	 * Register REST routes for article evaluations.
	 */
	private static function register_evaluation_routes() {
		register_rest_route(
			self::NAMESPACE,
			'/evaluations',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( __CLASS__, 'get_evaluations' ),
					'permission_callback' => array( __CLASS__, 'check_read_permission' ),
					'args'                => array(
						'target_post' => array( 'type' => 'integer' ),
						'change_item' => array( 'type' => 'string' ),
						'judgment'    => array( 'type' => 'string' ),
						'metric'      => array( 'type' => 'string' ),
						'unevaluated' => array( 'type' => 'boolean' ),
						'overdue'     => array( 'type' => 'boolean' ),
						's'           => array( 'type' => 'string' ),
						'per_page'    => array(
							'type'    => 'integer',
							'default' => 50,
							'minimum' => 1,
							'maximum' => 100,
						),
					),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( __CLASS__, 'create_evaluation' ),
					'permission_callback' => array( __CLASS__, 'check_write_permission' ),
					'args'                => self::evaluation_write_args( true ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/evaluations/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( __CLASS__, 'get_evaluation' ),
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
					'callback'            => array( __CLASS__, 'update_evaluation' ),
					'permission_callback' => array( __CLASS__, 'check_write_permission' ),
					'args'                => self::evaluation_write_args( false ),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( __CLASS__, 'delete_evaluation' ),
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
	}

	/**
	 * Register REST routes for site-level insights.
	 */
	private static function register_insight_routes() {
		register_rest_route(
			self::NAMESPACE,
			'/insights',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( __CLASS__, 'get_insights' ),
					'permission_callback' => array( __CLASS__, 'check_read_permission' ),
					'args'                => array(
						'status'     => array( 'type' => 'string' ),
						'action'     => array( 'type' => 'string' ),
						'evaluation' => array( 'type' => 'integer' ),
						's'          => array( 'type' => 'string' ),
						'per_page'   => array(
							'type'    => 'integer',
							'default' => 50,
							'minimum' => 1,
							'maximum' => 100,
						),
					),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( __CLASS__, 'create_insight' ),
					'permission_callback' => array( __CLASS__, 'check_write_permission' ),
					'args'                => self::insight_write_args( true ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/insights/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( __CLASS__, 'get_insight' ),
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
					'callback'            => array( __CLASS__, 'update_insight' ),
					'permission_callback' => array( __CLASS__, 'check_write_permission' ),
					'args'                => self::insight_write_args( false ),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( __CLASS__, 'delete_insight' ),
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
	}

	/**
	 * Shared arg schema for evaluation create/update.
	 *
	 * @param bool $is_create Whether this is the create (title required) variant.
	 * @return array
	 */
	private static function evaluation_write_args( $is_create ) {
		$args = array(
			'title'          => array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'change_detail'  => array( 'type' => 'string' ),
			'target_post'    => array( 'type' => 'integer' ),
			'change_item'    => array( 'type' => 'string' ),
			'purpose'        => array( 'type' => 'string' ),
			'implemented_at' => array( 'type' => 'string' ),
			'due_at'         => array( 'type' => 'string' ),
			'metric'         => array( 'type' => 'string' ),
			'before_value'   => array( 'type' => 'string' ),
			'after_value'    => array( 'type' => 'string' ),
			'result'         => array( 'type' => 'string' ),
			'judgment'       => array( 'type' => 'string' ),
			'note'           => array( 'type' => 'string' ),
		);

		if ( $is_create ) {
			$args['title']['required'] = true;
		} else {
			$args['id'] = array(
				'required' => true,
				'type'     => 'integer',
			);
		}

		return $args;
	}

	/**
	 * Shared arg schema for insight create/update.
	 *
	 * @param bool $is_create Whether this is the create (title required) variant.
	 * @return array
	 */
	private static function insight_write_args( $is_create ) {
		$args = array(
			'title'         => array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'site'          => array( 'type' => 'string' ),
			'post_kind'     => array( 'type' => 'string' ),
			'hypothesis'    => array( 'type' => 'string' ),
			'conclusion'    => array( 'type' => 'string' ),
			'total_count'   => array( 'type' => 'integer' ),
			'success_count' => array( 'type' => 'integer' ),
			'status'        => array( 'type' => 'string' ),
			'action'        => array( 'type' => 'string' ),
			'evaluations'   => array(
				'type'  => 'array',
				'items' => array( 'type' => 'integer' ),
			),
		);

		if ( $is_create ) {
			$args['title']['required'] = true;
		} else {
			$args['id'] = array(
				'required' => true,
				'type'     => 'integer',
			);
		}

		return $args;
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
	// Evaluation handlers
	// -------------------------------------------------------------------------

	/**
	 * GET /evaluations – list article evaluations with optional filters.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public static function get_evaluations( $request ) {
		$args = array();

		foreach ( array( 'target_post', 'change_item', 'judgment', 'metric', 's' ) as $field ) {
			if ( $request->get_param( $field ) ) {
				$args[ $field ] = $request->get_param( $field );
			}
		}

		if ( $request->get_param( 'unevaluated' ) ) {
			$args['unevaluated'] = true;
		}

		if ( $request->get_param( 'overdue' ) ) {
			$args['overdue'] = true;
		}

		if ( $request->get_param( 'per_page' ) ) {
			$args['posts_per_page'] = $request->get_param( 'per_page' );
		}

		return rest_ensure_response( Tsubakuro_Evaluations::get_evaluations( $args ) );
	}

	/**
	 * POST /evaluations – create an article evaluation.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function create_evaluation( $request ) {
		$eval_id = wp_insert_post(
			array(
				'post_type'    => Tsubakuro_Evaluations::POST_TYPE,
				'post_title'   => $request->get_param( 'title' ),
				'post_content' => wp_kses_post( (string) $request->get_param( 'change_detail' ) ),
				'post_status'  => 'publish',
			),
			true
		);

		if ( is_wp_error( $eval_id ) ) {
			return new WP_Error( 'create_failed', $eval_id->get_error_message(), array( 'status' => 500 ) );
		}

		Tsubakuro_Evaluations::save_meta( $eval_id, self::collect_evaluation_meta( $request ) );

		return rest_ensure_response( Tsubakuro_Evaluations::get_evaluation( $eval_id ) );
	}

	/**
	 * GET /evaluations/{id} – single evaluation with linked insights.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function get_evaluation( $request ) {
		$evaluation = Tsubakuro_Evaluations::get_evaluation( (int) $request['id'] );

		if ( ! $evaluation ) {
			return new WP_Error( 'not_found', '記事評価が見つかりません。', array( 'status' => 404 ) );
		}

		$evaluation['insights'] = Tsubakuro_Insights::get_insights_for_evaluation( $evaluation['id'] );

		return rest_ensure_response( $evaluation );
	}

	/**
	 * PUT /evaluations/{id} – update an evaluation.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function update_evaluation( $request ) {
		$eval_id    = (int) $request['id'];
		$evaluation = Tsubakuro_Evaluations::get_evaluation( $eval_id );

		if ( ! $evaluation ) {
			return new WP_Error( 'not_found', '記事評価が見つかりません。', array( 'status' => 404 ) );
		}

		$update = array( 'ID' => $eval_id );
		if ( null !== $request->get_param( 'title' ) ) {
			$update['post_title'] = $request->get_param( 'title' );
		}
		if ( null !== $request->get_param( 'change_detail' ) ) {
			$update['post_content'] = wp_kses_post( (string) $request->get_param( 'change_detail' ) );
		}
		wp_update_post( $update );

		Tsubakuro_Evaluations::save_meta( $eval_id, self::collect_evaluation_meta( $request ) );

		return rest_ensure_response( Tsubakuro_Evaluations::get_evaluation( $eval_id ) );
	}

	/**
	 * DELETE /evaluations/{id}.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function delete_evaluation( $request ) {
		$eval_id = (int) $request['id'];

		if ( ! Tsubakuro_Evaluations::get_evaluation( $eval_id ) ) {
			return new WP_Error( 'not_found', '記事評価が見つかりません。', array( 'status' => 404 ) );
		}

		wp_delete_post( $eval_id, true );

		return rest_ensure_response(
			array(
				'deleted' => true,
				'id'      => $eval_id,
			)
		);
	}

	/**
	 * Collect provided evaluation meta fields from a request.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return array
	 */
	private static function collect_evaluation_meta( $request ) {
		$meta = array();
		foreach ( array( 'target_post', 'change_item', 'purpose', 'implemented_at', 'due_at', 'metric', 'before_value', 'after_value', 'result', 'judgment', 'note' ) as $field ) {
			if ( null !== $request->get_param( $field ) ) {
				$meta[ $field ] = $request->get_param( $field );
			}
		}

		return $meta;
	}

	// -------------------------------------------------------------------------
	// Insight handlers
	// -------------------------------------------------------------------------

	/**
	 * GET /insights – list insights with optional filters.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public static function get_insights( $request ) {
		$args = array();

		foreach ( array( 'status', 'action', 'evaluation', 's' ) as $field ) {
			if ( $request->get_param( $field ) ) {
				$args[ $field ] = $request->get_param( $field );
			}
		}

		if ( $request->get_param( 'per_page' ) ) {
			$args['posts_per_page'] = $request->get_param( 'per_page' );
		}

		return rest_ensure_response( Tsubakuro_Insights::get_insights( $args ) );
	}

	/**
	 * POST /insights – create an insight.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function create_insight( $request ) {
		$insight_id = wp_insert_post(
			array(
				'post_type'   => Tsubakuro_Insights::POST_TYPE,
				'post_title'  => $request->get_param( 'title' ),
				'post_status' => 'publish',
			),
			true
		);

		if ( is_wp_error( $insight_id ) ) {
			return new WP_Error( 'create_failed', $insight_id->get_error_message(), array( 'status' => 500 ) );
		}

		Tsubakuro_Insights::save_meta( $insight_id, self::collect_insight_meta( $request ) );

		return rest_ensure_response( Tsubakuro_Insights::get_insight( $insight_id ) );
	}

	/**
	 * GET /insights/{id} – single insight with linked evaluations.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function get_insight( $request ) {
		$insight = Tsubakuro_Insights::get_insight( (int) $request['id'] );

		if ( ! $insight ) {
			return new WP_Error( 'not_found', '改善知見が見つかりません。', array( 'status' => 404 ) );
		}

		$insight['evaluations'] = array();
		foreach ( $insight['evaluation_ids'] as $eval_id ) {
			$evaluation = Tsubakuro_Evaluations::get_evaluation( $eval_id );
			if ( $evaluation ) {
				$insight['evaluations'][] = $evaluation;
			}
		}

		return rest_ensure_response( $insight );
	}

	/**
	 * PUT /insights/{id} – update an insight.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function update_insight( $request ) {
		$insight_id = (int) $request['id'];

		if ( ! Tsubakuro_Insights::get_insight( $insight_id ) ) {
			return new WP_Error( 'not_found', '改善知見が見つかりません。', array( 'status' => 404 ) );
		}

		if ( null !== $request->get_param( 'title' ) ) {
			wp_update_post(
				array(
					'ID'         => $insight_id,
					'post_title' => $request->get_param( 'title' ),
				)
			);
		}

		Tsubakuro_Insights::save_meta( $insight_id, self::collect_insight_meta( $request ) );

		return rest_ensure_response( Tsubakuro_Insights::get_insight( $insight_id ) );
	}

	/**
	 * DELETE /insights/{id}.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function delete_insight( $request ) {
		$insight_id = (int) $request['id'];

		if ( ! Tsubakuro_Insights::get_insight( $insight_id ) ) {
			return new WP_Error( 'not_found', '改善知見が見つかりません。', array( 'status' => 404 ) );
		}

		wp_delete_post( $insight_id, true );

		return rest_ensure_response(
			array(
				'deleted' => true,
				'id'      => $insight_id,
			)
		);
	}

	/**
	 * Collect provided insight meta fields from a request.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return array
	 */
	private static function collect_insight_meta( $request ) {
		$meta = array();
		foreach ( array( 'site', 'post_kind', 'hypothesis', 'conclusion', 'total_count', 'success_count', 'status', 'action', 'evaluations' ) as $field ) {
			if ( null !== $request->get_param( $field ) ) {
				$meta[ $field ] = $request->get_param( $field );
			}
		}

		return $meta;
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
