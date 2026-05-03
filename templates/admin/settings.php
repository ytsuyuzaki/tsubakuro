<?php
/**
 * Admin – Settings page template.
 *
 * Variables available:
 *   $mcp_url – full REST URL of the MCP endpoint
 *
 * @package Tsubakuro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$profile_url = admin_url( 'profile.php' );
?>
<div class="wrap tsubakuro-admin-wrap">
	<h1><?php esc_html_e( 'Tsubakuro 設定', 'tsubakuro' ); ?></h1>

	<!-- ======================================================
		Section 1 – MCP エンドポイント URL
		====================================================== -->
	<div class="tsubakuro-settings-card">
		<h2><?php esc_html_e( 'MCP エンドポイント URL', 'tsubakuro' ); ?></h2>
		<p>
			<?php esc_html_e( 'AIクライアント（Claude Desktop など）に登録する MCP サーバーの URL です。', 'tsubakuro' ); ?>
		</p>

		<div class="tsubakuro-url-row">
			<input
				type="text"
				id="tsubakuro-mcp-url"
				class="regular-text code"
				value="<?php echo esc_attr( $mcp_url ); ?>"
				readonly
			/>
			<button
				type="button"
				class="button tsubakuro-copy-btn"
				data-target="tsubakuro-mcp-url"
			><?php esc_html_e( 'コピー', 'tsubakuro' ); ?></button>
		</div>
	</div>

	<!-- ======================================================
		Section 2 – アプリケーションパスワードの作成
		====================================================== -->
	<div class="tsubakuro-settings-card">
		<h2><?php esc_html_e( '認証キー（アプリケーションパスワード）の作成', 'tsubakuro' ); ?></h2>
		<p>
			<?php
			printf(
				/* translators: %s: link to user profile page */
				wp_kses(
					__( 'MCP エンドポイントへのリクエストでは、まず WordPress の <strong>アプリケーションパスワード</strong> を使う方法を推奨します。<br>以下の手順で発行してください。', 'tsubakuro' ),
					array(
						'strong' => array(),
						'br'     => array(),
					)
				)
			);
			?>
		</p>

		<ol class="tsubakuro-steps">
			<li>
				<a href="<?php echo esc_url( $profile_url ); ?>" target="_blank">
					<?php esc_html_e( 'プロフィールページを開く', 'tsubakuro' ); ?>
				</a>
				<?php esc_html_e( '（WordPress 管理画面 → ユーザー → プロフィール）', 'tsubakuro' ); ?>
			</li>
			<li>
				<?php esc_html_e( 'ページ下部の「アプリケーションパスワード」セクションまでスクロールします。', 'tsubakuro' ); ?>
			</li>
			<li>
				<?php esc_html_e( '「新しいアプリケーションパスワードの名前」欄に識別名（例: "Claude Desktop"）を入力します。', 'tsubakuro' ); ?>
			</li>
			<li>
				<?php esc_html_e( '「新しいアプリケーションパスワードを追加」ボタンをクリックします。', 'tsubakuro' ); ?>
			</li>
			<li>
				<?php
				echo wp_kses(
					__( '表示されたパスワードを <strong>必ずコピーして保存</strong> してください。このページを離れると再表示されません。', 'tsubakuro' ),
					array( 'strong' => array() )
				);
				?>
			</li>
		</ol>

		<p class="description">
			<?php
			echo wp_kses(
				__( '認証には <code>ユーザー名:アプリケーションパスワード</code> を Base64 エンコードした値を <code>Authorization: Basic &lt;値&gt;</code> ヘッダーとして使用します。OAuth 2.1 / Bearer token は MCP の HTTP transport で標準寄りの方式ですが、このプラグイン単体では OAuth endpoint を提供していません。', 'tsubakuro' ),
				array( 'code' => array() )
			);
			?>
		</p>

		<!-- Authorization ヘッダー生成フォーム -->
		<div class="tsubakuro-auth-generator">
			<h3><?php esc_html_e( 'Authorization ヘッダーを生成する', 'tsubakuro' ); ?></h3>
			<p class="description">
				<?php esc_html_e( 'ユーザー名とアプリケーションパスワードを入力すると、すぐに使える Authorization ヘッダー値を生成します。', 'tsubakuro' ); ?>
			</p>

			<table class="form-table tsubakuro-auth-generator-table" role="presentation">
				<tr>
					<th scope="row">
						<label for="tsubakuro-auth-username"><?php esc_html_e( 'ユーザー名', 'tsubakuro' ); ?></label>
					</th>
					<td>
						<input
							type="text"
							id="tsubakuro-auth-username"
							class="regular-text"
							placeholder="<?php esc_attr_e( 'WordPress のログインユーザー名', 'tsubakuro' ); ?>"
							autocomplete="username"
						/>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="tsubakuro-auth-apppassword"><?php esc_html_e( 'アプリケーションパスワード', 'tsubakuro' ); ?></label>
					</th>
					<td>
						<div class="tsubakuro-password-row">
							<input
								type="password"
								id="tsubakuro-auth-apppassword"
								class="regular-text"
								placeholder="<?php esc_attr_e( '発行したアプリケーションパスワード', 'tsubakuro' ); ?>"
								autocomplete="new-password"
							/>
							<button
								type="button"
								class="button tsubakuro-toggle-password"
								aria-label="<?php esc_attr_e( 'パスワードを表示/非表示', 'tsubakuro' ); ?>"
								data-target="tsubakuro-auth-apppassword"
								data-show="<?php esc_attr_e( '表示', 'tsubakuro' ); ?>"
								data-hide="<?php esc_attr_e( '非表示', 'tsubakuro' ); ?>"
							><?php esc_html_e( '表示', 'tsubakuro' ); ?></button>
						</div>
						<p class="description"><?php esc_html_e( 'スペースが含まれていても自動で除去します。', 'tsubakuro' ); ?></p>
					</td>
				</tr>
			</table>

			<p>
				<button
					type="button"
					id="tsubakuro-generate-auth"
					class="button button-primary"
					data-error-empty="<?php esc_attr_e( 'ユーザー名とアプリケーションパスワードを両方入力してください。', 'tsubakuro' ); ?>"
				><?php esc_html_e( 'ヘッダーを生成', 'tsubakuro' ); ?></button>
			</p>

			<div id="tsubakuro-auth-result" class="tsubakuro-auth-result" hidden>
				<label for="tsubakuro-auth-header-value">
					<strong><?php esc_html_e( '生成された Authorization ヘッダー', 'tsubakuro' ); ?></strong>
				</label>
				<div class="tsubakuro-url-row">
					<input
						type="text"
						id="tsubakuro-auth-header-value"
						class="large-text code"
						readonly
						aria-label="<?php esc_attr_e( '生成された Authorization ヘッダー値', 'tsubakuro' ); ?>"
					/>
					<button
						type="button"
						class="button tsubakuro-copy-btn"
						data-target="tsubakuro-auth-header-value"
					><?php esc_html_e( 'コピー', 'tsubakuro' ); ?></button>
				</div>
				<p class="description">
					<?php esc_html_e( 'このヘッダー値を MCP クライアントの設定にそのまま貼り付けて使用できます。', 'tsubakuro' ); ?>
				</p>
			</div>
		</div>
	</div>

	<!-- ======================================================
		Section 3 – MCP クライアントへの設定方法
		====================================================== -->
	<div class="tsubakuro-settings-card">
		<h2><?php esc_html_e( 'MCP クライアントへの設定方法', 'tsubakuro' ); ?></h2>
		<p>
			<?php esc_html_e( 'クライアントがサポートする形式に合わせて、URL 直指定型・ヘッダー指定型・ローカルブリッジ型のいずれかで設定します。', 'tsubakuro' ); ?>
		</p>

		<h3><?php esc_html_e( 'ローカルブリッジ型（Claude Desktop など）', 'tsubakuro' ); ?></h3>
		<div class="tsubakuro-code-block-wrap">
			<button type="button" class="button tsubakuro-copy-btn" data-target="tsubakuro-claude-config">
				<?php esc_html_e( 'コピー', 'tsubakuro' ); ?>
			</button>
			<pre id="tsubakuro-claude-config" class="tsubakuro-code-block">
			<?php
			$example = '{
  "mcpServers": {
    "tsubakuro": {
      "command": "npx",
      "args": [
        "-y",
        "@modelcontextprotocol/server-fetch",
        "--url", "' . esc_js( $mcp_url ) . '",
        "--header", "Authorization: Basic <Base64エンコードした認証情報>"
      ]
    }
  }
}';
				echo esc_html( $example );
			?>
			</pre>
		</div>

		<h3><?php esc_html_e( 'URL とヘッダーを直接指定できるクライアント', 'tsubakuro' ); ?></h3>
		<div class="tsubakuro-code-block-wrap">
			<button type="button" class="button tsubakuro-copy-btn" data-target="tsubakuro-direct-config">
				<?php esc_html_e( 'コピー', 'tsubakuro' ); ?>
			</button>
			<pre id="tsubakuro-direct-config" class="tsubakuro-code-block">
			<?php
			$direct_example = '{
  "mcpServers": {
    "tsubakuro": {
      "url": "' . esc_js( $mcp_url ) . '",
      "headers": {
        "Authorization": "Basic <Base64エンコードした認証情報>"
      }
    }
  }
}';
			echo esc_html( $direct_example );
			?>
			</pre>
		</div>

		<h3><?php esc_html_e( '環境変数からヘッダーを組み立てる運用', 'tsubakuro' ); ?></h3>
		<div class="tsubakuro-code-block-wrap">
			<button type="button" class="button tsubakuro-copy-btn" data-target="tsubakuro-env-config">
				<?php esc_html_e( 'コピー', 'tsubakuro' ); ?>
			</button>
			<pre id="tsubakuro-env-config" class="tsubakuro-code-block">
			<?php
			$env_example = 'TSUBAKURO_MCP_URL="' . esc_js( $mcp_url ) . '"
TSUBAKURO_MCP_AUTH="Basic <Base64エンコードした認証情報>"';
			echo esc_html( $env_example );
			?>
			</pre>
		</div>

		<h3><?php esc_html_e( 'その他の接続方式の扱い', 'tsubakuro' ); ?></h3>
		<ul class="tsubakuro-guide-list">
			<li><?php esc_html_e( 'OAuth クライアント ID/シークレットは OAuth フローでクライアントを識別するための情報で、ツール呼び出し時に直接送る認証情報ではありません。', 'tsubakuro' ); ?></li>
			<li><?php esc_html_e( 'URL パス/クエリトークンはログ、履歴、Referer に残りやすいため推奨しません。どうしても必要なクライアント向けの将来オプション扱いです。', 'tsubakuro' ); ?></li>
			<li><?php esc_html_e( 'OAuth や IP 制限、監査ログ、レート制限が必要な場合は、リバースプロキシや API Gateway で外側に追加する構成を検討してください。', 'tsubakuro' ); ?></li>
		</ul>

		<p><?php esc_html_e( '認証情報の生成例（ターミナル）:', 'tsubakuro' ); ?></p>
		<div class="tsubakuro-code-block-wrap">
			<button type="button" class="button tsubakuro-copy-btn" data-target="tsubakuro-auth-cmd">
				<?php esc_html_e( 'コピー', 'tsubakuro' ); ?>
			</button>
			<pre id="tsubakuro-auth-cmd" class="tsubakuro-code-block" aria-label="<?php esc_attr_e( '認証情報生成コマンド', 'tsubakuro' ); ?>"><code><?php echo esc_html( 'echo -n "ユーザー名:アプリケーションパスワード" | base64' ); ?></code></pre>
		</div>

		<p class="description">
			<?php
			echo wp_kses(
				__( '<code>ユーザー名</code> は WordPress のログインユーザー名（メールアドレスではない）、<code>アプリケーションパスワード</code> は前のセクションで発行したパスワード（スペースを除去した文字列）に置き換えてください。', 'tsubakuro' ),
				array( 'code' => array() )
			);
			?>
		</p>
	</div>
</div><!-- .wrap -->

<script>
( function () {
	// パスワード表示/非表示トグル
	document.querySelectorAll( '.tsubakuro-toggle-password' ).forEach( function ( btn ) {
		btn.addEventListener( 'click', function () {
			var targetId = btn.getAttribute( 'data-target' );
			var input = document.getElementById( targetId );
			if ( ! input ) { return; }
			if ( input.type === 'password' ) {
				input.type = 'text';
				btn.textContent = btn.getAttribute( 'data-hide' ) || '非表示';
			} else {
				input.type = 'password';
				btn.textContent = btn.getAttribute( 'data-show' ) || '表示';
			}
		} );
	} );

	// UTF-8 対応 Base64 エンコード
	function utf8Btoa( str ) {
		return btoa( encodeURIComponent( str ).replace( /%([0-9A-F]{2})/g, function ( match, p1 ) {
			return String.fromCharCode( parseInt( p1, 16 ) );
		} ) );
	}

	// Authorization ヘッダー生成
	var generateBtn = document.getElementById( 'tsubakuro-generate-auth' );
	if ( generateBtn ) {
		generateBtn.addEventListener( 'click', function () {
			var username = ( document.getElementById( 'tsubakuro-auth-username' ).value || '' ).trim();
			var appPassword = ( document.getElementById( 'tsubakuro-auth-apppassword' ).value || '' ).replace( /\s/g, '' );

			if ( ! username || ! appPassword ) {
				alert( generateBtn.getAttribute( 'data-error-empty' ) || 'ユーザー名とアプリケーションパスワードを両方入力してください。' );
				return;
			}

			var encoded = utf8Btoa( username + ':' + appPassword );
			var headerValue = 'Authorization: Basic ' + encoded;

			var resultInput = document.getElementById( 'tsubakuro-auth-header-value' );
			var resultDiv   = document.getElementById( 'tsubakuro-auth-result' );
			resultInput.value = headerValue;
			resultDiv.hidden  = false;
			resultInput.select();
		} );
	}

	// コピーボタン
	document.querySelectorAll( '.tsubakuro-copy-btn' ).forEach( function ( btn ) {
		btn.addEventListener( 'click', function () {
			var targetId = btn.getAttribute( 'data-target' );
			var el = document.getElementById( targetId );
			if ( ! el ) { return; }

			var text = el.tagName === 'INPUT' ? el.value : el.textContent;
			navigator.clipboard.writeText( text ).then( function () {
				var original = btn.textContent;
				btn.textContent = 'コピーしました！';
				setTimeout( function () { btn.textContent = original; }, 2000 );
			} ).catch( function () {
				btn.textContent = 'コピーに失敗しました';
				setTimeout( function () { btn.textContent = btn.getAttribute( 'data-original' ) || 'コピー'; }, 2000 );
			} );
		} );
	} );
} )();
</script>
