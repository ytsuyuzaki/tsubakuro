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
		Section 3 – OAuth クライアント認証
		====================================================== -->
	<div class="tsubakuro-settings-card">
		<h2><?php esc_html_e( 'OAuth クライアント認証', 'tsubakuro' ); ?></h2>
		<p>
			<?php
			echo wp_kses(
				__( 'OAuth 2.0 <strong>クライアントクレデンシャル</strong> フローを使って MCP エンドポイントにアクセスできます。<br>クライアント ID / シークレットでトークンを取得し、<code>Authorization: Bearer &lt;token&gt;</code> で認証します。', 'tsubakuro' ),
				array(
					'strong' => array(),
					'br'     => array(),
					'code'   => array(),
				)
			);
			?>
		</p>

		<!-- トークンエンドポイント -->
		<h3><?php esc_html_e( 'トークンエンドポイント', 'tsubakuro' ); ?></h3>
		<p class="description">
			<?php esc_html_e( 'クライアント ID / シークレットを送信してアクセストークンを取得します（有効期限: 1 時間）。', 'tsubakuro' ); ?>
		</p>
		<div class="tsubakuro-url-row">
			<input
				type="text"
				id="tsubakuro-token-url"
				class="regular-text code"
				value="<?php echo esc_attr( $token_url ); ?>"
				readonly
			/>
			<button
				type="button"
				class="button tsubakuro-copy-btn"
				data-target="tsubakuro-token-url"
			><?php esc_html_e( 'コピー', 'tsubakuro' ); ?></button>
		</div>

		<div class="tsubakuro-code-block-wrap" style="margin-top:12px;">
			<button type="button" class="button tsubakuro-copy-btn" data-target="tsubakuro-token-request-example">
				<?php esc_html_e( 'コピー', 'tsubakuro' ); ?>
			</button>
			<pre id="tsubakuro-token-request-example" class="tsubakuro-code-block">
			<?php
			$token_example = 'POST ' . esc_html( $token_url ) . '
Content-Type: application/json

{
  "grant_type": "client_credentials",
  "client_id": "<client_id>",
  "client_secret": "<client_secret>"
}

# レスポンス:
# { "access_token": "...", "token_type": "Bearer", "expires_in": 3600 }';
			echo esc_html( $token_example );
			?>
			</pre>
		</div>

		<!-- 登録済みクライアント一覧 -->
		<h3><?php esc_html_e( '登録済みクライアント', 'tsubakuro' ); ?></h3>
		<div id="tsubakuro-oauth-clients-wrap">
		<?php if ( empty( $oauth_clients ) ) : ?>
			<p class="description" id="tsubakuro-no-clients-msg">
				<?php esc_html_e( '登録されているクライアントはありません。', 'tsubakuro' ); ?>
			</p>
		<?php else : ?>
			<table class="wp-list-table widefat fixed striped" id="tsubakuro-oauth-clients-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'クライアント名', 'tsubakuro' ); ?></th>
						<th><?php esc_html_e( 'クライアント ID', 'tsubakuro' ); ?></th>
						<th><?php esc_html_e( '作成日時', 'tsubakuro' ); ?></th>
						<th><?php esc_html_e( '操作', 'tsubakuro' ); ?></th>
					</tr>
				</thead>
				<tbody>
				<?php foreach ( $oauth_clients as $oc ) : ?>
					<tr data-client-id="<?php echo esc_attr( $oc['client_id'] ); ?>">
						<td><?php echo esc_html( $oc['name'] ); ?></td>
						<td><code><?php echo esc_html( $oc['client_id'] ); ?></code></td>
						<td><?php echo esc_html( $oc['created_at'] ); ?></td>
						<td>
							<button
								type="button"
								class="button button-link-delete tsubakuro-revoke-client"
								data-client-id="<?php echo esc_attr( $oc['client_id'] ); ?>"
								data-confirm="<?php esc_attr_e( 'このクライアントを無効化しますか？', 'tsubakuro' ); ?>"
							><?php esc_html_e( '無効化', 'tsubakuro' ); ?></button>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
		</div><!-- #tsubakuro-oauth-clients-wrap -->

		<!-- 新しいクライアントを生成 -->
		<h3><?php esc_html_e( '新しいクライアントを追加', 'tsubakuro' ); ?></h3>
		<div class="tsubakuro-oauth-generate-form">
			<input
				type="text"
				id="tsubakuro-oauth-client-name"
				class="regular-text"
				placeholder="<?php esc_attr_e( 'クライアント名（例: Claude Desktop）', 'tsubakuro' ); ?>"
			/>
			<button
				type="button"
				id="tsubakuro-generate-oauth-client"
				class="button button-primary"
				data-error-empty="<?php esc_attr_e( 'クライアント名を入力してください。', 'tsubakuro' ); ?>"
			><?php esc_html_e( 'クライアントを生成', 'tsubakuro' ); ?></button>
		</div>

		<!-- 生成結果（生成直後にのみ表示） -->
		<div id="tsubakuro-new-client-result" class="tsubakuro-settings-card" style="border-left:4px solid #d63638;margin-top:16px;" hidden>
			<p><strong><?php esc_html_e( '⚠ クライアントシークレットは今回のみ表示されます。必ずコピーして安全な場所に保管してください。', 'tsubakuro' ); ?></strong></p>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( 'クライアント ID', 'tsubakuro' ); ?></th>
					<td>
						<div class="tsubakuro-url-row">
							<input type="text" id="tsubakuro-new-client-id" class="regular-text code" readonly />
							<button type="button" class="button tsubakuro-copy-btn" data-target="tsubakuro-new-client-id">
								<?php esc_html_e( 'コピー', 'tsubakuro' ); ?>
							</button>
						</div>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'クライアントシークレット', 'tsubakuro' ); ?></th>
					<td>
						<div class="tsubakuro-url-row">
							<input type="text" id="tsubakuro-new-client-secret" class="regular-text code" readonly />
							<button type="button" class="button tsubakuro-copy-btn" data-target="tsubakuro-new-client-secret">
								<?php esc_html_e( 'コピー', 'tsubakuro' ); ?>
							</button>
						</div>
					</td>
				</tr>
			</table>
		</div>
	</div>

	<!-- ======================================================
		Section 4 – MCP クライアントへの設定方法
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
			<li><?php esc_html_e( 'OAuth クライアント ID/シークレットによる認証は、上記の「OAuth クライアント認証」セクションから設定できます。', 'tsubakuro' ); ?></li>
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

<script>
( function () {
	var oauthAjaxUrl = '<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>';
	var oauthNonce   = '<?php echo esc_js( wp_create_nonce( 'tsubakuro_admin' ) ); ?>';

	// OAuth クライアント生成
	var generateOAuthBtn = document.getElementById( 'tsubakuro-generate-oauth-client' );
	if ( generateOAuthBtn ) {
		generateOAuthBtn.addEventListener( 'click', function () {
			var nameInput = document.getElementById( 'tsubakuro-oauth-client-name' );
			var name = ( nameInput ? nameInput.value : '' ).trim();

			if ( ! name ) {
				alert( generateOAuthBtn.getAttribute( 'data-error-empty' ) || 'クライアント名を入力してください。' );
				return;
			}

			generateOAuthBtn.disabled = true;

			var formData = new FormData();
			formData.append( 'action', 'tsubakuro_generate_oauth_client' );
			formData.append( 'nonce', oauthNonce );
			formData.append( 'name', name );

			fetch( oauthAjaxUrl, { method: 'POST', body: formData } )
				.then( function ( r ) { return r.json(); } )
				.then( function ( data ) {
					generateOAuthBtn.disabled = false;
					if ( ! data.success ) {
						alert( ( data.data && data.data.message ) || 'エラーが発生しました。' );
						return;
					}

					// Show credentials (once)
					var result = document.getElementById( 'tsubakuro-new-client-result' );
					document.getElementById( 'tsubakuro-new-client-id' ).value     = data.data.client_id;
					document.getElementById( 'tsubakuro-new-client-secret' ).value = data.data.client_secret;
					result.hidden = false;

					// Add row to table
					addClientRow( data.data );

					// Clear name input
					if ( nameInput ) { nameInput.value = ''; }
				} )
				.catch( function () {
					generateOAuthBtn.disabled = false;
					alert( '通信エラーが発生しました。' );
				} );
		} );
	}

	// クライアント行を表に追加（動的生成）
	function addClientRow( client ) {
		var wrap = document.getElementById( 'tsubakuro-oauth-clients-wrap' );
		if ( ! wrap ) { return; }

		var noMsg = document.getElementById( 'tsubakuro-no-clients-msg' );
		if ( noMsg ) { noMsg.remove(); }

		var table = document.getElementById( 'tsubakuro-oauth-clients-table' );
		if ( ! table ) {
			table = document.createElement( 'table' );
			table.id = 'tsubakuro-oauth-clients-table';
			table.className = 'wp-list-table widefat fixed striped';
			table.innerHTML = '<thead><tr>'
				+ '<th><?php echo esc_js( esc_html__( 'クライアント名', 'tsubakuro' ) ); ?></th>'
				+ '<th><?php echo esc_js( esc_html__( 'クライアント ID', 'tsubakuro' ) ); ?></th>'
				+ '<th><?php echo esc_js( esc_html__( '作成日時', 'tsubakuro' ) ); ?></th>'
				+ '<th><?php echo esc_js( esc_html__( '操作', 'tsubakuro' ) ); ?></th>'
				+ '</tr></thead><tbody></tbody>';
			wrap.appendChild( table );
		}

		var tbody = table.querySelector( 'tbody' );
		var tr    = document.createElement( 'tr' );
		tr.setAttribute( 'data-client-id', client.client_id );
		tr.innerHTML = '<td>' + escHtml( client.name ) + '</td>'
			+ '<td><code>' + escHtml( client.client_id ) + '</code></td>'
			+ '<td>' + escHtml( client.created_at || '' ) + '</td>'
			+ '<td><button type="button" class="button button-link-delete tsubakuro-revoke-client"'
			+ ' data-client-id="' + escAttr( client.client_id ) + '"'
			+ ' data-confirm="<?php echo esc_js( esc_html__( 'このクライアントを無効化しますか？', 'tsubakuro' ) ); ?>">'
			+ '<?php echo esc_js( esc_html__( '無効化', 'tsubakuro' ) ); ?>'
			+ '</button></td>';
		tbody.appendChild( tr );
	}

	// クライアント無効化（イベント委任）
	document.addEventListener( 'click', function ( e ) {
		var btn = e.target.closest( '.tsubakuro-revoke-client' );
		if ( ! btn ) { return; }

		var confirmMsg = btn.getAttribute( 'data-confirm' ) || 'このクライアントを無効化しますか？';
		if ( ! window.confirm( confirmMsg ) ) { return; }

		var clientId = btn.getAttribute( 'data-client-id' );
		btn.disabled = true;

		var formData = new FormData();
		formData.append( 'action', 'tsubakuro_revoke_oauth_client' );
		formData.append( 'nonce', oauthNonce );
		formData.append( 'client_id', clientId );

		fetch( oauthAjaxUrl, { method: 'POST', body: formData } )
			.then( function ( r ) { return r.json(); } )
			.then( function ( data ) {
				if ( ! data.success ) {
					btn.disabled = false;
					alert( ( data.data && data.data.message ) || 'エラーが発生しました。' );
					return;
				}
				var row = document.querySelector( '[data-client-id="' + clientId + '"]' );
				if ( row ) { row.remove(); }
			} )
			.catch( function () {
				btn.disabled = false;
				alert( '通信エラーが発生しました。' );
			} );
	} );

	function escHtml( str ) {
		return String( str )
			.replace( /&/g, '&amp;' )
			.replace( /</g, '&lt;' )
			.replace( />/g, '&gt;' )
			.replace( /"/g, '&quot;' );
	}
	function escAttr( str ) { return escHtml( str ); }
} )();
</script>
