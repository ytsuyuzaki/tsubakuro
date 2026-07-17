<?php
/**
 * MCP (Model Context Protocol) endpoint.
 *
 * @package Tsubakuro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Streamable HTTP compatible MCP endpoint.
 */
class Tsubakuro_MCP {


	const ROUTE            = '/mcp';
	const SERVER_ID        = 'tsubakuro-server';
	const ABILITY_CATEGORY = 'tsubakuro';
	const PROTOCOL_VERSION = '2025-11-25';
	const SERVER_NAME      = 'tsubakuro-wordpress-mcp';

	/**
	 * Register WordPress hooks.
	 */
	public static function init() {
		add_action( 'wp_abilities_api_categories_init', array( __CLASS__, 'register_ability_category' ) );
		add_action( 'wp_abilities_api_init', array( __CLASS__, 'register_abilities' ) );
		add_action( 'mcp_adapter_init', array( __CLASS__, 'register_mcp_server' ) );
	}

	/**
	 * Register the ability category used by this plugin.
	 */
	public static function register_ability_category() {
		if ( ! function_exists( 'wp_register_ability_category' ) ) {
			return;
		}

		wp_register_ability_category(
			self::ABILITY_CATEGORY,
			array(
				'label'       => 'Tsubakuro',
				'description' => 'Tsubakuro task management tools',
			)
		);
	}

	/**
	 * Register Tsubakuro abilities exposed by mcp-adapter.
	 */
	public static function register_abilities() {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		self::register_ability_category();

		foreach ( self::get_ability_definitions() as $ability_name => $definition ) {
			if ( self::ability_exists( $ability_name ) ) {
				continue;
			}

			wp_register_ability( $ability_name, $definition );
		}
	}

	/**
	 * Register a custom MCP server via wordpress/mcp-adapter.
	 *
	 * @param mixed $adapter MCP adapter instance passed by mcp_adapter_init.
	 */
	public static function register_mcp_server( $adapter ) {
		if ( ! is_object( $adapter ) || ! method_exists( $adapter, 'create_server' ) ) {
			return;
		}

		self::register_ability_category();
		self::register_abilities();

		$tools               = array_keys( self::get_ability_definitions() );
		$error_handler_class = null;

		$transport_classes = array( '\\WP\\MCP\\Transport\\HttpTransport' );

		self::invoke_adapter_create_server( $adapter, $tools, $transport_classes, $error_handler_class );
	}

	/**
	 * Create adapter server while supporting multiple mcp-adapter signatures.
	 *
	 * @param object      $adapter             Adapter instance.
	 * @param array       $tools               Ability names to expose as tools.
	 * @param array       $transport_classes   Transport class names.
	 * @param string|null $error_handler_class Error handler class or null.
	 */
	private static function invoke_adapter_create_server( $adapter, $tools, $transport_classes, $error_handler_class ) {
		$server_id   = self::SERVER_ID;
		$namespace   = Tsubakuro_REST_API::NAMESPACE;
		$route       = ltrim( self::ROUTE, '/' );
		$server_name = 'Tsubakuro MCP Server';
		$description = 'Tsubakuro task management tools via MCP adapter';
		$server_ver  = TSUBAKURO_VERSION;
		$empty       = array();

		$signatures = array(
			array(
				$server_id,
				$namespace,
				$route,
				$server_name,
				$description,
				$server_ver,
				$transport_classes,
				$error_handler_class,
				null,
				$tools,
				$empty,
				$empty,
				null,
			),
			array(
				$server_id,
				$namespace,
				$route,
				$server_name,
				$description,
				$server_ver,
				$transport_classes,
				$error_handler_class,
				$tools,
				$empty,
				$empty,
			),
			array(
				$server_id,
				$namespace,
				$route,
				$server_name,
				$description,
				$server_ver,
				$transport_classes,
				$error_handler_class,
				$tools,
			),
		);

		foreach ( $signatures as $args ) {
			try {
				$result = call_user_func_array( array( $adapter, 'create_server' ), $args );

				if ( function_exists( 'is_wp_error' ) && is_wp_error( $result ) ) {
					continue;
				}

				return;
			} catch ( Throwable $e ) {
				continue;
			}
		}
	}

	/**
	 * Build all ability definitions for mcp-adapter tool registration.
	 *
	 * @return array
	 */
	private static function get_ability_definitions() {
		return array(
			'tsubakuro/list-tasks'                 => array(
				'label'               => 'Tsubakuro: List Tasks',
				'description'         => 'タスク一覧を取得します。',
				'category'            => self::ABILITY_CATEGORY,
				'input_schema'        => self::get_list_tasks_input_schema(),
				'execute_callback'    => array( __CLASS__, 'execute_list_tasks_ability' ),
				'permission_callback' => array( __CLASS__, 'can_use_mcp_tools' ),
				'meta'                => self::build_ability_meta( true, false, true ),
			),
			'tsubakuro/get-task'                   => array(
				'label'               => 'Tsubakuro: Get Task',
				'description'         => '指定 ID のタスク詳細を取得します。',
				'category'            => self::ABILITY_CATEGORY,
				'input_schema'        => self::get_single_id_input_schema(),
				'execute_callback'    => array( __CLASS__, 'execute_get_task_ability' ),
				'permission_callback' => array( __CLASS__, 'can_use_mcp_tools' ),
				'meta'                => self::build_ability_meta( true, false, true ),
			),
			'tsubakuro/create-task'                => array(
				'label'               => 'Tsubakuro: Create Task',
				'description'         => '新しいタスクを作成します。',
				'category'            => self::ABILITY_CATEGORY,
				'input_schema'        => self::get_create_task_input_schema(),
				'execute_callback'    => array( __CLASS__, 'execute_create_task_ability' ),
				'permission_callback' => array( __CLASS__, 'can_use_mcp_tools' ),
				'meta'                => self::build_ability_meta( false, false, false ),
			),
			'tsubakuro/update-task'                => array(
				'label'               => 'Tsubakuro: Update Task',
				'description'         => '既存タスクを更新します。',
				'category'            => self::ABILITY_CATEGORY,
				'input_schema'        => self::get_update_task_input_schema(),
				'execute_callback'    => array( __CLASS__, 'execute_update_task_ability' ),
				'permission_callback' => array( __CLASS__, 'can_use_mcp_tools' ),
				'meta'                => self::build_ability_meta( false, false, false ),
			),
			'tsubakuro/delete-task'                => array(
				'label'               => 'Tsubakuro: Delete Task',
				'description'         => '指定したタスクを削除します。',
				'category'            => self::ABILITY_CATEGORY,
				'input_schema'        => self::get_single_id_input_schema(),
				'execute_callback'    => array( __CLASS__, 'execute_delete_task_ability' ),
				'permission_callback' => array( __CLASS__, 'can_delete_mcp_tasks' ),
				'meta'                => self::build_ability_meta( false, true, true ),
			),
			'tsubakuro/add-comment'                => array(
				'label'               => 'Tsubakuro: Add Comment',
				'description'         => '指定したタスクにコメントを追加します。',
				'category'            => self::ABILITY_CATEGORY,
				'input_schema'        => self::get_add_comment_input_schema(),
				'execute_callback'    => array( __CLASS__, 'execute_add_comment_ability' ),
				'permission_callback' => array( __CLASS__, 'can_use_mcp_tools' ),
				'meta'                => self::build_ability_meta( false, false, false ),
			),
			'tsubakuro/list-evaluations'           => array(
				'label'               => 'Tsubakuro: List Evaluations',
				'description'         => '記事評価（変更タスク）一覧を取得します。',
				'category'            => self::ABILITY_CATEGORY,
				'input_schema'        => self::get_list_evaluations_input_schema(),
				'execute_callback'    => array( __CLASS__, 'execute_list_evaluations_ability' ),
				'permission_callback' => array( __CLASS__, 'can_use_mcp_tools' ),
				'meta'                => self::build_ability_meta( true, false, true ),
			),
			'tsubakuro/get-evaluation'             => array(
				'label'               => 'Tsubakuro: Get Evaluation',
				'description'         => '指定 ID の記事評価詳細を取得します。',
				'category'            => self::ABILITY_CATEGORY,
				'input_schema'        => self::get_single_id_input_schema(),
				'execute_callback'    => array( __CLASS__, 'execute_get_evaluation_ability' ),
				'permission_callback' => array( __CLASS__, 'can_use_mcp_tools' ),
				'meta'                => self::build_ability_meta( true, false, true ),
			),
			'tsubakuro/create-evaluation'          => array(
				'label'               => 'Tsubakuro: Create Evaluation',
				'description'         => '新しい記事評価を作成します。',
				'category'            => self::ABILITY_CATEGORY,
				'input_schema'        => self::get_create_evaluation_input_schema(),
				'execute_callback'    => array( __CLASS__, 'execute_create_evaluation_ability' ),
				'permission_callback' => array( __CLASS__, 'can_use_mcp_tools' ),
				'meta'                => self::build_ability_meta( false, false, false ),
			),
			'tsubakuro/update-evaluation'          => array(
				'label'               => 'Tsubakuro: Update Evaluation',
				'description'         => '既存の記事評価を更新します。',
				'category'            => self::ABILITY_CATEGORY,
				'input_schema'        => self::get_update_evaluation_input_schema(),
				'execute_callback'    => array( __CLASS__, 'execute_update_evaluation_ability' ),
				'permission_callback' => array( __CLASS__, 'can_use_mcp_tools' ),
				'meta'                => self::build_ability_meta( false, false, false ),
			),
			'tsubakuro/delete-evaluation'          => array(
				'label'               => 'Tsubakuro: Delete Evaluation',
				'description'         => '指定した記事評価を削除します。',
				'category'            => self::ABILITY_CATEGORY,
				'input_schema'        => self::get_single_id_input_schema(),
				'execute_callback'    => array( __CLASS__, 'execute_delete_evaluation_ability' ),
				'permission_callback' => array( __CLASS__, 'can_delete_mcp_tasks' ),
				'meta'                => self::build_ability_meta( false, true, true ),
			),
			'tsubakuro/list-insights'              => array(
				'label'               => 'Tsubakuro: List Insights',
				'description'         => 'サイト単位の改善知見一覧を取得します。',
				'category'            => self::ABILITY_CATEGORY,
				'input_schema'        => self::get_list_insights_input_schema(),
				'execute_callback'    => array( __CLASS__, 'execute_list_insights_ability' ),
				'permission_callback' => array( __CLASS__, 'can_use_mcp_tools' ),
				'meta'                => self::build_ability_meta( true, false, true ),
			),
			'tsubakuro/get-insight'                => array(
				'label'               => 'Tsubakuro: Get Insight',
				'description'         => '指定 ID の改善知見詳細を取得します。',
				'category'            => self::ABILITY_CATEGORY,
				'input_schema'        => self::get_single_id_input_schema(),
				'execute_callback'    => array( __CLASS__, 'execute_get_insight_ability' ),
				'permission_callback' => array( __CLASS__, 'can_use_mcp_tools' ),
				'meta'                => self::build_ability_meta( true, false, true ),
			),
			'tsubakuro/create-insight'             => array(
				'label'               => 'Tsubakuro: Create Insight',
				'description'         => '新しい改善知見を作成します。',
				'category'            => self::ABILITY_CATEGORY,
				'input_schema'        => self::get_create_insight_input_schema(),
				'execute_callback'    => array( __CLASS__, 'execute_create_insight_ability' ),
				'permission_callback' => array( __CLASS__, 'can_use_mcp_tools' ),
				'meta'                => self::build_ability_meta( false, false, false ),
			),
			'tsubakuro/update-insight'             => array(
				'label'               => 'Tsubakuro: Update Insight',
				'description'         => '既存の改善知見を更新します。',
				'category'            => self::ABILITY_CATEGORY,
				'input_schema'        => self::get_update_insight_input_schema(),
				'execute_callback'    => array( __CLASS__, 'execute_update_insight_ability' ),
				'permission_callback' => array( __CLASS__, 'can_use_mcp_tools' ),
				'meta'                => self::build_ability_meta( false, false, false ),
			),
			'tsubakuro/delete-insight'             => array(
				'label'               => 'Tsubakuro: Delete Insight',
				'description'         => '指定した改善知見を削除します。',
				'category'            => self::ABILITY_CATEGORY,
				'input_schema'        => self::get_single_id_input_schema(),
				'execute_callback'    => array( __CLASS__, 'execute_delete_insight_ability' ),
				'permission_callback' => array( __CLASS__, 'can_delete_mcp_tasks' ),
				'meta'                => self::build_ability_meta( false, true, true ),
			),
			'tsubakuro/link-evaluation-to-insight' => array(
				'label'               => 'Tsubakuro: Link Evaluation To Insight',
				'description'         => '記事評価を改善知見の根拠として関連付けます。',
				'category'            => self::ABILITY_CATEGORY,
				'input_schema'        => self::get_link_evaluation_input_schema(),
				'execute_callback'    => array( __CLASS__, 'execute_link_evaluation_ability' ),
				'permission_callback' => array( __CLASS__, 'can_use_mcp_tools' ),
				'meta'                => self::build_ability_meta( false, false, true ),
			),
		);
	}

	/**
	 * Build standard ability metadata for MCP exposure.
	 *
	 * @param bool $is_readonly Whether the tool is read-only.
	 * @param bool $destructive Whether the tool is destructive.
	 * @param bool $idempotent  Whether the tool is idempotent.
	 * @return array
	 */
	private static function build_ability_meta( $is_readonly, $destructive, $idempotent ) {
		return array(
			'mcp'         => array(
				'public' => true,
				'type'   => 'tool',
			),
			'annotations' => array(
				'readonly'    => (bool) $is_readonly,
				'destructive' => (bool) $destructive,
				'idempotent'  => (bool) $idempotent,
			),
		);
	}

	/**
	 * Determine whether an ability already exists.
	 *
	 * @param string $ability_name Ability name.
	 * @return bool
	 */
	private static function ability_exists( $ability_name ) {
		if ( function_exists( 'wp_has_ability' ) ) {
			return (bool) wp_has_ability( $ability_name );
		}

		if ( function_exists( 'wp_get_ability' ) ) {
			return null !== wp_get_ability( $ability_name );
		}

		return false;
	}

	/**
	 * Permission callback for read/write MCP tools.
	 *
	 * @return bool
	 */
	public static function can_use_mcp_tools() {
		return current_user_can( 'edit_posts' );
	}

	/**
	 * Permission callback for destructive MCP tools.
	 *
	 * @return bool
	 */
	public static function can_delete_mcp_tasks() {
		return current_user_can( 'delete_posts' );
	}

	/**
	 * Ability callback: list tasks.
	 *
	 * @param mixed $input Ability input.
	 * @return array
	 */
	public static function execute_list_tasks_ability( $input = array() ) {
		$arguments = is_array( $input ) ? $input : array();
		$args      = array();

		if ( ! empty( $arguments['status'] ) ) {
			$args['status'] = sanitize_text_field( $arguments['status'] );
		}

		if ( ! empty( $arguments['priority'] ) ) {
			$args['priority'] = sanitize_text_field( $arguments['priority'] );
		}

		if ( ! empty( $arguments['assignee'] ) ) {
			$args['assignee'] = absint( $arguments['assignee'] );
		}

		if ( ! empty( $arguments['related_page'] ) ) {
			$args['related_page'] = absint( $arguments['related_page'] );
		}

		if ( ! empty( $arguments['per_page'] ) ) {
			$args['posts_per_page'] = min( 100, max( 1, absint( $arguments['per_page'] ) ) );
		}

		if ( isset( $arguments['parent_id'] ) ) {
			$args['parent_id'] = absint( $arguments['parent_id'] );
		}

		foreach ( array( 's', 'orderby', 'order' ) as $key ) {
			if ( ! empty( $arguments[ $key ] ) ) {
				$args[ $key ] = sanitize_text_field( $arguments[ $key ] );
			}
		}

		return array(
			'tasks' => Tsubakuro_Post_Types::get_tasks( $args ),
		);
	}

	/**
	 * Ability callback: get single task.
	 *
	 * @param mixed $input Ability input.
	 * @return array|WP_Error
	 */
	public static function execute_get_task_ability( $input = array() ) {
		$arguments = is_array( $input ) ? $input : array();

		if ( empty( $arguments['id'] ) ) {
			return new WP_Error( 'invalid_input', 'id is required' );
		}

		$task = Tsubakuro_Post_Types::get_task( absint( $arguments['id'] ) );
		if ( ! $task ) {
			return new WP_Error( 'not_found', 'Task not found' );
		}

		$task['comments'] = Tsubakuro_Admin::get_task_comments( $task['id'] );

		return array(
			'task' => $task,
		);
	}

	/**
	 * Ability callback: create task.
	 *
	 * @param mixed $input Ability input.
	 * @return array|WP_Error
	 */
	public static function execute_create_task_ability( $input = array() ) {
		$arguments = is_array( $input ) ? $input : array();

		if ( empty( $arguments['title'] ) ) {
			return new WP_Error( 'invalid_input', 'title is required' );
		}

		$task_id = wp_insert_post(
			array(
				'post_type'    => Tsubakuro_Post_Types::TASK_POST_TYPE,
				'post_title'   => sanitize_text_field( $arguments['title'] ),
				'post_content' => wp_kses_post( $arguments['content'] ?? '' ),
				'post_status'  => 'publish',
				'post_parent'  => absint( $arguments['parent_id'] ?? 0 ),
			),
			true
		);

		if ( is_wp_error( $task_id ) ) {
			return $task_id;
		}

		if ( empty( $arguments['status'] ) || ! is_string( $arguments['status'] ) || ! array_key_exists( $arguments['status'], Tsubakuro_Post_Types::STATUSES ) ) {
			$arguments['status'] = 'todo';
		}

		Tsubakuro_Post_Types::save_meta( $task_id, $arguments );

		return array(
			'task' => Tsubakuro_Post_Types::get_task( $task_id ),
		);
	}

	/**
	 * Ability callback: update task.
	 *
	 * @param mixed $input Ability input.
	 * @return array|WP_Error
	 */
	public static function execute_update_task_ability( $input = array() ) {
		$arguments = is_array( $input ) ? $input : array();

		if ( empty( $arguments['id'] ) ) {
			return new WP_Error( 'invalid_input', 'id is required' );
		}

		$task_id = absint( $arguments['id'] );
		$task    = Tsubakuro_Post_Types::get_task( $task_id );

		if ( ! $task ) {
			return new WP_Error( 'not_found', 'Task not found' );
		}

		$update = array( 'ID' => $task_id );

		if ( isset( $arguments['title'] ) ) {
			$update['post_title'] = sanitize_text_field( $arguments['title'] );
		}

		if ( isset( $arguments['content'] ) ) {
			$update['post_content'] = wp_kses_post( $arguments['content'] );
		}

		if ( isset( $arguments['parent_id'] ) ) {
			$update['post_parent'] = absint( $arguments['parent_id'] );
		}

		wp_update_post( $update );
		Tsubakuro_Post_Types::save_meta( $task_id, $arguments );

		return array(
			'task' => Tsubakuro_Post_Types::get_task( $task_id ),
		);
	}

	/**
	 * Ability callback: delete task.
	 *
	 * @param mixed $input Ability input.
	 * @return array|WP_Error
	 */
	public static function execute_delete_task_ability( $input = array() ) {
		$arguments = is_array( $input ) ? $input : array();

		if ( empty( $arguments['id'] ) ) {
			return new WP_Error( 'invalid_input', 'id is required' );
		}

		if ( ! current_user_can( 'delete_posts' ) ) {
			return new WP_Error( 'forbidden', 'Permission denied' );
		}

		$task_id = absint( $arguments['id'] );
		$task    = Tsubakuro_Post_Types::get_task( $task_id );

		if ( ! $task ) {
			return new WP_Error( 'not_found', 'Task not found' );
		}

		wp_delete_post( $task_id, true );

		return array(
			'deleted' => true,
			'id'      => $task_id,
		);
	}

	/**
	 * Ability callback: add comment.
	 *
	 * @param mixed $input Ability input.
	 * @return array|WP_Error
	 */
	public static function execute_add_comment_ability( $input = array() ) {
		$arguments = is_array( $input ) ? $input : array();

		if ( empty( $arguments['id'] ) || empty( $arguments['comment'] ) ) {
			return new WP_Error( 'invalid_input', 'id and comment are required' );
		}

		$task_id = absint( $arguments['id'] );
		$task    = Tsubakuro_Post_Types::get_task( $task_id );

		if ( ! $task ) {
			return new WP_Error( 'not_found', 'Task not found' );
		}

		$comment_id = Tsubakuro_Admin::insert_comment(
			$task_id,
			get_current_user_id(),
			sanitize_textarea_field( $arguments['comment'] )
		);

		if ( false === $comment_id ) {
			return new WP_Error( 'insert_failed', 'Failed to insert comment' );
		}

		return array(
			'comment' => Tsubakuro_Admin::get_comment( $comment_id ),
		);
	}

	/**
	 * Input schema for list-task ability.
	 *
	 * @return array
	 */
	private static function get_list_tasks_input_schema() {
		return array(
			'type'       => 'object',
			'properties' => array(
				'status'       => array( 'type' => 'string' ),
				'priority'     => array( 'type' => 'string' ),
				'assignee'     => array( 'type' => 'integer' ),
				'related_page' => array( 'type' => 'integer' ),
				'parent_id'    => array( 'type' => 'integer' ),
				'per_page'     => array( 'type' => 'integer' ),
				's'            => array( 'type' => 'string' ),
				'orderby'      => array( 'type' => 'string' ),
				'order'        => array( 'type' => 'string' ),
			),
		);
	}

	/**
	 * Input schema with required id.
	 *
	 * @return array
	 */
	private static function get_single_id_input_schema() {
		return array(
			'type'       => 'object',
			'required'   => array( 'id' ),
			'properties' => array(
				'id' => array( 'type' => 'integer' ),
			),
		);
	}

	/**
	 * Input schema for create-task ability.
	 *
	 * @return array
	 */
	private static function get_create_task_input_schema() {
		return array(
			'type'       => 'object',
			'required'   => array( 'title' ),
			'properties' => array(
				'title'           => array( 'type' => 'string' ),
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
		);
	}

	/**
	 * Input schema for update-task ability.
	 *
	 * @return array
	 */
	private static function get_update_task_input_schema() {
		return array(
			'type'       => 'object',
			'required'   => array( 'id' ),
			'properties' => array(
				'id'              => array( 'type' => 'integer' ),
				'title'           => array( 'type' => 'string' ),
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
		);
	}

	/**
	 * Input schema for add-comment ability.
	 *
	 * @return array
	 */
	private static function get_add_comment_input_schema() {
		return array(
			'type'       => 'object',
			'required'   => array( 'id', 'comment' ),
			'properties' => array(
				'id'      => array( 'type' => 'integer' ),
				'comment' => array( 'type' => 'string' ),
			),
		);
	}

	/**
	 * Input schema for list-evaluations ability.
	 *
	 * @return array
	 */
	private static function get_list_evaluations_input_schema() {
		return array(
			'type'       => 'object',
			'properties' => array(
				'target_post' => array( 'type' => 'integer' ),
				'change_item' => array( 'type' => 'string' ),
				'judgment'    => array( 'type' => 'string' ),
				'metric'      => array( 'type' => 'string' ),
				'unevaluated' => array( 'type' => 'boolean' ),
				'overdue'     => array( 'type' => 'boolean' ),
				's'           => array( 'type' => 'string' ),
				'per_page'    => array( 'type' => 'integer' ),
			),
		);
	}

	/**
	 * Properties shared by evaluation create/update schemas.
	 *
	 * @return array
	 */
	private static function get_evaluation_field_properties() {
		return array(
			'title'          => array( 'type' => 'string' ),
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
	}

	/**
	 * Input schema for create-evaluation ability.
	 *
	 * @return array
	 */
	private static function get_create_evaluation_input_schema() {
		return array(
			'type'       => 'object',
			'required'   => array( 'title' ),
			'properties' => self::get_evaluation_field_properties(),
		);
	}

	/**
	 * Input schema for update-evaluation ability.
	 *
	 * @return array
	 */
	private static function get_update_evaluation_input_schema() {
		return array(
			'type'       => 'object',
			'required'   => array( 'id' ),
			'properties' => array_merge(
				array( 'id' => array( 'type' => 'integer' ) ),
				self::get_evaluation_field_properties()
			),
		);
	}

	/**
	 * Input schema for list-insights ability.
	 *
	 * @return array
	 */
	private static function get_list_insights_input_schema() {
		return array(
			'type'       => 'object',
			'properties' => array(
				'status'     => array( 'type' => 'string' ),
				'action'     => array( 'type' => 'string' ),
				'evaluation' => array( 'type' => 'integer' ),
				's'          => array( 'type' => 'string' ),
				'per_page'   => array( 'type' => 'integer' ),
			),
		);
	}

	/**
	 * Properties shared by insight create/update schemas.
	 *
	 * @return array
	 */
	private static function get_insight_field_properties() {
		return array(
			'title'         => array( 'type' => 'string' ),
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
	}

	/**
	 * Input schema for create-insight ability.
	 *
	 * @return array
	 */
	private static function get_create_insight_input_schema() {
		return array(
			'type'       => 'object',
			'required'   => array( 'title' ),
			'properties' => self::get_insight_field_properties(),
		);
	}

	/**
	 * Input schema for update-insight ability.
	 *
	 * @return array
	 */
	private static function get_update_insight_input_schema() {
		return array(
			'type'       => 'object',
			'required'   => array( 'id' ),
			'properties' => array_merge(
				array( 'id' => array( 'type' => 'integer' ) ),
				self::get_insight_field_properties()
			),
		);
	}

	/**
	 * Input schema for link-evaluation-to-insight ability.
	 *
	 * @return array
	 */
	private static function get_link_evaluation_input_schema() {
		return array(
			'type'       => 'object',
			'required'   => array( 'insight_id', 'evaluation_id' ),
			'properties' => array(
				'insight_id'    => array( 'type' => 'integer' ),
				'evaluation_id' => array( 'type' => 'integer' ),
			),
		);
	}

	// -------------------------------------------------------------------------
	// Shared evaluation/insight operations (used by both ability and JSON-RPC surfaces)
	// -------------------------------------------------------------------------

	/**
	 * Build evaluation list query args from raw tool arguments.
	 *
	 * @param array $arguments Raw arguments.
	 * @return array|WP_Error
	 */
	private static function op_list_evaluations( $arguments ) {
		$args = array();

		if ( ! empty( $arguments['target_post'] ) ) {
			$args['target_post'] = absint( $arguments['target_post'] );
		}

		foreach ( array( 'change_item', 'judgment', 'metric', 's' ) as $key ) {
			if ( ! empty( $arguments[ $key ] ) ) {
				$args[ $key ] = sanitize_text_field( $arguments[ $key ] );
			}
		}

		if ( ! empty( $arguments['unevaluated'] ) ) {
			$args['unevaluated'] = true;
		}

		if ( ! empty( $arguments['overdue'] ) ) {
			$args['overdue'] = true;
		}

		if ( ! empty( $arguments['per_page'] ) ) {
			$args['posts_per_page'] = min( 100, max( 1, absint( $arguments['per_page'] ) ) );
		}

		return array(
			'evaluations' => Tsubakuro_Evaluations::get_evaluations( $args ),
		);
	}

	/**
	 * Fetch a single evaluation with its linked insights.
	 *
	 * @param array $arguments Raw arguments.
	 * @return array|WP_Error
	 */
	private static function op_get_evaluation( $arguments ) {
		if ( empty( $arguments['id'] ) ) {
			return new WP_Error( 'invalid_input', 'id is required' );
		}

		$evaluation = Tsubakuro_Evaluations::get_evaluation( absint( $arguments['id'] ) );
		if ( ! $evaluation ) {
			return new WP_Error( 'not_found', 'Evaluation not found' );
		}

		$evaluation['insights'] = Tsubakuro_Insights::get_insights_for_evaluation( $evaluation['id'] );

		return array(
			'evaluation' => $evaluation,
		);
	}

	/**
	 * Create an evaluation.
	 *
	 * @param array $arguments Raw arguments.
	 * @return array|WP_Error
	 */
	private static function op_create_evaluation( $arguments ) {
		if ( empty( $arguments['title'] ) ) {
			return new WP_Error( 'invalid_input', 'title is required' );
		}

		$eval_id = wp_insert_post(
			array(
				'post_type'    => Tsubakuro_Evaluations::POST_TYPE,
				'post_title'   => sanitize_text_field( $arguments['title'] ),
				'post_content' => wp_kses_post( $arguments['change_detail'] ?? '' ),
				'post_status'  => 'publish',
			),
			true
		);

		if ( is_wp_error( $eval_id ) ) {
			return $eval_id;
		}

		Tsubakuro_Evaluations::save_meta( $eval_id, $arguments );

		return array(
			'evaluation' => Tsubakuro_Evaluations::get_evaluation( $eval_id ),
		);
	}

	/**
	 * Update an evaluation.
	 *
	 * @param array $arguments Raw arguments.
	 * @return array|WP_Error
	 */
	private static function op_update_evaluation( $arguments ) {
		if ( empty( $arguments['id'] ) ) {
			return new WP_Error( 'invalid_input', 'id is required' );
		}

		$eval_id = absint( $arguments['id'] );
		if ( ! Tsubakuro_Evaluations::get_evaluation( $eval_id ) ) {
			return new WP_Error( 'not_found', 'Evaluation not found' );
		}

		$update = array( 'ID' => $eval_id );
		if ( isset( $arguments['title'] ) ) {
			$update['post_title'] = sanitize_text_field( $arguments['title'] );
		}
		if ( isset( $arguments['change_detail'] ) ) {
			$update['post_content'] = wp_kses_post( $arguments['change_detail'] );
		}
		wp_update_post( $update );

		Tsubakuro_Evaluations::save_meta( $eval_id, $arguments );

		return array(
			'evaluation' => Tsubakuro_Evaluations::get_evaluation( $eval_id ),
		);
	}

	/**
	 * Delete an evaluation.
	 *
	 * @param array $arguments Raw arguments.
	 * @return array|WP_Error
	 */
	private static function op_delete_evaluation( $arguments ) {
		if ( empty( $arguments['id'] ) ) {
			return new WP_Error( 'invalid_input', 'id is required' );
		}

		if ( ! current_user_can( 'delete_posts' ) ) {
			return new WP_Error( 'forbidden', 'Permission denied' );
		}

		$eval_id = absint( $arguments['id'] );
		if ( ! Tsubakuro_Evaluations::get_evaluation( $eval_id ) ) {
			return new WP_Error( 'not_found', 'Evaluation not found' );
		}

		wp_delete_post( $eval_id, true );

		return array(
			'deleted' => true,
			'id'      => $eval_id,
		);
	}

	/**
	 * Build insight list query args from raw tool arguments.
	 *
	 * @param array $arguments Raw arguments.
	 * @return array|WP_Error
	 */
	private static function op_list_insights( $arguments ) {
		$args = array();

		foreach ( array( 'status', 'action', 's' ) as $key ) {
			if ( ! empty( $arguments[ $key ] ) ) {
				$args[ $key ] = sanitize_text_field( $arguments[ $key ] );
			}
		}

		if ( ! empty( $arguments['evaluation'] ) ) {
			$args['evaluation'] = absint( $arguments['evaluation'] );
		}

		if ( ! empty( $arguments['per_page'] ) ) {
			$args['posts_per_page'] = min( 100, max( 1, absint( $arguments['per_page'] ) ) );
		}

		return array(
			'insights' => Tsubakuro_Insights::get_insights( $args ),
		);
	}

	/**
	 * Fetch a single insight with its linked evaluations.
	 *
	 * @param array $arguments Raw arguments.
	 * @return array|WP_Error
	 */
	private static function op_get_insight( $arguments ) {
		if ( empty( $arguments['id'] ) ) {
			return new WP_Error( 'invalid_input', 'id is required' );
		}

		$insight = Tsubakuro_Insights::get_insight( absint( $arguments['id'] ) );
		if ( ! $insight ) {
			return new WP_Error( 'not_found', 'Insight not found' );
		}

		$insight['evaluations'] = array();
		foreach ( $insight['evaluation_ids'] as $linked_id ) {
			$linked = Tsubakuro_Evaluations::get_evaluation( $linked_id );
			if ( $linked ) {
				$insight['evaluations'][] = $linked;
			}
		}

		return array(
			'insight' => $insight,
		);
	}

	/**
	 * Create an insight.
	 *
	 * @param array $arguments Raw arguments.
	 * @return array|WP_Error
	 */
	private static function op_create_insight( $arguments ) {
		if ( empty( $arguments['title'] ) ) {
			return new WP_Error( 'invalid_input', 'title is required' );
		}

		$insight_id = wp_insert_post(
			array(
				'post_type'   => Tsubakuro_Insights::POST_TYPE,
				'post_title'  => sanitize_text_field( $arguments['title'] ),
				'post_status' => 'publish',
			),
			true
		);

		if ( is_wp_error( $insight_id ) ) {
			return $insight_id;
		}

		Tsubakuro_Insights::save_meta( $insight_id, $arguments );

		return array(
			'insight' => Tsubakuro_Insights::get_insight( $insight_id ),
		);
	}

	/**
	 * Update an insight.
	 *
	 * @param array $arguments Raw arguments.
	 * @return array|WP_Error
	 */
	private static function op_update_insight( $arguments ) {
		if ( empty( $arguments['id'] ) ) {
			return new WP_Error( 'invalid_input', 'id is required' );
		}

		$insight_id = absint( $arguments['id'] );
		if ( ! Tsubakuro_Insights::get_insight( $insight_id ) ) {
			return new WP_Error( 'not_found', 'Insight not found' );
		}

		if ( isset( $arguments['title'] ) ) {
			wp_update_post(
				array(
					'ID'         => $insight_id,
					'post_title' => sanitize_text_field( $arguments['title'] ),
				)
			);
		}

		Tsubakuro_Insights::save_meta( $insight_id, $arguments );

		return array(
			'insight' => Tsubakuro_Insights::get_insight( $insight_id ),
		);
	}

	/**
	 * Delete an insight.
	 *
	 * @param array $arguments Raw arguments.
	 * @return array|WP_Error
	 */
	private static function op_delete_insight( $arguments ) {
		if ( empty( $arguments['id'] ) ) {
			return new WP_Error( 'invalid_input', 'id is required' );
		}

		if ( ! current_user_can( 'delete_posts' ) ) {
			return new WP_Error( 'forbidden', 'Permission denied' );
		}

		$insight_id = absint( $arguments['id'] );
		if ( ! Tsubakuro_Insights::get_insight( $insight_id ) ) {
			return new WP_Error( 'not_found', 'Insight not found' );
		}

		wp_delete_post( $insight_id, true );

		return array(
			'deleted' => true,
			'id'      => $insight_id,
		);
	}

	/**
	 * Link an evaluation to an insight as supporting evidence.
	 *
	 * @param array $arguments Raw arguments.
	 * @return array|WP_Error
	 */
	private static function op_link_evaluation( $arguments ) {
		if ( empty( $arguments['insight_id'] ) || empty( $arguments['evaluation_id'] ) ) {
			return new WP_Error( 'invalid_input', 'insight_id and evaluation_id are required' );
		}

		$insight_id = absint( $arguments['insight_id'] );
		$eval_id    = absint( $arguments['evaluation_id'] );

		$insight = Tsubakuro_Insights::get_insight( $insight_id );
		if ( ! $insight ) {
			return new WP_Error( 'not_found', 'Insight not found' );
		}

		if ( ! Tsubakuro_Evaluations::get_evaluation( $eval_id ) ) {
			return new WP_Error( 'not_found', 'Evaluation not found' );
		}

		$linked = $insight['evaluation_ids'];
		if ( ! in_array( $eval_id, $linked, true ) ) {
			$linked[] = $eval_id;
		}
		Tsubakuro_Insights::save_linked_evaluations( $insight_id, $linked );

		return array(
			'insight' => Tsubakuro_Insights::get_insight( $insight_id ),
		);
	}

	// -------------------------------------------------------------------------
	// Ability callbacks (evaluations/insights) – return array|WP_Error
	// -------------------------------------------------------------------------

	/**
	 * Ability callback: list evaluations.
	 *
	 * @param mixed $input Ability input.
	 * @return array|WP_Error
	 */
	public static function execute_list_evaluations_ability( $input = array() ) {
		return self::op_list_evaluations( is_array( $input ) ? $input : array() );
	}

	/**
	 * Ability callback: get evaluation.
	 *
	 * @param mixed $input Ability input.
	 * @return array|WP_Error
	 */
	public static function execute_get_evaluation_ability( $input = array() ) {
		return self::op_get_evaluation( is_array( $input ) ? $input : array() );
	}

	/**
	 * Ability callback: create evaluation.
	 *
	 * @param mixed $input Ability input.
	 * @return array|WP_Error
	 */
	public static function execute_create_evaluation_ability( $input = array() ) {
		return self::op_create_evaluation( is_array( $input ) ? $input : array() );
	}

	/**
	 * Ability callback: update evaluation.
	 *
	 * @param mixed $input Ability input.
	 * @return array|WP_Error
	 */
	public static function execute_update_evaluation_ability( $input = array() ) {
		return self::op_update_evaluation( is_array( $input ) ? $input : array() );
	}

	/**
	 * Ability callback: delete evaluation.
	 *
	 * @param mixed $input Ability input.
	 * @return array|WP_Error
	 */
	public static function execute_delete_evaluation_ability( $input = array() ) {
		return self::op_delete_evaluation( is_array( $input ) ? $input : array() );
	}

	/**
	 * Ability callback: list insights.
	 *
	 * @param mixed $input Ability input.
	 * @return array|WP_Error
	 */
	public static function execute_list_insights_ability( $input = array() ) {
		return self::op_list_insights( is_array( $input ) ? $input : array() );
	}

	/**
	 * Ability callback: get insight.
	 *
	 * @param mixed $input Ability input.
	 * @return array|WP_Error
	 */
	public static function execute_get_insight_ability( $input = array() ) {
		return self::op_get_insight( is_array( $input ) ? $input : array() );
	}

	/**
	 * Ability callback: create insight.
	 *
	 * @param mixed $input Ability input.
	 * @return array|WP_Error
	 */
	public static function execute_create_insight_ability( $input = array() ) {
		return self::op_create_insight( is_array( $input ) ? $input : array() );
	}

	/**
	 * Ability callback: update insight.
	 *
	 * @param mixed $input Ability input.
	 * @return array|WP_Error
	 */
	public static function execute_update_insight_ability( $input = array() ) {
		return self::op_update_insight( is_array( $input ) ? $input : array() );
	}

	/**
	 * Ability callback: delete insight.
	 *
	 * @param mixed $input Ability input.
	 * @return array|WP_Error
	 */
	public static function execute_delete_insight_ability( $input = array() ) {
		return self::op_delete_insight( is_array( $input ) ? $input : array() );
	}

	/**
	 * Ability callback: link evaluation to insight.
	 *
	 * @param mixed $input Ability input.
	 * @return array|WP_Error
	 */
	public static function execute_link_evaluation_ability( $input = array() ) {
		return self::op_link_evaluation( is_array( $input ) ? $input : array() );
	}

	/**
	 * Register the MCP REST route.
	 */
	public static function register_routes() {
		register_rest_route(
			Tsubakuro_REST_API::NAMESPACE,
			self::ROUTE,
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( __CLASS__, 'handle_get' ),
					'permission_callback' => '__return_true',
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( __CLASS__, 'handle_jsonrpc' ),
					'permission_callback' => '__return_true',
				),
				array(
					'methods'             => 'OPTIONS',
					'callback'            => array( __CLASS__, 'handle_options' ),
					'permission_callback' => '__return_true',
				),
			)
		);
	}

	/**
	 * Handle GET /mcp.
	 *
	 * MCP Streamable HTTP uses POST for JSON-RPC messages. Some clients open
	 * GET as an optional SSE stream and try to decode any response as MCP
	 * messages, so do not return a non-JSON-RPC manifest here.
	 *
	 * @return WP_REST_Response|array
	 */
	public static function handle_get() {
		if ( ! self::check_permission() ) {
			return self::jsonrpc_response( self::error_response( null, -32001, 'Unauthorized' ), 401 );
		}

		return self::jsonrpc_response( self::error_response( null, -32000, 'SSE stream is not available for this MCP endpoint. Use POST with JSON-RPC 2.0.' ), 405 );
	}

	/**
	 * Handle OPTIONS /mcp.
	 *
	 * @return WP_REST_Response|array
	 */
	public static function handle_options() {
		return self::json_response(
			array(
				'ok' => true,
			),
			204
		);
	}

	/**
	 * Return a small endpoint description for GET requests and admin docs.
	 *
	 * @return array
	 */
	public static function get_manifest() {
		return array(
			'protocolVersion' => self::PROTOCOL_VERSION,
			'transport'       => 'streamable-http',
			'endpoint'        => rest_url( Tsubakuro_REST_API::NAMESPACE . self::ROUTE ),
			'serverInfo'      => self::get_server_info(),
			'capabilities'    => self::get_capabilities(),
		);
	}

	/**
	 * Handle POST /mcp.
	 *
	 * @param WP_REST_Request $request REST request object.
	 * @return WP_REST_Response|array
	 */
	public static function handle_jsonrpc( $request ) {
		if ( ! self::check_permission() ) {
			return self::jsonrpc_response( self::error_response( null, -32001, 'Unauthorized' ), 401 );
		}

		$body = $request->get_json_params();

		if ( null === $body || '' === $body ) {
			return self::jsonrpc_response( self::error_response( null, -32700, 'Parse error' ), 400 );
		}

		if ( self::is_list( $body ) ) {
			return self::jsonrpc_response( self::error_response( null, -32600, 'Invalid Request' ), 400 );
		}

		if ( self::is_jsonrpc_response_message( $body ) ) {
			return self::empty_response();
		}

		$protocol_validation_error = self::validate_protocol_version_header( $request, $body );
		if ( null !== $protocol_validation_error ) {
			return self::jsonrpc_response( $protocol_validation_error, 400 );
		}

		$response = self::dispatch( $body );
		if ( null === $response ) {
			return self::empty_response();
		}

		return self::jsonrpc_response( $response );
	}

	/**
	 * Validate the MCP-Protocol-Version header for non-initialize requests.
	 *
	 * @param WP_REST_Request $request REST request object.
	 * @param mixed           $body    Decoded request body.
	 * @return array|null Error response array, or null when validation passes.
	 */
	private static function validate_protocol_version_header( $request, $body ) {
		$method = is_array( $body ) ? (string) ( $body['method'] ?? '' ) : '';
		if ( ! is_array( $body ) || 'initialize' === $method ) {
			return null;
		}

		$header_version = self::get_mcp_protocol_version_header( $request );
		if ( '' === $header_version || self::PROTOCOL_VERSION === $header_version ) {
			return null;
		}

		$id = self::is_valid_request_id( $body['id'] ?? null ) ? $body['id'] : null;

		return self::error_response(
			$id,
			-32600,
			sprintf(
				'Bad Request: Unsupported protocol version: %s (supported versions: %s)',
				$header_version,
				self::PROTOCOL_VERSION
			)
		);
	}

	/**
	 * Get MCP-Protocol-Version header from request/server variables.
	 *
	 * @param WP_REST_Request $request REST request object.
	 * @return string Header value, or empty string when missing.
	 */
	private static function get_mcp_protocol_version_header( $request ) {
		if ( is_object( $request ) && method_exists( $request, 'get_header' ) ) {
			return trim( (string) $request->get_header( 'MCP-Protocol-Version' ) );
		}

		if ( ! empty( $_SERVER['HTTP_MCP_PROTOCOL_VERSION'] ) ) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Value is compared against an allow-listed protocol version.
			return trim( wp_unslash( (string) $_SERVER['HTTP_MCP_PROTOCOL_VERSION'] ) );
		}

		return '';
	}

	/**
	 * Route a single JSON-RPC call.
	 *
	 * @param mixed $rpc Decoded JSON-RPC call object.
	 * @return array|null JSON-RPC response array, or null for notifications.
	 */
	private static function dispatch( $rpc ) {
		if ( ! is_array( $rpc ) ) {
			return self::error_response( null, -32600, 'Invalid Request' );
		}

		$id               = $rpc['id'] ?? null;
		$is_notification  = ! array_key_exists( 'id', $rpc );
		$method           = $rpc['method'] ?? null;
		$params           = $rpc['params'] ?? array();
		$invalid_id       = ! $is_notification && ! self::is_valid_request_id( $id );
		$invalid_envelope = ( $rpc['jsonrpc'] ?? null ) !== '2.0' || ! is_string( $method ) || '' === $method || $invalid_id;

		if ( $invalid_envelope ) {
			return self::error_response( $invalid_id ? null : $id, -32600, 'Invalid Request' );
		}

		switch ( $method ) {
			case 'initialize':
				return self::success_response(
					$id,
					array(
						'protocolVersion' => self::PROTOCOL_VERSION,
						'capabilities'    => self::get_capabilities(),
						'serverInfo'      => self::get_server_info(),
					)
				);

			case 'notifications/initialized':
				return $is_notification ? null : self::success_response( $id, (object) array() );

			case 'tools/list':
				return self::success_response(
					$id,
					array(
						'tools' => self::get_tools(),
					)
				);

			case 'tools/call':
				return self::handle_tool_call( $id, $params );

			default:
				if ( $is_notification ) {
					return null;
				}

				return self::error_response( $id, -32601, 'Method not found: ' . $method );
		}
	}

	/**
	 * Handle tools/call.
	 *
	 * @param mixed $id     JSON-RPC request id.
	 * @param mixed $params Tool call parameters.
	 * @return array
	 */
	private static function handle_tool_call( $id, $params ) {
		if ( ! is_array( $params ) || empty( $params['name'] ) ) {
			return self::error_response( $id, -32602, 'Tool name is required' );
		}

		$name          = sanitize_key( $params['name'] );
		$arguments     = self::get_tool_arguments( $params );
		$tool_handlers = self::get_tool_handlers();

		if ( ! isset( $tool_handlers[ $name ] ) ) {
			return self::error_response( $id, -32602, 'Unknown tool: ' . sanitize_text_field( $params['name'] ) );
		}

		return call_user_func( $tool_handlers[ $name ], $id, $arguments );
	}

	/**
	 * Extract tool arguments from MCP tools/call params.
	 *
	 * @param array $params tools/call params.
	 * @return array
	 */
	private static function get_tool_arguments( $params ) {
		if ( isset( $params['arguments'] ) && is_array( $params['arguments'] ) ) {
			return $params['arguments'];
		}

		return array();
	}

	/**
	 * Return a map from MCP tool names to their handler methods.
	 *
	 * This map is consumed by handle_tool_call() to dispatch each tools/call
	 * request into the corresponding WordPress-side execution logic. Tool names
	 * not listed in this map are rejected as unknown tools.
	 *
	 * @return array<string, callable>
	 */
	private static function get_tool_handlers() {
		return array(
			'tsubakuro_list_tasks'                 => array( __CLASS__, 'tool_list_tasks' ),
			'tsubakuro_get_task'                   => array( __CLASS__, 'tool_get_task' ),
			'tsubakuro_create_task'                => array( __CLASS__, 'tool_create_task' ),
			'tsubakuro_update_task'                => array( __CLASS__, 'tool_update_task' ),
			'tsubakuro_delete_task'                => array( __CLASS__, 'tool_delete_task' ),
			'tsubakuro_add_comment'                => array( __CLASS__, 'tool_add_comment' ),
			'tsubakuro_list_evaluations'           => array( __CLASS__, 'tool_list_evaluations' ),
			'tsubakuro_get_evaluation'             => array( __CLASS__, 'tool_get_evaluation' ),
			'tsubakuro_create_evaluation'          => array( __CLASS__, 'tool_create_evaluation' ),
			'tsubakuro_update_evaluation'          => array( __CLASS__, 'tool_update_evaluation' ),
			'tsubakuro_delete_evaluation'          => array( __CLASS__, 'tool_delete_evaluation' ),
			'tsubakuro_list_insights'              => array( __CLASS__, 'tool_list_insights' ),
			'tsubakuro_get_insight'                => array( __CLASS__, 'tool_get_insight' ),
			'tsubakuro_create_insight'             => array( __CLASS__, 'tool_create_insight' ),
			'tsubakuro_update_insight'             => array( __CLASS__, 'tool_update_insight' ),
			'tsubakuro_delete_insight'             => array( __CLASS__, 'tool_delete_insight' ),
			'tsubakuro_link_evaluation_to_insight' => array( __CLASS__, 'tool_link_evaluation_to_insight' ),
		);
	}

	/**
	 * Map a shared-op result (array|WP_Error) into a JSON-RPC tool response.
	 *
	 * @param mixed $id     JSON-RPC request id.
	 * @param mixed $result Result from an op_* helper.
	 * @return array
	 */
	private static function tool_response_from_op( $id, $result ) {
		if ( is_wp_error( $result ) ) {
			$code_map = array(
				'invalid_input' => -32602,
				'not_found'     => 404,
				'forbidden'     => -32003,
			);
			$code     = $code_map[ $result->get_error_code() ] ?? 500;

			return self::error_response( $id, $code, $result->get_error_message() );
		}

		return self::tool_success_response( $id, $result );
	}

	/**
	 * Tool: list evaluations.
	 *
	 * @param mixed $id        JSON-RPC request id.
	 * @param array $arguments Tool arguments.
	 * @return array
	 */
	private static function tool_list_evaluations( $id, $arguments ) {
		return self::tool_response_from_op( $id, self::op_list_evaluations( $arguments ) );
	}

	/**
	 * Tool: get a single evaluation.
	 *
	 * @param mixed $id        JSON-RPC request id.
	 * @param array $arguments Tool arguments.
	 * @return array
	 */
	private static function tool_get_evaluation( $id, $arguments ) {
		return self::tool_response_from_op( $id, self::op_get_evaluation( $arguments ) );
	}

	/**
	 * Tool: create an evaluation.
	 *
	 * @param mixed $id        JSON-RPC request id.
	 * @param array $arguments Tool arguments.
	 * @return array
	 */
	private static function tool_create_evaluation( $id, $arguments ) {
		return self::tool_response_from_op( $id, self::op_create_evaluation( $arguments ) );
	}

	/**
	 * Tool: update an evaluation.
	 *
	 * @param mixed $id        JSON-RPC request id.
	 * @param array $arguments Tool arguments.
	 * @return array
	 */
	private static function tool_update_evaluation( $id, $arguments ) {
		return self::tool_response_from_op( $id, self::op_update_evaluation( $arguments ) );
	}

	/**
	 * Tool: delete an evaluation.
	 *
	 * @param mixed $id        JSON-RPC request id.
	 * @param array $arguments Tool arguments.
	 * @return array
	 */
	private static function tool_delete_evaluation( $id, $arguments ) {
		return self::tool_response_from_op( $id, self::op_delete_evaluation( $arguments ) );
	}

	/**
	 * Tool: list insights.
	 *
	 * @param mixed $id        JSON-RPC request id.
	 * @param array $arguments Tool arguments.
	 * @return array
	 */
	private static function tool_list_insights( $id, $arguments ) {
		return self::tool_response_from_op( $id, self::op_list_insights( $arguments ) );
	}

	/**
	 * Tool: get a single insight.
	 *
	 * @param mixed $id        JSON-RPC request id.
	 * @param array $arguments Tool arguments.
	 * @return array
	 */
	private static function tool_get_insight( $id, $arguments ) {
		return self::tool_response_from_op( $id, self::op_get_insight( $arguments ) );
	}

	/**
	 * Tool: create an insight.
	 *
	 * @param mixed $id        JSON-RPC request id.
	 * @param array $arguments Tool arguments.
	 * @return array
	 */
	private static function tool_create_insight( $id, $arguments ) {
		return self::tool_response_from_op( $id, self::op_create_insight( $arguments ) );
	}

	/**
	 * Tool: update an insight.
	 *
	 * @param mixed $id        JSON-RPC request id.
	 * @param array $arguments Tool arguments.
	 * @return array
	 */
	private static function tool_update_insight( $id, $arguments ) {
		return self::tool_response_from_op( $id, self::op_update_insight( $arguments ) );
	}

	/**
	 * Tool: delete an insight.
	 *
	 * @param mixed $id        JSON-RPC request id.
	 * @param array $arguments Tool arguments.
	 * @return array
	 */
	private static function tool_delete_insight( $id, $arguments ) {
		return self::tool_response_from_op( $id, self::op_delete_insight( $arguments ) );
	}

	/**
	 * Tool: link an evaluation to an insight.
	 *
	 * @param mixed $id        JSON-RPC request id.
	 * @param array $arguments Tool arguments.
	 * @return array
	 */
	private static function tool_link_evaluation_to_insight( $id, $arguments ) {
		return self::tool_response_from_op( $id, self::op_link_evaluation( $arguments ) );
	}

	/**
	 * Tool: list tasks with optional filters.
	 *
	 * @param mixed $id        JSON-RPC request id.
	 * @param array $arguments Tool arguments.
	 * @return array
	 */
	private static function tool_list_tasks( $id, $arguments ) {
		$args = array();

		if ( ! empty( $arguments['status'] ) ) {
			$args['status'] = sanitize_text_field( $arguments['status'] );
		}

		if ( ! empty( $arguments['priority'] ) ) {
			$args['priority'] = sanitize_text_field( $arguments['priority'] );
		}

		if ( ! empty( $arguments['assignee'] ) ) {
			$args['assignee'] = absint( $arguments['assignee'] );
		}

		if ( ! empty( $arguments['related_page'] ) ) {
			$args['related_page'] = absint( $arguments['related_page'] );
		}

		if ( ! empty( $arguments['per_page'] ) ) {
			$args['posts_per_page'] = min( 100, max( 1, absint( $arguments['per_page'] ) ) );
		}

		if ( isset( $arguments['parent_id'] ) ) {
			$args['parent_id'] = absint( $arguments['parent_id'] );
		}

		foreach ( array( 's', 'orderby', 'order' ) as $key ) {
			if ( ! empty( $arguments[ $key ] ) ) {
				$args[ $key ] = sanitize_text_field( $arguments[ $key ] );
			}
		}

		return self::tool_success_response(
			$id,
			array(
				'tasks' => Tsubakuro_Post_Types::get_tasks( $args ),
			)
		);
	}

	/**
	 * Tool: get a single task with comments.
	 *
	 * @param mixed $id        JSON-RPC request id.
	 * @param array $arguments Tool arguments.
	 * @return array
	 */
	private static function tool_get_task( $id, $arguments ) {
		if ( empty( $arguments['id'] ) ) {
			return self::error_response( $id, -32602, 'id is required' );
		}

		$task = Tsubakuro_Post_Types::get_task( absint( $arguments['id'] ) );
		if ( ! $task ) {
			return self::error_response( $id, 404, 'Task not found' );
		}

		$task['comments'] = Tsubakuro_Admin::get_task_comments( $task['id'] );
		$task['children'] = Tsubakuro_Post_Types::get_tasks( array( 'parent_id' => $task['id'] ) );

		return self::tool_success_response(
			$id,
			array(
				'task' => $task,
			)
		);
	}

	/**
	 * Tool: create a new task.
	 *
	 * @param mixed $id        JSON-RPC request id.
	 * @param array $arguments Tool arguments.
	 * @return array
	 */
	private static function tool_create_task( $id, $arguments ) {
		if ( empty( $arguments['title'] ) ) {
			return self::error_response( $id, -32602, 'title is required' );
		}

		$task_id = wp_insert_post(
			array(
				'post_type'    => 'tsubakuro_task',
				'post_title'   => sanitize_text_field( $arguments['title'] ),
				'post_content' => wp_kses_post( $arguments['content'] ?? '' ),
				'post_status'  => 'publish',
				'post_parent'  => absint( $arguments['parent_id'] ?? 0 ),
			),
			true
		);

		if ( is_wp_error( $task_id ) ) {
			return self::error_response( $id, 500, $task_id->get_error_message() );
		}

		if ( empty( $arguments['status'] ) || ! is_string( $arguments['status'] ) || ! array_key_exists( $arguments['status'], Tsubakuro_Post_Types::STATUSES ) ) {
			$arguments['status'] = 'todo';
		}

		Tsubakuro_Post_Types::save_meta( $task_id, $arguments );

		return self::tool_success_response(
			$id,
			array(
				'task' => Tsubakuro_Post_Types::get_task( $task_id ),
			)
		);
	}

	/**
	 * Tool: update an existing task.
	 *
	 * @param mixed $id        JSON-RPC request id.
	 * @param array $arguments Tool arguments.
	 * @return array
	 */
	private static function tool_update_task( $id, $arguments ) {
		if ( empty( $arguments['id'] ) ) {
			return self::error_response( $id, -32602, 'id is required' );
		}

		$task_id = absint( $arguments['id'] );
		$task    = Tsubakuro_Post_Types::get_task( $task_id );

		if ( ! $task ) {
			return self::error_response( $id, 404, 'Task not found' );
		}

		$update = array( 'ID' => $task_id );

		if ( isset( $arguments['title'] ) ) {
			$update['post_title'] = sanitize_text_field( $arguments['title'] );
		}

		if ( isset( $arguments['content'] ) ) {
			$update['post_content'] = wp_kses_post( $arguments['content'] );
		}

		if ( isset( $arguments['parent_id'] ) ) {
			$update['post_parent'] = absint( $arguments['parent_id'] );
		}

		wp_update_post( $update );
		Tsubakuro_Post_Types::save_meta( $task_id, $arguments );

		return self::tool_success_response(
			$id,
			array(
				'task' => Tsubakuro_Post_Types::get_task( $task_id ),
			)
		);
	}

	/**
	 * Tool: delete a task.
	 *
	 * @param mixed $id        JSON-RPC request id.
	 * @param array $arguments Tool arguments.
	 * @return array
	 */
	private static function tool_delete_task( $id, $arguments ) {
		if ( empty( $arguments['id'] ) ) {
			return self::error_response( $id, -32602, 'id is required' );
		}

		if ( ! current_user_can( 'delete_posts' ) ) {
			return self::error_response( $id, -32003, 'Permission denied' );
		}

		$task_id = absint( $arguments['id'] );
		$task    = Tsubakuro_Post_Types::get_task( $task_id );

		if ( ! $task ) {
			return self::error_response( $id, 404, 'Task not found' );
		}

		wp_delete_post( $task_id, true );

		return self::tool_success_response(
			$id,
			array(
				'deleted' => true,
				'id'      => $task_id,
			)
		);
	}

	/**
	 * Tool: add a comment to a task.
	 *
	 * @param mixed $id        JSON-RPC request id.
	 * @param array $arguments Tool arguments.
	 * @return array
	 */
	private static function tool_add_comment( $id, $arguments ) {
		if ( empty( $arguments['id'] ) || empty( $arguments['comment'] ) ) {
			return self::error_response( $id, -32602, 'id and comment are required' );
		}

		$task_id = absint( $arguments['id'] );
		$task    = Tsubakuro_Post_Types::get_task( $task_id );

		if ( ! $task ) {
			return self::error_response( $id, 404, 'Task not found' );
		}

		$comment_id = Tsubakuro_Admin::insert_comment(
			$task_id,
			get_current_user_id(),
			sanitize_textarea_field( $arguments['comment'] )
		);

		if ( false === $comment_id ) {
			return self::error_response( $id, 500, 'Failed to insert comment' );
		}

		return self::tool_success_response(
			$id,
			array(
				'comment' => Tsubakuro_Admin::get_comment( $comment_id ),
			)
		);
	}

	/**
	 * Return server capabilities.
	 *
	 * @return array
	 */
	private static function get_capabilities() {
		return array(
			'tools' => (object) array(),
		);
	}

	/**
	 * Return server info.
	 *
	 * @return array
	 */
	private static function get_server_info() {
		return array(
			'name'    => self::SERVER_NAME,
			'version' => '0.1.0',
		);
	}

	/**
	 * Return available MCP tools.
	 *
	 * @return array
	 */
	private static function get_tools() {
		return array(
			array(
				'name'        => 'tsubakuro_list_tasks',
				'description' => 'タスク一覧を取得します。status、priority、assignee、related_page、per_page、s、orderby、order で絞り込みできます。',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'status'       => array(
							'type'        => 'string',
							'description' => 'todo / in_progress / completed',
						),
						'priority'     => array(
							'type'        => 'string',
							'description' => 'low / medium / high',
						),
						'assignee'     => array(
							'type'        => 'integer',
							'description' => 'アサインされた WordPress ユーザー ID',
						),
						'related_page' => array(
							'type'        => 'integer',
							'description' => '関連ページ ID',
						),
						'per_page'     => array(
							'type'        => 'integer',
							'description' => '取得件数。最大100。',
						),
						's'            => array(
							'type'        => 'string',
							'description' => '検索語',
						),
						'orderby'      => array(
							'type'        => 'string',
							'description' => 'id / title / date / status / priority / assignee',
						),
						'order'        => array(
							'type'        => 'string',
							'description' => 'ASC / DESC',
						),
					),
				),
			),
			array(
				'name'        => 'tsubakuro_get_task',
				'description' => '指定IDのタスク詳細をコメント込みで取得します。',
				'inputSchema' => array(
					'type'       => 'object',
					'required'   => array( 'id' ),
					'properties' => array(
						'id' => array(
							'type'        => 'integer',
							'description' => 'タスク ID',
						),
					),
				),
			),
			array(
				'name'        => 'tsubakuro_create_task',
				'description' => '新しいタスクを作成します。',
				'inputSchema' => array(
					'type'       => 'object',
					'required'   => array( 'title' ),
					'properties' => array(
						'title'         => array(
							'type'        => 'string',
							'description' => 'タイトル',
						),
						'content'       => array(
							'type'        => 'string',
							'description' => '内容・説明',
						),
						'status'        => array(
							'type'        => 'string',
							'description' => 'todo / in_progress / completed',
						),
						'priority'      => array(
							'type'        => 'string',
							'description' => 'low / medium / high',
						),
						'assignee'      => array(
							'type'        => 'integer',
							'description' => 'アサインする WordPress ユーザー ID',
						),
						'related_pages' => array(
							'type'        => 'array',
							'description' => '関連ページ ID の配列',
							'items'       => array( 'type' => 'integer' ),
						),
					),
				),
			),
			array(
				'name'        => 'tsubakuro_update_task',
				'description' => '既存タスクを更新します。指定したフィールドのみ変更します。',
				'inputSchema' => array(
					'type'       => 'object',
					'required'   => array( 'id' ),
					'properties' => array(
						'id'            => array( 'type' => 'integer' ),
						'title'         => array( 'type' => 'string' ),
						'content'       => array( 'type' => 'string' ),
						'status'        => array( 'type' => 'string' ),
						'priority'      => array( 'type' => 'string' ),
						'assignee'      => array( 'type' => 'integer' ),
						'related_pages' => array(
							'type'  => 'array',
							'items' => array( 'type' => 'integer' ),
						),
					),
				),
			),
			array(
				'name'        => 'tsubakuro_delete_task',
				'description' => '指定したタスクを削除します。',
				'inputSchema' => array(
					'type'       => 'object',
					'required'   => array( 'id' ),
					'properties' => array(
						'id' => array( 'type' => 'integer' ),
					),
				),
			),
			array(
				'name'        => 'tsubakuro_add_comment',
				'description' => '指定したタスクにコメントを追加します。',
				'inputSchema' => array(
					'type'       => 'object',
					'required'   => array( 'id', 'comment' ),
					'properties' => array(
						'id'      => array(
							'type'        => 'integer',
							'description' => 'タスク ID',
						),
						'comment' => array(
							'type'        => 'string',
							'description' => 'コメント本文',
						),
					),
				),
			),
			array(
				'name'        => 'tsubakuro_list_evaluations',
				'description' => '記事評価（変更タスク）一覧を取得します。target_post、change_item、judgment、metric、unevaluated、overdue、s、per_page で絞り込みできます。',
				'inputSchema' => self::get_list_evaluations_input_schema(),
			),
			array(
				'name'        => 'tsubakuro_get_evaluation',
				'description' => '指定 ID の記事評価詳細と関連する改善知見を取得します。',
				'inputSchema' => self::get_single_id_input_schema(),
			),
			array(
				'name'        => 'tsubakuro_create_evaluation',
				'description' => '新しい記事評価を作成します。判定(judgment)は success / partial / no_change / failure / pending。',
				'inputSchema' => self::get_create_evaluation_input_schema(),
			),
			array(
				'name'        => 'tsubakuro_update_evaluation',
				'description' => '既存の記事評価を更新します。判定を記録すると未評価から外れます。',
				'inputSchema' => self::get_update_evaluation_input_schema(),
			),
			array(
				'name'        => 'tsubakuro_delete_evaluation',
				'description' => '指定した記事評価を削除します。',
				'inputSchema' => self::get_single_id_input_schema(),
			),
			array(
				'name'        => 'tsubakuro_list_insights',
				'description' => 'サイト単位の改善知見一覧を取得します。status、action、evaluation、s、per_page で絞り込みできます。',
				'inputSchema' => self::get_list_insights_input_schema(),
			),
			array(
				'name'        => 'tsubakuro_get_insight',
				'description' => '指定 ID の改善知見詳細と根拠の記事評価を取得します。',
				'inputSchema' => self::get_single_id_input_schema(),
			),
			array(
				'name'        => 'tsubakuro_create_insight',
				'description' => '新しい改善知見を作成します。ステータス(status)は hypothesis / verifying / effective / unclear / ineffective / ruled。',
				'inputSchema' => self::get_create_insight_input_schema(),
			),
			array(
				'name'        => 'tsubakuro_update_insight',
				'description' => '既存の改善知見を更新します。',
				'inputSchema' => self::get_update_insight_input_schema(),
			),
			array(
				'name'        => 'tsubakuro_delete_insight',
				'description' => '指定した改善知見を削除します。',
				'inputSchema' => self::get_single_id_input_schema(),
			),
			array(
				'name'        => 'tsubakuro_link_evaluation_to_insight',
				'description' => '記事評価を改善知見の根拠として関連付けます。',
				'inputSchema' => self::get_link_evaluation_input_schema(),
			),
		);
	}

	/**
	 * Build a JSON-RPC 2.0 success response.
	 *
	 * @param mixed $id     Request id.
	 * @param mixed $result Result payload.
	 * @return array
	 */
	private static function success_response( $id, $result ) {
		return array(
			'jsonrpc' => '2.0',
			'id'      => $id,
			'result'  => $result,
		);
	}

	/**
	 * Build a MCP tool success response with structured and text content.
	 *
	 * @param mixed $id   Request id.
	 * @param array $data Structured tool data.
	 * @return array
	 */
	private static function tool_success_response( $id, $data ) {
		return self::success_response(
			$id,
			array(
				'content'           => array(
					array(
						'type' => 'text',
						'text' => self::encode_tool_text( $data ),
					),
				),
				'structuredContent' => $data,
			)
		);
	}

	/**
	 * Encode structured data for a text content item.
	 *
	 * @param mixed $data Data to encode.
	 * @return string
	 */
	private static function encode_tool_text( $data ) {
		if ( function_exists( 'wp_json_encode' ) ) {
			return wp_json_encode( $data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT );
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- Fallback for the lightweight PHPUnit bootstrap where wp_json_encode() is unavailable.
		return json_encode( $data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT );
	}

	/**
	 * Build a JSON-RPC 2.0 error response.
	 *
	 * @param mixed  $id      Request id.
	 * @param int    $code    JSON-RPC error code.
	 * @param string $message Human-readable error message.
	 * @return array
	 */
	private static function error_response( $id, $code, $message ) {
		return array(
			'jsonrpc' => '2.0',
			'id'      => $id,
			'error'   => array(
				'code'    => $code,
				'message' => $message,
			),
		);
	}

	/**
	 * Permission check for MCP requests.
	 *
	 * WordPress Application Passwords authenticate Basic Authorization before
	 * the REST callback runs.
	 *
	 * @return bool
	 */
	public static function check_permission() {
		$authorization = self::get_authorization_header();

		if ( '' === $authorization ) {
			return false;
		}

		if ( preg_match( '/^Basic\s+\S+$/i', $authorization ) ) {
			return current_user_can( 'edit_posts' );
		}

		return false;
	}

	/**
	 * Get the incoming Authorization header.
	 *
	 * @return string
	 */
	private static function get_authorization_header() {
		if ( ! empty( $_SERVER['HTTP_AUTHORIZATION'] ) ) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Authorization scheme is validated before use.
			return trim( wp_unslash( (string) $_SERVER['HTTP_AUTHORIZATION'] ) );
		}

		if ( ! empty( $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ) ) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Authorization scheme is validated before use.
			return trim( wp_unslash( (string) $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ) );
		}

		if ( function_exists( 'getallheaders' ) ) {
			$headers = getallheaders();
			foreach ( $headers as $name => $value ) {
				if ( 'authorization' === strtolower( (string) $name ) ) {
					return trim( wp_unslash( (string) $value ) );
				}
			}
		}

		return '';
	}

	/**
	 * Return a JSON-RPC response.
	 *
	 * @param mixed $data   Response data.
	 * @param int   $status HTTP status code.
	 * @return WP_REST_Response|array
	 */
	private static function jsonrpc_response( $data, $status = 200 ) {
		return self::json_response( $data, $status );
	}

	/**
	 * Return a JSON response with MCP-friendly headers.
	 *
	 * @param mixed $data   Response data.
	 * @param int   $status HTTP status code.
	 * @return WP_REST_Response|array
	 */
	private static function json_response( $data, $status = 200 ) {
		if ( class_exists( 'WP_REST_Response' ) ) {
			$response = new WP_REST_Response( $data, $status );
			$response->header( 'Content-Type', 'application/json; charset=' . get_option( 'blog_charset', 'UTF-8' ) );
			$response->header( 'Access-Control-Allow-Methods', 'GET, POST, OPTIONS' );
			$response->header( 'Access-Control-Allow-Headers', 'Authorization, Content-Type, Accept, MCP-Protocol-Version' );
			$response->header( 'Access-Control-Allow-Origin', '*' );
			return $response;
		}

		return $data;
	}

	/**
	 * Return an empty response for JSON-RPC notifications.
	 *
	 * MCP clients do not expect a JSON-RPC response for notifications. Returning
	 * JSON null makes some clients try to deserialize null as a JSON-RPC message.
	 *
	 * @return WP_REST_Response|string
	 */
	private static function empty_response() {
		if ( class_exists( 'WP_REST_Response' ) ) {
			$response = new WP_REST_Response( '', 202 );
			$response->header( 'Access-Control-Allow-Methods', 'GET, POST, OPTIONS' );
			$response->header( 'Access-Control-Allow-Headers', 'Authorization, Content-Type, Accept, MCP-Protocol-Version' );
			$response->header( 'Access-Control-Allow-Origin', '*' );
			return $response;
		}

		return '';
	}

	/**
	 * Determine whether an array is a JSON list.
	 *
	 * @param array $value Value to check.
	 * @return bool
	 */
	private static function is_list( $value ) {
		if ( function_exists( 'array_is_list' ) ) {
			return array_is_list( $value );
		}

		return array_keys( $value ) === range( 0, count( $value ) - 1 );
	}

	/**
	 * Determine whether a decoded body is a JSON-RPC response message.
	 *
	 * @param mixed $value Decoded JSON value.
	 * @return bool
	 */
	private static function is_jsonrpc_response_message( $value ) {
		if ( ! is_array( $value ) || ( $value['jsonrpc'] ?? null ) !== '2.0' || ! array_key_exists( 'id', $value ) ) {
			return false;
		}

		$has_result = array_key_exists( 'result', $value );
		$has_error  = array_key_exists( 'error', $value );

		return ! array_key_exists( 'method', $value ) && $has_result !== $has_error;
	}

	/**
	 * Check MCP request id validity.
	 *
	 * @param mixed $id Request id.
	 * @return bool
	 */
	private static function is_valid_request_id( $id ) {
		return is_string( $id ) || is_int( $id );
	}
}
