<?php
/**
 * Admin – About page template.
 *
 * Variables available:
 *   $story_items     – swallow metaphor points.
 *   $value_points    – product positioning points.
 *   $reference_links – useful admin links.
 *
 * @package Tsubakuro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap tsubakuro-admin-wrap tsubakuro-about-wrap">
	<h1><?php esc_html_e( 'なぜツバクロなのか', 'tsubakuro' ); ?></h1>

	<div class="tsubakuro-about-lead">
		<p>
			<?php esc_html_e( 'ツバクロは、WordPress 内の課題・依頼・改善案を、実行可能な形で整理するための場所として開発がはじまりました。', 'tsubakuro' ); ?>
		</p>
		<p>
			<?php esc_html_e( '主役は「AI エージェントで実行すること」そのものではなく、サイト運用者が日々見ている WordPress の文脈で issue を集め、判断し、必要な実行先へ渡せる状態をつくることです。', 'tsubakuro' ); ?>
		</p>
	</div>

	<div class="tsubakuro-about-grid">
		<main class="tsubakuro-about-main">
			<section class="tsubakuro-about-section">
				<h2><?php esc_html_e( 'ツバクロが担う役割', 'tsubakuro' ); ?></h2>
				<p>
					<?php esc_html_e( 'このプラグインは、WordPress で issue 管理を行うためのツールです。AI、手動作業、外部サービスのどれで実行する場合でも、まず対応すべき課題を WordPress 内に整理しておくことで、実行に移しやすくします。', 'tsubakuro' ); ?>
				</p>
				<p>
					<?php esc_html_e( 'ツバクロは「タスクを実行する鳥」よりも、「サイト内の課題を見つけ、運び、巣にまとめ、必要な実行先へ渡す鳥」という関係性を目指しています。', 'tsubakuro' ); ?>
				</p>
			</section>

			<section class="tsubakuro-about-section">
				<h2><?php esc_html_e( '名前に込めた比喩', 'tsubakuro' ); ?></h2>
				<div class="tsubakuro-about-story-list">
					<?php foreach ( $story_items as $item ) : ?>
						<article class="tsubakuro-about-story-item">
							<h3><?php echo esc_html( $item['title'] ); ?></h3>
							<p><?php echo esc_html( $item['description'] ); ?></p>
						</article>
					<?php endforeach; ?>
				</div>
			</section>

			<section class="tsubakuro-about-section">
				<h2><?php esc_html_e( 'WordPress 内に置く価値', 'tsubakuro' ); ?></h2>
				<p>
					<?php esc_html_e( '実行部分を主役にすると、n8n、GitHub Actions、Linear、GitHub Issues、MCP 対応ツールなどと競合しやすくなります。ツバクロはそれらの代替ではなく、WordPress の中で課題を見つけ、文脈を残し、渡しやすくするための場所です。', 'tsubakuro' ); ?>
				</p>
				<ul class="tsubakuro-about-value-list">
					<?php foreach ( $value_points as $point ) : ?>
						<li><?php echo esc_html( $point ); ?></li>
					<?php endforeach; ?>
				</ul>
			</section>
		</main>

		<aside class="tsubakuro-about-side">
			<section class="tsubakuro-about-section">
				<h2><?php esc_html_e( '参照リンク', 'tsubakuro' ); ?></h2>
				<ul class="tsubakuro-about-link-list">
					<?php foreach ( $reference_links as $reference_link ) : ?>
						<li>
							<a href="<?php echo esc_url( $reference_link['url'] ); ?>"><?php echo esc_html( $reference_link['label'] ); ?></a>
							<span><?php echo esc_html( $reference_link['description'] ); ?></span>
						</li>
					<?php endforeach; ?>
				</ul>
			</section>

			<section class="tsubakuro-about-section">
				<h2><?php esc_html_e( 'ひとことで言うと', 'tsubakuro' ); ?></h2>
				<p>
					<?php esc_html_e( '軽やかに巡回しながら、課題を運び、積み上げていく存在です。', 'tsubakuro' ); ?>
				</p>
			</section>
		</aside>
	</div>
</div>
