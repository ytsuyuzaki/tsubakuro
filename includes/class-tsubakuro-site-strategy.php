<?php
/**
 * Site strategy: the single, site-wide definition of purpose and direction.
 *
 * Unlike tasks / evaluations / insights (custom post types with many records),
 * the site strategy is a singleton – there is exactly one per site – so it is
 * persisted with the options API instead of a CPT.
 *
 * Stored under the single option `tsubakuro_site_strategy` as an associative
 * array of the fields listed in FIELDS plus the maintenance keys `updated_at`
 * (MySQL datetime) and `updated_by` (WordPress user ID).
 *
 * @package Tsubakuro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manages the site strategy singleton stored in wp_options.
 */
class Tsubakuro_Site_Strategy {


	const OPTION = 'tsubakuro_site_strategy';

	/** Editable free-text fields (field key => label). */
	const FIELDS = array(
		'purpose'   => 'サイトの目的',
		'position'  => '期待するポジション',
		'direction' => '進む方向性',
		'audience'  => 'ターゲット読者',
		'value'     => '提供価値',
	);

	/**
	 * Get the current site strategy as a normalized array.
	 *
	 * Always returns every field in FIELDS (empty string when unset) plus
	 * `updated_at` and `updated_by`.
	 *
	 * @return array
	 */
	public static function get_strategy() {
		$stored = get_option( self::OPTION, array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		$strategy = array();
		foreach ( array_keys( self::FIELDS ) as $field ) {
			$strategy[ $field ] = isset( $stored[ $field ] ) ? (string) $stored[ $field ] : '';
		}

		$strategy['updated_at'] = isset( $stored['updated_at'] ) ? (string) $stored['updated_at'] : '';
		$strategy['updated_by'] = isset( $stored['updated_by'] ) ? (int) $stored['updated_by'] : 0;

		return $strategy;
	}

	/**
	 * Save the site strategy, merging the submitted fields over the stored ones.
	 *
	 * Only keys present in $data are updated (partial updates are allowed); each
	 * value is sanitized as multi-line text. The `updated_at` / `updated_by`
	 * maintenance keys are refreshed on every save.
	 *
	 * @param array $data Assoc array keyed by FIELDS keys.
	 * @return array The saved strategy (as returned by get_strategy()).
	 */
	public static function save_strategy( $data ) {
		$current = self::get_strategy();

		foreach ( array_keys( self::FIELDS ) as $field ) {
			if ( isset( $data[ $field ] ) ) {
				$current[ $field ] = sanitize_textarea_field( $data[ $field ] );
			}
		}

		$current['updated_at'] = current_time( 'mysql' );
		$current['updated_by'] = get_current_user_id();

		update_option( self::OPTION, $current );

		return self::get_strategy();
	}
}
