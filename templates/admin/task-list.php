<?php
/**
 * Admin – Task list page template.
 *
 * Variables available:
 *   $tasks          – array of task data (from Tsubakuro_Post_Types::get_tasks)
 *   $status_filter  – currently active status filter string
 *
 * @package Tsubakuro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap tsubakuro-admin-wrap">
	<h1 class="wp-heading-inline">
		<?php esc_html_e( 'タスク管理', 'tsubakuro' ); ?>
	</h1>
	<a href="<?php echo esc_url( admin_url( 'admin.php?page=tsubakuro-task-form' ) ); ?>" class="page-title-action">
		<?php esc_html_e( '新規タスク追加', 'tsubakuro' ); ?>
	</a>

	<?php if ( 'saved' === $message ) : ?>
	<div class="notice notice-success is-dismissible">
		<p><?php esc_html_e( 'タスクを保存しました。', 'tsubakuro' ); ?></p>
	</div>
	<?php endif; ?>

	<!-- Status filter tabs -->
	<ul class="subsubsub tsubakuro-filter-tabs">
		<li>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=tsubakuro-tasks' ) ); ?>"
			   class="<?php echo ( '' === $status_filter ) ? 'current' : ''; ?>">
				<?php esc_html_e( 'すべて', 'tsubakuro' ); ?>
			</a> |
		</li>
		<?php foreach ( Tsubakuro_Post_Types::STATUSES as $key => $label ) : ?>
		<li>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=tsubakuro-tasks&status=' . $key ) ); ?>"
			   class="<?php echo ( $status_filter === $key ) ? 'current' : ''; ?>">
				<?php echo esc_html( $label ); ?>
			</a>
			<?php echo $key !== array_key_last( Tsubakuro_Post_Types::STATUSES ) ? ' | ' : ''; ?>
		</li>
		<?php endforeach; ?>
	</ul>

	<!-- Task table -->
	<table class="wp-list-table widefat fixed striped tsubakuro-task-table">
		<thead>
			<tr>
				<th><?php esc_html_e( 'ID', 'tsubakuro' ); ?></th>
				<th><?php esc_html_e( 'タイトル', 'tsubakuro' ); ?></th>
				<th><?php esc_html_e( 'ステータス', 'tsubakuro' ); ?></th>
				<th><?php esc_html_e( 'アサイン', 'tsubakuro' ); ?></th>
				<th><?php esc_html_e( '関連ページ', 'tsubakuro' ); ?></th>
				<th><?php esc_html_e( '作成日', 'tsubakuro' ); ?></th>
				<th><?php esc_html_e( '操作', 'tsubakuro' ); ?></th>
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
				<td><?php echo esc_html( $task['id'] ); ?></td>
				<td>
					<strong>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=tsubakuro-task-form&task_id=' . $task['id'] ) ); ?>">
							<?php echo esc_html( $task['title'] ); ?>
						</a>
					</strong>
				</td>
				<td>
					<span class="tsubakuro-status tsubakuro-status--<?php echo esc_attr( $task['status'] ); ?>">
						<?php echo esc_html( $task['status_label'] ); ?>
					</span>
				</td>
				<td>
					<?php echo $task['assignee'] ? esc_html( $task['assignee']['display_name'] ) : '&#8212;'; ?>
				</td>
				<td>
					<?php
					if ( ! empty( $task['related_pages'] ) ) {
						$titles = array();
						foreach ( $task['related_pages'] as $page_id ) {
							$p = get_post( $page_id );
							if ( $p ) {
								$titles[] = esc_html( $p->post_title );
							}
						}
						echo implode( ', ', $titles );
					} else {
						echo '&#8212;';
					}
					?>
				</td>
				<td><?php echo esc_html( mysql2date( 'Y/m/d', $task['created_at'] ) ); ?></td>
				<td>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=tsubakuro-task-form&task_id=' . $task['id'] ) ); ?>" class="button button-small">
						<?php esc_html_e( '詳細', 'tsubakuro' ); ?>
					</a>
					<button class="button button-small tsubakuro-delete-task" data-task-id="<?php echo esc_attr( $task['id'] ); ?>">
						<?php esc_html_e( '削除', 'tsubakuro' ); ?>
					</button>
				</td>
			</tr>
			<?php endforeach; ?>
		<?php endif; ?>
		</tbody>
	</table>
</div><!-- .wrap -->

