<?php
/**
 * Admin – Task list page template.
 *
 * Variables available:
 *   $tasks      – array of task data (from Tsubakuro_Post_Types::get_tasks)
 *   $list_args  – current list query args
 *   $message    – current admin notice key
 *   $users      – formatted WordPress users
 *
 * @package Tsubakuro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$status_filter   = $list_args['status'] ?? '';
$assignee_filter = (int) ( $list_args['assignee'] ?? 0 );
$search_query    = $list_args['s'] ?? '';
$current_orderby = $list_args['orderby'] ?? 'date';
$current_order   = $list_args['order'] ?? 'DESC';
$deleted_count   = absint( $_GET['deleted_count'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display-only notice count.

$sortable_columns = array(
	'id'       => __( 'ID', 'tsubakuro' ),
	'title'    => __( 'タイトル', 'tsubakuro' ),
	'status'   => __( 'ステータス', 'tsubakuro' ),
	'assignee' => __( 'アサイン', 'tsubakuro' ),
	'date'     => __( '作成日', 'tsubakuro' ),
);
?>
<div class="wrap tsubakuro-admin-wrap">
	<h1 class="wp-heading-inline">
		<?php esc_html_e( 'タスク管理', 'tsubakuro' ); ?>
	</h1>
	<a href="<?php echo esc_url( admin_url( 'admin.php?page=tsubakuro-task-form' ) ); ?>" class="page-title-action">
		<?php esc_html_e( '新規タスク追加', 'tsubakuro' ); ?>
	</a>
	<hr class="wp-header-end">

	<?php if ( 'saved' === $message ) : ?>
	<div class="notice notice-success is-dismissible">
		<p><?php esc_html_e( 'タスクを保存しました。', 'tsubakuro' ); ?></p>
	</div>
	<?php elseif ( 'bulk_deleted' === $message ) : ?>
	<div class="notice notice-success is-dismissible">
		<p>
			<?php
			printf(
				/* translators: %d: deleted task count. */
				esc_html__( '%d件のタスクを削除しました。', 'tsubakuro' ),
				esc_html( $deleted_count )
			);
			?>
		</p>
	</div>
	<?php elseif ( 'no_tasks_selected' === $message ) : ?>
	<div class="notice notice-warning is-dismissible">
		<p><?php esc_html_e( '削除するタスクを選択してください。', 'tsubakuro' ); ?></p>
	</div>
	<?php endif; ?>

	<ul class="subsubsub tsubakuro-filter-tabs">
		<li>
			<a href="<?php echo esc_url( Tsubakuro_Admin::get_task_list_url( array( 'status' => null ) ) ); ?>"
				class="<?php echo ( '' === $status_filter ) ? 'current' : ''; ?>">
				<?php esc_html_e( 'すべて', 'tsubakuro' ); ?>
			</a> |
		</li>
		<?php foreach ( Tsubakuro_Post_Types::STATUSES as $key => $label ) : ?>
		<li>
			<a href="<?php echo esc_url( Tsubakuro_Admin::get_task_list_url( array( 'status' => $key ) ) ); ?>"
				class="<?php echo ( $status_filter === $key ) ? 'current' : ''; ?>">
				<?php echo esc_html( $label ); ?>
			</a>
			<?php echo array_key_last( Tsubakuro_Post_Types::STATUSES ) !== $key ? ' | ' : ''; ?>
		</li>
		<?php endforeach; ?>
	</ul>

	<form method="get" class="tsubakuro-list-filter-form">
		<input type="hidden" name="page" value="tsubakuro-tasks" />

		<p class="search-box">
			<label class="screen-reader-text" for="tsubakuro-task-search-input"><?php esc_html_e( 'タスクを検索', 'tsubakuro' ); ?></label>
			<input type="search" id="tsubakuro-task-search-input" name="s" value="<?php echo esc_attr( $search_query ); ?>" />
			<input type="submit" class="button" value="<?php esc_attr_e( '検索', 'tsubakuro' ); ?>" />
		</p>

		<div class="tablenav top">
			<div class="alignleft actions">
				<label class="screen-reader-text" for="tsubakuro-filter-status"><?php esc_html_e( 'ステータスで絞り込み', 'tsubakuro' ); ?></label>
				<select id="tsubakuro-filter-status" name="status">
					<option value=""><?php esc_html_e( 'すべてのステータス', 'tsubakuro' ); ?></option>
					<?php foreach ( Tsubakuro_Post_Types::STATUSES as $key => $label ) : ?>
						<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $status_filter, $key ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>

				<label class="screen-reader-text" for="tsubakuro-filter-assignee"><?php esc_html_e( '担当者で絞り込み', 'tsubakuro' ); ?></label>
				<select id="tsubakuro-filter-assignee" name="assignee">
					<option value="0"><?php esc_html_e( 'すべての担当者', 'tsubakuro' ); ?></option>
					<?php foreach ( $users as $user ) : ?>
						<option value="<?php echo esc_attr( $user['id'] ); ?>" <?php selected( $assignee_filter, $user['id'] ); ?>><?php echo esc_html( $user['name'] ); ?></option>
					<?php endforeach; ?>
				</select>

				<input type="hidden" name="orderby" value="<?php echo esc_attr( $current_orderby ); ?>" />
				<input type="hidden" name="order" value="<?php echo esc_attr( $current_order ); ?>" />
				<input type="submit" class="button" value="<?php esc_attr_e( '絞り込み', 'tsubakuro' ); ?>" />
			</div>
			<br class="clear" />
		</div>
	</form>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="tsubakuro_bulk_tasks" />
		<?php wp_nonce_field( 'tsubakuro_bulk_tasks', 'tsubakuro_bulk_nonce' ); ?>
		<?php foreach ( $list_args as $key => $value ) : ?>
			<input type="hidden" name="<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( $value ); ?>" />
		<?php endforeach; ?>

		<div class="tablenav top">
			<div class="alignleft actions bulkactions">
				<label for="tsubakuro-bulk-action-selector-top" class="screen-reader-text"><?php esc_html_e( '一括操作を選択', 'tsubakuro' ); ?></label>
				<select name="bulk_action" id="tsubakuro-bulk-action-selector-top">
					<option value=""><?php esc_html_e( '一括操作', 'tsubakuro' ); ?></option>
					<option value="delete"><?php esc_html_e( '削除', 'tsubakuro' ); ?></option>
				</select>
				<input type="submit" class="button action" value="<?php esc_attr_e( '適用', 'tsubakuro' ); ?>" />
			</div>
			<br class="clear" />
		</div>

		<table class="wp-list-table widefat fixed striped table-view-list tsubakuro-task-table">
			<thead>
				<tr>
					<td class="manage-column column-cb check-column">
						<input type="checkbox" id="cb-select-all-1" class="tsubakuro-select-all" />
					</td>
					<?php foreach ( $sortable_columns as $column => $label ) : ?>
						<?php
						$next_order = Tsubakuro_Admin::get_next_task_list_order( $column );
						$sort_class = $current_orderby === $column ? 'sorted ' . strtolower( $current_order ) : 'sortable asc';
						$sort_url   = Tsubakuro_Admin::get_task_list_url(
							array(
								'orderby' => $column,
								'order'   => $next_order,
							)
						);
						?>
						<th scope="col" class="manage-column column-<?php echo esc_attr( $column ); ?> <?php echo esc_attr( $sort_class ); ?>">
							<a href="<?php echo esc_url( $sort_url ); ?>">
								<span><?php echo esc_html( $label ); ?></span>
								<span class="sorting-indicators">
									<span class="sorting-indicator asc" aria-hidden="true"></span>
									<span class="sorting-indicator desc" aria-hidden="true"></span>
								</span>
							</a>
						</th>
					<?php endforeach; ?>
					<th scope="col" class="manage-column column-actions"><?php esc_html_e( '操作', 'tsubakuro' ); ?></th>
				</tr>
			</thead>
			<tbody>
			<?php if ( empty( $tasks ) ) : ?>
				<tr>
					<td colspan="7"><?php esc_html_e( 'タスクがありません。', 'tsubakuro' ); ?></td>
				</tr>
			<?php else : ?>
				<?php foreach ( $tasks as $task ) : ?>
				<tr data-task-id="<?php echo esc_attr( $task['id'] ); ?>">
					<th scope="row" class="check-column">
						<input type="checkbox" name="task_ids[]" value="<?php echo esc_attr( $task['id'] ); ?>" />
					</th>
					<td><?php echo esc_html( $task['id'] ); ?></td>
					<td class="title column-title has-row-actions column-primary">
						<strong>
							<a class="row-title" href="<?php echo esc_url( admin_url( 'admin.php?page=tsubakuro-task-form&task_id=' . $task['id'] ) ); ?>">
								<?php echo esc_html( $task['title'] ); ?>
							</a>
						</strong>
						<div class="row-actions">
							<span class="edit">
								<a href="<?php echo esc_url( admin_url( 'admin.php?page=tsubakuro-task-form&task_id=' . $task['id'] ) ); ?>"><?php esc_html_e( '編集', 'tsubakuro' ); ?></a> |
							</span>
							<span class="trash">
								<button type="button" class="button-link delete tsubakuro-delete-task" data-task-id="<?php echo esc_attr( $task['id'] ); ?>">
									<?php esc_html_e( '削除', 'tsubakuro' ); ?>
								</button>
							</span>
						</div>
					</td>
					<td>
						<span class="tsubakuro-status tsubakuro-status--<?php echo esc_attr( $task['status'] ); ?>">
							<?php echo esc_html( $task['status_label'] ); ?>
						</span>
					</td>
					<td>
						<?php echo $task['assignee'] ? esc_html( $task['assignee']['display_name'] ) : '&#8212;'; ?>
					</td>
					<td><?php echo esc_html( mysql2date( 'Y/m/d', $task['created_at'] ) ); ?></td>
					<td>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=tsubakuro-task-form&task_id=' . $task['id'] ) ); ?>" class="button button-small">
							<?php esc_html_e( '詳細', 'tsubakuro' ); ?>
						</a>
					</td>
				</tr>
				<?php endforeach; ?>
			<?php endif; ?>
			</tbody>
		</table>

		<div class="tablenav bottom">
			<div class="alignleft actions bulkactions">
				<label for="tsubakuro-bulk-action-selector-bottom" class="screen-reader-text"><?php esc_html_e( '一括操作を選択', 'tsubakuro' ); ?></label>
				<select name="bulk_action_bottom" id="tsubakuro-bulk-action-selector-bottom" class="tsubakuro-bulk-action-bottom">
					<option value=""><?php esc_html_e( '一括操作', 'tsubakuro' ); ?></option>
					<option value="delete"><?php esc_html_e( '削除', 'tsubakuro' ); ?></option>
				</select>
				<input type="submit" class="button action tsubakuro-bulk-apply-bottom" value="<?php esc_attr_e( '適用', 'tsubakuro' ); ?>" />
			</div>
			<br class="clear" />
		</div>
	</form>
</div><!-- .wrap -->
