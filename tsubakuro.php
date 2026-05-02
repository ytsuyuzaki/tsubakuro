<?php
/**
 * Plugin Name: Tsubakuro Task Manager
 * Plugin URI:  https://github.com/ytsuyuzaki/tsubakuro
 * Description: WordPress管理画面でのタスク管理プラグイン。タスクの書き出し・コメント・ステータス管理・関連ページ・アサイン・MCP対応・フロントエンドポップアップを実現します。
 * Version:     1.0.0
 * Author:      ytsuyuzaki
 * License:     GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: tsubakuro
 * Domain Path: /languages
 *
 * @package Tsubakuro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'TSUBAKURO_VERSION', '1.0.0' );
define( 'TSUBAKURO_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'TSUBAKURO_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'TSUBAKURO_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

require_once TSUBAKURO_PLUGIN_DIR . 'includes/class-tsubakuro-activator.php';
require_once TSUBAKURO_PLUGIN_DIR . 'includes/class-tsubakuro-post-types.php';
require_once TSUBAKURO_PLUGIN_DIR . 'includes/class-tsubakuro-admin.php';
require_once TSUBAKURO_PLUGIN_DIR . 'includes/class-tsubakuro-rest-api.php';
require_once TSUBAKURO_PLUGIN_DIR . 'includes/class-tsubakuro-mcp.php';
require_once TSUBAKURO_PLUGIN_DIR . 'includes/class-tsubakuro-frontend.php';

register_activation_hook( __FILE__, array( 'Tsubakuro_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Tsubakuro_Activator', 'deactivate' ) );

/**
 * Bootstrap all plugin modules on plugins_loaded.
 */
function tsubakuro_init() {
	Tsubakuro_Post_Types::init();
	Tsubakuro_Admin::init();
	Tsubakuro_REST_API::init();
	Tsubakuro_MCP::init();
	Tsubakuro_Frontend::init();
}
add_action( 'plugins_loaded', 'tsubakuro_init' );
