<?php
/**
 * Admin – Task form page template (new / edit).
 *
 * Variables available:
 *   $task     – array of task data (or null for new task)
 *   $comments – array of comments (edit mode only)
 *
 * @package Tsubakuro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$is_edit    = ! empty( $task );
$page_title = $is_edit ? 'タスクを編集' : '新規タスク追加';
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
			<div class="tsubakuro-form-row">
				<label for="tsubakuro-task-title">
					<?php esc_html_e( 'タイトル', 'tsubakuro' ); ?>
					<span class="required">*</span>
				</label>
				<input type="text" id="tsubakuro-task-title" name="title" class="widefat"
					value="<?php echo esc_attr( $task['title'] ?? '' ); ?>"
					placeholder="<?php esc_attr_e( 'タスクのタイトル', 'tsubakuro' ); ?>"
					required>
			</div>

			<div class="tsubakuro-form-row">
				<label for="tsubakuro-task-content">
					<?php esc_html_e( '内容・説明', 'tsubakuro' ); ?>
				</label>
				<textarea id="tsubakuro-task-content" name="content" class="widefat" rows="6"
					placeholder="<?php esc_attr_e( 'タスクの詳細を入力してください', 'tsubakuro' ); ?>"><?php echo esc_textarea( $task['content'] ?? '' ); ?></textarea>
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

			<div class="tsubakuro-form-row">
				<label><?php esc_html_e( '関連ページ', 'tsubakuro' ); ?></label>
				<input type="hidden" id="tsubakuro-task-related" name="related_pages"
					value="<?php echo esc_attr( implode( ', ', $task['related_pages'] ?? array() ) ); ?>">

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
	<?php endif; ?>
</div><!-- .wrap -->
