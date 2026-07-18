<?php
/**
 * Admin – Site strategy edit form template.
 *
 * The site strategy is a singleton, so this is a single edit form (no list).
 *
 * Variables available:
 *   $strategy – site strategy data array (Tsubakuro_Site_Strategy::get_strategy)
 *   $message  – current admin notice key
 *
 * @package Tsubakuro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$field_hints = array(
	'purpose'   => '例：初めての人でも失敗しない選び方を提供する',
	'position'  => '例：〇〇分野で最初に思い出される比較メディア',
	'direction' => '例：一次情報と実体験にもとづく比較を増やす',
	'audience'  => '例：〇〇を初めて検討する20〜30代',
	'value'     => '例：中立的な比較と根拠の明示',
);
?>
<div class="wrap tsubakuro-admin-wrap">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'サイト方針', 'tsubakuro' ); ?></h1>
	<hr class="wp-header-end">

	<?php if ( 'saved' === $message ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'サイト方針を保存しました。', 'tsubakuro' ); ?></p></div>
	<?php endif; ?>

	<p class="tsubakuro-form-lead">
		<?php esc_html_e( 'サイトが何のために存在し、どこを目指すのかを定義します。ここで決めた方針が、タスク管理や記事評価の判断のよりどころになります。', 'tsubakuro' ); ?>
	</p>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php wp_nonce_field( 'tsubakuro_save_site_strategy', 'tsubakuro_site_strategy_nonce' ); ?>
		<input type="hidden" name="action" value="tsubakuro_save_site_strategy">

		<div class="tsubakuro-form-card">
			<?php foreach ( Tsubakuro_Site_Strategy::FIELDS as $key => $label ) : ?>
				<div class="tsubakuro-form-row">
					<label for="tsubakuro-site-strategy-<?php echo esc_attr( $key ); ?>">
						<?php echo esc_html( $label ); ?>
					</label>
					<textarea
						id="tsubakuro-site-strategy-<?php echo esc_attr( $key ); ?>"
						name="<?php echo esc_attr( $key ); ?>"
						class="widefat"
						rows="3"
						placeholder="<?php echo esc_attr( $field_hints[ $key ] ?? '' ); ?>"><?php echo esc_textarea( $strategy[ $key ] ?? '' ); ?></textarea>
				</div>
			<?php endforeach; ?>

			<?php if ( ! empty( $strategy['updated_at'] ) ) : ?>
				<p class="description">
					<?php
					printf(
						/* translators: %s: last updated datetime. */
						esc_html__( '最終更新: %s', 'tsubakuro' ),
						esc_html( $strategy['updated_at'] )
					);
					?>
				</p>
			<?php endif; ?>

			<div class="tsubakuro-form-actions">
				<button type="submit" class="button button-primary"><?php esc_html_e( '保存', 'tsubakuro' ); ?></button>
			</div>
		</div>
	</form>
</div><!-- .wrap -->
