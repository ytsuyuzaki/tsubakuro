<?php

/**
 * Minimal WordPress test doubles for exercising plugin code without a full
 * WordPress install.
 */

define('ABSPATH', dirname(__DIR__) . '/tests/wordpress/');
define('ARRAY_A', 'ARRAY_A');
define('MINUTE_IN_SECONDS', 60);

/**
 * Minimal $wpdb mock used by code paths that still need database-like helpers.
 */
class MockWpdb
{
	public $prefix    = 'wp_';
	public $insert_id = 0;

	public function insert($table, $data, $format = null)
	{
		if (! empty($GLOBALS['tsubakuro_test']['wpdb_insert_fail'])) {
			return false;
		}
		$next_id         = count($GLOBALS['tsubakuro_test']['wpdb_inserts']) + 1;
		$this->insert_id = $next_id;
		$GLOBALS['tsubakuro_test']['wpdb_inserts'][$next_id] = array_merge(array('id' => $next_id), $data);
		return 1;
	}

	/**
	 * Return a single row from the mock database store, or null if none is set.
	 *
	 * @param string $sql         Ignored in the test stub.
	 * @param string $output_type Ignored in the test stub.
	 * @return array|null
	 */
	public function get_row($sql, $output_type = null)
	{
		return $GLOBALS['tsubakuro_test']['wpdb_row'] ?? null;
	}

	/**
	 * Return multiple rows from the mock database store.
	 *
	 * @param string $sql         Ignored in the test stub.
	 * @param string $output_type Ignored in the test stub.
	 * @return array
	 */
	public function get_results($sql, $output_type = null)
	{
		return $GLOBALS['tsubakuro_test']['wpdb_rows'] ?? array();
	}

	/**
	 * No-op stub that returns the SQL string unchanged (no real parameter binding).
	 *
	 * @param string $sql  Query template.
	 * @param mixed  ...$args Ignored.
	 * @return string
	 */
	public function prepare($sql, ...$args)
	{
		return $sql;
	}

	/**
	 * Returns an empty string; the test environment has no real charset to report.
	 *
	 * @return string
	 */
	public function get_charset_collate()
	{
		return '';
	}
}

if (! class_exists('WP_REST_Server')) {
	class WP_REST_Server
	{
		const READABLE  = 'GET';
		const CREATABLE = 'POST';
		const EDITABLE  = 'PUT';
		const DELETABLE = 'DELETE';
	}
}

if (! class_exists('WP_REST_Request')) {
	/**
	 * Minimal WP_REST_Request stub that supports both get_param() and
	 * array-style access ($request['id']).
	 */
	class WP_REST_Request implements ArrayAccess
	{
		private $params;
		private $json_params;

		public function __construct($params = array(), $json_params = null)
		{
			$this->params      = $params;
			$this->json_params = $json_params;
		}

		public function get_param($key)
		{
			return array_key_exists($key, $this->params) ? $this->params[$key] : null;
		}

		public function get_json_params()
		{
			return $this->json_params;
		}

		// ArrayAccess.
		#[\ReturnTypeWillChange]
		public function offsetExists($key)
		{
			return isset($this->params[$key]);
		}

		#[\ReturnTypeWillChange]
		public function offsetGet($key)
		{
			return $this->params[$key] ?? null;
		}

		#[\ReturnTypeWillChange]
		public function offsetSet($key, $value)
		{
			$this->params[$key] = $value;
		}

		#[\ReturnTypeWillChange]
		public function offsetUnset($key)
		{
			unset($this->params[$key]);
		}
	}
}

if (! class_exists('WP_Error')) {
	class WP_Error
	{
		private $code;
		private $message;
		private $data;

		public function __construct($code = '', $message = '', $data = array())
		{
			$this->code    = $code;
			$this->message = $message;
			$this->data    = $data;
		}

		public function get_error_message()
		{
			return $this->message;
		}

		public function get_error_code()
		{
			return $this->code;
		}

		public function get_error_data()
		{
			return $this->data;
		}
	}
}

if (! class_exists('Tsubakuro_Test_Json_Response')) {
	class Tsubakuro_Test_Json_Response extends Exception
	{
	}
}

class WP_Query
{
	public $posts = array();

	public function __construct($args)
	{
		$GLOBALS['tsubakuro_test']['last_query_args'] = $args;
		$posts                                        = array_values(
			array_filter(
				$GLOBALS['tsubakuro_test']['posts'],
				function ($post) use ($args) {
					if (($args['post_type'] ?? '') !== $post->post_type) {
						return false;
					}

					$post_status = $post->post_status ?? 'publish';
					if (isset($args['post_status']) && 'any' !== $args['post_status']) {
						$allowed_statuses = is_array($args['post_status'])
							? $args['post_status']
							: array($args['post_status']);
						if ( ! in_array($post_status, $allowed_statuses, true) ) {
							return false;
						}
					}

					if (! empty($args['s']) && false === stripos($post->post_title . ' ' . $post->post_content, $args['s'])) {
						return false;
					}

					if (isset($args['post_parent']) && (int) $args['post_parent'] !== (int) ($post->post_parent ?? 0)) {
						return false;
					}

					foreach ($args['meta_query'] ?? array() as $meta_filter) {
						$values = $GLOBALS['tsubakuro_test']['post_meta'][$post->ID][$meta_filter['key']] ?? array();
						$compare = $meta_filter['compare'] ?? '=';
						$value   = $meta_filter['value'] ?? null;
						if ('<=' === $compare) {
							$matched = false;
							foreach ($values as $stored) {
								if ((string) $stored <= (string) $value) {
									$matched = true;
									break;
								}
							}
							if (! $matched) {
								return false;
							}
							continue;
						}

						if (! in_array($value, $values, false)) {
							return false;
						}
					}

					if (! empty($args['post__in']) && ! in_array((int) $post->ID, array_map('intval', $args['post__in']), true)) {
						return false;
					}

					return true;
				}
			)
		);

		usort(
			$posts,
			function ($a, $b) use ($args) {
				$orderby = $args['orderby'] ?? 'date';
				$order   = $args['order'] ?? 'DESC';

				if ('ID' === $orderby) {
					$left  = $a->ID;
					$right = $b->ID;
				} elseif ('title' === $orderby) {
					$left  = $a->post_title;
					$right = $b->post_title;
				} elseif (in_array($orderby, array('meta_value', 'meta_value_num'), true)) {
					$key   = $args['meta_key'] ?? '';
					$left  = $GLOBALS['tsubakuro_test']['post_meta'][$a->ID][$key][0] ?? '';
					$right = $GLOBALS['tsubakuro_test']['post_meta'][$b->ID][$key][0] ?? '';
					if ('meta_value_num' === $orderby) {
						$left  = (int) $left;
						$right = (int) $right;
					}
				} else {
					$left  = $a->post_date;
					$right = $b->post_date;
				}

				$result = is_string($left) || is_string($right)
					? strcmp((string) $left, (string) $right)
					: $left <=> $right;

				return 'ASC' === $order ? $result : -$result;
			}
		);

		$this->posts = $posts;
	}
}

function tsubakuro_test_reset()
{
	global $menu, $submenu, $wpdb;
	$GLOBALS['tsubakuro_test'] = array(
		'actions'            => array(),
		'filters'            => array(),
		'activation_hooks'   => array(),
		'deactivation_hooks' => array(),
		'post_types'         => array(),
		'rest_routes'        => array(),
		'posts'              => array(),
		'post_meta'          => array(),
		'users'              => array(),
		'can'                => array(
			'edit_posts'    => true,
			'delete_posts'  => true,
			'manage_options' => true,
		),
		'last_query_args'    => array(),
		'comments'           => array(),
		'wpdb_inserts'       => array(),
		'wpdb_row'           => null,
		'wpdb_rows'          => array(),
		'wpdb_insert_fail'   => false,
		'is_admin'           => false,
		'is_logged_in'       => false,
		'enqueued_scripts'   => array(),
		'enqueued_styles'    => array(),
		'menu_pages'         => array(),
		'submenu_pages'      => array(),
		'filters_applied'    => array(),
		'deleted_posts'      => array(),
		'redirected_to'      => null,
		'died'               => null,
		'options'            => array(),
		'transients'         => array(),
		'last_get_users_args' => array(),
		'abilities'          => array(),
		'pwd_counter'        => 0,
		'cron_events'        => array(),
		'sent_mails'         => array(),
		'meta_boxes'         => array(),
		'json_response'      => null,
		'updated_posts'      => array(),
	);
	$menu    = array();
	$submenu = array();
	$wpdb = new MockWpdb();
}

function plugin_dir_path($file)
{
	return dirname($file) . '/';
}

function plugin_dir_url($file)
{
	return 'https://example.test/wp-content/plugins/' . basename(dirname($file)) . '/';
}

function plugin_basename($file)
{
	return basename(dirname($file)) . '/' . basename($file);
}

function register_activation_hook($file, $callback)
{
	$GLOBALS['tsubakuro_test']['activation_hooks'][] = array($file, $callback);
}

function register_deactivation_hook($file, $callback)
{
	$GLOBALS['tsubakuro_test']['deactivation_hooks'][] = array($file, $callback);
}

function add_action($hook, $callback)
{
	$GLOBALS['tsubakuro_test']['actions'][$hook][] = $callback;
}

function add_filter($hook, $callback, $priority = 10, $accepted_args = 1)
{
	$GLOBALS['tsubakuro_test']['filters'][$hook][] = $callback;
}

function register_post_type($post_type, $args)
{
	$GLOBALS['tsubakuro_test']['post_types'][$post_type] = $args;
}

function register_rest_route($namespace, $route, $args)
{
	$GLOBALS['tsubakuro_test']['rest_routes'][$namespace . $route] = $args;
}

function current_user_can($capability)
{
	return ! empty($GLOBALS['tsubakuro_test']['can'][$capability]);
}

function sanitize_text_field($value)
{
	return trim(wp_strip_all_tags((string) $value));
}

function sanitize_textarea_field($value)
{
	return trim(wp_strip_all_tags((string) $value));
}

function sanitize_key($key)
{
	return preg_replace('/[^a-z0-9_\-]/', '', strtolower((string) $key));
}

function wp_strip_all_tags($value)
{
	return strip_tags((string) $value);
}

function wp_unslash($value)
{
	return is_array($value) ? array_map('wp_unslash', $value) : stripslashes((string) $value);
}

function absint($value)
{
	return max(0, abs((int) $value));
}

function wp_kses_post($value)
{
	return (string) $value;
}

function update_post_meta($post_id, $key, $value)
{
	$GLOBALS['tsubakuro_test']['post_meta'][$post_id][$key] = array($value);
}

function add_post_meta($post_id, $key, $value)
{
	$GLOBALS['tsubakuro_test']['post_meta'][$post_id][$key][] = $value;
}

function delete_post_meta($post_id, $key)
{
	unset($GLOBALS['tsubakuro_test']['post_meta'][$post_id][$key]);
}

function get_post_meta($post_id, $key, $single = false)
{
	$values = $GLOBALS['tsubakuro_test']['post_meta'][$post_id][$key] ?? array();
	if ($single) {
		return $values[0] ?? '';
	}
	return $values;
}

function get_post($post_id)
{
	return $GLOBALS['tsubakuro_test']['posts'][$post_id] ?? null;
}

function get_user_by($field, $value)
{
	if ('id' !== $field) {
		return false;
	}
	return $GLOBALS['tsubakuro_test']['users'][(int) $value] ?? false;
}

function wp_insert_comment($data)
{
	if (! empty($GLOBALS['tsubakuro_test']['wpdb_insert_fail'])) {
		return false;
	}

	$next_id = count($GLOBALS['tsubakuro_test']['comments']) + 1;

	$GLOBALS['tsubakuro_test']['comments'][$next_id] = (object) array(
		'comment_ID'       => $next_id,
		'comment_post_ID'  => (int) ($data['comment_post_ID'] ?? 0),
		'user_id'          => (int) ($data['user_id'] ?? 0),
		'comment_content'  => (string) ($data['comment_content'] ?? ''),
		'comment_type'     => (string) ($data['comment_type'] ?? ''),
		'comment_approved' => $data['comment_approved'] ?? 1,
		'comment_date'     => $data['comment_date'] ?? current_time('mysql'),
	);

	return $next_id;
}

function get_comment($comment_id)
{
	return $GLOBALS['tsubakuro_test']['comments'][(int) $comment_id] ?? null;
}

function get_comments($args = array())
{
	$comments = array_values($GLOBALS['tsubakuro_test']['comments']);

	$comments = array_filter(
		$comments,
		static function ($comment) use ($args) {
			if (isset($args['post_id']) && (int) $args['post_id'] !== (int) $comment->comment_post_ID) {
				return false;
			}

			if (isset($args['type']) && (string) $args['type'] !== (string) $comment->comment_type) {
				return false;
			}

			if (isset($args['status']) && 'approve' === $args['status'] && 1 !== (int) $comment->comment_approved) {
				return false;
			}

			return true;
		}
	);

	usort(
		$comments,
		static function ($a, $b) use ($args) {
			$result = strcmp((string) $a->comment_date, (string) $b->comment_date);
			return ($args['order'] ?? 'ASC') === 'DESC' ? -$result : $result;
		}
	);

	return array_values($comments);
}

function get_users($args = array())
{
	$GLOBALS['tsubakuro_test']['last_get_users_args'] = $args;
	return array_values($GLOBALS['tsubakuro_test']['users']);
}

function rest_ensure_response($response)
{
	return $response;
}

function is_wp_error($thing)
{
	return $thing instanceof WP_Error;
}

function wp_insert_post($postarr = array(), $wp_error = false)
{
	if (! empty($GLOBALS['tsubakuro_test']['wpdb_insert_fail'])) {
		return $wp_error ? new WP_Error('insert_failed', 'Insert failed') : 0;
	}

	if (($postarr['post_type'] ?? '') === Tsubakuro_Post_Types::COMMENT_POST_TYPE) {
		$post_ids = array_keys($GLOBALS['tsubakuro_test']['posts']);
		$next_id  = empty($post_ids) ? 1 : (max($post_ids) + 1);

		$GLOBALS['tsubakuro_test']['posts'][$next_id] = (object) array(
			'ID'            => $next_id,
			'post_type'     => (string) $postarr['post_type'],
			'post_title'    => (string) ($postarr['post_title'] ?? ''),
			'post_content'  => (string) ($postarr['post_content'] ?? ''),
			'post_date'     => current_time('mysql'),
			'post_modified' => current_time('mysql'),
			'post_author'   => (int) ($postarr['post_author'] ?? 0),
			'post_parent'   => (int) ($postarr['post_parent'] ?? 0),
			'post_status'   => (string) ($postarr['post_status'] ?? 'publish'),
		);

		foreach ($postarr['meta_input'] ?? array() as $meta_key => $meta_value) {
			update_post_meta($next_id, $meta_key, $meta_value);
		}

		return $next_id;
	}

	return 123;
}

function wp_update_post($postarr = array())
{
	$GLOBALS['tsubakuro_test']['updated_posts'][] = $postarr;
	$post_id = (int) ($postarr['ID'] ?? 0);
	if ($post_id && isset($GLOBALS['tsubakuro_test']['posts'][$post_id])) {
		foreach (
			array(
				'post_title'   => 'post_title',
				'post_content' => 'post_content',
				'post_parent'  => 'post_parent',
			) as $input_key => $post_key
		) {
			if (array_key_exists($input_key, $postarr)) {
				$GLOBALS['tsubakuro_test']['posts'][$post_id]->{$post_key} = $postarr[$input_key];
			}
		}
	}
	return $post_id ?: 123;
}

function wp_delete_post()
{
	$GLOBALS['tsubakuro_test']['deleted_posts'][] = func_get_arg(0);
	return true;
}

function get_current_user_id()
{
	return 7;
}

function is_admin()
{
	return ! empty($GLOBALS['tsubakuro_test']['is_admin']);
}

function wp_enqueue_style($handle, ...$args)
{
	$GLOBALS['tsubakuro_test']['enqueued_styles'][] = $handle;
}
function wp_enqueue_script($handle, ...$args)
{
	$GLOBALS['tsubakuro_test']['enqueued_scripts'][] = $handle;
}
function wp_localize_script() {}
function admin_url($path = '')
{
	return 'https://example.test/wp-admin/' . $path;
}
function add_query_arg($args, $url = '')
{
	$separator = str_contains($url, '?') ? '&' : '?';
	return $url . $separator . http_build_query($args);
}
function rest_url($path = '')
{
	return 'https://example.test/wp-json/' . $path;
}
function wp_create_nonce()
{
	return 'nonce';
}
function get_queried_object_id()
{
	return 55;
}
function add_menu_page($page_title, $menu_title, $capability, $menu_slug, $callback = '', $icon_url = '', $position = null)
{
	global $menu;
	$GLOBALS['tsubakuro_test']['menu_pages'][] = compact('page_title', 'menu_title', 'capability', 'menu_slug', 'callback', 'icon_url', 'position');
	$menu[]                                    = array($menu_title, $capability, $menu_slug, $page_title, '', '', $icon_url);
}
function add_submenu_page($parent_slug, $page_title, $menu_title, $capability, $menu_slug, $callback = '')
{
	global $submenu;
	$GLOBALS['tsubakuro_test']['submenu_pages'][] = compact('parent_slug', 'page_title', 'menu_title', 'capability', 'menu_slug', 'callback');
	$submenu[$parent_slug][]                      = array($menu_title, $capability, $menu_slug, $page_title);
}
function check_ajax_referer() {}
function check_admin_referer() {}
function wp_nonce_field($action = -1, $name = '_wpnonce')
{
	echo '<input type="hidden" name="' . esc_attr($name) . '" value="nonce" />';
}
function wp_send_json_error($value = null, $status_code = null)
{
	$GLOBALS['tsubakuro_test']['json_response'] = array(
		'success' => false,
		'data'    => $value,
		'status'  => $status_code,
	);
	throw new Tsubakuro_Test_Json_Response();
}
function wp_send_json_success($value = null, $status_code = null)
{
	$GLOBALS['tsubakuro_test']['json_response'] = array(
		'success' => true,
		'data'    => $value,
		'status'  => $status_code,
	);
	throw new Tsubakuro_Test_Json_Response();
}
function wp_safe_redirect($location)
{
	$GLOBALS['tsubakuro_test']['redirected_to'] = $location;
}
function wp_redirect($location)
{
	$GLOBALS['tsubakuro_test']['redirected_to'] = $location;
}
function wp_login_url($redirect = '')
{
	$url = 'https://example.test/wp-login.php';
	if ($redirect) {
		$url .= '?redirect_to=' . rawurlencode($redirect);
	}
	return $url;
}
function home_url($path = '')
{
	return 'https://example.test' . $path;
}
function wp_verify_nonce($nonce, $action = -1)
{
	return 'nonce' === $nonce ? 1 : false;
}
function status_header($code) {}
function wp_die($message = '')
{
	$GLOBALS['tsubakuro_test']['died'] = $message;
}
function wp_next_scheduled($hook)
{
	foreach ($GLOBALS['tsubakuro_test']['cron_events'] as $event) {
		if (($event['hook'] ?? '') === $hook) {
			return $event['timestamp'] ?? time();
		}
	}

	return false;
}
function wp_schedule_event($timestamp, $recurrence, $hook, $args = array())
{
	$GLOBALS['tsubakuro_test']['cron_events'][] = array(
		'timestamp'  => (int) $timestamp,
		'recurrence' => (string) $recurrence,
		'hook'       => (string) $hook,
		'args'       => (array) $args,
	);

	return true;
}
function wp_clear_scheduled_hook($hook)
{
	$GLOBALS['tsubakuro_test']['cron_events'] = array_values(
		array_filter(
			$GLOBALS['tsubakuro_test']['cron_events'],
			static function ($event) use ($hook) {
				return ($event['hook'] ?? '') !== $hook;
			}
		)
	);
}
function wp_mail($to, $subject, $message, $headers = '', $attachments = array())
{
	$GLOBALS['tsubakuro_test']['sent_mails'][] = array(
		'to'      => $to,
		'subject' => $subject,
		'message' => $message,
	);

	return true;
}
function current_time()
{
	return '2026-05-02 00:00:00';
}
function add_option() {}
function get_option($option, $default = false)
{
	return $GLOBALS['tsubakuro_test']['options'][$option] ?? $default;
}
function update_option($option, $value, $autoload = null)
{
	$GLOBALS['tsubakuro_test']['options'][$option] = $value;
	return true;
}
function delete_option($option)
{
	unset($GLOBALS['tsubakuro_test']['options'][$option]);
	return true;
}
function set_transient($key, $value, $expiry = 0)
{
	$GLOBALS['tsubakuro_test']['transients'][$key] = $value;
	return true;
}
function get_transient($key)
{
	return $GLOBALS['tsubakuro_test']['transients'][$key] ?? false;
}
function delete_transient($key)
{
	unset($GLOBALS['tsubakuro_test']['transients'][$key]);
	return true;
}
function wp_generate_password($length = 12, $special_chars = true)
{
	$GLOBALS['tsubakuro_test']['pwd_counter'] = ($GLOBALS['tsubakuro_test']['pwd_counter'] ?? 0) + 1;
	return str_pad('pwd' . $GLOBALS['tsubakuro_test']['pwd_counter'], (int) $length, 'x');
}
function wp_hash_password($password)
{
	return 'hashed:' . $password;
}
function wp_check_password($password, $hash)
{
	return $hash === 'hashed:' . $password;
}
function is_user_logged_in()
{
	return ! empty($GLOBALS['tsubakuro_test']['is_logged_in']);
}
function add_meta_box($id, $title, $callback, $screen = null, $context = 'advanced', $priority = 'default', $callback_args = null)
{
	$GLOBALS['tsubakuro_test']['meta_boxes'][] = compact('id', 'title', 'callback', 'screen', 'context', 'priority');
}

function get_post_types($args = array(), $output = 'names')
{
	return $GLOBALS['tsubakuro_test']['public_post_types'] ?? array('post', 'page');
}
function get_permalink($post_id = 0)
{
	return 'https://example.test/?p=' . (int) $post_id;
}
function esc_html__($text, $domain = 'default')
{
	return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
}
function __($text, $domain = 'default')
{
	return $text;
}
function esc_html($text)
{
	return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
}
function esc_html_e($text, $domain = 'default')
{
	echo esc_html__($text, $domain);
}
function esc_attr($text)
{
	return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
}
function esc_textarea($text)
{
	return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
}
function esc_attr_e($text, $domain = 'default')
{
	echo esc_attr($text);
}
function esc_url($url)
{
	return filter_var((string) $url, FILTER_SANITIZE_URL);
}
function esc_url_raw($url)
{
	return filter_var((string) $url, FILTER_SANITIZE_URL);
}
function selected($selected, $current = true, $display = true)
{
	$result = (string) $selected === (string) $current ? ' selected="selected"' : '';
	if ($display) {
		echo $result;
	}
	return $result;
}
function mysql2date($format, $date)
{
	return date($format, strtotime($date));
}
function apply_filters($hook_name, $value)
{
	$GLOBALS['tsubakuro_test']['filters_applied'][] = $hook_name;
	return $value;
}

function wp_register_ability($name, $args)
{
	$GLOBALS['tsubakuro_test']['abilities'][(string) $name] = (array) $args;
	return true;
}

function wp_has_ability($name)
{
	return array_key_exists((string) $name, $GLOBALS['tsubakuro_test']['abilities'] ?? array());
}

function wp_get_ability($name)
{
	if (! wp_has_ability($name)) {
		return null;
	}

	return (object) array(
		'name' => (string) $name,
		'args' => $GLOBALS['tsubakuro_test']['abilities'][(string) $name],
	);
}

if (! class_exists('WP_Comment_Query')) {
	class WP_Comment_Query
	{
		public $query_vars = array();

		public function set($key, $value)
		{
			$this->query_vars[$key] = $value;
		}
	}
}

tsubakuro_test_reset();
require_once dirname(__DIR__) . '/tsubakuro.php';
$GLOBALS['tsubakuro_test_bootstrap_state'] = $GLOBALS['tsubakuro_test'];
