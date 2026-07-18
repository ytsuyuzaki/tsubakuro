<?php
/**
 * Admin – Article evaluation add/edit form template.
 *
 * Variables available:
 *   $evaluation      – evaluation data array (or null for new)
 *   $linked_insights – insights referencing this evaluation (edit mode)
 *   $post_choices    – array of { id, title } target post choices
 *
 * @package Tsubakuro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$is_edit    = ! empty( $evaluation );
$page_title = $is_edit ? '記事評価を編集' : '記事評価を追加';
$field      = static function ( $key ) use ( $evaluation ) {
	return $evaluation[ $key ] ?? '';
};
?>
<div class="wrap tsubakuro-admin-wrap">
	<h1 class="wp-heading-inline"><?php echo esc_html( $page_title ); ?></h1>
	<a href="<?php echo esc_url( admin_url( 'admin.php?page=tsubakuro-evaluations' ) ); ?>" class="page-title-action">
		<?php esc_html_e( '← 記事評価一覧に戻る', 'tsubakuro' ); ?>
	</a>

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
		<?php wp_nonce_field( 'tsubakuro_save_evaluation', 'tsubakuro_evaluation_nonce' ); ?>
		<input type="hidden" name="action" value="tsubakuro_save_evaluation">
		<?php if ( $is_edit ) : ?>
			<input type="hidden" name="evaluation_id" value="<?php echo esc_attr( $evaluation['id'] ); ?>">
		<?php endif; ?>

		<div class="tsubakuro-form-card">
			<div class="tsubakuro-form-row">
				<label for="tsubakuro-eval-title"><?php esc_html_e( 'タイトル', 'tsubakuro' ); ?></label>
				<input type="text" id="tsubakuro-eval-title" name="title" class="widefat"
					value="<?php echo esc_attr( $field( 'title' ) ); ?>"
					placeholder="<?php esc_attr_e( '未入力の場合は対象記事と変更項目から自動生成します', 'tsubakuro' ); ?>">
			</div>

			<div class="tsubakuro-form-row tsubakuro-form-row--half">
				<div>
					<label for="tsubakuro-eval-target"><?php esc_html_e( '対象記事', 'tsubakuro' ); ?></label>
					<select id="tsubakuro-eval-target" name="target_post" class="widefat">
						<option value="0"><?php esc_html_e( '（未選択）', 'tsubakuro' ); ?></option>
						<?php foreach ( $post_choices as $choice ) : ?>
							<option value="<?php echo esc_attr( $choice['id'] ); ?>" <?php selected( (int) $field( 'target_post_id' ), (int) $choice['id'] ); ?>><?php echo esc_html( $choice['title'] ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<div>
					<label for="tsubakuro-eval-change-item"><?php esc_html_e( '変更項目', 'tsubakuro' ); ?></label>
					<select id="tsubakuro-eval-change-item" name="change_item" class="widefat">
						<option value=""><?php esc_html_e( '（未選択）', 'tsubakuro' ); ?></option>
						<?php foreach ( Tsubakuro_Evaluations::CHANGE_ITEMS as $key => $label ) : ?>
							<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $field( 'change_item' ), $key ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
			</div>

			<div class="tsubakuro-form-row">
				<label for="tsubakuro-eval-detail"><?php esc_html_e( '変更内容', 'tsubakuro' ); ?></label>
				<textarea id="tsubakuro-eval-detail" name="change_detail" class="widefat" rows="4"><?php echo esc_textarea( $field( 'change_detail' ) ); ?></textarea>
			</div>

			<?php if ( ! $is_edit ) : ?>
				<div class="tsubakuro-form-row">
					<label for="tsubakuro-eval-due"><?php esc_html_e( '評価予定日（リマインド）', 'tsubakuro' ); ?></label>
					<input type="date" id="tsubakuro-eval-due" name="due_at" class="widefat" value="<?php echo esc_attr( $field( 'due_at' ) ); ?>">
				</div>
			<?php endif; ?>

			<?php if ( ! $is_edit ) : ?>
				<div class="tsubakuro-form-row">
					<p class="description"><?php esc_html_e( '作成後に詳細項目を追記できます。進捗や追加情報はコメントで管理できます。', 'tsubakuro' ); ?></p>
				</div>
			<?php endif; ?>

			<?php if ( $is_edit ) : ?>
			<div class="tsubakuro-form-row">
				<label for="tsubakuro-eval-purpose"><?php esc_html_e( '目的', 'tsubakuro' ); ?></label>
				<textarea id="tsubakuro-eval-purpose" name="purpose" class="widefat" rows="3"><?php echo esc_textarea( $field( 'purpose' ) ); ?></textarea>
			</div>

			<div class="tsubakuro-form-row tsubakuro-form-row--half">
				<div>
					<label for="tsubakuro-eval-implemented"><?php esc_html_e( '実施日', 'tsubakuro' ); ?></label>
					<input type="date" id="tsubakuro-eval-implemented" name="implemented_at" class="widefat" value="<?php echo esc_attr( $field( 'implemented_at' ) ); ?>">
				</div>
				<div>
					<label for="tsubakuro-eval-due"><?php esc_html_e( '評価予定日', 'tsubakuro' ); ?></label>
					<input type="date" id="tsubakuro-eval-due" name="due_at" class="widefat" value="<?php echo esc_attr( $field( 'due_at' ) ); ?>">
				</div>
			</div>

			<div class="tsubakuro-form-row tsubakuro-form-row--half">
				<div>
					<label for="tsubakuro-eval-metric"><?php esc_html_e( '評価指標', 'tsubakuro' ); ?></label>
					<select id="tsubakuro-eval-metric" name="metric" class="widefat">
						<option value=""><?php esc_html_e( '（未選択）', 'tsubakuro' ); ?></option>
						<?php foreach ( Tsubakuro_Evaluations::METRICS as $key => $label ) : ?>
							<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $field( 'metric' ), $key ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<div>
					<label for="tsubakuro-eval-judgment"><?php esc_html_e( '判定', 'tsubakuro' ); ?></label>
					<select id="tsubakuro-eval-judgment" name="judgment" class="widefat">
						<option value=""><?php esc_html_e( '未評価', 'tsubakuro' ); ?></option>
						<?php foreach ( Tsubakuro_Evaluations::JUDGMENTS as $key => $label ) : ?>
							<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $field( 'judgment' ), $key ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
			</div>

			<div class="tsubakuro-form-row tsubakuro-form-row--half">
				<div>
					<label for="tsubakuro-eval-before"><?php esc_html_e( '実施前の数値', 'tsubakuro' ); ?></label>
					<input type="text" id="tsubakuro-eval-before" name="before_value" class="widefat" value="<?php echo esc_attr( $field( 'before_value' ) ); ?>">
				</div>
				<div>
					<label for="tsubakuro-eval-after"><?php esc_html_e( '実施後の数値', 'tsubakuro' ); ?></label>
					<input type="text" id="tsubakuro-eval-after" name="after_value" class="widefat" value="<?php echo esc_attr( $field( 'after_value' ) ); ?>">
				</div>
			</div>

			<div class="tsubakuro-form-row">
				<label for="tsubakuro-eval-result"><?php esc_html_e( '結果', 'tsubakuro' ); ?></label>
				<textarea id="tsubakuro-eval-result" name="result" class="widefat" rows="3"><?php echo esc_textarea( $field( 'result' ) ); ?></textarea>
			</div>

			<div class="tsubakuro-form-row">
				<label for="tsubakuro-eval-note"><?php esc_html_e( '備考', 'tsubakuro' ); ?></label>
				<textarea id="tsubakuro-eval-note" name="note" class="widefat" rows="2"><?php echo esc_textarea( $field( 'note' ) ); ?></textarea>
			</div>
			<?php endif; ?>

			<?php if ( $is_edit && ! empty( $linked_insights ) ) : ?>
				<div class="tsubakuro-form-row">
					<label><?php esc_html_e( '関連する改善知見', 'tsubakuro' ); ?></label>
					<ul class="tsubakuro-linked-list">
						<?php foreach ( $linked_insights as $linked_insight ) : ?>
							<li>
								<a href="<?php echo esc_url( admin_url( 'admin.php?page=tsubakuro-insight-form&insight_id=' . $linked_insight['id'] ) ); ?>">
									<?php echo esc_html( $linked_insight['title'] ); ?>
								</a>
							</li>
						<?php endforeach; ?>
					</ul>
				</div>
			<?php endif; ?>

			<div class="tsubakuro-form-actions">
				<button type="submit" class="button button-primary"><?php esc_html_e( '保存', 'tsubakuro' ); ?></button>
			</div>
		</div>
	</form>

	<?php if ( $is_edit ) : ?>
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
				<input type="hidden" id="tsubakuro-comment-parent-id" value="<?php echo esc_attr( $evaluation['id'] ); ?>">
				<textarea id="tsubakuro-new-comment" class="widefat" rows="2" placeholder="<?php esc_attr_e( 'コメントを入力...', 'tsubakuro' ); ?>"></textarea>
				<button class="button button-secondary" id="tsubakuro-add-comment-btn"><?php esc_html_e( 'コメント追加', 'tsubakuro' ); ?></button>
			</div>
		</div>
	<?php endif; ?>
</div><!-- .wrap -->
