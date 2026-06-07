<?php
/**
 * Admin – Related tasks meta box template.
 *
 * Variables available:
 *   $tasks – array of task data for the current post (from Tsubakuro_Post_Types::get_tasks)
 *   $post  – WP_Post object of the current post being edited
 *
 * @package Tsubakuro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<?php if ( empty( $tasks ) ) : ?>
	<p class="description"><?php esc_html_e( 'このページに関連するタスクはありません。', 'tsubakuro' ); ?></p>
<?php else : ?>
	<ul class="tsubakuro-meta-box-task-list">
		<?php foreach ( $tasks as $task ) : ?>
		<li class="tsubakuro-meta-box-task-item">
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=tsubakuro-task-form&task_id=' . $task['id'] ) ); ?>">
				<?php echo esc_html( $task['title'] ); ?>
			</a>
			<span class="tsubakuro-status tsubakuro-status--<?php echo esc_attr( $task['status'] ); ?>">
				<?php echo esc_html( $task['status_label'] ); ?>
			</span>
		</li>
		<?php endforeach; ?>
	</ul>
<?php endif; ?>
<p class="tsubakuro-meta-box-actions">
	<a href="<?php echo esc_url( admin_url( 'admin.php?page=tsubakuro-task-form&related_pages=' . $post->ID ) ); ?>" class="button button-small">
		<?php esc_html_e( 'タスクを追加', 'tsubakuro' ); ?>
	</a>
	<?php if ( ! empty( $tasks ) ) : ?>
	<a href="<?php echo esc_url( admin_url( 'admin.php?page=tsubakuro-tasks&related_page=' . $post->ID ) ); ?>" class="button button-small">
		<?php esc_html_e( 'タスク一覧', 'tsubakuro' ); ?>
	</a>
	<?php endif; ?>
</p>
