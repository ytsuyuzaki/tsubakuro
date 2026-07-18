<?php
/**
 * Admin – Article evaluation list page template.
 *
 * Variables available:
 *   $evaluations – array of evaluation data (Tsubakuro_Evaluations::get_evaluations)
 *   $list_args   – current list query args
 *   $insights    – all insights (for the related-insight lookup)
 *   $post_choices – array of { id, title } target post choices
 *   $message     – current admin notice key
 *
 * @package Tsubakuro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$change_filter      = $list_args['change_item'] ?? '';
$judgment_filter    = $list_args['judgment'] ?? '';
$metric_filter      = $list_args['metric'] ?? '';
$target_filter      = (int) ( $list_args['target_post'] ?? 0 );
$implemented_filter = $list_args['implemented_at'] ?? '';
$due_filter         = $list_args['due_at'] ?? '';
$insight_filter     = (int) ( $list_args['insight'] ?? 0 );
$search_query       = $list_args['s'] ?? '';
$unevaluated_only   = ! empty( $list_args['unevaluated'] );

// Build a map of evaluation_id => insight titles for the related-insight column.
$insight_map = array();
foreach ( $insights as $insight ) {
	foreach ( $insight['evaluation_ids'] as $linked_eval_id ) {
		$insight_map[ $linked_eval_id ][] = $insight['title'];
	}
}
?>
<div class="wrap tsubakuro-admin-wrap">
	<h1 class="wp-heading-inline"><?php esc_html_e( '記事評価一覧', 'tsubakuro' ); ?></h1>
	<a href="<?php echo esc_url( admin_url( 'admin.php?page=tsubakuro-evaluation-form' ) ); ?>" class="page-title-action">
		<?php esc_html_e( '新規記事評価', 'tsubakuro' ); ?>
	</a>
	<hr class="wp-header-end">

	<?php if ( 'saved' === $message ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( '記事評価を保存しました。', 'tsubakuro' ); ?></p></div>
	<?php elseif ( 'deleted' === $message ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( '記事評価を削除しました。', 'tsubakuro' ); ?></p></div>
	<?php endif; ?>

	<form method="get" class="tsubakuro-list-filter-form">
		<input type="hidden" name="page" value="tsubakuro-evaluations" />

		<p class="search-box">
			<label class="screen-reader-text" for="tsubakuro-eval-search"><?php esc_html_e( '記事評価を検索', 'tsubakuro' ); ?></label>
			<input type="search" id="tsubakuro-eval-search" name="s" value="<?php echo esc_attr( $search_query ); ?>" />
			<input type="submit" class="button" value="<?php esc_attr_e( '検索', 'tsubakuro' ); ?>" />
		</p>

		<div class="tablenav top">
			<div class="alignleft actions">
				<select name="target_post">
					<option value="0"><?php esc_html_e( 'すべての対象記事', 'tsubakuro' ); ?></option>
					<?php foreach ( $post_choices as $choice ) : ?>
						<option value="<?php echo esc_attr( $choice['id'] ); ?>" <?php selected( $target_filter, (int) $choice['id'] ); ?>><?php echo esc_html( $choice['title'] ); ?></option>
					<?php endforeach; ?>
				</select>

				<select name="change_item">
					<option value=""><?php esc_html_e( 'すべての変更項目', 'tsubakuro' ); ?></option>
					<?php foreach ( Tsubakuro_Evaluations::CHANGE_ITEMS as $key => $label ) : ?>
						<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $change_filter, $key ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>

				<select name="judgment">
					<option value=""><?php esc_html_e( 'すべての判定', 'tsubakuro' ); ?></option>
					<?php foreach ( Tsubakuro_Evaluations::JUDGMENTS as $key => $label ) : ?>
						<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $judgment_filter, $key ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>

				<select name="metric">
					<option value=""><?php esc_html_e( 'すべての評価指標', 'tsubakuro' ); ?></option>
					<?php foreach ( Tsubakuro_Evaluations::METRICS as $key => $label ) : ?>
						<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $metric_filter, $key ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>

				<input type="date" name="implemented_at" value="<?php echo esc_attr( $implemented_filter ); ?>" aria-label="<?php esc_attr_e( '実施日', 'tsubakuro' ); ?>" />

				<input type="date" name="due_at" value="<?php echo esc_attr( $due_filter ); ?>" aria-label="<?php esc_attr_e( '評価予定日', 'tsubakuro' ); ?>" />

				<select name="insight">
					<option value="0"><?php esc_html_e( 'すべての改善知見', 'tsubakuro' ); ?></option>
					<?php foreach ( $insights as $insight ) : ?>
						<option value="<?php echo esc_attr( $insight['id'] ); ?>" <?php selected( $insight_filter, (int) $insight['id'] ); ?>><?php echo esc_html( $insight['title'] ); ?></option>
					<?php endforeach; ?>
				</select>

				<label class="tsubakuro-inline-check">
					<input type="checkbox" name="unevaluated" value="1" <?php checked( $unevaluated_only ); ?> />
					<?php esc_html_e( '未評価のみ', 'tsubakuro' ); ?>
				</label>

				<input type="submit" class="button" value="<?php esc_attr_e( '絞り込み', 'tsubakuro' ); ?>" />
			</div>
			<br class="clear" />
		</div>
	</form>

	<div class="tsubakuro-table-scroll">
	<table class="wp-list-table widefat fixed striped table-view-list">
		<thead>
			<tr>
				<th scope="col" class="manage-column"><?php esc_html_e( 'タイトル', 'tsubakuro' ); ?></th>
				<th scope="col" class="manage-column"><?php esc_html_e( '対象記事', 'tsubakuro' ); ?></th>
				<th scope="col" class="manage-column"><?php esc_html_e( '変更項目', 'tsubakuro' ); ?></th>
				<th scope="col" class="manage-column"><?php esc_html_e( '判定', 'tsubakuro' ); ?></th>
				<th scope="col" class="manage-column"><?php esc_html_e( '実施日', 'tsubakuro' ); ?></th>
				<th scope="col" class="manage-column"><?php esc_html_e( '評価予定日', 'tsubakuro' ); ?></th>
				<th scope="col" class="manage-column"><?php esc_html_e( '関連する改善知見', 'tsubakuro' ); ?></th>
				<th scope="col" class="manage-column column-actions"><?php esc_html_e( '操作', 'tsubakuro' ); ?></th>
			</tr>
		</thead>
		<tbody>
		<?php if ( empty( $evaluations ) ) : ?>
			<tr><td colspan="8"><?php esc_html_e( '記事評価がありません。', 'tsubakuro' ); ?></td></tr>
		<?php else : ?>
			<?php foreach ( $evaluations as $evaluation ) : ?>
				<?php $edit_url = admin_url( 'admin.php?page=tsubakuro-evaluation-form&evaluation_id=' . $evaluation['id'] ); ?>
				<tr>
					<td class="column-primary">
						<strong><a href="<?php echo esc_url( $edit_url ); ?>"><?php echo esc_html( $evaluation['title'] ); ?></a></strong>
					</td>
					<td>
						<?php if ( $evaluation['target_post'] ) : ?>
							<?php echo esc_html( $evaluation['target_post']['title'] ); ?>
						<?php else : ?>
							&#8212;
						<?php endif; ?>
					</td>
					<td><?php echo '' !== $evaluation['change_item_label'] ? esc_html( $evaluation['change_item_label'] ) : '&#8212;'; ?></td>
					<td>
						<?php if ( $evaluation['is_evaluated'] ) : ?>
							<span class="tsubakuro-judgment tsubakuro-judgment--<?php echo esc_attr( $evaluation['judgment'] ); ?>"><?php echo esc_html( $evaluation['judgment_label'] ); ?></span>
						<?php else : ?>
							<span class="tsubakuro-judgment tsubakuro-judgment--unevaluated"><?php esc_html_e( '未評価', 'tsubakuro' ); ?></span>
						<?php endif; ?>
					</td>
					<td><?php echo '' !== $evaluation['implemented_at'] ? esc_html( $evaluation['implemented_at'] ) : '&#8212;'; ?></td>
					<td><?php echo '' !== $evaluation['due_at'] ? esc_html( $evaluation['due_at'] ) : '&#8212;'; ?></td>
					<td>
						<?php
						if ( ! empty( $insight_map[ $evaluation['id'] ] ) ) {
							echo esc_html( implode( ', ', $insight_map[ $evaluation['id'] ] ) );
						} else {
							echo '&#8212;';
						}
						?>
					</td>
					<td>
						<a href="<?php echo esc_url( $edit_url ); ?>" class="button button-small"><?php esc_html_e( '編集', 'tsubakuro' ); ?></a>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="tsubakuro-inline-delete" onsubmit="return confirm('<?php echo esc_js( '削除してよろしいですか？' ); ?>');">
							<input type="hidden" name="action" value="tsubakuro_delete_evaluation" />
							<input type="hidden" name="evaluation_id" value="<?php echo esc_attr( $evaluation['id'] ); ?>" />
							<?php wp_nonce_field( 'tsubakuro_delete_evaluation', 'tsubakuro_evaluation_nonce' ); ?>
							<button type="submit" class="button-link delete"><?php esc_html_e( '削除', 'tsubakuro' ); ?></button>
						</form>
					</td>
				</tr>
			<?php endforeach; ?>
		<?php endif; ?>
		</tbody>
	</table>
	</div><!-- .tsubakuro-table-scroll -->
</div><!-- .wrap -->
