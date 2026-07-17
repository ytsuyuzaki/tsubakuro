<?php
/**
 * Register the tsubakuro_insight custom post type and its meta.
 *
 * Site-level improvement insight meta fields (all prefixed _tsubakuro_insight_):
 *   site          – target site label (free text; single-site install)
 *   post_kind     – target article kind (free text)
 *   hypothesis    – free text hypothesis
 *   conclusion    – free text conclusion
 *   total_count   – number of articles the measure was applied to (int)
 *   success_count – number of successful applications (int)
 *   status        – lifecycle status slug (see STATUSES)
 *   action        – future handling slug (see ACTIONS)
 *   evaluation    – linked evaluation post IDs (stored as multiple meta rows)
 *
 * The insight title is stored in post_title. The success rate is computed on
 * read from total_count / success_count and is never persisted.
 *
 * @package Tsubakuro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manages the tsubakuro_insight custom post type.
 */
class Tsubakuro_Insights {


	const POST_TYPE = 'tsubakuro_insight';

	/** Insight lifecycle statuses (ステータス). */
	const STATUSES = array(
		'hypothesis'  => '仮説',
		'verifying'   => '検証中',
		'effective'   => '有効',
		'unclear'     => '効果不明',
		'ineffective' => '無効',
		'ruled'       => '運用ルール化',
	);

	/** Future handling options (今後の扱い). */
	const ACTIONS = array(
		'try_others'   => '他の記事でも試す',
		'try_kind'     => '特定の記事種別で試す',
		'standardize'  => '標準施策として採用する',
		'deprioritize' => '優先度を下げる',
		'stop'         => '今後は実施しない',
		'reverify'     => '再検証する',
	);

	/**
	 * Register WordPress hooks.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_post_type' ) );
	}

	/**
	 * Register the insight custom post type.
	 */
	public static function register_post_type() {
		$labels = array(
			'name'               => '改善知見',
			'singular_name'      => '改善知見',
			'add_new'            => '新規追加',
			'add_new_item'       => '新しい改善知見を追加',
			'edit_item'          => '改善知見を編集',
			'new_item'           => '新しい改善知見',
			'view_item'          => '改善知見を表示',
			'search_items'       => '改善知見を検索',
			'not_found'          => '改善知見が見つかりません',
			'not_found_in_trash' => 'ゴミ箱に改善知見はありません',
		);

		$args = array(
			'labels'             => $labels,
			'public'             => false,
			'publicly_queryable' => false,
			'show_ui'            => false, // We build our own admin UI.
			'show_in_menu'       => false,
			'query_var'          => false,
			'rewrite'            => false,
			'capability_type'    => 'post',
			'has_archive'        => false,
			'hierarchical'       => false,
			'supports'           => array( 'title', 'author' ),
			'show_in_rest'       => true,
			'rest_base'          => 'tsubakuro-insights',
		);

		register_post_type( self::POST_TYPE, $args );
	}

	/**
	 * Save insight meta from an array of data.
	 *
	 * @param int   $insight_id Post ID.
	 * @param array $data       Assoc array of insight fields.
	 */
	public static function save_meta( $insight_id, $data ) {
		foreach ( array( 'site', 'post_kind' ) as $text_key ) {
			if ( isset( $data[ $text_key ] ) ) {
				update_post_meta( $insight_id, '_tsubakuro_insight_' . $text_key, sanitize_text_field( $data[ $text_key ] ) );
			}
		}

		foreach ( array( 'hypothesis', 'conclusion' ) as $textarea_key ) {
			if ( isset( $data[ $textarea_key ] ) ) {
				update_post_meta( $insight_id, '_tsubakuro_insight_' . $textarea_key, sanitize_textarea_field( $data[ $textarea_key ] ) );
			}
		}

		foreach ( array( 'total_count', 'success_count' ) as $count_key ) {
			if ( isset( $data[ $count_key ] ) ) {
				update_post_meta( $insight_id, '_tsubakuro_insight_' . $count_key, absint( $data[ $count_key ] ) );
			}
		}

		self::save_enum_meta( $insight_id, '_tsubakuro_insight_status', $data, 'status', self::STATUSES );
		self::save_enum_meta( $insight_id, '_tsubakuro_insight_action', $data, 'action', self::ACTIONS );

		if ( isset( $data['evaluations'] ) ) {
			self::save_linked_evaluations( $insight_id, $data['evaluations'] );
		}
	}

	/**
	 * Save an enum meta value, or delete it when the submitted value is empty.
	 *
	 * An empty string (e.g. the "（未選択）" option) clears the meta so a
	 * previously set value can be unset; unknown non-empty values are ignored.
	 *
	 * @param int    $insight_id Insight ID.
	 * @param string $meta_key   Full meta key.
	 * @param array  $data       Submitted data.
	 * @param string $field      Field key within $data.
	 * @param array  $allowed    Allowed enum map.
	 */
	private static function save_enum_meta( $insight_id, $meta_key, $data, $field, $allowed ) {
		if ( ! isset( $data[ $field ] ) ) {
			return;
		}

		$value = sanitize_text_field( $data[ $field ] );

		if ( '' === $value ) {
			delete_post_meta( $insight_id, $meta_key );
		} elseif ( array_key_exists( $value, $allowed ) ) {
			update_post_meta( $insight_id, $meta_key, $value );
		}
	}

	/**
	 * Replace the set of linked evaluation IDs for an insight.
	 *
	 * @param int   $insight_id Insight ID.
	 * @param mixed $evaluations Array or comma-separated evaluation IDs.
	 */
	public static function save_linked_evaluations( $insight_id, $evaluations ) {
		$ids = is_array( $evaluations )
			? array_map( 'absint', $evaluations )
			: array_map( 'absint', explode( ',', (string) $evaluations ) );
		$ids = array_unique( array_filter( $ids ) );

		delete_post_meta( $insight_id, '_tsubakuro_insight_evaluation' );
		foreach ( $ids as $eval_id ) {
			add_post_meta( $insight_id, '_tsubakuro_insight_evaluation', $eval_id );
		}
	}

	/**
	 * Get full insight data including meta.
	 *
	 * @param int $insight_id Post ID.
	 * @return array|null
	 */
	public static function get_insight( $insight_id ) {
		$post = get_post( $insight_id );
		if ( ! $post || self::POST_TYPE !== $post->post_type ) {
			return null;
		}

		return self::format_insight( $post );
	}

	/**
	 * Format a WP_Post into a structured insight array.
	 *
	 * @param WP_Post $post The post object to format.
	 * @return array
	 */
	public static function format_insight( $post ) {
		$status         = (string) get_post_meta( $post->ID, '_tsubakuro_insight_status', true );
		$action         = (string) get_post_meta( $post->ID, '_tsubakuro_insight_action', true );
		$total_count    = (int) get_post_meta( $post->ID, '_tsubakuro_insight_total_count', true );
		$success_count  = (int) get_post_meta( $post->ID, '_tsubakuro_insight_success_count', true );
		$evaluation_ids = array_map( 'intval', get_post_meta( $post->ID, '_tsubakuro_insight_evaluation', false ) );

		$success_rate = $total_count > 0 ? round( ( $success_count / $total_count ) * 100, 1 ) : null;

		return array(
			'id'             => $post->ID,
			'title'          => $post->post_title,
			'site'           => (string) get_post_meta( $post->ID, '_tsubakuro_insight_site', true ),
			'post_kind'      => (string) get_post_meta( $post->ID, '_tsubakuro_insight_post_kind', true ),
			'hypothesis'     => (string) get_post_meta( $post->ID, '_tsubakuro_insight_hypothesis', true ),
			'conclusion'     => (string) get_post_meta( $post->ID, '_tsubakuro_insight_conclusion', true ),
			'total_count'    => $total_count,
			'success_count'  => $success_count,
			'success_rate'   => $success_rate,
			'status'         => $status,
			'status_label'   => '' !== $status ? ( self::STATUSES[ $status ] ?? $status ) : '',
			'action'         => $action,
			'action_label'   => '' !== $action ? ( self::ACTIONS[ $action ] ?? $action ) : '',
			'evaluation_ids' => $evaluation_ids,
			'created_at'     => $post->post_date,
			'updated_at'     => $post->post_modified,
			'author_id'      => (int) $post->post_author,
		);
	}

	/**
	 * Query insights with optional filters.
	 *
	 * Supported $args keys: status, action, s, per_page/posts_per_page,
	 * orderby, order.
	 *
	 * @param array $args Optional filters.
	 * @return array
	 */
	public static function get_insights( $args = array() ) {
		$defaults = array(
			'post_type'      => self::POST_TYPE,
			'post_status'    => 'publish',
			'posts_per_page' => 50,
			'orderby'        => 'modified',
			'order'          => 'DESC',
		);

		$meta_query = array();

		foreach ( array( 'status', 'action' ) as $enum_key ) {
			if ( ! empty( $args[ $enum_key ] ) ) {
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- meta_query required for filtering.
				$meta_query[] = array(
					'key'   => '_tsubakuro_insight_' . $enum_key,
					'value' => sanitize_text_field( $args[ $enum_key ] ),
				);
			}
		}

		if ( ! empty( $args['evaluation'] ) ) {
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- meta_query required for reverse lookup.
			$meta_query[] = array(
				'key'     => '_tsubakuro_insight_evaluation',
				'value'   => absint( $args['evaluation'] ),
				'compare' => '=',
				'type'    => 'NUMERIC',
			);
		}

		if ( ! empty( $meta_query ) ) {
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- meta_query required for filters.
			$defaults['meta_query'] = $meta_query;
		}

		if ( ! empty( $args['s'] ) ) {
			$defaults['s'] = sanitize_text_field( $args['s'] );
		}

		if ( ! empty( $args['posts_per_page'] ) ) {
			$defaults['posts_per_page'] = (int) $args['posts_per_page'];
		}

		$query = new WP_Query( $defaults );

		$insights = array();
		foreach ( $query->posts as $post ) {
			$insights[] = self::format_insight( $post );
		}

		return $insights;
	}

	/**
	 * Find insights that reference a given evaluation (reverse lookup).
	 *
	 * @param int $eval_id Evaluation post ID.
	 * @return array
	 */
	public static function get_insights_for_evaluation( $eval_id ) {
		$eval_id = absint( $eval_id );
		if ( ! $eval_id ) {
			return array();
		}

		return self::get_insights( array( 'evaluation' => $eval_id ) );
	}
}
