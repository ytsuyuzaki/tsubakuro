<?php
/**
 * Admin – Improvement insight list page template.
 *
 * Variables available:
 *   $insights  – array of insight data (Tsubakuro_Insights::get_insights)
 *   $list_args – current list query args
 *   $message   – current admin notice key
 *
 * @package Tsubakuro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$status_filter = $list_args['status'] ?? '';
$action_filter = $list_args['action'] ?? '';
$search_query  = $list_args['s'] ?? '';
?>
<div class="wrap tsubakuro-admin-wrap">
	<h1 class="wp-heading-inline"><?php esc_html_e( '改善知見一覧', 'tsubakuro' ); ?></h1>
	<a href="<?php echo esc_url( admin_url( 'admin.php?page=tsubakuro-insight-form' ) ); ?>" class="page-title-action">
		<?php esc_html_e( '新規改善知見', 'tsubakuro' ); ?>
	</a>
	<hr class="wp-header-end">

	<?php if ( 'saved' === $message ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( '改善知見を保存しました。', 'tsubakuro' ); ?></p></div>
	<?php elseif ( 'deleted' === $message ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( '改善知見を削除しました。', 'tsubakuro' ); ?></p></div>
	<?php endif; ?>

	<form method="get" class="tsubakuro-list-filter-form">
		<input type="hidden" name="page" value="tsubakuro-insights" />

		<p class="search-box">
			<label class="screen-reader-text" for="tsubakuro-insight-search"><?php esc_html_e( '改善知見を検索', 'tsubakuro' ); ?></label>
			<input type="search" id="tsubakuro-insight-search" name="s" value="<?php echo esc_attr( $search_query ); ?>" />
			<input type="submit" class="button" value="<?php esc_attr_e( '検索', 'tsubakuro' ); ?>" />
		</p>

		<div class="tablenav top">
			<div class="alignleft actions">
				<select name="status">
					<option value=""><?php esc_html_e( 'すべてのステータス', 'tsubakuro' ); ?></option>
					<?php foreach ( Tsubakuro_Insights::STATUSES as $key => $label ) : ?>
						<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $status_filter, $key ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>

				<select name="action_filter">
					<option value=""><?php esc_html_e( 'すべての今後の扱い', 'tsubakuro' ); ?></option>
					<?php foreach ( Tsubakuro_Insights::ACTIONS as $key => $label ) : ?>
						<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $action_filter, $key ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>

				<input type="submit" class="button" value="<?php esc_attr_e( '絞り込み', 'tsubakuro' ); ?>" />
			</div>
			<br class="clear" />
		</div>
	</form>

	<div class="tsubakuro-table-scroll">
	<table class="wp-list-table widefat fixed striped table-view-list">
		<thead>
			<tr>
				<th scope="col" class="manage-column"><?php esc_html_e( '知見のタイトル', 'tsubakuro' ); ?></th>
				<th scope="col" class="manage-column"><?php esc_html_e( 'ステータス', 'tsubakuro' ); ?></th>
				<th scope="col" class="manage-column"><?php esc_html_e( '実施件数', 'tsubakuro' ); ?></th>
				<th scope="col" class="manage-column"><?php esc_html_e( '成功率', 'tsubakuro' ); ?></th>
				<th scope="col" class="manage-column"><?php esc_html_e( '対象記事種別', 'tsubakuro' ); ?></th>
				<th scope="col" class="manage-column"><?php esc_html_e( '今後の扱い', 'tsubakuro' ); ?></th>
				<th scope="col" class="manage-column"><?php esc_html_e( '最終更新日', 'tsubakuro' ); ?></th>
				<th scope="col" class="manage-column column-actions"><?php esc_html_e( '操作', 'tsubakuro' ); ?></th>
			</tr>
		</thead>
		<tbody>
		<?php if ( empty( $insights ) ) : ?>
			<tr><td colspan="8"><?php esc_html_e( '改善知見がありません。', 'tsubakuro' ); ?></td></tr>
		<?php else : ?>
			<?php foreach ( $insights as $insight ) : ?>
				<?php $edit_url = admin_url( 'admin.php?page=tsubakuro-insight-form&insight_id=' . $insight['id'] ); ?>
				<tr>
					<td class="column-primary">
						<strong><a href="<?php echo esc_url( $edit_url ); ?>"><?php echo esc_html( $insight['title'] ); ?></a></strong>
					</td>
					<td>
						<?php if ( '' !== $insight['status'] ) : ?>
							<span class="tsubakuro-insight-status tsubakuro-insight-status--<?php echo esc_attr( $insight['status'] ); ?>"><?php echo esc_html( $insight['status_label'] ); ?></span>
						<?php else : ?>
							&#8212;
						<?php endif; ?>
					</td>
					<td><?php echo esc_html( $insight['total_count'] ); ?></td>
					<td>
						<?php echo null === $insight['success_rate'] ? '&#8212;' : esc_html( $insight['success_rate'] . '%' ); ?>
					</td>
					<td><?php echo '' !== $insight['post_kind'] ? esc_html( $insight['post_kind'] ) : '&#8212;'; ?></td>
					<td><?php echo '' !== $insight['action_label'] ? esc_html( $insight['action_label'] ) : '&#8212;'; ?></td>
					<td><?php echo esc_html( mysql2date( 'Y/m/d', $insight['updated_at'] ) ); ?></td>
					<td>
						<a href="<?php echo esc_url( $edit_url ); ?>" class="button button-small"><?php esc_html_e( '編集', 'tsubakuro' ); ?></a>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="tsubakuro-inline-delete" onsubmit="return confirm('<?php echo esc_js( '削除してよろしいですか？' ); ?>');">
							<input type="hidden" name="action" value="tsubakuro_delete_insight" />
							<input type="hidden" name="insight_id" value="<?php echo esc_attr( $insight['id'] ); ?>" />
							<?php wp_nonce_field( 'tsubakuro_delete_insight', 'tsubakuro_insight_nonce' ); ?>
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
