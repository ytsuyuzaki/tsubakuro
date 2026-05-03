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
	const PROTOCOL_VERSION = '2024-11-05';
	const SERVER_NAME      = 'tsubakuro-wordpress-mcp';

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
	 * @return WP_REST_Response|array
	 */
	public static function handle_get() {
		if ( ! self::check_permission() ) {
			return self::jsonrpc_response( self::error_response( null, -32001, 'Unauthorized' ), 401 );
		}

		return self::json_response( self::get_manifest() );
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
			if ( empty( $body ) ) {
				return self::jsonrpc_response( self::error_response( null, -32600, 'Invalid Request' ), 400 );
			}

			$responses = array();
			foreach ( $body as $single ) {
				$response = self::dispatch( $single );
				if ( null !== $response ) {
					$responses[] = $response;
				}
			}

			if ( empty( $responses ) ) {
				return self::json_response( null, 202 );
			}

			return self::jsonrpc_response( $responses );
		}

		$response = self::dispatch( $body );
		if ( null === $response ) {
			return self::json_response( null, 202 );
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
		$invalid_envelope = ( $rpc['jsonrpc'] ?? null ) !== '2.0' || ! is_string( $method ) || '' === $method;

		if ( $invalid_envelope ) {
			return self::error_response( $id, -32600, 'Invalid Request' );
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
						'resources' => array(),
					)
				);

			case 'prompts/list':
				return self::success_response(
					$id,
					array(
						'prompts' => array(),
					)
				);

			default:
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

		if ( 'ping' !== $params['name'] ) {
			return self::error_response( $id, -32602, 'Unknown tool: ' . sanitize_text_field( $params['name'] ) );
		}

		return self::success_response(
			$id,
			array(
				'content' => array(
					array(
						'type' => 'text',
						'text' => 'pong',
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
				'name'        => 'ping',
				'description' => '接続確認用',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => (object) array(),
				),
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
	 * the REST callback runs. Bearer tokens issued by this plugin are accepted
	 * as an additional transport-friendly option.
	 *
	 * @return bool
	 */
	public static function check_permission() {
		$authorization = self::get_authorization_header();

		if ( '' === $authorization ) {
			return false;
		}

		if ( preg_match( '/^Bearer\s+\S+$/i', $authorization ) ) {
			return Tsubakuro_OAuth::has_valid_bearer_token() && current_user_can( 'edit_posts' );
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
}
