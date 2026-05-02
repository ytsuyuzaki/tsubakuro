<?php
/**
 * Frontend – floating task panel template.
 * Rendered in wp_footer() for logged-in editors/admins.
 *
 * @package Tsubakuro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<!-- Tsubakuro Frontend Task Panel -->
<div id="tsubakuro-fab" title="<?php esc_attr_e( 'タスク管理', 'tsubakuro' ); ?>">
	<span class="dashicons dashicons-list-view"></span>
</div>

<div id="tsubakuro-panel" class="tsubakuro-panel" style="display:none;">
	<div class="tsubakuro-panel-header">
		<span><?php esc_html_e( 'タスク管理', 'tsubakuro' ); ?></span>
		<button class="tsubakuro-panel-close" aria-label="<?php esc_attr_e( '閉じる', 'tsubakuro' ); ?>">&times;</button>
	</div>

	<!-- Tab bar -->
	<div class="tsubakuro-tabs">
		<button class="tsubakuro-tab active" data-tab="list"><?php esc_html_e( '一覧', 'tsubakuro' ); ?></button>
		<button class="tsubakuro-tab" data-tab="new"><?php esc_html_e( '新規追加', 'tsubakuro' ); ?></button>
	</div>

	<!-- Task list tab -->
	<div class="tsubakuro-tab-content active" data-tab="list">
		<div class="tsubakuro-task-filters">
			<select id="tsubakuro-pub-status-filter">
				<option value=""><?php esc_html_e( '全ステータス', 'tsubakuro' ); ?></option>
				<?php foreach ( Tsubakuro_Post_Types::STATUSES as $key => $label ) : ?>
				<option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
			<label>
				<input type="checkbox" id="tsubakuro-pub-page-filter" checked>
				<?php esc_html_e( 'このページのタスクのみ', 'tsubakuro' ); ?>
			</label>
		</div>
		<div id="tsubakuro-pub-task-list">
			<p class="tsubakuro-loading"><?php esc_html_e( '読み込み中...', 'tsubakuro' ); ?></p>
		</div>
	</div>

	<!-- New task tab -->
	<div class="tsubakuro-tab-content" data-tab="new">
		<div class="tsubakuro-pub-form">
			<input type="hidden" id="tsubakuro-pub-editing-id" value="">

			<div class="tsubakuro-pub-form-row">
				<label><?php esc_html_e( 'タイトル', 'tsubakuro' ); ?> <span class="required">*</span></label>
				<input type="text" id="tsubakuro-pub-title" placeholder="<?php esc_attr_e( 'タスクのタイトル', 'tsubakuro' ); ?>">
			</div>

			<div class="tsubakuro-pub-form-row">
				<label><?php esc_html_e( '内容', 'tsubakuro' ); ?></label>
				<textarea id="tsubakuro-pub-content" rows="3"
					placeholder="<?php esc_attr_e( 'タスクの詳細...', 'tsubakuro' ); ?>"></textarea>
			</div>

			<div class="tsubakuro-pub-form-row">
				<label><?php esc_html_e( 'ステータス', 'tsubakuro' ); ?></label>
				<select id="tsubakuro-pub-status">
					<?php foreach ( Tsubakuro_Post_Types::STATUSES as $key => $label ) : ?>
					<option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>

			<div class="tsubakuro-pub-form-row">
				<label><?php esc_html_e( 'アサイン', 'tsubakuro' ); ?></label>
				<select id="tsubakuro-pub-assignee">
					<option value=""><?php esc_html_e( '未アサイン', 'tsubakuro' ); ?></option>
					<?php foreach ( Tsubakuro_Admin::get_users_list() as $user ) : ?>
					<option value="<?php echo esc_attr( $user['id'] ); ?>"><?php echo esc_html( $user['name'] ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>

			<div class="tsubakuro-pub-form-row tsubakuro-pub-form-row--checkbox">
				<label>
					<input type="checkbox" id="tsubakuro-pub-link-page" checked>
					<?php esc_html_e( 'このページに関連付ける', 'tsubakuro' ); ?>
				</label>
			</div>

			<div class="tsubakuro-pub-form-actions">
				<button class="tsubakuro-btn tsubakuro-btn--secondary" id="tsubakuro-pub-cancel-edit" style="display:none;">
					<?php esc_html_e( 'キャンセル', 'tsubakuro' ); ?>
				</button>
				<button class="tsubakuro-btn tsubakuro-btn--primary" id="tsubakuro-pub-save">
					<?php esc_html_e( 'タスクを保存', 'tsubakuro' ); ?>
				</button>
			</div>
		</div>
	</div>

	<!-- Task detail (comments) – shown inline within list tab -->
	<div id="tsubakuro-pub-detail" class="tsubakuro-pub-detail" style="display:none;">
		<div class="tsubakuro-pub-detail-header">
			<button id="tsubakuro-pub-detail-back">&larr; <?php esc_html_e( '一覧に戻る', 'tsubakuro' ); ?></button>
			<h3 id="tsubakuro-pub-detail-title"></h3>
		</div>
		<div id="tsubakuro-pub-detail-content" class="tsubakuro-pub-detail-content"></div>
		<div class="tsubakuro-pub-detail-status">
			<label><?php esc_html_e( 'ステータス変更', 'tsubakuro' ); ?></label>
			<select id="tsubakuro-pub-detail-status">
				<?php foreach ( Tsubakuro_Post_Types::STATUSES as $key => $label ) : ?>
				<option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
			<button class="tsubakuro-btn tsubakuro-btn--small" id="tsubakuro-pub-update-status">
				<?php esc_html_e( '更新', 'tsubakuro' ); ?>
			</button>
		</div>
		<div class="tsubakuro-pub-comments">
			<h4><?php esc_html_e( 'コメント', 'tsubakuro' ); ?></h4>
			<div id="tsubakuro-pub-comment-list"></div>
			<div class="tsubakuro-pub-add-comment">
				<textarea id="tsubakuro-pub-comment-input" rows="2"
					placeholder="<?php esc_attr_e( 'コメントを入力...', 'tsubakuro' ); ?>"></textarea>
				<button class="tsubakuro-btn tsubakuro-btn--primary" id="tsubakuro-pub-comment-submit">
					<?php esc_html_e( '送信', 'tsubakuro' ); ?>
				</button>
			</div>
		</div>
	</div>
</div>
