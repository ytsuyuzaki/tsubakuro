<?php
/**
 * Admin – Improvement insight add/edit form template.
 *
 * Variables available:
 *   $insight         – insight data array (or null for new)
 *   $all_evaluations – array of evaluations for the evidence multi-select
 *
 * @package Tsubakuro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$is_edit         = ! empty( $insight );
$page_title      = $is_edit ? '改善知見を編集' : '新規改善知見';
$linked_ids      = $is_edit ? $insight['evaluation_ids'] : array();
$field           = static function ( $key ) use ( $insight ) {
	return $insight[ $key ] ?? '';
};
$task_create_url = '';
if ( $is_edit ) {
	$task_content_parts = array_filter(
		array(
			'改善知見: ' . (string) $field( 'title' ),
			$field( 'hypothesis' ) ? '仮説: ' . (string) $field( 'hypothesis' ) : '',
			$field( 'conclusion' ) ? '結論: ' . (string) $field( 'conclusion' ) : '',
			$field( 'action_label' ) ? '今後の扱い: ' . (string) $field( 'action_label' ) : '',
		)
	);
	$task_query         = array(
		'page'    => 'tsubakuro-task-form',
		'title'   => '改善知見を記事改善に反映: ' . (string) $field( 'title' ),
		'content' => implode( "\n", $task_content_parts ),
	);
	foreach ( $all_evaluations as $evaluation ) {
		if ( in_array( (int) $evaluation['id'], array_map( 'intval', $linked_ids ), true ) && ! empty( $evaluation['target_post_id'] ) ) {
			$task_query['related_page'] = (int) $evaluation['target_post_id'];
			break;
		}
	}
	$task_create_url = add_query_arg( $task_query, admin_url( 'admin.php' ) );
}
?>
<div class="wrap tsubakuro-admin-wrap">
	<h1 class="wp-heading-inline"><?php echo esc_html( $page_title ); ?></h1>
	<a href="<?php echo esc_url( admin_url( 'admin.php?page=tsubakuro-insights' ) ); ?>" class="page-title-action">
		<?php esc_html_e( '← 改善知見一覧に戻る', 'tsubakuro' ); ?>
	</a>
	<?php if ( $is_edit ) : ?>
		<a href="<?php echo esc_url( $task_create_url ); ?>" class="page-title-action">
			<?php esc_html_e( 'この知見からタスク作成', 'tsubakuro' ); ?>
		</a>
	<?php endif; ?>

	<?php
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- displaying redirect error message set by the plugin.
	if ( isset( $_GET['error'] ) ) :
		?>
		<div class="notice notice-error is-dismissible">
			<?php // phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized below. ?>
			<p><?php echo esc_html( sanitize_text_field( rawurldecode( wp_unslash( $_GET['error'] ) ) ) ); ?></p>
		</div>
	<?php endif; ?>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php wp_nonce_field( 'tsubakuro_save_insight', 'tsubakuro_insight_nonce' ); ?>
		<input type="hidden" name="action" value="tsubakuro_save_insight">
		<?php if ( $is_edit ) : ?>
			<input type="hidden" name="insight_id" value="<?php echo esc_attr( $insight['id'] ); ?>">
		<?php endif; ?>

		<div class="tsubakuro-form-card">
			<div class="tsubakuro-form-row">
				<label for="tsubakuro-insight-title">
					<?php esc_html_e( '知見のタイトル', 'tsubakuro' ); ?> <span class="required">*</span>
				</label>
				<input type="text" id="tsubakuro-insight-title" name="title" class="widefat" required value="<?php echo esc_attr( $field( 'title' ) ); ?>">
			</div>

			<div class="tsubakuro-form-row tsubakuro-form-row--half">
				<div>
					<label for="tsubakuro-insight-site"><?php esc_html_e( '対象サイト', 'tsubakuro' ); ?></label>
					<input type="text" id="tsubakuro-insight-site" name="site" class="widefat" value="<?php echo esc_attr( $field( 'site' ) ); ?>">
				</div>
				<div>
					<label for="tsubakuro-insight-kind"><?php esc_html_e( '対象となる記事種別', 'tsubakuro' ); ?></label>
					<input type="text" id="tsubakuro-insight-kind" name="post_kind" class="widefat" value="<?php echo esc_attr( $field( 'post_kind' ) ); ?>" placeholder="<?php esc_attr_e( '例：比較記事', 'tsubakuro' ); ?>">
				</div>
			</div>

			<div class="tsubakuro-form-row">
				<label for="tsubakuro-insight-hypothesis"><?php esc_html_e( '仮説', 'tsubakuro' ); ?></label>
				<textarea id="tsubakuro-insight-hypothesis" name="hypothesis" class="widefat" rows="3"><?php echo esc_textarea( $field( 'hypothesis' ) ); ?></textarea>
			</div>

			<div class="tsubakuro-form-row">
				<label for="tsubakuro-insight-conclusion"><?php esc_html_e( '結論', 'tsubakuro' ); ?></label>
				<textarea id="tsubakuro-insight-conclusion" name="conclusion" class="widefat" rows="3"><?php echo esc_textarea( $field( 'conclusion' ) ); ?></textarea>
			</div>

			<div class="tsubakuro-form-row tsubakuro-form-row--half">
				<div>
					<label for="tsubakuro-insight-total"><?php esc_html_e( '実施件数', 'tsubakuro' ); ?></label>
					<input type="number" min="0" id="tsubakuro-insight-total" name="total_count" class="widefat" value="<?php echo esc_attr( $is_edit ? $insight['total_count'] : '' ); ?>">
				</div>
				<div>
					<label for="tsubakuro-insight-success"><?php esc_html_e( '成功件数', 'tsubakuro' ); ?></label>
					<input type="number" min="0" id="tsubakuro-insight-success" name="success_count" class="widefat" value="<?php echo esc_attr( $is_edit ? $insight['success_count'] : '' ); ?>">
				</div>
			</div>

			<?php if ( $is_edit && null !== $insight['success_rate'] ) : ?>
				<div class="tsubakuro-form-row">
					<label><?php esc_html_e( '成功率', 'tsubakuro' ); ?></label>
					<span class="tsubakuro-success-rate"><?php echo esc_html( $insight['success_rate'] . '%' ); ?></span>
				</div>
			<?php endif; ?>

			<div class="tsubakuro-form-row tsubakuro-form-row--half">
				<div>
					<label for="tsubakuro-insight-status"><?php esc_html_e( 'ステータス', 'tsubakuro' ); ?></label>
					<select id="tsubakuro-insight-status" name="status" class="widefat">
						<option value=""><?php esc_html_e( '（未選択）', 'tsubakuro' ); ?></option>
						<?php foreach ( Tsubakuro_Insights::STATUSES as $key => $label ) : ?>
							<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $field( 'status' ), $key ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<div>
					<label for="tsubakuro-insight-action"><?php esc_html_e( '今後の扱い', 'tsubakuro' ); ?></label>
					<select id="tsubakuro-insight-action" name="action_type" class="widefat">
						<option value=""><?php esc_html_e( '（未選択）', 'tsubakuro' ); ?></option>
						<?php foreach ( Tsubakuro_Insights::ACTIONS as $key => $label ) : ?>
							<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $field( 'action' ), $key ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
			</div>

			<div class="tsubakuro-form-row">
				<label for="tsubakuro-insight-evaluations"><?php esc_html_e( '根拠となる評価履歴', 'tsubakuro' ); ?></label>
				<select id="tsubakuro-insight-evaluations" name="evaluations[]" class="widefat" multiple size="8">
					<?php foreach ( $all_evaluations as $evaluation ) : ?>
						<option value="<?php echo esc_attr( $evaluation['id'] ); ?>" <?php echo in_array( (int) $evaluation['id'], array_map( 'intval', $linked_ids ), true ) ? 'selected="selected"' : ''; ?>>
							<?php echo esc_html( $evaluation['title'] . ( $evaluation['judgment_label'] ? '（' . $evaluation['judgment_label'] . '）' : '' ) ); ?>
						</option>
					<?php endforeach; ?>
				</select>
				<p class="description"><?php esc_html_e( 'Ctrl / Cmd キーで複数選択できます。', 'tsubakuro' ); ?></p>
			</div>

			<div class="tsubakuro-form-actions">
				<button type="submit" class="button button-primary"><?php esc_html_e( '保存', 'tsubakuro' ); ?></button>
			</div>
		</div>
	</form>
</div><!-- .wrap -->
