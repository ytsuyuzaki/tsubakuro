<?php
/**
 * MCP (Model Context Protocol) endpoint.
 *
 * Implements JSON-RPC 2.0 over WordPress REST API at:
 *   /wp-json/tsubakuro/v1/mcp
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
	const PROTOCOL_VERSION = '2025-11-25';
	const SERVER_NAME      = 'tsubakuro-wordpress-mcp';
	const GUIDE_PAGE_SLUG  = 'tsubakuro-mcp-guide';

	/**
	 * Register WordPress hooks.
	 */
	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
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

		$response = self::dispatch( $body );
		if ( null === $response ) {
			return self::empty_response();
		}

		return self::jsonrpc_response( $response );
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

			case 'initialized':
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

			case 'resources/list':
				return self::success_response(
					$id,
					array(
						'resources' => self::get_resources(),
					)
				);

			case 'resources/read':
				return self::handle_resource_read( $id, $params );

			case 'prompts/list':
				return self::success_response(
					$id,
					array(
						'prompts' => array(),
					)
				);

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

		$name      = sanitize_key( $params['name'] );
		$arguments = self::get_tool_arguments( $params );

		switch ( $name ) {
			case 'tsubakuro_list_tasks':
				return self::tool_list_tasks( $id, $arguments );

			case 'tsubakuro_get_task':
				return self::tool_get_task( $id, $arguments );

			case 'tsubakuro_create_task':
				return self::tool_create_task( $id, $arguments );

			case 'tsubakuro_update_task':
				return self::tool_update_task( $id, $arguments );

			case 'tsubakuro_delete_task':
				return self::tool_delete_task( $id, $arguments );

			case 'tsubakuro_add_comment':
				return self::tool_add_comment( $id, $arguments );

			default:
				return self::error_response( $id, -32602, 'Unknown tool: ' . sanitize_text_field( $params['name'] ) );
		}
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
			),
			true
		);

		if ( is_wp_error( $task_id ) ) {
			return self::error_response( $id, 500, $task_id->get_error_message() );
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
	 * Handle resources/read.
	 *
	 * @param mixed $id     JSON-RPC request id.
	 * @param mixed $params Resource read parameters.
	 * @return array
	 */
	private static function handle_resource_read( $id, $params ) {
		if ( ! is_array( $params ) || empty( $params['uri'] ) ) {
			return self::error_response( $id, -32602, 'Resource uri is required' );
		}

		if ( self::get_guide_resource_uri() !== $params['uri'] ) {
			return self::error_response( $id, -32602, 'Unknown resource: ' . sanitize_text_field( $params['uri'] ) );
		}

		return self::success_response(
			$id,
			array(
				'contents' => array(
					array(
						'uri'      => self::get_guide_resource_uri(),
						'mimeType' => 'text/markdown',
						'text'     => self::get_guide_resource_text(),
					),
				),
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
			'tools'     => (object) array(),
			'resources' => (object) array(),
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
		);
	}

	/**
	 * Return available MCP resources.
	 *
	 * @return array
	 */
	private static function get_resources() {
		return array(
			array(
				'uri'         => self::get_guide_resource_uri(),
				'name'        => 'Tsubakuro MCP Guide',
				'description' => 'WordPress 管理画面の page=tsubakuro-mcp-guide に記載している MCP / タスク管理ドキュメントです。',
				'mimeType'    => 'text/markdown',
			),
		);
	}

	/**
	 * Return the MCP guide resource URI.
	 *
	 * @return string
	 */
	private static function get_guide_resource_uri() {
		return admin_url( 'admin.php?page=' . self::GUIDE_PAGE_SLUG );
	}

	/**
	 * Return the MCP guide resource text.
	 *
	 * @return string
	 */
	private static function get_guide_resource_text() {
		$mcp_url   = rest_url( Tsubakuro_REST_API::NAMESPACE . self::ROUTE );
		$admin_url = self::get_guide_resource_uri();

		return implode(
			"\n",
			array(
				'# Tsubakuro MCP Guide',
				'',
				'WordPress 管理画面の `page=tsubakuro-mcp-guide` に記載している MCP 接続ガイドです。',
				'',
				'## Paths',
				'',
				'- Admin guide: `' . $admin_url . '`',
				'- MCP endpoint: `' . $mcp_url . '`',
				'- REST route: `/wp-json/tsubakuro/v1/mcp`',
				'',
				'## Transport',
				'',
				'- Streamable HTTP',
				'- JSON-RPC 2.0',
				'- Methods: `initialize`, `initialized`, `tools/list`, `tools/call`, `resources/list`, `resources/read`, `prompts/list`',
				'',
				'## Authentication',
				'',
				'- Use `Authorization: Basic <Base64(username:application_password)>` with WordPress Application Passwords.',
				'- The authenticated WordPress user must have the `edit_posts` capability.',
				'',
				'## Tools',
				'',
				'### tsubakuro_list_tasks',
				'',
				'- Description: タスク一覧を取得します。',
				'- Arguments: `status`, `priority`, `assignee`, `related_page`, `per_page`, `s`, `orderby`, `order`',
				'',
				'### tsubakuro_get_task',
				'',
				'- Description: 指定IDのタスク詳細をコメント込みで取得します。',
				'- Required arguments: `id`',
				'',
				'### tsubakuro_create_task',
				'',
				'- Description: 新しいタスクを作成します。',
				'- Required arguments: `title`',
				'- Optional arguments: `content`, `status`, `priority`, `assignee`, `related_pages`',
				'',
				'### tsubakuro_update_task',
				'',
				'- Description: 既存タスクを更新します。',
				'- Required arguments: `id`',
				'- Optional arguments: `title`, `content`, `status`, `priority`, `assignee`, `related_pages`',
				'',
				'### tsubakuro_delete_task',
				'',
				'- Description: 指定したタスクを削除します。',
				'- Required arguments: `id`',
				'',
				'### tsubakuro_add_comment',
				'',
				'- Description: 指定したタスクにコメントを追加します。',
				'- Required arguments: `id`, `comment`',
				'',
				'## Resources',
				'',
				'### Tsubakuro MCP Guide',
				'',
				'- URI: `' . $admin_url . '`',
				'- Read with: `resources/read`',
				'- Content type: `text/markdown`',
				'',
				'## Example JSON-RPC',
				'',
				'```json',
				'{"jsonrpc":"2.0","id":1,"method":"initialize","params":{"protocolVersion":"2025-11-25","capabilities":{},"clientInfo":{"name":"curl-test","version":"0.1.0"}}}',
				'```',
				'',
				'```json',
				'{"jsonrpc":"2.0","id":2,"method":"resources/list","params":{}}',
				'```',
				'',
				'```json',
				'{"jsonrpc":"2.0","id":3,"method":"resources/read","params":{"uri":"' . $admin_url . '"}}',
				'```',
				'',
			)
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
