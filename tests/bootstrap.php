<?php
/**
 * Minimal WordPress test doubles for exercising plugin code without a full
 * WordPress install.
 */

define( 'ABSPATH', dirname( __DIR__ ) . '/tests/wordpress/' );
define( 'ARRAY_A', 'ARRAY_A' );

if ( ! class_exists( 'WP_REST_Server' ) ) {
	class WP_REST_Server {
		const READABLE  = 'GET';
		const CREATABLE = 'POST';
		const EDITABLE  = 'PUT';
		const DELETABLE = 'DELETE';
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
	);
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
	return false;
}

function wp_enqueue_style() {}
function wp_enqueue_script() {}
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
function wp_send_json_error() {}
function wp_send_json_success() {}
function current_time() {
	return '2026-05-02 00:00:00'; }
function add_option() {}

tsubakuro_test_reset();
require_once dirname( __DIR__ ) . '/tsubakuro.php';
$GLOBALS['tsubakuro_test_bootstrap_state'] = $GLOBALS['tsubakuro_test'];
