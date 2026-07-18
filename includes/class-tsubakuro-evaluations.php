<?php
/**
 * Register the tsubakuro_evaluation custom post type and its meta.
 *
 * Article evaluation / change-task meta fields (all prefixed _tsubakuro_eval_):
 *   target_post    – WordPress post/page ID the change was applied to
 *   change_item    – change category slug (see CHANGE_ITEMS)
 *   purpose        – free text describing the goal
 *   implemented_at – date the change was applied (Y-m-d)
 *   due_at         – date the result should be evaluated (Y-m-d)
 *   metric         – evaluation metric slug (see METRICS)
 *   before_value   – measured value before the change (free text)
 *   after_value    – measured value after the change (free text)
 *   result         – free text result summary
 *   judgment       – verdict slug (see JUDGMENTS); empty = unevaluated
 *   note           – free text remarks
 *   reminded_at    – MySQL datetime the overdue reminder was sent (sent-state)
 *
 * The change detail is stored in the post_content field, the title in post_title.
 *
 * @package Tsubakuro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manages the tsubakuro_evaluation custom post type.
 */
class Tsubakuro_Evaluations {


	const POST_TYPE = 'tsubakuro_evaluation';

	/** Change item categories (変更項目). */
	const CHANGE_ITEMS = array(
		'title'         => 'タイトル変更',
		'heading'       => '見出し追加',
		'body'          => '本文追記',
		'faq'           => 'FAQ追加',
		'comparison'    => '比較表追加',
		'internal_link' => '内部リンク追加',
		'image'         => '画像追加',
		'structured'    => '構造化データ追加',
		'merge'         => '記事統合',
		'redirect'      => 'リダイレクト設定',
	);

	/** Evaluation metrics (評価指標). */
	const METRICS = array(
		'search_rank'    => '検索順位',
		'clicks'         => 'クリック数',
		'impressions'    => '表示回数',
		'ctr'            => 'CTR',
		'pv'             => 'PV',
		'conversion'     => 'コンバージョン',
		'internal_click' => '内部リンクのクリック',
		'index_status'   => 'インデックス状況',
	);

	/** Verdicts (判定). */
	const JUDGMENTS = array(
		'success'   => '成功',
		'partial'   => '一部成功',
		'no_change' => '変化なし',
		'failure'   => '失敗',
		'pending'   => '判定保留',
	);

	/**
	 * Register WordPress hooks.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_post_type' ) );
	}

	/**
	 * Register the evaluation custom post type.
	 */
	public static function register_post_type() {
		$labels = array(
			'name'               => '記事評価',
			'singular_name'      => '記事評価',
			'add_new'            => '新規追加',
			'add_new_item'       => '新しい記事評価を追加',
			'edit_item'          => '記事評価を編集',
			'new_item'           => '新しい記事評価',
			'view_item'          => '記事評価を表示',
			'search_items'       => '記事評価を検索',
			'not_found'          => '記事評価が見つかりません',
			'not_found_in_trash' => 'ゴミ箱に記事評価はありません',
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
			'supports'           => array( 'title', 'editor', 'author' ),
			'show_in_rest'       => true,
			'rest_base'          => 'tsubakuro-evaluations',
		);

		register_post_type( self::POST_TYPE, $args );
	}

	/**
	 * Save evaluation meta from an array of data.
	 *
	 * @param int   $eval_id Post ID.
	 * @param array $data    Assoc array of evaluation fields.
	 */
	public static function save_meta( $eval_id, $data ) {
		if ( isset( $data['target_post'] ) ) {
			update_post_meta( $eval_id, '_tsubakuro_eval_target_post', absint( $data['target_post'] ) );
		}

		self::save_enum_meta( $eval_id, '_tsubakuro_eval_change_item', $data, 'change_item', self::CHANGE_ITEMS );
		self::save_enum_meta( $eval_id, '_tsubakuro_eval_metric', $data, 'metric', self::METRICS );

		$judgment_changed = false;
		if ( isset( $data['judgment'] ) ) {
			$judgment = sanitize_text_field( $data['judgment'] );
			if ( '' === $judgment || array_key_exists( $judgment, self::JUDGMENTS ) ) {
				$previous = (string) get_post_meta( $eval_id, '_tsubakuro_eval_judgment', true );
				if ( '' === $judgment ) {
					delete_post_meta( $eval_id, '_tsubakuro_eval_judgment' );
				} else {
					update_post_meta( $eval_id, '_tsubakuro_eval_judgment', $judgment );
				}
				$judgment_changed = ( $previous !== $judgment );
			}
		}

		foreach ( array( 'purpose', 'result', 'note', 'before_value', 'after_value' ) as $text_key ) {
			if ( isset( $data[ $text_key ] ) ) {
				update_post_meta( $eval_id, '_tsubakuro_eval_' . $text_key, sanitize_textarea_field( $data[ $text_key ] ) );
			}
		}

		foreach ( array( 'implemented_at', 'due_at' ) as $date_key ) {
			if ( array_key_exists( $date_key, $data ) ) {
				self::save_date_meta( $eval_id, $date_key, $data[ $date_key ] );
			}
		}

		// When the verdict is (re)recorded or cleared, reset the overdue-reminder sent flag
		// so an unevaluated task can be reminded again after the verdict is removed.
		if ( $judgment_changed ) {
			delete_post_meta( $eval_id, '_tsubakuro_eval_reminded_at' );
		}
	}

	/**
	 * Save an enum meta value, or delete it when the submitted value is empty.
	 *
	 * An empty string (e.g. the "（未選択）" option) clears the meta so a
	 * previously set value can be unset; unknown non-empty values are ignored.
	 *
	 * @param int    $eval_id  Evaluation ID.
	 * @param string $meta_key Full meta key.
	 * @param array  $data     Submitted data.
	 * @param string $field    Field key within $data.
	 * @param array  $allowed  Allowed enum map.
	 */
	private static function save_enum_meta( $eval_id, $meta_key, $data, $field, $allowed ) {
		if ( ! isset( $data[ $field ] ) ) {
			return;
		}

		$value = sanitize_text_field( $data[ $field ] );

		if ( '' === $value ) {
			delete_post_meta( $eval_id, $meta_key );
		} elseif ( array_key_exists( $value, $allowed ) ) {
			update_post_meta( $eval_id, $meta_key, $value );
		}
	}

	/**
	 * Save a single date meta value normalized to Y-m-d, or delete when empty.
	 *
	 * @param int    $eval_id Evaluation ID.
	 * @param string $key     Field key (without prefix).
	 * @param mixed  $value   Raw date input.
	 */
	private static function save_date_meta( $eval_id, $key, $value ) {
		$meta_key   = '_tsubakuro_eval_' . $key;
		$normalized = self::normalize_date( $value );

		if ( '' === $normalized ) {
			delete_post_meta( $eval_id, $meta_key );
			return;
		}

		update_post_meta( $eval_id, $meta_key, $normalized );
	}

	/**
	 * Normalize a date input to Y-m-d format.
	 *
	 * @param mixed $value Raw date value.
	 * @return string
	 */
	private static function normalize_date( $value ) {
		if ( ! is_scalar( $value ) ) {
			return '';
		}

		$raw = trim( (string) $value );
		if ( '' === $raw ) {
			return '';
		}

		// The canonical Y-m-d form (from the date picker / API) is stored as-is.
		// Converting a date-only value through a timestamp would shift it across
		// the UTC boundary on non-UTC servers, so validate and keep it verbatim.
		if ( preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $raw, $matches ) ) {
			if ( checkdate( (int) $matches[2], (int) $matches[3], (int) $matches[1] ) ) {
				return $raw;
			}

			return '';
		}

		// Best-effort parse for other accepted formats.
		$timestamp = strtotime( $raw );
		if ( false === $timestamp ) {
			return '';
		}

		return gmdate( 'Y-m-d', $timestamp );
	}

	/**
	 * Get full evaluation data including meta.
	 *
	 * @param int $eval_id Post ID.
	 * @return array|null
	 */
	public static function get_evaluation( $eval_id ) {
		$post = get_post( $eval_id );
		if ( ! $post || self::POST_TYPE !== $post->post_type ) {
			return null;
		}

		return self::format_evaluation( $post );
	}

	/**
	 * Format a WP_Post into a structured evaluation array.
	 *
	 * @param WP_Post $post The post object to format.
	 * @return array
	 */
	public static function format_evaluation( $post ) {
		$target_post_id = (int) get_post_meta( $post->ID, '_tsubakuro_eval_target_post', true );
		$change_item    = (string) get_post_meta( $post->ID, '_tsubakuro_eval_change_item', true );
		$metric         = (string) get_post_meta( $post->ID, '_tsubakuro_eval_metric', true );
		$judgment       = (string) get_post_meta( $post->ID, '_tsubakuro_eval_judgment', true );

		$target_post = null;
		if ( $target_post_id ) {
			$target = get_post( $target_post_id );
			if ( $target ) {
				$target_post = array(
					'id'    => $target->ID,
					'title' => $target->post_title ? $target->post_title : sprintf( '(ID: %d)', $target->ID ),
					'url'   => (string) get_permalink( $target->ID ),
				);
			}
		}

		return array(
			'id'                => $post->ID,
			'title'             => $post->post_title,
			'change_detail'     => $post->post_content,
			'target_post'       => $target_post,
			'target_post_id'    => $target_post_id,
			'change_item'       => $change_item,
			'change_item_label' => self::CHANGE_ITEMS[ $change_item ] ?? $change_item,
			'purpose'           => (string) get_post_meta( $post->ID, '_tsubakuro_eval_purpose', true ),
			'implemented_at'    => (string) get_post_meta( $post->ID, '_tsubakuro_eval_implemented_at', true ),
			'due_at'            => (string) get_post_meta( $post->ID, '_tsubakuro_eval_due_at', true ),
			'metric'            => $metric,
			'metric_label'      => self::METRICS[ $metric ] ?? $metric,
			'before_value'      => (string) get_post_meta( $post->ID, '_tsubakuro_eval_before_value', true ),
			'after_value'       => (string) get_post_meta( $post->ID, '_tsubakuro_eval_after_value', true ),
			'result'            => (string) get_post_meta( $post->ID, '_tsubakuro_eval_result', true ),
			'judgment'          => $judgment,
			'judgment_label'    => '' !== $judgment ? ( self::JUDGMENTS[ $judgment ] ?? $judgment ) : '',
			'note'              => (string) get_post_meta( $post->ID, '_tsubakuro_eval_note', true ),
			'is_evaluated'      => '' !== $judgment,
			'created_at'        => $post->post_date,
			'updated_at'        => $post->post_modified,
			'author_id'         => (int) $post->post_author,
		);
	}

	/**
	 * Query evaluations with optional filters.
	 *
	 * Supported $args keys: target_post, change_item, judgment, metric,
	 * implemented_at, due_at, include_ids, unevaluated (bool), overdue (bool),
	 * s, per_page/posts_per_page, orderby, order.
	 *
	 * @param array $args Optional filters.
	 * @return array
	 */
	public static function get_evaluations( $args = array() ) {
		$defaults = array(
			'post_type'      => self::POST_TYPE,
			// Contributors can access the screen with the edit_posts capability,
			// but WordPress stores their submitted evaluations as pending without
			// the publish_posts capability.
			// Keep internal lists inclusive without surfacing trash/auto-drafts.
			'post_status'    => array( 'publish', 'pending', 'draft', 'future', 'private' ),
			'posts_per_page' => 50,
			'orderby'        => 'date',
			'order'          => 'DESC',
		);

		$meta_query  = array();
		$unevaluated = ! empty( $args['unevaluated'] ) || ! empty( $args['overdue'] );

		if ( ! empty( $args['target_post'] ) ) {
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- meta_query required for filtering.
			$meta_query[] = array(
				'key'     => '_tsubakuro_eval_target_post',
				'value'   => absint( $args['target_post'] ),
				'compare' => '=',
				'type'    => 'NUMERIC',
			);
		}

		foreach ( array( 'change_item', 'metric', 'judgment' ) as $enum_key ) {
			if ( ! empty( $args[ $enum_key ] ) ) {
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- meta_query required for filtering.
				$meta_query[] = array(
					'key'   => '_tsubakuro_eval_' . $enum_key,
					'value' => sanitize_text_field( $args[ $enum_key ] ),
				);
			}
		}

		foreach ( array( 'implemented_at', 'due_at' ) as $date_key ) {
			if ( ! empty( $args[ $date_key ] ) ) {
				$date = self::normalize_date( $args[ $date_key ] );
				if ( '' !== $date ) {
					// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- meta_query required for date filters.
					$meta_query[] = array(
						'key'     => '_tsubakuro_eval_' . $date_key,
						'value'   => $date,
						'compare' => '=',
						'type'    => 'DATE',
					);
				}
			}
		}

		if ( ! empty( $args['overdue'] ) ) {
			$today = current_time( 'Y-m-d' );
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- meta_query required for overdue filter.
			$meta_query[] = array(
				'key'     => '_tsubakuro_eval_due_at',
				'value'   => $today,
				'compare' => '<=',
				'type'    => 'DATE',
			);
		}

		if ( ! empty( $meta_query ) ) {
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- meta_query required for filters.
			$defaults['meta_query'] = $meta_query;
		}

		if ( ! empty( $args['s'] ) ) {
			$defaults['s'] = sanitize_text_field( $args['s'] );
		}

		if ( array_key_exists( 'include_ids', $args ) && is_array( $args['include_ids'] ) ) {
			$include_ids = array_values( array_unique( array_filter( array_map( 'absint', $args['include_ids'] ) ) ) );
			if ( empty( $include_ids ) ) {
				return array();
			}
			$defaults['post__in'] = $include_ids;
		}

		$defaults = self::apply_orderby( $defaults, $args );

		$query = new WP_Query( $defaults );

		$evaluations = array();
		foreach ( $query->posts as $post ) {
			$formatted = self::format_evaluation( $post );
			// The "unevaluated" condition (empty judgment) cannot be expressed as a
			// reliable meta_query because unevaluated posts have no judgment meta row,
			// so filter it in PHP after formatting.
			if ( $unevaluated && $formatted['is_evaluated'] ) {
				continue;
			}
			$evaluations[] = $formatted;
		}

		return $evaluations;
	}

	/**
	 * Apply the orderby/order allow-list to query args.
	 *
	 * @param array $defaults Base query args.
	 * @param array $args     Incoming args.
	 * @return array
	 */
	private static function apply_orderby( $defaults, $args ) {
		if ( ! empty( $args['orderby'] ) ) {
			$orderby_map = array(
				'id'             => 'ID',
				'title'          => 'title',
				'date'           => 'date',
				'due_at'         => 'meta_value',
				'implemented_at' => 'meta_value',
			);
			$orderby     = sanitize_key( $args['orderby'] );
			if ( isset( $orderby_map[ $orderby ] ) ) {
				$defaults['orderby'] = $orderby_map[ $orderby ];
				if ( 'due_at' === $orderby ) {
					$defaults['meta_key'] = '_tsubakuro_eval_due_at'; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- list sorting.
				}
				if ( 'implemented_at' === $orderby ) {
					$defaults['meta_key'] = '_tsubakuro_eval_implemented_at'; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- list sorting.
				}
			}
		}

		if ( ! empty( $args['order'] ) ) {
			$order = strtoupper( sanitize_text_field( $args['order'] ) );
			if ( in_array( $order, array( 'ASC', 'DESC' ), true ) ) {
				$defaults['order'] = $order;
			}
		}

		return $defaults;
	}
}
