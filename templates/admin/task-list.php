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
	<button class="page-title-action" id="tsubakuro-new-task-btn">
		<?php esc_html_e( '新規タスク追加', 'tsubakuro' ); ?>
	</button>

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
						<a href="#" class="tsubakuro-task-detail-link" data-task-id="<?php echo esc_attr( $task['id'] ); ?>">
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
					<button class="button button-small tsubakuro-task-detail-link" data-task-id="<?php echo esc_attr( $task['id'] ); ?>">
						<?php esc_html_e( '詳細', 'tsubakuro' ); ?>
					</button>
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

<!-- ===== New Task Modal ===== -->
<div id="tsubakuro-modal-overlay" class="tsubakuro-modal-overlay" style="display:none;">
	<div class="tsubakuro-modal" id="tsubakuro-task-modal">
		<div class="tsubakuro-modal-header">
			<h2 id="tsubakuro-modal-title"><?php esc_html_e( '新規タスク追加', 'tsubakuro' ); ?></h2>
			<button class="tsubakuro-modal-close" aria-label="<?php esc_attr_e( '閉じる', 'tsubakuro' ); ?>">&times;</button>
		</div>
		<div class="tsubakuro-modal-body">
			<input type="hidden" id="tsubakuro-task-id" value="">

			<div class="tsubakuro-form-row">
				<label for="tsubakuro-task-title"><?php esc_html_e( 'タイトル', 'tsubakuro' ); ?> <span class="required">*</span></label>
				<input type="text" id="tsubakuro-task-title" class="widefat" placeholder="<?php esc_attr_e( 'タスクのタイトル', 'tsubakuro' ); ?>">
			</div>

			<div class="tsubakuro-form-row">
				<label for="tsubakuro-task-content"><?php esc_html_e( '内容・説明', 'tsubakuro' ); ?></label>
				<textarea id="tsubakuro-task-content" class="widefat" rows="4"
					placeholder="<?php esc_attr_e( 'タスクの詳細を入力してください', 'tsubakuro' ); ?>"></textarea>
			</div>

			<div class="tsubakuro-form-row tsubakuro-form-row--half">
				<div>
					<label for="tsubakuro-task-status"><?php esc_html_e( 'ステータス', 'tsubakuro' ); ?></label>
					<select id="tsubakuro-task-status" class="widefat">
						<?php foreach ( Tsubakuro_Post_Types::STATUSES as $key => $label ) : ?>
						<option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>

				<div>
					<label for="tsubakuro-task-assignee"><?php esc_html_e( 'アサイン', 'tsubakuro' ); ?></label>
					<select id="tsubakuro-task-assignee" class="widefat">
						<option value=""><?php esc_html_e( '未アサイン', 'tsubakuro' ); ?></option>
						<?php foreach ( Tsubakuro_Admin::get_users_list() as $user ) : ?>
						<option value="<?php echo esc_attr( $user['id'] ); ?>"><?php echo esc_html( $user['name'] ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
			</div>

			<div class="tsubakuro-form-row">
				<label for="tsubakuro-task-related"><?php esc_html_e( '関連ページ（ページIDをカンマ区切り）', 'tsubakuro' ); ?></label>
				<input type="text" id="tsubakuro-task-related" class="widefat"
					placeholder="<?php esc_attr_e( '例: 1, 5, 10', 'tsubakuro' ); ?>">
			</div>

			<!-- Comments section (visible in edit mode) -->
			<div id="tsubakuro-comments-section" style="display:none;">
				<hr>
				<h3><?php esc_html_e( 'コメント', 'tsubakuro' ); ?></h3>
				<div id="tsubakuro-comment-list"></div>
				<div class="tsubakuro-form-row">
					<textarea id="tsubakuro-new-comment" class="widefat" rows="2"
						placeholder="<?php esc_attr_e( 'コメントを入力...', 'tsubakuro' ); ?>"></textarea>
					<button class="button button-secondary" id="tsubakuro-add-comment-btn">
						<?php esc_html_e( 'コメント追加', 'tsubakuro' ); ?>
					</button>
				</div>
			</div>
		</div>
		<div class="tsubakuro-modal-footer">
			<button class="button tsubakuro-modal-close"><?php esc_html_e( 'キャンセル', 'tsubakuro' ); ?></button>
			<button class="button button-primary" id="tsubakuro-save-task-btn">
				<?php esc_html_e( '保存', 'tsubakuro' ); ?>
			</button>
		</div>
	</div>
</div>
