<?php
/**
 * OAuth 2.0 authorization consent page.
 *
 * Variables available (set by Tsubakuro_OAuth::handle_authorize()):
 *   $client      – registered client record (array with 'name', 'client_id')
 *   $redirect_uri – redirect_uri validated for this client
 *   $state        – opaque state value from the authorization request
 *   $nonce        – WordPress nonce for the consent form
 *   $consent_url  – form action URL (admin-post.php)
 *
 * @package Tsubakuro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title><?php esc_html_e( 'アクセス許可の確認 – Tsubakuro', 'tsubakuro' ); ?></title>
	<style>
		* { box-sizing: border-box; margin: 0; padding: 0; }
		body {
			font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen, sans-serif;
			background: #f0f0f1;
			display: flex;
			align-items: center;
			justify-content: center;
			min-height: 100vh;
			padding: 20px;
		}
		.consent-card {
			background: #fff;
			border-radius: 8px;
			box-shadow: 0 2px 8px rgba(0,0,0,.12);
			max-width: 480px;
			width: 100%;
			padding: 40px;
		}
		.consent-logo {
			text-align: center;
			margin-bottom: 24px;
			font-size: 28px;
		}
		h1 {
			font-size: 20px;
			font-weight: 600;
			text-align: center;
			color: #1d2327;
			margin-bottom: 8px;
		}
		.consent-client-name {
			text-align: center;
			font-size: 16px;
			font-weight: 600;
			color: #2271b1;
			margin-bottom: 20px;
		}
		.consent-description {
			font-size: 14px;
			color: #50575e;
			line-height: 1.6;
			margin-bottom: 24px;
		}
		.consent-permissions {
			background: #f6f7f7;
			border: 1px solid #dcdcde;
			border-radius: 4px;
			padding: 16px;
			margin-bottom: 28px;
		}
		.consent-permissions h2 {
			font-size: 13px;
			font-weight: 600;
			color: #1d2327;
			text-transform: uppercase;
			letter-spacing: .05em;
			margin-bottom: 10px;
		}
		.consent-permissions ul {
			list-style: none;
			padding: 0;
		}
		.consent-permissions li {
			font-size: 14px;
			color: #3c434a;
			padding: 4px 0 4px 20px;
			position: relative;
		}
		.consent-permissions li::before {
			content: "✓";
			position: absolute;
			left: 0;
			color: #00a32a;
			font-weight: 700;
		}
		.consent-actions {
			display: flex;
			gap: 12px;
		}
		.consent-actions button {
			flex: 1;
			padding: 10px 16px;
			font-size: 14px;
			font-weight: 600;
			border-radius: 4px;
			cursor: pointer;
			border: 1px solid transparent;
			transition: background .1s, border-color .1s;
		}
		.btn-approve {
			background: #2271b1;
			color: #fff;
		}
		.btn-approve:hover { background: #135e96; }
		.btn-deny {
			background: #fff;
			color: #1d2327;
			border-color: #c3c4c7;
		}
		.btn-deny:hover { background: #f6f7f7; }
		.consent-footer {
			margin-top: 20px;
			font-size: 12px;
			color: #787c82;
			text-align: center;
			line-height: 1.5;
		}
	</style>
</head>
<body>
	<div class="consent-card">
		<div class="consent-logo">🌸</div>
		<h1><?php esc_html_e( 'アクセス許可の確認', 'tsubakuro' ); ?></h1>
		<p class="consent-client-name"><?php echo esc_html( $client['name'] ); ?></p>
		<p class="consent-description">
			<?php
			printf(
				/* translators: %s: client application name */
				esc_html__( '%s があなたの Tsubakuro タスクマネージャーへのアクセスを要求しています。以下の操作を許可しますか？', 'tsubakuro' ),
				esc_html( $client['name'] )
			);
			?>
		</p>

		<div class="consent-permissions">
			<h2><?php esc_html_e( 'このアプリに許可する内容', 'tsubakuro' ); ?></h2>
			<ul>
				<li><?php esc_html_e( 'タスクの一覧取得・詳細表示', 'tsubakuro' ); ?></li>
				<li><?php esc_html_e( 'タスクの作成・更新・削除', 'tsubakuro' ); ?></li>
				<li><?php esc_html_e( 'タスクへのコメント追加', 'tsubakuro' ); ?></li>
			</ul>
		</div>

		<form method="post" action="<?php echo esc_url( $consent_url ); ?>">
			<input type="hidden" name="action" value="tsubakuro_oauth_consent">
			<input type="hidden" name="client_id" value="<?php echo esc_attr( $client['client_id'] ); ?>">
			<input type="hidden" name="redirect_uri" value="<?php echo esc_attr( $redirect_uri ); ?>">
			<input type="hidden" name="state" value="<?php echo esc_attr( $state ); ?>">
			<input type="hidden" name="_wpnonce" value="<?php echo esc_attr( $nonce ); ?>">

			<div class="consent-actions">
				<button type="submit" name="consent_action" value="approve" class="btn-approve">
					<?php esc_html_e( '許可する', 'tsubakuro' ); ?>
				</button>
				<button type="submit" name="consent_action" value="deny" class="btn-deny">
					<?php esc_html_e( '拒否する', 'tsubakuro' ); ?>
				</button>
			</div>
		</form>

		<p class="consent-footer">
			<?php esc_html_e( '許可すると、このアプリケーションはあなたのアカウントの代わりに上記の操作を実行できるようになります。', 'tsubakuro' ); ?>
		</p>
	</div>
</body>
</html>
