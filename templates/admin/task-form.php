<?php
/**
 * Admin – Task form page template (new / edit).
 *
 * Variables available:
 *   $task                 – array of task data (or null for new task)
 *   $comments             – array of comments (edit mode only)
 *   $parent_task          – array of parent task data (or null)
 *   $child_tasks          – array of child task data (edit mode only)
 *   $default_parent_id    – pre-selected parent_id for new task (int)
 *   $task_defaults        – default new-task field values from request params
 *
 * @package Tsubakuro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$is_edit    = ! empty( $task );
$page_title = $is_edit ? 'タスクを編集' : 'タスクを追加';
?>
<div class="wrap tsubakuro-admin-wrap">
	<h1 class="wp-heading-inline">
		<?php echo esc_html( $page_title ); ?>
	</h1>
	<a href="<?php echo esc_url( admin_url( 'admin.php?page=tsubakuro-tasks' ) ); ?>" class="page-title-action">
		<?php esc_html_e( '← タスク一覧に戻る', 'tsubakuro' ); ?>
	</a>

	<?php
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- displaying redirect error message set by the plugin.
	if ( isset( $_GET['error'] ) ) :
		?>
		<div class="notice notice-error is-dismissible">
			<?php
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- error message is sanitized via sanitize_text_field below.
			$error_msg = sanitize_text_field( rawurldecode( wp_unslash( $_GET['error'] ) ) );
			?>
			<p><?php echo esc_html( $error_msg ); ?></p>
		</div>
	<?php endif; ?>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php wp_nonce_field( 'tsubakuro_save_task', 'tsubakuro_nonce' ); ?>
		<input type="hidden" name="action" value="tsubakuro_save_task">
		<?php if ( $is_edit ) : ?>
			<input type="hidden" name="task_id" value="<?php echo esc_attr( $task['id'] ); ?>">
		<?php endif; ?>

		<div class="tsubakuro-form-card">
			<?php if ( $is_edit ) : ?>
				<div class="tsubakuro-form-row">
					<label><?php esc_html_e( 'ID', 'tsubakuro' ); ?></label>
					<span class="tsubakuro-task-id"><?php echo esc_html( $task['id'] ); ?></span>
				</div>
			<?php endif; ?>

			<div class="tsubakuro-form-row">
				<label for="tsubakuro-task-title">
					<?php esc_html_e( 'タイトル', 'tsubakuro' ); ?>
					<span class="required">*</span>
				</label>
				<input type="text" id="tsubakuro-task-title" name="title" class="widefat"
					value="<?php echo esc_attr( $task['title'] ?? ( $task_defaults['title'] ?? '' ) ); ?>"
					placeholder="<?php esc_attr_e( 'タスクのタイトル', 'tsubakuro' ); ?>"
					required>
			</div>

			<div class="tsubakuro-form-row">
				<div class="tsubakuro-content-header">
					<label><?php esc_html_e( '内容・説明', 'tsubakuro' ); ?></label>
					<div class="tsubakuro-content-tabs" role="group" aria-label="<?php esc_attr_e( '表示モード', 'tsubakuro' ); ?>">
						<button type="button" class="tsubakuro-content-tab" data-mode="preview"><?php esc_html_e( 'プレビュー', 'tsubakuro' ); ?></button>
						<button type="button" class="tsubakuro-content-tab" data-mode="edit"><?php esc_html_e( '編集', 'tsubakuro' ); ?></button>
					</div>
				</div>

				<div id="tsubakuro-content-preview" class="tsubakuro-content-preview" aria-live="polite"></div>

				<textarea id="tsubakuro-task-content" name="content" class="widefat" rows="6"
					placeholder="<?php esc_attr_e( 'タスクの詳細を入力してください', 'tsubakuro' ); ?>"><?php echo esc_textarea( $task['content'] ?? ( $task_defaults['content'] ?? '' ) ); ?></textarea>
			</div>

			<div class="tsubakuro-form-row tsubakuro-form-row--half">
				<div>
					<label for="tsubakuro-task-status">
						<?php esc_html_e( 'ステータス', 'tsubakuro' ); ?>
					</label>
					<select id="tsubakuro-task-status" name="status" class="widefat">
						<?php foreach ( Tsubakuro_Post_Types::STATUSES as $key => $label ) : ?>
							<option value="<?php echo esc_attr( $key ); ?>"
								<?php selected( $task['status'] ?? 'todo', $key ); ?>>
								<?php echo esc_html( $label ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>

				<div>
					<label for="tsubakuro-task-priority">
						<?php esc_html_e( '優先度', 'tsubakuro' ); ?>
					</label>
					<select id="tsubakuro-task-priority" name="priority" class="widefat">
						<?php foreach ( Tsubakuro_Post_Types::PRIORITIES as $key => $label ) : ?>
							<option value="<?php echo esc_attr( $key ); ?>"
								<?php selected( $task['priority'] ?? 'medium', $key ); ?>>
								<?php echo esc_html( $label ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>
			</div>

			<div class="tsubakuro-form-row tsubakuro-form-row--half">
				<div>
					<label for="tsubakuro-task-assignee">
						<?php esc_html_e( 'アサイン', 'tsubakuro' ); ?>
					</label>
					<select id="tsubakuro-task-assignee" name="assignee" class="widefat">
						<option value=""><?php esc_html_e( '未アサイン', 'tsubakuro' ); ?></option>
						<?php foreach ( Tsubakuro_Admin::get_users_list() as $user ) : ?>
							<option value="<?php echo esc_attr( $user['id'] ); ?>"
								<?php selected( $task['assignee']['id'] ?? '', $user['id'] ); ?>>
								<?php echo esc_html( $user['name'] ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>
			</div>

			<div class="tsubakuro-form-row tsubakuro-form-row--half">
				<div>
					<label for="tsubakuro-task-start-remind-at">
						<?php esc_html_e( '開始時間リマインド', 'tsubakuro' ); ?>
					</label>
					<input type="datetime-local" id="tsubakuro-task-start-remind-at" name="start_remind_at" class="widefat"
						value="<?php echo esc_attr( ! empty( $task['start_remind_at'] ) ? str_replace( ' ', 'T', substr( $task['start_remind_at'], 0, 16 ) ) : '' ); ?>">
				</div>

				<div>
					<label for="tsubakuro-task-due-remind-at">
						<?php esc_html_e( '完了期限リマインド', 'tsubakuro' ); ?>
					</label>
					<input type="datetime-local" id="tsubakuro-task-due-remind-at" name="due_remind_at" class="widefat"
						value="<?php echo esc_attr( ! empty( $task['due_remind_at'] ) ? str_replace( ' ', 'T', substr( $task['due_remind_at'], 0, 16 ) ) : '' ); ?>">
				</div>
			</div>

			<div class="tsubakuro-form-row">
				<label for="tsubakuro-parent-task-id">
					<?php esc_html_e( '親タスク', 'tsubakuro' ); ?>
				</label>
				<?php
				$current_parent_id = $is_edit ? ( $task['parent_id'] ?? 0 ) : ( $default_parent_id ?? 0 );
				?>
				<input type="hidden" id="tsubakuro-parent-task-id" name="parent_id"
					value="<?php echo esc_attr( $current_parent_id ); ?>">

				<div id="tsubakuro-parent-task-display" class="tsubakuro-related-tags">
					<?php if ( $parent_task ) : ?>
						<span class="tsubakuro-related-tag" data-id="<?php echo esc_attr( $parent_task['id'] ); ?>">
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=tsubakuro-task-form&task_id=' . $parent_task['id'] ) ); ?>">
								<?php echo esc_html( '#' . $parent_task['id'] . ' ' . $parent_task['title'] ); ?>
							</a>
							<button type="button" class="tsubakuro-related-remove" id="tsubakuro-parent-task-remove"
								aria-label="<?php esc_attr_e( '削除', 'tsubakuro' ); ?>">&#x2715;</button>
						</span>
					<?php endif; ?>
				</div>

				<div class="tsubakuro-related-search">
					<input type="text" id="tsubakuro-parent-task-search-input" class="widefat"
						placeholder="<?php esc_attr_e( 'タスクタイトルで検索して親タスクに設定...', 'tsubakuro' ); ?>"
						autocomplete="off"
						<?php echo $parent_task ? 'style="display:none;"' : ''; ?>>
					<div id="tsubakuro-parent-task-results" class="tsubakuro-related-results" hidden></div>
				</div>
			</div>

			<div class="tsubakuro-form-row">
				<label><?php esc_html_e( '関連ページ', 'tsubakuro' ); ?></label>
				<input type="hidden" id="tsubakuro-task-related" name="related_pages"
					value="<?php echo esc_attr( implode( ', ', $task['related_pages'] ?? ( $task_defaults['related_pages'] ?? array() ) ) ); ?>">

				<div id="tsubakuro-related-tags" class="tsubakuro-related-tags">
					<?php foreach ( $related_page_objects as $rp ) : ?>
						<span class="tsubakuro-related-tag" data-id="<?php echo esc_attr( $rp['id'] ); ?>">
							<?php if ( $rp['url'] ) : ?>
								<a href="<?php echo esc_url( $rp['url'] ); ?>" target="_blank" rel="noopener">
									<?php echo esc_html( $rp['title'] ); ?>
								</a>
							<?php else : ?>
								<?php echo esc_html( $rp['title'] ); ?>
							<?php endif; ?>
							<button type="button" class="tsubakuro-related-remove" data-id="<?php echo esc_attr( $rp['id'] ); ?>"
								aria-label="<?php esc_attr_e( '削除', 'tsubakuro' ); ?>">&#x2715;</button>
						</span>
					<?php endforeach; ?>
				</div>

				<div class="tsubakuro-related-search">
					<input type="text" id="tsubakuro-related-search-input" class="widefat"
						placeholder="<?php esc_attr_e( '記事タイトルで検索して関連付け...', 'tsubakuro' ); ?>"
						autocomplete="off">
					<div id="tsubakuro-related-results" class="tsubakuro-related-results" hidden></div>
				</div>
			</div>

			<div class="tsubakuro-form-footer">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=tsubakuro-tasks' ) ); ?>" class="button">
					<?php esc_html_e( 'キャンセル', 'tsubakuro' ); ?>
				</a>
				<button type="submit" class="button button-primary">
					<?php esc_html_e( '保存', 'tsubakuro' ); ?>
				</button>
			</div>
		</div>
	</form>

	<?php if ( $is_edit ) : ?>
		<!-- Comments -->
		<div class="tsubakuro-form-card" style="margin-top:20px;">
			<h2><?php esc_html_e( 'コメント', 'tsubakuro' ); ?></h2>

			<div id="tsubakuro-comment-list">
				<?php if ( empty( $comments ) ) : ?>
					<p class="tsubakuro-no-comments" style="color:#888;font-size:13px;"><?php esc_html_e( 'コメントはありません。', 'tsubakuro' ); ?></p>
				<?php else : ?>
					<?php foreach ( $comments as $c ) : ?>
						<div class="tsubakuro-comment-item">
							<div class="tsubakuro-comment-meta">
								<strong><?php echo esc_html( $c['user_name'] ); ?></strong>
								&mdash; <?php echo esc_html( $c['created_at'] ); ?>
							</div>
							<div class="tsubakuro-comment-body"><?php echo esc_html( $c['comment'] ); ?></div>
						</div>
					<?php endforeach; ?>
				<?php endif; ?>
			</div>

			<div class="tsubakuro-form-row" style="margin-top:12px;">
				<input type="hidden" id="tsubakuro-task-id" value="<?php echo esc_attr( $task['id'] ); ?>">
				<textarea id="tsubakuro-new-comment" class="widefat" rows="2"
					placeholder="<?php esc_attr_e( 'コメントを入力...', 'tsubakuro' ); ?>"></textarea>
				<button class="button button-secondary" id="tsubakuro-add-comment-btn">
					<?php esc_html_e( 'コメント追加', 'tsubakuro' ); ?>
				</button>
			</div>
		</div>

		<!-- Sub tasks -->
		<div class="tsubakuro-form-card tsubakuro-subtasks-card">
			<h2>
				<?php esc_html_e( 'サブタスク', 'tsubakuro' ); ?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=tsubakuro-task-form&parent_id=' . $task['id'] ) ); ?>" class="page-title-action tsubakuro-subtasks-add-link">
					<?php esc_html_e( '+ サブタスクを追加', 'tsubakuro' ); ?>
				</a>
			</h2>

			<?php if ( empty( $child_tasks ) ) : ?>
				<p class="tsubakuro-subtasks-empty"><?php esc_html_e( 'サブタスクはありません。', 'tsubakuro' ); ?></p>
			<?php else : ?>
				<table class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'ID', 'tsubakuro' ); ?></th>
							<th><?php esc_html_e( 'タイトル', 'tsubakuro' ); ?></th>
							<th><?php esc_html_e( 'ステータス', 'tsubakuro' ); ?></th>
							<th><?php esc_html_e( '優先度', 'tsubakuro' ); ?></th>
							<th><?php esc_html_e( '操作', 'tsubakuro' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $child_tasks as $child ) : ?>
							<tr>
								<td><?php echo esc_html( $child['id'] ); ?></td>
								<td>
									<a href="<?php echo esc_url( admin_url( 'admin.php?page=tsubakuro-task-form&task_id=' . $child['id'] ) ); ?>">
										<?php echo esc_html( $child['title'] ); ?>
									</a>
								</td>
								<td>
									<span class="tsubakuro-status tsubakuro-status--<?php echo esc_attr( $child['status'] ); ?>">
										<?php echo esc_html( $child['status_label'] ); ?>
									</span>
								</td>
								<td>
									<span class="tsubakuro-priority tsubakuro-priority--<?php echo esc_attr( $child['priority'] ); ?>">
										<?php echo esc_html( $child['priority_label'] ); ?>
									</span>
								</td>
								<td>
									<a href="<?php echo esc_url( admin_url( 'admin.php?page=tsubakuro-task-form&task_id=' . $child['id'] ) ); ?>" class="button button-small">
										<?php esc_html_e( '詳細', 'tsubakuro' ); ?>
									</a>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
	<?php endif; ?>
</div><!-- .wrap -->
