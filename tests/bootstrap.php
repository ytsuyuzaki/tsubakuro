<?php
/**
 * Minimal WordPress test doubles for exercising plugin code without a full
 * WordPress install.
 */

define( 'ABSPATH', dirname( __DIR__ ) . '/tests/wordpress/' );
define( 'ARRAY_A', 'ARRAY_A' );

/**
 * Minimal $wpdb mock that stores comments in the test globals.
 */
class MockWpdb {
	public $prefix    = 'wp_';
	public $insert_id = 0;

	public function insert( $table, $data, $format = null ) {
		if ( ! empty( $GLOBALS['tsubakuro_test']['wpdb_insert_fail'] ) ) {
			return false;
		}
		$next_id          = count( $GLOBALS['tsubakuro_test']['comments'] ) + 1;
		$this->insert_id  = $next_id;
		$GLOBALS['tsubakuro_test']['comments'][ $next_id ] = array_merge( array( 'id' => $next_id ), $data );
		return 1;
	}

	// phpcs:ignore Squiz.Commenting.FunctionComment.Missing
	public function get_row( $sql, $output_type = null ) {
		return $GLOBALS['tsubakuro_test']['wpdb_row'] ?? null;
	}

	// phpcs:ignore Squiz.Commenting.FunctionComment.Missing
	public function get_results( $sql, $output_type = null ) {
		return $GLOBALS['tsubakuro_test']['wpdb_rows'] ?? array();
	}

	// phpcs:ignore Squiz.Commenting.FunctionComment.Missing
	public function prepare( $sql, ...$args ) {
		return $sql;
	}

	// phpcs:ignore Squiz.Commenting.FunctionComment.Missing
	public function get_charset_collate() {
		return '';
	}
}

if ( ! class_exists( 'WP_REST_Server' ) ) {
	class WP_REST_Server {
		const READABLE  = 'GET';
		const CREATABLE = 'POST';
		const EDITABLE  = 'PUT';
		const DELETABLE = 'DELETE';
	}
}

if ( ! class_exists( 'WP_REST_Request' ) ) {
	/**
	 * Minimal WP_REST_Request stub that supports both get_param() and
	 * array-style access ($request['id']).
	 */
	class WP_REST_Request implements ArrayAccess {
		private $params;
		private $json_params;

		public function __construct( $params = array(), $json_params = null ) {
			$this->params      = $params;
			$this->json_params = $json_params;
		}

		public function get_param( $key ) {
			return array_key_exists( $key, $this->params ) ? $this->params[ $key ] : null;
		}

		public function get_json_params() {
			return $this->json_params;
		}

		// ArrayAccess.
		#[\ReturnTypeWillChange]
		public function offsetExists( $key ) { return isset( $this->params[ $key ] ); }

		#[\ReturnTypeWillChange]
		public function offsetGet( $key ) { return $this->params[ $key ] ?? null; }

		#[\ReturnTypeWillChange]
		public function offsetSet( $key, $value ) { $this->params[ $key ] = $value; }

		#[\ReturnTypeWillChange]
		public function offsetUnset( $key ) { unset( $this->params[ $key ] ); }
	}
}

if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {
		private $code;
		private $message;
		private $data;

		public function __construct( $code = '', $message = '', $data = array() ) {
			$this->code    = $code;
			$this->message = $message;
			$this->data    = $data;
		}

		public function get_error_message() {
			return $this->message;
		}

		public function get_error_code() {
			return $this->code;
		}

		public function get_error_data() {
			return $this->data;
		}
	}
}

class WP_Query {
	public $posts = array();

	public function __construct( $args ) {
		$GLOBALS['tsubakuro_test']['last_query_args'] = $args;
		$this->posts                                  = array_values(
			array_filter(
				$GLOBALS['tsubakuro_test']['posts'],
				function ( $post ) use ( $args ) {
					return ( $args['post_type'] ?? '' ) === $post->post_type;
				}
			)
		);
	}
}

function tsubakuro_test_reset() {
	global $wpdb;
	$GLOBALS['tsubakuro_test'] = array(
		'actions'            => array(),
		'activation_hooks'   => array(),
		'deactivation_hooks' => array(),
		'post_types'         => array(),
		'rest_routes'        => array(),
		'posts'              => array(),
		'post_meta'          => array(),
		'users'              => array(),
		'can'                => array(
			'edit_posts'   => true,
			'delete_posts' => true,
		),
		'last_query_args'    => array(),
		'comments'           => array(),
		'wpdb_row'           => null,
		'wpdb_rows'          => array(),
		'wpdb_insert_fail'   => false,
		'is_admin'           => false,
		'is_logged_in'       => false,
		'enqueued_scripts'   => array(),
		'enqueued_styles'    => array(),
		'redirected_to'      => null,
	);
	$wpdb = new MockWpdb();
}

function plugin_dir_path( $file ) {
	return dirname( $file ) . '/';
}

function plugin_dir_url( $file ) {
	return 'https://example.test/wp-content/plugins/' . basename( dirname( $file ) ) . '/';
}

function plugin_basename( $file ) {
	return basename( dirname( $file ) ) . '/' . basename( $file );
}

function register_activation_hook( $file, $callback ) {
	$GLOBALS['tsubakuro_test']['activation_hooks'][] = array( $file, $callback );
}

function register_deactivation_hook( $file, $callback ) {
	$GLOBALS['tsubakuro_test']['deactivation_hooks'][] = array( $file, $callback );
}

function add_action( $hook, $callback ) {
	$GLOBALS['tsubakuro_test']['actions'][ $hook ][] = $callback;
}

function register_post_type( $post_type, $args ) {
	$GLOBALS['tsubakuro_test']['post_types'][ $post_type ] = $args;
}

function register_rest_route( $namespace, $route, $args ) {
	$GLOBALS['tsubakuro_test']['rest_routes'][ $namespace . $route ] = $args;
}

function current_user_can( $capability ) {
	return ! empty( $GLOBALS['tsubakuro_test']['can'][ $capability ] );
}

function sanitize_text_field( $value ) {
	return trim( wp_strip_all_tags( (string) $value ) );
}

function sanitize_textarea_field( $value ) {
	return trim( wp_strip_all_tags( (string) $value ) );
}

function wp_strip_all_tags( $value ) {
	return strip_tags( (string) $value );
}

function absint( $value ) {
	return max( 0, abs( (int) $value ) );
}

function wp_kses_post( $value ) {
	return (string) $value;
}

function update_post_meta( $post_id, $key, $value ) {
	$GLOBALS['tsubakuro_test']['post_meta'][ $post_id ][ $key ] = array( $value );
}

function add_post_meta( $post_id, $key, $value ) {
	$GLOBALS['tsubakuro_test']['post_meta'][ $post_id ][ $key ][] = $value;
}

function delete_post_meta( $post_id, $key ) {
	unset( $GLOBALS['tsubakuro_test']['post_meta'][ $post_id ][ $key ] );
}

function get_post_meta( $post_id, $key, $single = false ) {
	$values = $GLOBALS['tsubakuro_test']['post_meta'][ $post_id ][ $key ] ?? array();
	if ( $single ) {
		return $values[0] ?? '';
	}
	return $values;
}

function get_post( $post_id ) {
	return $GLOBALS['tsubakuro_test']['posts'][ $post_id ] ?? null;
}

function get_user_by( $field, $value ) {
	if ( 'id' !== $field ) {
		return false;
	}
	return $GLOBALS['tsubakuro_test']['users'][ (int) $value ] ?? false;
}

function get_users() {
	return array_values( $GLOBALS['tsubakuro_test']['users'] );
}

function rest_ensure_response( $response ) {
	return $response;
}

function is_wp_error( $thing ) {
	return $thing instanceof WP_Error;
}

function wp_insert_post() {
	return 123;
}

function wp_update_post() {
	return 123;
}

function wp_delete_post() {
	return true;
}

function get_current_user_id() {
	return 7;
}

function is_admin() {
	return ! empty( $GLOBALS['tsubakuro_test']['is_admin'] );
}

function wp_enqueue_style( $handle, ...$args ) {
	$GLOBALS['tsubakuro_test']['enqueued_styles'][] = $handle;
}
function wp_enqueue_script( $handle, ...$args ) {
	$GLOBALS['tsubakuro_test']['enqueued_scripts'][] = $handle;
}
function wp_localize_script() {}
function admin_url( $path = '' ) {
	return 'https://example.test/wp-admin/' . $path; }
function rest_url( $path = '' ) {
	return 'https://example.test/wp-json/' . $path; }
function wp_create_nonce() {
	return 'nonce'; }
function get_queried_object_id() {
	return 55; }
function add_menu_page() {}
function add_submenu_page() {}
function check_ajax_referer() {}
function check_admin_referer() {}
function wp_send_json_error() {}
function wp_send_json_success() {}
function current_time() {
	return '2026-05-02 00:00:00'; }
function add_option() {}
function is_user_logged_in() {
	return ! empty( $GLOBALS['tsubakuro_test']['is_logged_in'] );
}
function get_post_types( $args = array(), $output = 'names' ) {
	return $GLOBALS['tsubakuro_test']['public_post_types'] ?? array( 'post', 'page' );
}
function get_permalink( $post_id = 0 ) {
	return 'https://example.test/?p=' . (int) $post_id;
}
function esc_html__( $text, $domain = 'default' ) {
	return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
}

tsubakuro_test_reset();
require_once dirname( __DIR__ ) . '/tsubakuro.php';
$GLOBALS['tsubakuro_test_bootstrap_state'] = $GLOBALS['tsubakuro_test'];
