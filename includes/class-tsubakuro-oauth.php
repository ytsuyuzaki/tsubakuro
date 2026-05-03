<?php
/**
 * OAuth 2.0 client credentials authentication for the MCP endpoint.
 *
 * Provides:
 *   POST /wp-json/tsubakuro/v1/oauth/token
 *     – Exchange client_id / client_secret for a short-lived Bearer token
 *       (grant_type=client_credentials).
 *
 * Registered Bearer tokens are accepted transparently on all protected
 * endpoints (including MCP) via the determine_current_user filter.
 *
 * Admin-only AJAX actions for managing clients:
 *   tsubakuro_generate_oauth_client – create a new client credential pair
 *   tsubakuro_revoke_oauth_client   – delete an existing client
 *
 * @package Tsubakuro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * OAuth 2.0 client credentials flow for MCP authentication.
 */
class Tsubakuro_OAuth {

	/** WordPress option key that stores registered clients. */
	const OPTION_CLIENTS = 'tsubakuro_oauth_clients';

	/** Transient key prefix for issued tokens. */
	const TRANSIENT_PREFIX = 'tsubakuro_tok_';

	/** Access token lifetime in seconds (1 hour). */
	const TOKEN_EXPIRY = 3600;

	// -------------------------------------------------------------------------
	// Bootstrap
	// -------------------------------------------------------------------------

	/**
	 * Register all WordPress hooks.
	 */
	public static function init() {
		add_filter( 'determine_current_user', array( __CLASS__, 'authenticate_bearer_token' ) );
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
		add_action( 'wp_ajax_tsubakuro_generate_oauth_client', array( __CLASS__, 'ajax_generate_client' ) );
		add_action( 'wp_ajax_tsubakuro_revoke_oauth_client', array( __CLASS__, 'ajax_revoke_client' ) );
	}

	// -------------------------------------------------------------------------
	// REST route: token endpoint
	// -------------------------------------------------------------------------

	/**
	 * Register the /oauth/token REST route.
	 */
	public static function register_routes() {
		register_rest_route(
			Tsubakuro_REST_API::NAMESPACE,
			'/oauth/token',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'handle_token' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * POST /oauth/token – issue a Bearer access token.
	 *
	 * Accepts JSON body or form-encoded body:
	 *   grant_type    = "client_credentials"
	 *   client_id     = registered client ID
	 *   client_secret = registered client secret (plain text)
	 *
	 * @param WP_REST_Request $request Incoming REST request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function handle_token( $request ) {
		$params = $request->get_json_params();
		if ( empty( $params ) ) {
			$params = array(
				'grant_type'    => $request->get_param( 'grant_type' ),
				'client_id'     => $request->get_param( 'client_id' ),
				'client_secret' => $request->get_param( 'client_secret' ),
			);
		}

		$grant_type    = $params['grant_type'] ?? '';
		$client_id     = $params['client_id'] ?? '';
		$client_secret = $params['client_secret'] ?? '';

		if ( 'client_credentials' !== $grant_type ) {
			return new WP_Error(
				'unsupported_grant_type',
				'サポートされていない grant_type です。client_credentials を指定してください。',
				array( 'status' => 400 )
			);
		}

		if ( empty( $client_id ) || empty( $client_secret ) ) {
			return new WP_Error(
				'invalid_request',
				'client_id と client_secret は必須です。',
				array( 'status' => 400 )
			);
		}

		$client = self::find_client( $client_id );
		if ( ! $client ) {
			return new WP_Error(
				'invalid_client',
				'クライアントが見つかりません。',
				array( 'status' => 401 )
			);
		}

		if ( ! wp_check_password( $client_secret, $client['secret_hash'] ) ) {
			return new WP_Error(
				'invalid_client',
				'クライアントシークレットが正しくありません。',
				array( 'status' => 401 )
			);
		}

		$token = self::issue_token( $client );

		return rest_ensure_response(
			array(
				'access_token' => $token,
				'token_type'   => 'Bearer',
				'expires_in'   => self::TOKEN_EXPIRY,
			)
		);
	}

	// -------------------------------------------------------------------------
	// WordPress authentication filter
	// -------------------------------------------------------------------------

	/**
	 * Determine_current_user filter: authenticate via Bearer token.
	 *
	 * Returns the WordPress user ID stored in the token when a valid
	 * Bearer token is present in the Authorization header and no other
	 * authentication method has already identified a user.
	 *
	 * @param int|false $user_id User ID already determined by other mechanisms.
	 * @return int|false
	 */
	public static function authenticate_bearer_token( $user_id ) {
		if ( $user_id ) {
			return $user_id;
		}

		$token = self::get_bearer_token_from_header();
		if ( ! $token ) {
			return $user_id;
		}

		$token_data = get_transient( self::TRANSIENT_PREFIX . hash( 'sha256', $token ) );
		if ( ! $token_data ) {
			return $user_id;
		}

		return (int) $token_data['user_id'];
	}

	// -------------------------------------------------------------------------
	// Client management (admin)
	// -------------------------------------------------------------------------

	/**
	 * Generate and persist a new OAuth client credential pair.
	 *
	 * Returns the new client array including the plain-text client_secret
	 * (shown once; not stored in plain text).
	 *
	 * @param string $name    Human-readable client name.
	 * @param int    $user_id WordPress user ID to associate with this client.
	 * @return array Client data including client_secret.
	 */
	public static function generate_client( $name, $user_id ) {
		$client_id     = bin2hex( random_bytes( 16 ) );
		$client_secret = bin2hex( random_bytes( 32 ) );

		$client = array(
			'client_id'   => $client_id,
			'secret_hash' => wp_hash_password( $client_secret ),
			'name'        => sanitize_text_field( $name ),
			'user_id'     => (int) $user_id,
			'created_at'  => current_time( 'mysql' ),
		);

		$clients   = get_option( self::OPTION_CLIENTS, array() );
		$clients[] = $client;
		update_option( self::OPTION_CLIENTS, $clients );

		return array_merge( $client, array( 'client_secret' => $client_secret ) );
	}

	/**
	 * Return all registered OAuth clients (secrets excluded).
	 *
	 * @return array
	 */
	public static function get_clients() {
		return get_option( self::OPTION_CLIENTS, array() );
	}

	/**
	 * Revoke an OAuth client by removing it from storage.
	 *
	 * @param string $client_id Client ID to remove.
	 */
	public static function revoke_client( $client_id ) {
		$clients = get_option( self::OPTION_CLIENTS, array() );
		$clients = array_values(
			array_filter(
				$clients,
				static function ( $c ) use ( $client_id ) {
					return $c['client_id'] !== $client_id;
				}
			)
		);
		update_option( self::OPTION_CLIENTS, $clients );
	}

	// -------------------------------------------------------------------------
	// AJAX handlers (admin-only)
	// -------------------------------------------------------------------------

	/**
	 * AJAX: generate a new OAuth client credential pair.
	 */
	public static function ajax_generate_client() {
		check_ajax_referer( 'tsubakuro_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => '権限がありません。' ), 403 );
		}

		$name = sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) );
		if ( empty( $name ) ) {
			wp_send_json_error( array( 'message' => 'クライアント名は必須です。' ), 400 );
		}

		$client = self::generate_client( $name, get_current_user_id() );
		wp_send_json_success( $client );
	}

	/**
	 * AJAX: revoke an existing OAuth client.
	 */
	public static function ajax_revoke_client() {
		check_ajax_referer( 'tsubakuro_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => '権限がありません。' ), 403 );
		}

		$client_id = sanitize_text_field( wp_unslash( $_POST['client_id'] ?? '' ) );
		if ( empty( $client_id ) ) {
			wp_send_json_error( array( 'message' => 'client_id は必須です。' ), 400 );
		}

		self::revoke_client( $client_id );
		wp_send_json_success( array( 'revoked' => true ) );
	}

	// -------------------------------------------------------------------------
	// Private helpers
	// -------------------------------------------------------------------------

	/**
	 * Find a registered client by client_id.
	 *
	 * @param string $client_id Client ID to look up.
	 * @return array|null Client record or null if not found.
	 */
	private static function find_client( $client_id ) {
		foreach ( get_option( self::OPTION_CLIENTS, array() ) as $client ) {
			if ( $client['client_id'] === $client_id ) {
				return $client;
			}
		}
		return null;
	}

	/**
	 * Issue an access token for the given client and store it as a transient.
	 *
	 * @param array $client Client record (must contain user_id and client_id).
	 * @return string Plain-text access token.
	 */
	private static function issue_token( $client ) {
		$token     = bin2hex( random_bytes( 32 ) );
		$token_key = self::TRANSIENT_PREFIX . hash( 'sha256', $token );

		set_transient(
			$token_key,
			array(
				'client_id' => $client['client_id'],
				'user_id'   => (int) $client['user_id'],
			),
			self::TOKEN_EXPIRY
		);

		return $token;
	}

	/**
	 * Extract a Bearer token from the HTTP Authorization header.
	 *
	 * @return string|null Token string or null if not present/not a Bearer token.
	 */
	private static function get_bearer_token_from_header() {
		$header = '';

		if ( ! empty( $_SERVER['HTTP_AUTHORIZATION'] ) ) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Bearer token value validated via regex before use.
			$header = wp_unslash( (string) $_SERVER['HTTP_AUTHORIZATION'] );
		} elseif ( ! empty( $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ) ) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Bearer token value validated via regex before use.
			$header = wp_unslash( (string) $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] );
		}

		if ( '' === $header ) {
			return null;
		}

		if ( preg_match( '/^Bearer\s+(\S+)$/i', trim( $header ), $matches ) ) {
			return $matches[1];
		}

		return null;
	}
}
