<?php
/**
 * MCP (Model Context Protocol) endpoint.
 *
 * Implements JSON-RPC 2.0 over HTTP POST at:
 *   /wp-json/tsubakuro/v1/mcp
 *
 * Supported tools:
 *   tsubakuro_list_tasks   – list tasks (optional filters: status, related_page)
 *   tsubakuro_get_task     – get a single task with comments
 *   tsubakuro_create_task  – create a new task
 *   tsubakuro_update_task  – update title/content/status/assignee/related_pages
 *   tsubakuro_delete_task  – delete a task
 *   tsubakuro_add_comment  – add a comment to a task
 *
 * Discovery endpoint (GET /wp-json/tsubakuro/v1/mcp) returns the MCP
 * server manifest so that AI clients can discover available tools.
 *
 * @package Tsubakuro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Tsubakuro_MCP {

	const ROUTE = '/mcp';

	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	public static function register_routes() {
		// Discovery (GET).
		register_rest_route(
			Tsubakuro_REST_API::NAMESPACE,
			self::ROUTE,
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( __CLASS__, 'handle_manifest' ),
					'permission_callback' => '__return_true',
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( __CLASS__, 'handle_jsonrpc' ),
					'permission_callback' => array( __CLASS__, 'check_permission' ),
				),
			)
		);
	}

	// -------------------------------------------------------------------------
	// MCP manifest
	// -------------------------------------------------------------------------

	public static function handle_manifest() {
		return rest_ensure_response( self::get_manifest() );
	}

	public static function get_manifest() {
		return array(
			'schema_version' => '2024-11-05',
			'name'           => 'tsubakuro-task-manager',
			'version'        => TSUBAKURO_VERSION,
			'description'    => 'WordPress task management plugin – manage tasks, comments, status, assignees and related pages.',
			'tools'          => array(
				array(
					'name'        => 'tsubakuro_list_tasks',
					'description' => 'タスク一覧を取得します。',
					'inputSchema' => array(
						'type'       => 'object',
						'properties' => array(
							'status'       => array( 'type' => 'string', 'description' => 'フィルタ: todo | in_progress | completed' ),
							'related_page' => array( 'type' => 'integer', 'description' => '関連ページIDでフィルタ' ),
							'per_page'     => array( 'type' => 'integer', 'description' => '取得件数 (max 100)', 'default' => 50 ),
						),
					),
				),
				array(
					'name'        => 'tsubakuro_get_task',
					'description' => '指定IDのタスク詳細（コメント含む）を取得します。',
					'inputSchema' => array(
						'type'       => 'object',
						'required'   => array( 'id' ),
						'properties' => array(
							'id' => array( 'type' => 'integer', 'description' => 'タスクID' ),
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
							'title'         => array( 'type' => 'string', 'description' => 'タイトル' ),
							'content'       => array( 'type' => 'string', 'description' => '内容・説明' ),
							'status'        => array( 'type' => 'string', 'description' => 'todo | in_progress | completed', 'default' => 'todo' ),
							'assignee'      => array( 'type' => 'integer', 'description' => 'アサインするWordPressユーザーID' ),
							'related_pages' => array( 'type' => 'array', 'items' => array( 'type' => 'integer' ), 'description' => '関連ページIDの配列' ),
						),
					),
				),
				array(
					'name'        => 'tsubakuro_update_task',
					'description' => 'タスクを更新します。',
					'inputSchema' => array(
						'type'       => 'object',
						'required'   => array( 'id' ),
						'properties' => array(
							'id'            => array( 'type' => 'integer', 'description' => 'タスクID' ),
							'title'         => array( 'type' => 'string' ),
							'content'       => array( 'type' => 'string' ),
							'status'        => array( 'type' => 'string' ),
							'assignee'      => array( 'type' => 'integer' ),
							'related_pages' => array( 'type' => 'array', 'items' => array( 'type' => 'integer' ) ),
						),
					),
				),
				array(
					'name'        => 'tsubakuro_delete_task',
					'description' => 'タスクを削除します。',
					'inputSchema' => array(
						'type'       => 'object',
						'required'   => array( 'id' ),
						'properties' => array(
							'id' => array( 'type' => 'integer', 'description' => 'タスクID' ),
						),
					),
				),
				array(
					'name'        => 'tsubakuro_add_comment',
					'description' => 'タスクにコメントを追加します。',
					'inputSchema' => array(
						'type'       => 'object',
						'required'   => array( 'id', 'comment' ),
						'properties' => array(
							'id'      => array( 'type' => 'integer', 'description' => 'タスクID' ),
							'comment' => array( 'type' => 'string', 'description' => 'コメント本文' ),
						),
					),
				),
			),
		);
	}

	// -------------------------------------------------------------------------
	// JSON-RPC 2.0 dispatcher
	// -------------------------------------------------------------------------

	public static function handle_jsonrpc( $request ) {
		$body = $request->get_json_params();

		if ( empty( $body ) ) {
			return self::error_response( null, -32700, 'Parse error' );
		}

		// Support batch requests (array of calls).
		if ( isset( $body[0] ) ) {
			$responses = array();
			foreach ( $body as $single ) {
				$responses[] = self::dispatch( $single );
			}
			return rest_ensure_response( $responses );
		}

		return rest_ensure_response( self::dispatch( $body ) );
	}

	private static function dispatch( $rpc ) {
		$id     = $rpc['id'] ?? null;
		$method = $rpc['method'] ?? '';
		$params = $rpc['params'] ?? array();

		if ( empty( $method ) ) {
			return self::error_response( $id, -32600, 'Invalid Request' );
		}

		switch ( $method ) {
			case 'tsubakuro_list_tasks':
				return self::tool_list_tasks( $id, $params );

			case 'tsubakuro_get_task':
				return self::tool_get_task( $id, $params );

			case 'tsubakuro_create_task':
				return self::tool_create_task( $id, $params );

			case 'tsubakuro_update_task':
				return self::tool_update_task( $id, $params );

			case 'tsubakuro_delete_task':
				return self::tool_delete_task( $id, $params );

			case 'tsubakuro_add_comment':
				return self::tool_add_comment( $id, $params );

			default:
				return self::error_response( $id, -32601, 'Method not found: ' . $method );
		}
	}

	// -------------------------------------------------------------------------
	// Tool implementations
	// -------------------------------------------------------------------------

	private static function tool_list_tasks( $id, $params ) {
		$args = array();

		if ( ! empty( $params['status'] ) ) {
			$args['status'] = sanitize_text_field( $params['status'] );
		}

		if ( ! empty( $params['related_page'] ) ) {
			$args['related_page'] = absint( $params['related_page'] );
		}

		if ( ! empty( $params['per_page'] ) ) {
			$args['posts_per_page'] = min( 100, absint( $params['per_page'] ) );
		}

		$tasks = Tsubakuro_Post_Types::get_tasks( $args );

		return self::success_response( $id, $tasks );
	}

	private static function tool_get_task( $id, $params ) {
		if ( empty( $params['id'] ) ) {
			return self::error_response( $id, -32602, 'id is required' );
		}

		$task = Tsubakuro_Post_Types::get_task( absint( $params['id'] ) );

		if ( ! $task ) {
			return self::error_response( $id, 404, 'Task not found' );
		}

		$task['comments'] = Tsubakuro_Admin::get_task_comments( $task['id'] );

		return self::success_response( $id, $task );
	}

	private static function tool_create_task( $id, $params ) {
		if ( empty( $params['title'] ) ) {
			return self::error_response( $id, -32602, 'title is required' );
		}

		$task_id = wp_insert_post(
			array(
				'post_type'    => 'tsubakuro_task',
				'post_title'   => sanitize_text_field( $params['title'] ),
				'post_content' => wp_kses_post( $params['content'] ?? '' ),
				'post_status'  => 'publish',
			),
			true
		);

		if ( is_wp_error( $task_id ) ) {
			return self::error_response( $id, 500, $task_id->get_error_message() );
		}

		Tsubakuro_Post_Types::save_meta( $task_id, $params );

		return self::success_response( $id, Tsubakuro_Post_Types::get_task( $task_id ) );
	}

	private static function tool_update_task( $id, $params ) {
		if ( empty( $params['id'] ) ) {
			return self::error_response( $id, -32602, 'id is required' );
		}

		$task_id = absint( $params['id'] );
		$task    = Tsubakuro_Post_Types::get_task( $task_id );

		if ( ! $task ) {
			return self::error_response( $id, 404, 'Task not found' );
		}

		$update = array( 'ID' => $task_id );

		if ( isset( $params['title'] ) ) {
			$update['post_title'] = sanitize_text_field( $params['title'] );
		}

		if ( isset( $params['content'] ) ) {
			$update['post_content'] = wp_kses_post( $params['content'] );
		}

		wp_update_post( $update );
		Tsubakuro_Post_Types::save_meta( $task_id, $params );

		return self::success_response( $id, Tsubakuro_Post_Types::get_task( $task_id ) );
	}

	private static function tool_delete_task( $id, $params ) {
		if ( empty( $params['id'] ) ) {
			return self::error_response( $id, -32602, 'id is required' );
		}

		$task_id = absint( $params['id'] );
		$task    = Tsubakuro_Post_Types::get_task( $task_id );

		if ( ! $task ) {
			return self::error_response( $id, 404, 'Task not found' );
		}

		wp_delete_post( $task_id, true );

		return self::success_response( $id, array( 'deleted' => true, 'id' => $task_id ) );
	}

	private static function tool_add_comment( $id, $params ) {
		if ( empty( $params['id'] ) || empty( $params['comment'] ) ) {
			return self::error_response( $id, -32602, 'id and comment are required' );
		}

		$task_id = absint( $params['id'] );
		$task    = Tsubakuro_Post_Types::get_task( $task_id );

		if ( ! $task ) {
			return self::error_response( $id, 404, 'Task not found' );
		}

		$comment_id = Tsubakuro_Admin::insert_comment(
			$task_id,
			get_current_user_id(),
			sanitize_textarea_field( $params['comment'] )
		);

		if ( false === $comment_id ) {
			return self::error_response( $id, 500, 'Failed to insert comment' );
		}

		return self::success_response( $id, Tsubakuro_Admin::get_comment( $comment_id ) );
	}

	// -------------------------------------------------------------------------
	// JSON-RPC helpers
	// -------------------------------------------------------------------------

	private static function success_response( $id, $result ) {
		return array(
			'jsonrpc' => '2.0',
			'id'      => $id,
			'result'  => $result,
		);
	}

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

	// -------------------------------------------------------------------------
	// Permission
	// -------------------------------------------------------------------------

	public static function check_permission() {
		return current_user_can( 'edit_posts' );
	}
}
