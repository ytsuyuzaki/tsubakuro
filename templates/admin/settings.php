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

$profile_url   = admin_url( 'profile.php' );
$oauth_clients = Tsubakuro_OAuth::get_clients();
$authorize_url = rest_url( Tsubakuro_REST_API::NAMESPACE . '/oauth/authorize' );
$token_url     = rest_url( Tsubakuro_REST_API::NAMESPACE . '/oauth/token' );
$metadata_url  = rest_url( Tsubakuro_REST_API::NAMESPACE . '/oauth/metadata' );
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
		Section 2 – OAuth 2.0 / claude.ai Custom Connector
		====================================================== -->
	<div class="tsubakuro-settings-card">
		<h2><?php esc_html_e( 'OAuth 2.0 – claude.ai Custom Connector 連携', 'tsubakuro' ); ?></h2>
		<p>
			<?php
			echo wp_kses(
				__( 'claude.ai の <strong>Custom Connector</strong> からこの MCP サーバーに接続するには、OAuth 2.0 の認可コードフローを使います。<br>下記の手順でクライアントアプリを登録し、claude.ai 側に各 URL を設定してください。', 'tsubakuro' ),
				array(
					'strong' => array(),
					'br'     => array(),
				)
			);
			?>
		</p>

		<h3><?php esc_html_e( 'OAuth エンドポイント URL', 'tsubakuro' ); ?></h3>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( '認可エンドポイント', 'tsubakuro' ); ?></th>
				<td>
					<div class="tsubakuro-url-row">
						<input type="text" id="tsubakuro-oauth-authorize-url" class="regular-text code" value="<?php echo esc_attr( $authorize_url ); ?>" readonly />
						<button type="button" class="button tsubakuro-copy-btn" data-target="tsubakuro-oauth-authorize-url"><?php esc_html_e( 'コピー', 'tsubakuro' ); ?></button>
					</div>
					<p class="description"><?php esc_html_e( 'claude.ai の「Authorization URL」に入力します。', 'tsubakuro' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'トークンエンドポイント', 'tsubakuro' ); ?></th>
				<td>
					<div class="tsubakuro-url-row">
						<input type="text" id="tsubakuro-oauth-token-url" class="regular-text code" value="<?php echo esc_attr( $token_url ); ?>" readonly />
						<button type="button" class="button tsubakuro-copy-btn" data-target="tsubakuro-oauth-token-url"><?php esc_html_e( 'コピー', 'tsubakuro' ); ?></button>
					</div>
					<p class="description"><?php esc_html_e( 'claude.ai の「Token URL」に入力します。', 'tsubakuro' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'メタデータ URL', 'tsubakuro' ); ?></th>
				<td>
					<div class="tsubakuro-url-row">
						<input type="text" id="tsubakuro-oauth-metadata-url" class="regular-text code" value="<?php echo esc_attr( $metadata_url ); ?>" readonly />
						<button type="button" class="button tsubakuro-copy-btn" data-target="tsubakuro-oauth-metadata-url"><?php esc_html_e( 'コピー', 'tsubakuro' ); ?></button>
					</div>
					<p class="description"><?php esc_html_e( 'OAuth サーバーメタデータ（認可・トークン URL を返します）。', 'tsubakuro' ); ?></p>
				</td>
			</tr>
		</table>

		<h3><?php esc_html_e( 'OAuth クライアントの登録', 'tsubakuro' ); ?></h3>
		<p class="description">
			<?php esc_html_e( 'claude.ai Custom Connector を登録する前に、クライアントを作成してください。作成後に表示される「クライアントシークレット」は一度しか表示されません。必ずコピーして保存してください。', 'tsubakuro' ); ?>
		</p>

		<table class="form-table tsubakuro-auth-generator-table" role="presentation">
			<tr>
				<th scope="row"><label for="tsubakuro-oauth-client-name"><?php esc_html_e( 'クライアント名', 'tsubakuro' ); ?></label></th>
				<td>
					<input type="text" id="tsubakuro-oauth-client-name" class="regular-text" placeholder="<?php esc_attr_e( '例: claude.ai Custom Connector', 'tsubakuro' ); ?>" />
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="tsubakuro-oauth-redirect-uri"><?php esc_html_e( 'Redirect URI', 'tsubakuro' ); ?></label></th>
				<td>
					<input type="url" id="tsubakuro-oauth-redirect-uri" class="regular-text" placeholder="<?php esc_attr_e( 'https://claude.ai/api/mcp/auth_callback', 'tsubakuro' ); ?>" />
					<p class="description">
						<?php esc_html_e( 'claude.ai Custom Connector の「Redirect URI」を入力します。認可コードはこの URL にリダイレクトされます。', 'tsubakuro' ); ?>
					</p>
				</td>
			</tr>
		</table>

		<p>
			<button
				type="button"
				id="tsubakuro-generate-oauth-client"
				class="button button-primary"
				data-nonce="<?php echo esc_attr( wp_create_nonce( 'tsubakuro_admin' ) ); ?>"
				data-ajax-url="<?php echo esc_attr( admin_url( 'admin-ajax.php' ) ); ?>"
				data-error-empty="<?php esc_attr_e( 'クライアント名と Redirect URI を入力してください。', 'tsubakuro' ); ?>"
			><?php esc_html_e( 'クライアントを作成', 'tsubakuro' ); ?></button>
		</p>

		<div id="tsubakuro-oauth-client-result" hidden>
			<div class="notice notice-warning inline">
				<p><?php esc_html_e( '⚠️ クライアントシークレットは一度しか表示されません。今すぐコピーして保存してください。', 'tsubakuro' ); ?></p>
			</div>
			<table class="form-table" role="presentation">
				<tr>
					<th><?php esc_html_e( 'クライアント ID', 'tsubakuro' ); ?></th>
					<td>
						<div class="tsubakuro-url-row">
							<input type="text" id="tsubakuro-new-client-id" class="regular-text code" readonly />
							<button type="button" class="button tsubakuro-copy-btn" data-target="tsubakuro-new-client-id"><?php esc_html_e( 'コピー', 'tsubakuro' ); ?></button>
						</div>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'クライアントシークレット', 'tsubakuro' ); ?></th>
					<td>
						<div class="tsubakuro-url-row">
							<input type="text" id="tsubakuro-new-client-secret" class="regular-text code" readonly />
							<button type="button" class="button tsubakuro-copy-btn" data-target="tsubakuro-new-client-secret"><?php esc_html_e( 'コピー', 'tsubakuro' ); ?></button>
						</div>
					</td>
				</tr>
			</table>
		</div>

		<?php if ( ! empty( $oauth_clients ) ) : ?>
		<h3><?php esc_html_e( '登録済みクライアント', 'tsubakuro' ); ?></h3>
		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'クライアント名', 'tsubakuro' ); ?></th>
					<th><?php esc_html_e( 'クライアント ID', 'tsubakuro' ); ?></th>
					<th><?php esc_html_e( 'Redirect URI', 'tsubakuro' ); ?></th>
					<th><?php esc_html_e( '作成日', 'tsubakuro' ); ?></th>
					<th><?php esc_html_e( '操作', 'tsubakuro' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $oauth_clients as $client ) : ?>
				<tr>
					<td><?php echo esc_html( $client['name'] ); ?></td>
					<td><code><?php echo esc_html( $client['client_id'] ); ?></code></td>
					<td>
						<?php if ( ! empty( $client['redirect_uri'] ) ) : ?>
							<code><?php echo esc_html( $client['redirect_uri'] ); ?></code>
						<?php else : ?>
							<span class="description"><?php esc_html_e( '（未設定 – client_credentials のみ）', 'tsubakuro' ); ?></span>
						<?php endif; ?>
					</td>
					<td><?php echo esc_html( $client['created_at'] ?? '' ); ?></td>
					<td>
						<button
							type="button"
							class="button button-small tsubakuro-revoke-oauth-client"
							data-client-id="<?php echo esc_attr( $client['client_id'] ); ?>"
							data-nonce="<?php echo esc_attr( wp_create_nonce( 'tsubakuro_admin' ) ); ?>"
							data-ajax-url="<?php echo esc_attr( admin_url( 'admin-ajax.php' ) ); ?>"
							data-confirm="<?php esc_attr_e( 'このクライアントを削除しますか？', 'tsubakuro' ); ?>"
						><?php esc_html_e( '削除', 'tsubakuro' ); ?></button>
					</td>
				</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php endif; ?>
	</div>

	<!-- ======================================================
		Section 3 – アプリケーションパスワードの作成
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
				__( '認証には <code>ユーザー名:アプリケーションパスワード</code> を Base64 エンコードした値を <code>Authorization: Basic &lt;値&gt;</code> ヘッダーとして使用します。', 'tsubakuro' ),
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
			<?php
			$mcp_guide_link = sprintf(
				'<a href="%s">%s</a>',
				esc_url( admin_url( 'admin.php?page=tsubakuro-mcp-guide' ) ),
				esc_html__( 'MCP ガイド', 'tsubakuro' )
			);
			echo wp_kses(
				sprintf(
					/* translators: %s: link to MCP guide page */
					__( 'MCP 接続についてのドキュメントはこちら: %s', 'tsubakuro' ),
					$mcp_guide_link
				),
				array(
					'a' => array(
						'href' => array(),
					),
				)
			);
			?>
		</p>
		<p class="description">
			<?php esc_html_e( 'Codex CLI、Claude Code、GitHub Copilot、Claude.ai などの設定例と接続確認用 curl は MCP ガイドにまとめています。', 'tsubakuro' ); ?>
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

	// OAuth クライアント作成
	var generateOAuthClientBtn = document.getElementById( 'tsubakuro-generate-oauth-client' );
	if ( generateOAuthClientBtn ) {
		generateOAuthClientBtn.addEventListener( 'click', function () {
			var name         = ( document.getElementById( 'tsubakuro-oauth-client-name' ).value || '' ).trim();
			var redirectUri  = ( document.getElementById( 'tsubakuro-oauth-redirect-uri' ).value || '' ).trim();
			var errorMsg     = generateOAuthClientBtn.getAttribute( 'data-error-empty' ) || 'クライアント名と Redirect URI を入力してください。';
			var ajaxUrl      = generateOAuthClientBtn.getAttribute( 'data-ajax-url' );
			var nonce        = generateOAuthClientBtn.getAttribute( 'data-nonce' );

			if ( ! name || ! redirectUri ) {
				// eslint-disable-next-line no-alert
				window.alert( errorMsg );
				return;
			}

			var formData = new FormData();
			formData.append( 'action', 'tsubakuro_generate_oauth_client' );
			formData.append( 'nonce', nonce );
			formData.append( 'name', name );
			formData.append( 'redirect_uri', redirectUri );

			generateOAuthClientBtn.disabled = true;
			fetch( ajaxUrl, { method: 'POST', body: formData } )
				.then( function ( r ) { return r.json(); } )
				.then( function ( data ) {
					if ( data.success ) {
						var result = data.data;
						document.getElementById( 'tsubakuro-new-client-id' ).value     = result.client_id;
						document.getElementById( 'tsubakuro-new-client-secret' ).value = result.client_secret;
						document.getElementById( 'tsubakuro-oauth-client-result' ).hidden = false;
						document.getElementById( 'tsubakuro-oauth-client-name' ).value = '';
						document.getElementById( 'tsubakuro-oauth-redirect-uri' ).value = '';
						// Reload after a short delay to show the new client in the table.
						setTimeout( function () { window.location.reload(); }, 6000 );
					} else {
						// eslint-disable-next-line no-alert
						window.alert( ( data.data && data.data.message ) || '作成に失敗しました。' );
					}
				} )
				.catch( function () {
					// eslint-disable-next-line no-alert
					window.alert( '通信エラーが発生しました。' );
				} )
				.finally( function () {
					generateOAuthClientBtn.disabled = false;
				} );
		} );
	}

	// OAuth クライアント削除
	document.querySelectorAll( '.tsubakuro-revoke-oauth-client' ).forEach( function ( btn ) {
		btn.addEventListener( 'click', function () {
			var confirmMsg = btn.getAttribute( 'data-confirm' ) || 'このクライアントを削除しますか？';
			// eslint-disable-next-line no-alert
			if ( ! window.confirm( confirmMsg ) ) { return; }

			var clientId = btn.getAttribute( 'data-client-id' );
			var ajaxUrl  = btn.getAttribute( 'data-ajax-url' );
			var nonce    = btn.getAttribute( 'data-nonce' );

			var formData = new FormData();
			formData.append( 'action', 'tsubakuro_revoke_oauth_client' );
			formData.append( 'nonce', nonce );
			formData.append( 'client_id', clientId );

			fetch( ajaxUrl, { method: 'POST', body: formData } )
				.then( function ( r ) { return r.json(); } )
				.then( function ( data ) {
					if ( data.success ) {
						window.location.reload();
					} else {
						// eslint-disable-next-line no-alert
						window.alert( ( data.data && data.data.message ) || '削除に失敗しました。' );
					}
				} )
				.catch( function () {
					// eslint-disable-next-line no-alert
					window.alert( '通信エラーが発生しました。' );
				} );
		} );
	} );
} )();
</script>
