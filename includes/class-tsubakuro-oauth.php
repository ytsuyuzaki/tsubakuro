<?php
/**
 * OAuth 2.0 authentication for the MCP endpoint.
 *
 * Provides:
 *   POST /wp-json/tsubakuro/v1/oauth/token
 *     – Exchange client_id / client_secret for a short-lived Bearer token
 *       (grant_type=client_credentials).
 *     – Exchange an authorization code for a Bearer token
 *       (grant_type=authorization_code).
 *
 *   GET  /wp-json/tsubakuro/v1/oauth/authorize
 *     – Display the user-consent page (authorization code flow).
 *
 *   GET  /wp-json/tsubakuro/v1/oauth/metadata
 *     – Return OAuth 2.0 server metadata (RFC 8414) for discovery.
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
 * OAuth 2.0 client credentials and authorization code flow for MCP authentication.
 */
class Tsubakuro_OAuth {

	/** WordPress option key that stores registered clients. */
	const OPTION_CLIENTS = 'tsubakuro_oauth_clients';

	/** Transient key prefix for issued tokens. */
	const TRANSIENT_PREFIX = 'tsubakuro_tok_';

	/** Transient key prefix for authorization codes. */
	const TRANSIENT_CODE_PREFIX = 'tsubakuro_code_';

	/** WordPress option key that stores issued grants. */
	const OPTION_GRANTS = 'tsubakuro_oauth_grants';

	/** Access token lifetime in seconds (1 hour). */
	const TOKEN_EXPIRY = 3600;

	/** Authorization code lifetime in seconds (10 minutes). */
	const CODE_EXPIRY = 600;

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
		add_action( 'wp_ajax_tsubakuro_revoke_grant', array( __CLASS__, 'ajax_revoke_grant' ) );
		add_action( 'admin_post_tsubakuro_oauth_consent', array( __CLASS__, 'handle_consent_post' ) );
		add_action( 'admin_post_nopriv_tsubakuro_oauth_consent', array( __CLASS__, 'handle_nopriv_consent' ) );
	}

	// -------------------------------------------------------------------------
	// REST route: token endpoint
	// -------------------------------------------------------------------------

	/**
	 * Register the OAuth REST routes.
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

		register_rest_route(
			Tsubakuro_REST_API::NAMESPACE,
			'/oauth/authorize',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'handle_authorize' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			Tsubakuro_REST_API::NAMESPACE,
			'/oauth/metadata',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'handle_metadata' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * POST /oauth/token – issue a Bearer access token.
	 *
	 * Accepts JSON body or form-encoded body.
	 *
	 * For grant_type=client_credentials:
	 *   grant_type    = "client_credentials"
	 *   client_id     = registered client ID
	 *   client_secret = registered client secret (plain text)
	 *
	 * For grant_type=authorization_code:
	 *   grant_type    = "authorization_code"
	 *   code          = authorization code issued by the authorize endpoint
	 *   redirect_uri  = must match the redirect_uri used when requesting the code
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
				'code'          => $request->get_param( 'code' ),
				'redirect_uri'  => $request->get_param( 'redirect_uri' ),
			);
		}

		$grant_type = $params['grant_type'] ?? '';

		if ( 'client_credentials' === $grant_type ) {
			return self::handle_token_client_credentials( $params );
		}

		if ( 'authorization_code' === $grant_type ) {
			return self::handle_token_authorization_code( $params );
		}

		return new WP_Error(
			'unsupported_grant_type',
			'サポートされていない grant_type です。client_credentials または authorization_code を指定してください。',
			array( 'status' => 400 )
		);
	}

	/**
	 * Handle client_credentials grant type.
	 *
	 * @param array $params Request parameters.
	 * @return array|WP_Error
	 */
	private static function handle_token_client_credentials( array $params ) {
		$client_id     = $params['client_id'] ?? '';
		$client_secret = $params['client_secret'] ?? '';

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

	/**
	 * Handle authorization_code grant type.
	 *
	 * @param array $params Request parameters.
	 * @return array|WP_Error
	 */
	private static function handle_token_authorization_code( array $params ) {
		$client_id     = $params['client_id'] ?? '';
		$client_secret = $params['client_secret'] ?? '';
		$code          = $params['code'] ?? '';
		$redirect_uri  = $params['redirect_uri'] ?? '';

		if ( empty( $client_id ) || empty( $client_secret ) ) {
			return new WP_Error(
				'invalid_request',
				'client_id と client_secret は必須です。',
				array( 'status' => 400 )
			);
		}

		if ( empty( $code ) ) {
			return new WP_Error(
				'invalid_request',
				'code は必須です。',
				array( 'status' => 400 )
			);
		}

		if ( empty( $redirect_uri ) ) {
			return new WP_Error(
				'invalid_request',
				'redirect_uri は必須です。',
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

		$code_data = get_transient( self::TRANSIENT_CODE_PREFIX . hash( 'sha256', $code ) );
		if ( ! $code_data ) {
			return new WP_Error(
				'invalid_grant',
				'認証コードが無効または期限切れです。',
				array( 'status' => 400 )
			);
		}

		if ( $code_data['client_id'] !== $client_id ) {
			return new WP_Error(
				'invalid_grant',
				'認証コードはこのクライアントに発行されていません。',
				array( 'status' => 400 )
			);
		}

		if ( $code_data['redirect_uri'] !== $redirect_uri ) {
			return new WP_Error(
				'invalid_grant',
				'redirect_uri が認証コード発行時と一致しません。',
				array( 'status' => 400 )
			);
		}

		// Consume the code (one-time use).
		delete_transient( self::TRANSIENT_CODE_PREFIX . hash( 'sha256', $code ) );

		$token = self::issue_token(
			array(
				'client_id' => $client_id,
				'user_id'   => (int) $code_data['user_id'],
			)
		);

		self::record_grant( $client_id, (int) $code_data['user_id'], $token );

		return rest_ensure_response(
			array(
				'access_token' => $token,
				'token_type'   => 'Bearer',
				'expires_in'   => self::TOKEN_EXPIRY,
			)
		);
	}

	// -------------------------------------------------------------------------
	// REST route: authorize endpoint (authorization code flow)
	// -------------------------------------------------------------------------

	/**
	 * GET /oauth/authorize – display the user consent page.
	 *
	 * Query parameters:
	 *   response_type = "code"
	 *   client_id     = registered client ID
	 *   redirect_uri  = must match the registered redirect_uri of the client
	 *   state         = opaque value passed through to the redirect (recommended)
	 *
	 * When the user is not logged in they are redirected to the WordPress login
	 * page.  After login they are redirected back here with the same parameters.
	 *
	 * On success this method outputs an HTML consent page and exits; it does
	 * NOT return a REST response object.
	 *
	 * @param WP_REST_Request $request Incoming REST request.
	 * @return WP_Error|void Returns WP_Error for invalid input; otherwise outputs HTML and exits.
	 */
	public static function handle_authorize( $request ) {
		$response_type = sanitize_text_field( (string) ( $request->get_param( 'response_type' ) ?? '' ) );
		$client_id     = sanitize_text_field( (string) ( $request->get_param( 'client_id' ) ?? '' ) );
		$redirect_uri  = esc_url_raw( (string) ( $request->get_param( 'redirect_uri' ) ?? '' ) );
		$state         = sanitize_text_field( (string) ( $request->get_param( 'state' ) ?? '' ) );

		if ( 'code' !== $response_type ) {
			return new WP_Error(
				'unsupported_response_type',
				'サポートされていない response_type です。response_type=code を指定してください。',
				array( 'status' => 400 )
			);
		}

		if ( empty( $client_id ) ) {
			return new WP_Error(
				'invalid_request',
				'client_id は必須です。',
				array( 'status' => 400 )
			);
		}

		$client = self::find_client( $client_id );
		if ( ! $client ) {
			return new WP_Error(
				'invalid_client',
				'クライアントが見つかりません。',
				array( 'status' => 400 )
			);
		}

		if ( empty( $redirect_uri ) ) {
			return new WP_Error(
				'invalid_request',
				'redirect_uri は必須です。',
				array( 'status' => 400 )
			);
		}

		if ( ! self::validate_redirect_uri( $client, $redirect_uri ) ) {
			return new WP_Error(
				'invalid_redirect_uri',
				'redirect_uri が登録された値と一致しません。',
				array( 'status' => 400 )
			);
		}

		if ( ! is_user_logged_in() ) {
			$authorize_url = add_query_arg(
				array(
					'response_type' => 'code',
					'client_id'     => rawurlencode( $client_id ),
					'redirect_uri'  => rawurlencode( $redirect_uri ),
					'state'         => rawurlencode( $state ),
				),
				rest_url( Tsubakuro_REST_API::NAMESPACE . '/oauth/authorize' )
			);
			wp_safe_redirect( wp_login_url( $authorize_url ) );
			exit;
		}

		// User is already logged in: issue an authorization code immediately
		// without showing a separate consent form.  This is intentional:
		// trusted WordPress users accessing this site's own MCP server are
		// considered pre-consented; they can revoke access at any time via
		// the "認証済みの接続" section on the Settings page.
		$redirect_url = self::build_auto_approve_redirect( $client, get_current_user_id(), $redirect_uri, $state );

		// phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- redirect_uri was validated against the registered client value.
		wp_redirect( $redirect_url );
		exit;
	}

	/**
	 * Build the redirect URL for the auto-approve authorization code flow.
	 *
	 * Issues an authorization code for the given user and returns the redirect
	 * URL (redirect_uri with code and optional state appended).
	 *
	 * Separated from handle_authorize() to allow unit-testing the business
	 * logic without triggering the exit() call.
	 *
	 * @param array  $client       Registered client record.
	 * @param int    $user_id      WordPress user ID.
	 * @param string $redirect_uri Validated redirect URI for this client.
	 * @param string $state        Opaque state value from the request.
	 * @return string Redirect URL.
	 */
	public static function build_auto_approve_redirect( $client, $user_id, $redirect_uri, $state ) {
		$code            = self::issue_code( $client, $user_id, $redirect_uri );
		$redirect_params = array( 'code' => $code );
		if ( $state ) {
			$redirect_params['state'] = $state;
		}
		return add_query_arg( $redirect_params, $redirect_uri );
	}

	// -------------------------------------------------------------------------
	// admin-post.php handlers: consent form submission
	// -------------------------------------------------------------------------

	/**
	 * Handle consent form POST for logged-in users.
	 *
	 * Validates the nonce and the user's approval/denial, then either issues
	 * an authorization code and redirects or calls wp_die on error.
	 */
	public static function handle_consent_post() {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- nonce is validated manually below using wp_verify_nonce.
		$client_id    = isset( $_POST['client_id'] ) ? sanitize_text_field( wp_unslash( $_POST['client_id'] ) ) : '';
		$redirect_uri = isset( $_POST['redirect_uri'] ) ? esc_url_raw( wp_unslash( $_POST['redirect_uri'] ) ) : '';
		$state        = isset( $_POST['state'] ) ? sanitize_text_field( wp_unslash( $_POST['state'] ) ) : '';
		$action       = isset( $_POST['consent_action'] ) ? sanitize_key( wp_unslash( $_POST['consent_action'] ) ) : '';
		$nonce        = isset( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		$redirect_url = self::process_consent( $client_id, $redirect_uri, $state, $action, $nonce );
		if ( is_wp_error( $redirect_url ) ) {
			wp_die( esc_html( $redirect_url->get_error_message() ), (int) ( $redirect_url->get_error_data()['status'] ?? 400 ) );
			return;
		}

		// phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- redirect_uri was validated against the registered client value.
		wp_redirect( $redirect_url );
		exit;
	}

	/**
	 * Build the redirect URL for a consent form submission (testable business logic).
	 *
	 * Returns the redirect URL string on success, or a WP_Error when the
	 * request cannot be processed (nonce failure, missing data, etc.).
	 *
	 * @param string $client_id    Registered OAuth client ID.
	 * @param string $redirect_uri The redirect URI from the form.
	 * @param string $state        Opaque state value to pass through.
	 * @param string $action       'approve' or anything else (treated as deny).
	 * @param string $nonce        WordPress nonce value to verify.
	 * @return string|WP_Error Redirect URL or WP_Error on failure.
	 */
	public static function process_consent( $client_id, $redirect_uri, $state, $action, $nonce ) {
		if ( ! wp_verify_nonce( $nonce, 'tsubakuro_oauth_consent_' . $client_id ) ) {
			return new WP_Error( 'invalid_nonce', 'セキュリティチェックに失敗しました。', array( 'status' => 403 ) );
		}

		if ( ! is_user_logged_in() ) {
			return new WP_Error( 'not_logged_in', 'ログインが必要です。', array( 'status' => 401 ) );
		}

		if ( empty( $redirect_uri ) ) {
			return new WP_Error( 'invalid_request', 'redirect_uri が指定されていません。', array( 'status' => 400 ) );
		}

		$redirect_params = array();
		if ( $state ) {
			$redirect_params['state'] = $state;
		}

		if ( 'approve' !== $action ) {
			$redirect_params['error']             = 'access_denied';
			$redirect_params['error_description'] = 'ユーザーがアクセスを拒否しました。';
			return add_query_arg( $redirect_params, $redirect_uri );
		}

		$client = self::find_client( $client_id );
		if ( ! $client ) {
			return new WP_Error( 'invalid_client', 'クライアントが見つかりません。', array( 'status' => 400 ) );
		}

		if ( ! self::validate_redirect_uri( $client, $redirect_uri ) ) {
			return new WP_Error( 'invalid_redirect_uri', 'redirect_uri が登録された値と一致しません。', array( 'status' => 400 ) );
		}

		$code                    = self::issue_code( $client, get_current_user_id(), $redirect_uri );
		$redirect_params['code'] = $code;

		return add_query_arg( $redirect_params, $redirect_uri );
	}

	/**
	 * Handle consent form POST for non-logged-in users (redirect to login).
	 */
	public static function handle_nopriv_consent() {
		wp_safe_redirect( wp_login_url( admin_url( 'admin.php?page=tsubakuro-settings' ) ) );
		exit;
	}

	// -------------------------------------------------------------------------
	// REST route: metadata endpoint
	// -------------------------------------------------------------------------

	/**
	 * GET /oauth/metadata – return OAuth 2.0 server metadata (RFC 8414).
	 *
	 * @return WP_REST_Response
	 */
	public static function handle_metadata() {
		$base = rest_url( Tsubakuro_REST_API::NAMESPACE );

		return rest_ensure_response(
			array(
				'issuer'                                => home_url(),
				'authorization_endpoint'                => $base . '/oauth/authorize',
				'token_endpoint'                        => $base . '/oauth/token',
				'response_types_supported'              => array( 'code' ),
				'grant_types_supported'                 => array( 'authorization_code', 'client_credentials' ),
				'code_challenge_methods_supported'      => array(),
				'token_endpoint_auth_methods_supported' => array( 'client_secret_post' ),
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
	 * @param string $name         Human-readable client name.
	 * @param int    $user_id      WordPress user ID to associate with this client.
	 * @param string $redirect_uri Optional redirect URI for the authorization code flow.
	 * @return array Client data including client_secret.
	 */
	public static function generate_client( $name, $user_id, $redirect_uri = '' ) {
		$client_id     = bin2hex( random_bytes( 16 ) );
		$client_secret = bin2hex( random_bytes( 32 ) );

		$client = array(
			'client_id'    => $client_id,
			'secret_hash'  => wp_hash_password( $client_secret ),
			'name'         => sanitize_text_field( $name ),
			'user_id'      => (int) $user_id,
			'redirect_uri' => esc_url_raw( (string) $redirect_uri ),
			'created_at'   => current_time( 'mysql' ),
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

		$name         = sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) );
		$redirect_uri = esc_url_raw( wp_unslash( $_POST['redirect_uri'] ?? '' ) );

		if ( empty( $name ) ) {
			wp_send_json_error( array( 'message' => 'クライアント名は必須です。' ), 400 );
		}

		$client = self::generate_client( $name, get_current_user_id(), $redirect_uri );
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
	// Grant management (per-user authorizations)
	// -------------------------------------------------------------------------

	/**
	 * Return all grants for a specific WordPress user.
	 *
	 * @param int $user_id WordPress user ID.
	 * @return array
	 */
	public static function get_user_grants( $user_id ) {
		$grants = get_option( self::OPTION_GRANTS, array() );
		return array_values(
			array_filter(
				$grants,
				static function ( $grant ) use ( $user_id ) {
					return (int) $grant['user_id'] === (int) $user_id;
				}
			)
		);
	}

	/**
	 * Revoke a grant: invalidate its access token and remove it from storage.
	 *
	 * The caller must ensure the current user is either the grant owner or
	 * has the manage_options capability before invoking this method.
	 *
	 * @param string $grant_id Grant ID to revoke.
	 * @param int    $user_id  User ID that owns the grant (used for ownership check).
	 * @return true|WP_Error True on success, WP_Error when the grant is not found
	 *                        or the caller does not have permission.
	 */
	public static function revoke_grant( $grant_id, $user_id ) {
		$grants  = get_option( self::OPTION_GRANTS, array() );
		$updated = array();
		$found   = false;

		foreach ( $grants as $grant ) {
			if ( $grant['grant_id'] === $grant_id ) {
				if ( (int) $grant['user_id'] !== (int) $user_id && ! current_user_can( 'manage_options' ) ) {
					return new WP_Error( 'forbidden', '権限がありません。', array( 'status' => 403 ) );
				}
				// Invalidate the stored access token.
				if ( ! empty( $grant['token_hash'] ) ) {
					delete_transient( self::TRANSIENT_PREFIX . $grant['token_hash'] );
				}
				$found = true;
				// Skip this grant so it is removed from the updated list.
				continue;
			}
			$updated[] = $grant;
		}

		if ( ! $found ) {
			return new WP_Error( 'not_found', '指定された認証が見つかりません。', array( 'status' => 404 ) );
		}

		update_option( self::OPTION_GRANTS, array_values( $updated ) );
		return true;
	}

	/**
	 * AJAX: revoke a grant (authorized connection) for the current user.
	 */
	public static function ajax_revoke_grant() {
		check_ajax_referer( 'tsubakuro_admin', 'nonce' );

		$grant_id = sanitize_text_field( wp_unslash( $_POST['grant_id'] ?? '' ) );
		if ( empty( $grant_id ) ) {
			wp_send_json_error( array( 'message' => 'grant_id は必須です。' ), 400 );
		}

		$result = self::revoke_grant( $grant_id, get_current_user_id() );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ), (int) ( $result->get_error_data()['status'] ?? 400 ) );
			return;
		}

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
	 * Issue a short-lived authorization code for the given client, user, and redirect URI.
	 *
	 * The code is stored as a transient with a sha256-hashed key so the raw code
	 * value is never retained at rest.
	 *
	 * @param array  $client       Client record (must contain client_id).
	 * @param int    $user_id      WordPress user ID who approved the request.
	 * @param string $redirect_uri The redirect_uri presented in the authorization request.
	 * @return string Plain-text authorization code.
	 */
	private static function issue_code( $client, $user_id, $redirect_uri ) {
		$code     = bin2hex( random_bytes( 32 ) );
		$code_key = self::TRANSIENT_CODE_PREFIX . hash( 'sha256', $code );

		set_transient(
			$code_key,
			array(
				'client_id'    => $client['client_id'],
				'user_id'      => (int) $user_id,
				'redirect_uri' => $redirect_uri,
			),
			self::CODE_EXPIRY
		);

		return $code;
	}

	/**
	 * Validate the redirect_uri against the one registered for the client.
	 *
	 * An exact string match is required (RFC 6749 §3.1.2.3).
	 *
	 * @param array  $client       Client record.
	 * @param string $redirect_uri The URI presented in the request.
	 * @return bool True when the URI is valid for this client.
	 */
	private static function validate_redirect_uri( $client, $redirect_uri ) {
		$registered = $client['redirect_uri'] ?? '';
		if ( empty( $registered ) ) {
			return false;
		}
		return $registered === $redirect_uri;
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
	 * Persist a grant record linking a client, user, and access token.
	 *
	 * Called after a token is successfully issued via the authorization_code
	 * flow so users can later view and revoke their authorized connections.
	 *
	 * @param string $client_id OAuth client ID.
	 * @param int    $user_id   WordPress user ID.
	 * @param string $token     Plain-text access token (stored only as a hash).
	 */
	private static function record_grant( $client_id, $user_id, $token ) {
		$grant_id = bin2hex( random_bytes( 8 ) );

		$grants   = get_option( self::OPTION_GRANTS, array() );
		$grants[] = array(
			'grant_id'   => $grant_id,
			'client_id'  => $client_id,
			'user_id'    => (int) $user_id,
			'token_hash' => hash( 'sha256', $token ),
			'created_at' => current_time( 'mysql' ),
		);
		update_option( self::OPTION_GRANTS, $grants );
	}

	/**
	 * Check whether the current request carries a valid Bearer token.
	 *
	 * Extracts the token from the Authorization header and validates it
	 * against the stored transient.  Returns true only when a token that
	 * was issued by the OAuth endpoint is found.
	 *
	 * @return bool
	 */
	public static function has_valid_bearer_token() {
		$token = self::get_bearer_token_from_header();
		if ( ! $token ) {
			return false;
		}

		return (bool) get_transient( self::TRANSIENT_PREFIX . hash( 'sha256', $token ) );
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
