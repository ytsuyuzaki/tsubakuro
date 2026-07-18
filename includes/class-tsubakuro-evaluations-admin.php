<?php
/**
 * Admin UI for article evaluations and site-level improvement insights.
 *
 * Registers the list and form screens under the Tsubakuro top-level menu and
 * handles their form submissions and deletions. Shares the CSS/JS enqueued by
 * Tsubakuro_Admin (its hook guard matches any `tsubakuro` admin page).
 *
 * @package Tsubakuro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manages the evaluation / insight admin pages and their handlers.
 */
class Tsubakuro_Evaluations_Admin {


	/**
	 * Register WordPress action hooks.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'add_menu' ), 20 );
		add_action( 'admin_post_tsubakuro_save_evaluation', array( __CLASS__, 'handle_save_evaluation' ) );
		add_action( 'admin_post_tsubakuro_delete_evaluation', array( __CLASS__, 'handle_delete_evaluation' ) );
		add_action( 'admin_post_tsubakuro_save_insight', array( __CLASS__, 'handle_save_insight' ) );
		add_action( 'admin_post_tsubakuro_delete_insight', array( __CLASS__, 'handle_delete_insight' ) );
	}

	// -------------------------------------------------------------------------
	// Menu
	// -------------------------------------------------------------------------

	/**
	 * Register the evaluation / insight submenu pages.
	 */
	public static function add_menu() {
		add_submenu_page(
			'tsubakuro-tasks',
			'記事評価一覧',
			'記事評価一覧',
			'edit_posts',
			'tsubakuro-evaluations',
			array( __CLASS__, 'render_evaluation_list' )
		);

		add_submenu_page(
			'tsubakuro-tasks',
			'記事評価を追加',
			'記事評価を追加',
			'edit_posts',
			'tsubakuro-evaluation-form',
			array( __CLASS__, 'render_evaluation_form' )
		);

		add_submenu_page(
			'tsubakuro-tasks',
			'改善知見一覧',
			'改善知見一覧',
			'edit_posts',
			'tsubakuro-insights',
			array( __CLASS__, 'render_insight_list' )
		);

		add_submenu_page(
			'tsubakuro-tasks',
			'改善知見を追加',
			'改善知見を追加',
			'edit_posts',
			'tsubakuro-insight-form',
			array( __CLASS__, 'render_insight_form' )
		);
	}

	// -------------------------------------------------------------------------
	// Evaluation screens
	// -------------------------------------------------------------------------

	/**
	 * Render the evaluation list screen.
	 */
	public static function render_evaluation_list() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( '権限がありません。' );
		}

		$list_args    = self::get_evaluation_list_args_from_request();
		$evaluations  = Tsubakuro_Evaluations::get_evaluations( $list_args );
		$insights     = Tsubakuro_Insights::get_insights( array( 'posts_per_page' => 200 ) ); // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page -- bounded related-insight lookup.
		$post_choices = self::get_target_post_choices();
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display-only notice key.
		$message = isset( $_GET['message'] ) ? sanitize_key( wp_unslash( $_GET['message'] ) ) : '';

		include TSUBAKURO_PLUGIN_DIR . 'templates/admin/evaluation-list.php';
	}

	/**
	 * Render the evaluation add/edit form.
	 */
	public static function render_evaluation_form() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( '権限がありません。' );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display param, not form data.
		$evaluation_id   = absint( $_GET['evaluation_id'] ?? 0 );
		$evaluation      = $evaluation_id ? Tsubakuro_Evaluations::get_evaluation( $evaluation_id ) : null;
		$linked_insights = $evaluation ? Tsubakuro_Insights::get_insights_for_evaluation( $evaluation['id'] ) : array();
		$post_choices    = self::get_target_post_choices();

		include TSUBAKURO_PLUGIN_DIR . 'templates/admin/evaluation-form.php';
	}

	/**
	 * Build evaluation list args from display-only request params.
	 *
	 * @return array
	 */
	public static function get_evaluation_list_args_from_request() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- display-only list filters.
		$args = array(
			'target_post'    => isset( $_GET['target_post'] ) ? absint( wp_unslash( $_GET['target_post'] ) ) : 0,
			'change_item'    => isset( $_GET['change_item'] ) ? sanitize_text_field( wp_unslash( $_GET['change_item'] ) ) : '',
			'judgment'       => isset( $_GET['judgment'] ) ? sanitize_text_field( wp_unslash( $_GET['judgment'] ) ) : '',
			'metric'         => isset( $_GET['metric'] ) ? sanitize_text_field( wp_unslash( $_GET['metric'] ) ) : '',
			'implemented_at' => isset( $_GET['implemented_at'] ) ? sanitize_text_field( wp_unslash( $_GET['implemented_at'] ) ) : '',
			'due_at'         => isset( $_GET['due_at'] ) ? sanitize_text_field( wp_unslash( $_GET['due_at'] ) ) : '',
			'insight'        => isset( $_GET['insight'] ) ? absint( wp_unslash( $_GET['insight'] ) ) : 0,
			'unevaluated'    => ! empty( $_GET['unevaluated'] ),
			's'              => isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '',
			'orderby'        => isset( $_GET['orderby'] ) ? sanitize_key( wp_unslash( $_GET['orderby'] ) ) : 'date',
			'order'          => isset( $_GET['order'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_GET['order'] ) ) ) : 'DESC',
		);
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		if ( ! array_key_exists( $args['change_item'], Tsubakuro_Evaluations::CHANGE_ITEMS ) ) {
			$args['change_item'] = '';
		}
		if ( ! array_key_exists( $args['judgment'], Tsubakuro_Evaluations::JUDGMENTS ) ) {
			$args['judgment'] = '';
		}
		if ( ! array_key_exists( $args['metric'], Tsubakuro_Evaluations::METRICS ) ) {
			$args['metric'] = '';
		}
		foreach ( array( 'implemented_at', 'due_at' ) as $date_key ) {
			if ( '' !== $args[ $date_key ] && ! self::is_valid_date_string( $args[ $date_key ] ) ) {
				$args[ $date_key ] = '';
			}
		}
		if ( $args['insight'] ) {
			$insight = Tsubakuro_Insights::get_insight( $args['insight'] );
			if ( $insight ) {
				$args['include_ids'] = $insight['evaluation_ids'];
			} else {
				$args['insight'] = 0;
			}
		}

		return array_filter(
			$args,
			static function ( $value ) {
				return is_array( $value ) || ( '' !== $value && 0 !== $value && false !== $value );
			}
		);
	}

	/**
	 * Validate a display date filter.
	 *
	 * @param string $value Date string.
	 * @return bool
	 */
	private static function is_valid_date_string( $value ) {
		if ( ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $value, $matches ) ) {
			return false;
		}

		return checkdate( (int) $matches[2], (int) $matches[3], (int) $matches[1] );
	}

	/**
	 * Handle the evaluation create/update form submission.
	 */
	public static function handle_save_evaluation() {
		check_admin_referer( 'tsubakuro_save_evaluation', 'tsubakuro_evaluation_nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( '権限がありません。' );
		}

		$eval_id       = absint( $_POST['evaluation_id'] ?? 0 );
		$change_detail = wp_kses_post( wp_unslash( $_POST['change_detail'] ?? '' ) );
		$target_post   = absint( $_POST['target_post'] ?? 0 );
		$change_item   = sanitize_text_field( wp_unslash( $_POST['change_item'] ?? '' ) );
		$title         = self::resolve_evaluation_title( $_POST, $target_post, $change_item );

		$meta = self::collect_evaluation_meta_from_post();

		if ( $eval_id ) {
			if ( ! Tsubakuro_Evaluations::get_evaluation( $eval_id ) ) {
				wp_safe_redirect(
					add_query_arg(
						'error',
						rawurlencode( '記事評価が見つかりません。' ),
						admin_url( 'admin.php?page=tsubakuro-evaluation-form&evaluation_id=' . $eval_id )
					)
				);
				exit;
			}

			wp_update_post(
				array(
					'ID'           => $eval_id,
					'post_title'   => $title,
					'post_content' => $change_detail,
				)
			);
			Tsubakuro_Evaluations::save_meta( $eval_id, $meta );
		} else {
			$eval_id = wp_insert_post(
				array(
					'post_type'    => Tsubakuro_Evaluations::POST_TYPE,
					'post_title'   => $title,
					'post_content' => $change_detail,
					'post_status'  => 'publish',
				),
				true
			);

			if ( is_wp_error( $eval_id ) ) {
				wp_safe_redirect( add_query_arg( 'error', rawurlencode( $eval_id->get_error_message() ), admin_url( 'admin.php?page=tsubakuro-evaluation-form' ) ) );
				exit;
			}

			Tsubakuro_Evaluations::save_meta( $eval_id, $meta );
		}

		wp_safe_redirect( admin_url( 'admin.php?page=tsubakuro-evaluations&message=saved' ) );
		exit;
	}

	/**
	 * Handle deletion of an evaluation.
	 */
	public static function handle_delete_evaluation() {
		check_admin_referer( 'tsubakuro_delete_evaluation', 'tsubakuro_evaluation_nonce' );

		if ( ! current_user_can( 'delete_posts' ) ) {
			wp_die( '権限がありません。' );
		}

		$eval_id = absint( $_POST['evaluation_id'] ?? 0 );
		if ( $eval_id && Tsubakuro_Evaluations::get_evaluation( $eval_id ) ) {
			wp_delete_post( $eval_id, true );
		}

		wp_safe_redirect( admin_url( 'admin.php?page=tsubakuro-evaluations&message=deleted' ) );
		exit;
	}

	/**
	 * Collect evaluation meta fields from the submitted form.
	 *
	 * @return array
	 */
	private static function collect_evaluation_meta_from_post() {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- nonce verified by caller.
		$meta = array(
			'target_post'    => absint( $_POST['target_post'] ?? 0 ),
			'change_item'    => sanitize_text_field( wp_unslash( $_POST['change_item'] ?? '' ) ),
			'metric'         => sanitize_text_field( wp_unslash( $_POST['metric'] ?? '' ) ),
			'purpose'        => sanitize_textarea_field( wp_unslash( $_POST['purpose'] ?? '' ) ),
			'implemented_at' => sanitize_text_field( wp_unslash( $_POST['implemented_at'] ?? '' ) ),
			'due_at'         => sanitize_text_field( wp_unslash( $_POST['due_at'] ?? '' ) ),
			'before_value'   => sanitize_text_field( wp_unslash( $_POST['before_value'] ?? '' ) ),
			'after_value'    => sanitize_text_field( wp_unslash( $_POST['after_value'] ?? '' ) ),
			'result'         => sanitize_textarea_field( wp_unslash( $_POST['result'] ?? '' ) ),
			'judgment'       => sanitize_text_field( wp_unslash( $_POST['judgment'] ?? '' ) ),
			'note'           => sanitize_textarea_field( wp_unslash( $_POST['note'] ?? '' ) ),
		);
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		return $meta;
	}

	/**
	 * Determine the evaluation title, falling back to target + change item.
	 *
	 * @param array  $data        Submitted form data.
	 * @param int    $target_post Target post ID.
	 * @param string $change_item Change item slug.
	 * @return string
	 */
	private static function resolve_evaluation_title( $data, $target_post, $change_item ) {
		$title = sanitize_text_field( wp_unslash( $data['title'] ?? '' ) );
		if ( '' !== $title ) {
			return $title;
		}

		$target_label = '';
		if ( $target_post ) {
			$post = get_post( $target_post );
			if ( $post ) {
				$target_label = $post->post_title ? $post->post_title : sprintf( '#%d', $target_post );
			}
		}

		$item_label = Tsubakuro_Evaluations::CHANGE_ITEMS[ $change_item ] ?? '';
		$parts      = array_filter( array( $target_label, $item_label ) );

		return $parts ? implode( ' - ', $parts ) : '記事評価';
	}

	// -------------------------------------------------------------------------
	// Insight screens
	// -------------------------------------------------------------------------

	/**
	 * Render the insight list screen.
	 */
	public static function render_insight_list() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( '権限がありません。' );
		}

		$list_args = self::get_insight_list_args_from_request();
		$insights  = Tsubakuro_Insights::get_insights( $list_args );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display-only notice key.
		$message = isset( $_GET['message'] ) ? sanitize_key( wp_unslash( $_GET['message'] ) ) : '';

		include TSUBAKURO_PLUGIN_DIR . 'templates/admin/insight-list.php';
	}

	/**
	 * Render the insight add/edit form.
	 */
	public static function render_insight_form() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( '権限がありません。' );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display param, not form data.
		$insight_id      = absint( $_GET['insight_id'] ?? 0 );
		$insight         = $insight_id ? Tsubakuro_Insights::get_insight( $insight_id ) : null;
		$all_evaluations = Tsubakuro_Evaluations::get_evaluations( array( 'posts_per_page' => 200 ) ); // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page -- bounded evidence picker list.

		include TSUBAKURO_PLUGIN_DIR . 'templates/admin/insight-form.php';
	}

	/**
	 * Build insight list args from display-only request params.
	 *
	 * @return array
	 */
	public static function get_insight_list_args_from_request() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- display-only list filters.
		$args = array(
			'status' => isset( $_GET['status'] ) ? sanitize_text_field( wp_unslash( $_GET['status'] ) ) : '',
			'action' => isset( $_GET['action_filter'] ) ? sanitize_text_field( wp_unslash( $_GET['action_filter'] ) ) : '',
			's'      => isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '',
		);
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		if ( ! array_key_exists( $args['status'], Tsubakuro_Insights::STATUSES ) ) {
			$args['status'] = '';
		}
		if ( ! array_key_exists( $args['action'], Tsubakuro_Insights::ACTIONS ) ) {
			$args['action'] = '';
		}

		return array_filter(
			$args,
			static function ( $value ) {
				return '' !== $value;
			}
		);
	}

	/**
	 * Handle the insight create/update form submission.
	 */
	public static function handle_save_insight() {
		check_admin_referer( 'tsubakuro_save_insight', 'tsubakuro_insight_nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( '権限がありません。' );
		}

		$insight_id = absint( $_POST['insight_id'] ?? 0 );
		$title      = sanitize_text_field( wp_unslash( $_POST['title'] ?? '' ) );

		if ( '' === $title ) {
			$back = admin_url( 'admin.php?page=tsubakuro-insight-form' );
			if ( $insight_id ) {
				$back .= '&insight_id=' . $insight_id;
			}
			wp_safe_redirect( add_query_arg( 'error', rawurlencode( 'タイトルは必須です。' ), $back ) );
			exit;
		}

		$meta = self::collect_insight_meta_from_post();

		if ( $insight_id ) {
			if ( ! Tsubakuro_Insights::get_insight( $insight_id ) ) {
				wp_safe_redirect(
					add_query_arg(
						'error',
						rawurlencode( '改善知見が見つかりません。' ),
						admin_url( 'admin.php?page=tsubakuro-insight-form&insight_id=' . $insight_id )
					)
				);
				exit;
			}

			wp_update_post(
				array(
					'ID'         => $insight_id,
					'post_title' => $title,
				)
			);
			Tsubakuro_Insights::save_meta( $insight_id, $meta );
		} else {
			$insight_id = wp_insert_post(
				array(
					'post_type'   => Tsubakuro_Insights::POST_TYPE,
					'post_title'  => $title,
					'post_status' => 'publish',
				),
				true
			);

			if ( is_wp_error( $insight_id ) ) {
				wp_safe_redirect( add_query_arg( 'error', rawurlencode( $insight_id->get_error_message() ), admin_url( 'admin.php?page=tsubakuro-insight-form' ) ) );
				exit;
			}

			Tsubakuro_Insights::save_meta( $insight_id, $meta );
		}

		wp_safe_redirect( admin_url( 'admin.php?page=tsubakuro-insights&message=saved' ) );
		exit;
	}

	/**
	 * Handle deletion of an insight.
	 */
	public static function handle_delete_insight() {
		check_admin_referer( 'tsubakuro_delete_insight', 'tsubakuro_insight_nonce' );

		if ( ! current_user_can( 'delete_posts' ) ) {
			wp_die( '権限がありません。' );
		}

		$insight_id = absint( $_POST['insight_id'] ?? 0 );
		if ( $insight_id && Tsubakuro_Insights::get_insight( $insight_id ) ) {
			wp_delete_post( $insight_id, true );
		}

		wp_safe_redirect( admin_url( 'admin.php?page=tsubakuro-insights&message=deleted' ) );
		exit;
	}

	/**
	 * Collect insight meta fields from the submitted form.
	 *
	 * @return array
	 */
	private static function collect_insight_meta_from_post() {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- nonce verified by caller.
		$evaluations = array();
		if ( isset( $_POST['evaluations'] ) && is_array( $_POST['evaluations'] ) ) {
			$evaluations = array_map( 'absint', wp_unslash( $_POST['evaluations'] ) );
		}

		$meta = array(
			'site'          => sanitize_text_field( wp_unslash( $_POST['site'] ?? '' ) ),
			'post_kind'     => sanitize_text_field( wp_unslash( $_POST['post_kind'] ?? '' ) ),
			'hypothesis'    => sanitize_textarea_field( wp_unslash( $_POST['hypothesis'] ?? '' ) ),
			'conclusion'    => sanitize_textarea_field( wp_unslash( $_POST['conclusion'] ?? '' ) ),
			'total_count'   => absint( $_POST['total_count'] ?? 0 ),
			'success_count' => absint( $_POST['success_count'] ?? 0 ),
			'status'        => sanitize_text_field( wp_unslash( $_POST['status'] ?? '' ) ),
			'action'        => sanitize_text_field( wp_unslash( $_POST['action_type'] ?? '' ) ),
			'evaluations'   => $evaluations,
		);
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		return $meta;
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Get selectable target posts/pages for the evaluation form.
	 *
	 * @return array List of { id, title } entries.
	 */
	private static function get_target_post_choices() {
		$post_types = array_values(
			array_diff(
				get_post_types( array( 'public' => true ), 'names' ),
				array( Tsubakuro_Post_Types::TASK_POST_TYPE, Tsubakuro_Post_Types::COMMENT_POST_TYPE )
			)
		);

		$query = new WP_Query(
			array(
				'post_type'      => $post_types,
				'post_status'    => 'publish',
				'posts_per_page' => 200, // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page -- bounded picker list.
				'orderby'        => 'title',
				'order'          => 'ASC',
				'no_found_rows'  => true,
			)
		);

		$choices = array();
		foreach ( $query->posts as $post ) {
			$choices[] = array(
				'id'    => $post->ID,
				'title' => $post->post_title ? $post->post_title : sprintf( '#%d', $post->ID ),
			);
		}

		return $choices;
	}
}
