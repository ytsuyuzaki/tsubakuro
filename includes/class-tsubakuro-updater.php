<?php
/**
 * GitHub Releases update integration.
 *
 * @package Tsubakuro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers GitHub Releases as the plugin update source.
 */
class Tsubakuro_Updater {

	/**
	 * Public repository used for update metadata.
	 */
	const REPOSITORY_URL = 'https://github.com/ytsuyuzaki/tsubakuro/';

	/**
	 * WordPress plugin slug.
	 */
	const PLUGIN_SLUG = 'tsubakuro';

	/**
	 * Select only the release ZIP built for this plugin.
	 */
	const RELEASE_ASSET_PATTERN = '/^tsubakuro\.zip$/i';

	/**
	 * Require a matching release asset instead of using GitHub's source ZIP.
	 */
	const REQUIRE_RELEASE_ASSETS = 2;

	/**
	 * Update checker instance.
	 *
	 * @var object|null
	 */
	private static $update_checker = null;

	/**
	 * Load the bundled library and register the update checker.
	 *
	 * @return object|null Update checker instance, or null when unavailable.
	 */
	public static function init() {
		$library_file = TSUBAKURO_PLUGIN_DIR . 'plugin-update-checker/plugin-update-checker.php';

		if ( ! is_readable( $library_file ) ) {
			return null;
		}

		require_once $library_file;

		$factory_class = 'YahnisElsts\\PluginUpdateChecker\\v5\\PucFactory';
		if ( ! class_exists( $factory_class ) ) {
			return null;
		}

		return self::build_update_checker( $factory_class );
	}

	/**
	 * Build and configure an update checker.
	 *
	 * The factory is injectable to keep the integration independently testable.
	 *
	 * @param string $factory_class Update checker factory class.
	 * @return object Update checker instance.
	 */
	public static function build_update_checker( $factory_class ) {
		$update_checker = $factory_class::buildUpdateChecker(
			self::REPOSITORY_URL,
			TSUBAKURO_PLUGIN_DIR . 'tsubakuro.php',
			self::PLUGIN_SLUG
		);

		$vcs_api = $update_checker->getVcsApi();
		$vcs_api->enableReleaseAssets(
			self::RELEASE_ASSET_PATTERN,
			self::REQUIRE_RELEASE_ASSETS
		);

		self::$update_checker = $update_checker;

		return $update_checker;
	}
}
