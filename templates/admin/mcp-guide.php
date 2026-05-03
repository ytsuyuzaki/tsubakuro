<?php
/**
 * Admin – MCP Guide page template.
 *
 * Variables available:
 *   $mcp_url – full REST URL of the MCP endpoint
 *
 * @package Tsubakuro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap tsubakuro-admin-wrap">
	<h1><?php esc_html_e( 'MCP (Model Context Protocol) ガイド', 'tsubakuro' ); ?></h1>
	<p class="tsubakuro-guide-intro">
		<?php esc_html_e( 'このプラグインは MCP に対応しており、ChatGPT・Claude・Gemini などの生成 AI クライアントからタスクを直接操作できます。', 'tsubakuro' ); ?>
	</p>

	<!-- ==================================================================
		Section 1 – エンドポイント概要
		================================================================== -->
	<div class="tsubakuro-guide-card">
		<h2><?php esc_html_e( 'エンドポイント概要', 'tsubakuro' ); ?></h2>
		<table class="widefat tsubakuro-guide-table">
			<thead>
				<tr>
					<th><?php esc_html_e( '種別', 'tsubakuro' ); ?></th>
					<th><?php esc_html_e( 'メソッド', 'tsubakuro' ); ?></th>
					<th><?php esc_html_e( 'パス', 'tsubakuro' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<tr>
					<td><?php esc_html_e( 'ディスカバリー（マニフェスト取得）', 'tsubakuro' ); ?></td>
					<td><code>GET</code></td>
					<td><code>/wp-json/tsubakuro/v1/mcp</code></td>
				</tr>
				<tr>
					<td><?php esc_html_e( 'ツール呼び出し（JSON-RPC 2.0）', 'tsubakuro' ); ?></td>
					<td><code>POST</code></td>
					<td><code>/wp-json/tsubakuro/v1/mcp</code></td>
				</tr>
			</tbody>
		</table>
		<p class="description">
			<?php esc_html_e( 'このサイトの MCP エンドポイント URL:', 'tsubakuro' ); ?>
			<code><?php echo esc_html( $mcp_url ); ?></code>
		</p>
		<p><?php esc_html_e( 'ディスカバリーを呼び出すと、利用可能なツールの一覧を含む MCP サーバーマニフェストが返ります。', 'tsubakuro' ); ?></p>
		<div class="tsubakuro-code-block-wrap">
			<button type="button" class="button tsubakuro-copy-btn" data-target="tsubakuro-guide-manifest-example">
				<?php esc_html_e( 'コピー', 'tsubakuro' ); ?>
			</button>
			<pre id="tsubakuro-guide-manifest-example" class="tsubakuro-code-block"><?php echo esc_html( '{"schema_version":"2024-11-05","name":"tsubakuro-task-manager","version":"1.0.0","description":"WordPress task management plugin - manage tasks, comments, status, assignees and related pages.","tools":[...]}' ); ?></pre>
		</div>
	</div>

	<!-- ==================================================================
		Section 2 – 認証
		================================================================== -->
	<div class="tsubakuro-guide-card">
		<h2><?php esc_html_e( '認証', 'tsubakuro' ); ?></h2>
		<p>
			<?php
			echo wp_kses(
				__( '<code>POST</code> リクエスト（ツール呼び出し）には WordPress の認証が必要です。呼び出しユーザーに <code>edit_posts</code> 権限が必要です。', 'tsubakuro' ),
				array( 'code' => array() )
			);
			?>
		</p>
		<ul class="tsubakuro-guide-list">
			<li>
				<strong><?php esc_html_e( 'Application Passwords（まず使う方法）', 'tsubakuro' ); ?></strong>
				<?php
				echo wp_kses(
					__( '— WordPress 管理画面 → ユーザー → プロフィール → アプリケーションパスワードを発行し、<code>Authorization: Basic &lt;Base64(username:application_password)&gt;</code> ヘッダーで使用します。', 'tsubakuro' ),
					array( 'code' => array() )
				);
				?>
			</li>
			<li>
				<strong><?php esc_html_e( 'Cookie 認証', 'tsubakuro' ); ?></strong>
				<?php
				echo wp_kses(
					__( '— ブラウザ上の WordPress セッションを利用する場合（<code>X-WP-Nonce</code> ヘッダーが必要）。', 'tsubakuro' ),
					array( 'code' => array() )
				);
				?>
			</li>
		</ul>
		<p class="description"><?php esc_html_e( 'GET によるディスカバリーは認証不要です。', 'tsubakuro' ); ?></p>
		<h3><?php esc_html_e( '接続方式の比較', 'tsubakuro' ); ?></h3>
		<table class="widefat tsubakuro-guide-table">
			<thead>
				<tr>
					<th><?php esc_html_e( '方式', 'tsubakuro' ); ?></th>
					<th><?php esc_html_e( '扱い', 'tsubakuro' ); ?></th>
					<th><?php esc_html_e( '用途・注意点', 'tsubakuro' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<tr>
					<td><code>Authorization: Basic</code></td>
					<td><?php esc_html_e( 'メイン推奨・実装済み', 'tsubakuro' ); ?></td>
					<td><?php esc_html_e( 'WordPress 標準の Application Passwords を使います。ユーザー権限は edit_posts で判定されます。', 'tsubakuro' ); ?></td>
				</tr>
				<tr>
					<td><code>OAuth 2.0 認可コードフロー / Bearer</code></td>
					<td><?php esc_html_e( '実装済み（claude.ai 向け）', 'tsubakuro' ); ?></td>
					<td>
						<?php
						$settings_link = sprintf(
							'<a href="%s">%s</a>',
							esc_url( admin_url( 'admin.php?page=tsubakuro-settings' ) ),
							esc_html__( '設定ページ', 'tsubakuro' )
						);
						echo wp_kses(
							sprintf(
								/* translators: %s: link to settings page */
								__( 'claude.ai Custom Connector など OAuth 2.0 を必要とするクライアント向けです。%s でクライアントを登録し、認可コードフローでアクセストークンを取得します。', 'tsubakuro' ),
								$settings_link
							),
							array( 'a' => array( 'href' => array() ) )
						);
						?>
					</td>
				</tr>
				<tr>
					<td><?php esc_html_e( '任意ヘッダー値', 'tsubakuro' ); ?></td>
					<td><?php esc_html_e( '実用パターン', 'tsubakuro' ); ?></td>
					<td><?php esc_html_e( 'クライアントやローカルブリッジが Authorization などのヘッダーを設定できる場合に使います。', 'tsubakuro' ); ?></td>
				</tr>
				<tr>
					<td><?php esc_html_e( 'URL パス/クエリトークン', 'tsubakuro' ); ?></td>
					<td><?php esc_html_e( '非推奨', 'tsubakuro' ); ?></td>
					<td><?php esc_html_e( 'URL はログ、履歴、Referer に残りやすいため採用しません。どうしても必要なクライアント向けの将来オプション扱いです。', 'tsubakuro' ); ?></td>
				</tr>
				<tr>
					<td><code>Cookie + X-WP-Nonce</code></td>
					<td><?php esc_html_e( 'ブラウザ内操作向け', 'tsubakuro' ); ?></td>
					<td><?php esc_html_e( 'WordPress 管理画面や同一ブラウザセッションから呼び出す場合に使います。外部 MCP クライアントの主方式にはしません。', 'tsubakuro' ); ?></td>
				</tr>
				<tr>
					<td><?php esc_html_e( 'ローカル STDIO/ブリッジ + 環境変数', 'tsubakuro' ); ?></td>
					<td><?php esc_html_e( '補助パターン', 'tsubakuro' ); ?></td>
					<td><?php esc_html_e( '直接 HTTP ヘッダーを扱いにくいクライアントでは、ローカルプロセスが環境変数からヘッダーを生成して中継します。', 'tsubakuro' ); ?></td>
				</tr>
				<tr>
					<td><?php esc_html_e( 'リバースプロキシ/API Gateway/OAuth proxy', 'tsubakuro' ); ?></td>
					<td><?php esc_html_e( '運用オプション', 'tsubakuro' ); ?></td>
					<td><?php esc_html_e( 'プラグイン外で OAuth、IP 制限、監査ログ、レート制限を追加したい場合に使います。', 'tsubakuro' ); ?></td>
				</tr>
			</tbody>
		</table>
		<p class="description">
			<?php
			echo wp_kses(
				__( 'MCP 公式仕様では、HTTP transport の認可として OAuth 2.1 / Bearer token / Protected Resource Metadata が中心に整理されています。このプラグインは WordPress プラグインとして配布しやすいことを優先し、現時点では WordPress 標準の Application Passwords を採用しています。参考: <a href="https://modelcontextprotocol.io/specification/2025-11-25/basic/authorization" target="_blank">Authorization specification</a> / <a href="https://modelcontextprotocol.io/specification/2025-11-25/basic" target="_blank">Basic specification</a>', 'tsubakuro' ),
				array(
					'a' => array(
						'href'   => array(),
						'target' => array(),
					),
				)
			);
			?>
		</p>
	</div>

	<!-- ==================================================================
		Section 3 – ツール一覧
		================================================================== -->
	<div class="tsubakuro-guide-card">
		<h2><?php esc_html_e( 'ツール一覧', 'tsubakuro' ); ?></h2>
		<p>
			<?php
			echo wp_kses(
				__( 'MCP ツールの呼び出しは <a href="https://www.jsonrpc.org/specification" target="_blank">JSON-RPC 2.0</a> 形式で <code>POST</code> します。', 'tsubakuro' ),
				array(
					'a'    => array(
						'href'   => array(),
						'target' => array(),
					),
					'code' => array(),
				)
			);
			?>
		</p>

		<!-- tsubakuro_list_tasks -->
		<div class="tsubakuro-guide-tool">
			<h3><code>tsubakuro_list_tasks</code> &mdash; <?php esc_html_e( 'タスク一覧取得', 'tsubakuro' ); ?></h3>
			<p><?php esc_html_e( 'タスクの一覧を取得します。フィルタは省略可能です。', 'tsubakuro' ); ?></p>
			<table class="widefat tsubakuro-guide-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'パラメータ', 'tsubakuro' ); ?></th>
						<th><?php esc_html_e( '型', 'tsubakuro' ); ?></th>
						<th><?php esc_html_e( '必須', 'tsubakuro' ); ?></th>
						<th><?php esc_html_e( '説明', 'tsubakuro' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<tr>
						<td><code>status</code></td>
						<td>string</td>
						<td>&#8212;</td>
						<td><?php esc_html_e( 'todo / in_progress / completed でフィルタ', 'tsubakuro' ); ?></td>
					</tr>
					<tr>
						<td><code>related_page</code></td>
						<td>integer</td>
						<td>&#8212;</td>
						<td><?php esc_html_e( '関連ページ ID でフィルタ', 'tsubakuro' ); ?></td>
					</tr>
					<tr>
						<td><code>per_page</code></td>
						<td>integer</td>
						<td>&#8212;</td>
						<td><?php esc_html_e( '取得件数（最大 100、デフォルト 50）', 'tsubakuro' ); ?></td>
					</tr>
				</tbody>
			</table>
			<div class="tsubakuro-code-block-wrap">
				<button type="button" class="button tsubakuro-copy-btn" data-target="tsubakuro-ex-list">
					<?php esc_html_e( 'コピー', 'tsubakuro' ); ?>
				</button>
				<pre id="tsubakuro-ex-list" class="tsubakuro-code-block"><?php echo esc_html( '{"jsonrpc":"2.0","id":1,"method":"tsubakuro_list_tasks","params":{"status":"in_progress","per_page":10}}' ); ?></pre>
			</div>
		</div>

		<!-- tsubakuro_get_task -->
		<div class="tsubakuro-guide-tool">
			<h3><code>tsubakuro_get_task</code> &mdash; <?php esc_html_e( 'タスク詳細取得', 'tsubakuro' ); ?></h3>
			<p><?php esc_html_e( '指定した ID のタスク詳細をコメントも含めて取得します。', 'tsubakuro' ); ?></p>
			<table class="widefat tsubakuro-guide-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'パラメータ', 'tsubakuro' ); ?></th>
						<th><?php esc_html_e( '型', 'tsubakuro' ); ?></th>
						<th><?php esc_html_e( '必須', 'tsubakuro' ); ?></th>
						<th><?php esc_html_e( '説明', 'tsubakuro' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<tr>
						<td><code>id</code></td>
						<td>integer</td>
						<td>&#10003;</td>
						<td><?php esc_html_e( 'タスク ID', 'tsubakuro' ); ?></td>
					</tr>
				</tbody>
			</table>
			<div class="tsubakuro-code-block-wrap">
				<button type="button" class="button tsubakuro-copy-btn" data-target="tsubakuro-ex-get">
					<?php esc_html_e( 'コピー', 'tsubakuro' ); ?>
				</button>
				<pre id="tsubakuro-ex-get" class="tsubakuro-code-block"><?php echo esc_html( '{"jsonrpc":"2.0","id":2,"method":"tsubakuro_get_task","params":{"id":42}}' ); ?></pre>
			</div>
		</div>

		<!-- tsubakuro_create_task -->
		<div class="tsubakuro-guide-tool">
			<h3><code>tsubakuro_create_task</code> &mdash; <?php esc_html_e( 'タスク作成', 'tsubakuro' ); ?></h3>
			<p><?php esc_html_e( '新しいタスクを作成します。', 'tsubakuro' ); ?></p>
			<table class="widefat tsubakuro-guide-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'パラメータ', 'tsubakuro' ); ?></th>
						<th><?php esc_html_e( '型', 'tsubakuro' ); ?></th>
						<th><?php esc_html_e( '必須', 'tsubakuro' ); ?></th>
						<th><?php esc_html_e( '説明', 'tsubakuro' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<tr>
						<td><code>title</code></td>
						<td>string</td>
						<td>&#10003;</td>
						<td><?php esc_html_e( 'タイトル', 'tsubakuro' ); ?></td>
					</tr>
					<tr>
						<td><code>content</code></td>
						<td>string</td>
						<td>&#8212;</td>
						<td><?php esc_html_e( '内容・説明', 'tsubakuro' ); ?></td>
					</tr>
					<tr>
						<td><code>status</code></td>
						<td>string</td>
						<td>&#8212;</td>
						<td><?php esc_html_e( 'todo / in_progress / completed（デフォルト: todo）', 'tsubakuro' ); ?></td>
					</tr>
					<tr>
						<td><code>assignee</code></td>
						<td>integer</td>
						<td>&#8212;</td>
						<td><?php esc_html_e( 'アサインする WordPress ユーザー ID', 'tsubakuro' ); ?></td>
					</tr>
					<tr>
						<td><code>related_pages</code></td>
						<td>integer[]</td>
						<td>&#8212;</td>
						<td><?php esc_html_e( '関連ページ ID の配列', 'tsubakuro' ); ?></td>
					</tr>
				</tbody>
			</table>
			<div class="tsubakuro-code-block-wrap">
				<button type="button" class="button tsubakuro-copy-btn" data-target="tsubakuro-ex-create">
					<?php esc_html_e( 'コピー', 'tsubakuro' ); ?>
				</button>
				<pre id="tsubakuro-ex-create" class="tsubakuro-code-block"><?php echo esc_html( '{"jsonrpc":"2.0","id":3,"method":"tsubakuro_create_task","params":{"title":"お問い合わせフォームの文言修正","content":"送信完了メッセージを見直す","status":"todo","assignee":2,"related_pages":[5]}}' ); ?></pre>
			</div>
		</div>

		<!-- tsubakuro_update_task -->
		<div class="tsubakuro-guide-tool">
			<h3><code>tsubakuro_update_task</code> &mdash; <?php esc_html_e( 'タスク更新', 'tsubakuro' ); ?></h3>
			<p><?php esc_html_e( '既存のタスクを更新します。指定したフィールドのみ上書きされます。', 'tsubakuro' ); ?></p>
			<table class="widefat tsubakuro-guide-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'パラメータ', 'tsubakuro' ); ?></th>
						<th><?php esc_html_e( '型', 'tsubakuro' ); ?></th>
						<th><?php esc_html_e( '必須', 'tsubakuro' ); ?></th>
						<th><?php esc_html_e( '説明', 'tsubakuro' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<tr>
						<td><code>id</code></td>
						<td>integer</td>
						<td>&#10003;</td>
						<td><?php esc_html_e( 'タスク ID', 'tsubakuro' ); ?></td>
					</tr>
					<tr>
						<td><code>title</code></td>
						<td>string</td>
						<td>&#8212;</td>
						<td><?php esc_html_e( 'タイトル', 'tsubakuro' ); ?></td>
					</tr>
					<tr>
						<td><code>content</code></td>
						<td>string</td>
						<td>&#8212;</td>
						<td><?php esc_html_e( '内容・説明', 'tsubakuro' ); ?></td>
					</tr>
					<tr>
						<td><code>status</code></td>
						<td>string</td>
						<td>&#8212;</td>
						<td><?php esc_html_e( 'todo / in_progress / completed', 'tsubakuro' ); ?></td>
					</tr>
					<tr>
						<td><code>assignee</code></td>
						<td>integer</td>
						<td>&#8212;</td>
						<td><?php esc_html_e( 'アサインする WordPress ユーザー ID', 'tsubakuro' ); ?></td>
					</tr>
					<tr>
						<td><code>related_pages</code></td>
						<td>integer[]</td>
						<td>&#8212;</td>
						<td><?php esc_html_e( '関連ページ ID の配列', 'tsubakuro' ); ?></td>
					</tr>
				</tbody>
			</table>
			<div class="tsubakuro-code-block-wrap">
				<button type="button" class="button tsubakuro-copy-btn" data-target="tsubakuro-ex-update">
					<?php esc_html_e( 'コピー', 'tsubakuro' ); ?>
				</button>
				<pre id="tsubakuro-ex-update" class="tsubakuro-code-block"><?php echo esc_html( '{"jsonrpc":"2.0","id":4,"method":"tsubakuro_update_task","params":{"id":42,"status":"completed"}}' ); ?></pre>
			</div>
		</div>

		<!-- tsubakuro_delete_task -->
		<div class="tsubakuro-guide-tool">
			<h3><code>tsubakuro_delete_task</code> &mdash; <?php esc_html_e( 'タスク削除', 'tsubakuro' ); ?></h3>
			<p><?php esc_html_e( '指定したタスクを削除します。', 'tsubakuro' ); ?></p>
			<table class="widefat tsubakuro-guide-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'パラメータ', 'tsubakuro' ); ?></th>
						<th><?php esc_html_e( '型', 'tsubakuro' ); ?></th>
						<th><?php esc_html_e( '必須', 'tsubakuro' ); ?></th>
						<th><?php esc_html_e( '説明', 'tsubakuro' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<tr>
						<td><code>id</code></td>
						<td>integer</td>
						<td>&#10003;</td>
						<td><?php esc_html_e( 'タスク ID', 'tsubakuro' ); ?></td>
					</tr>
				</tbody>
			</table>
			<div class="tsubakuro-code-block-wrap">
				<button type="button" class="button tsubakuro-copy-btn" data-target="tsubakuro-ex-delete">
					<?php esc_html_e( 'コピー', 'tsubakuro' ); ?>
				</button>
				<pre id="tsubakuro-ex-delete" class="tsubakuro-code-block"><?php echo esc_html( '{"jsonrpc":"2.0","id":5,"method":"tsubakuro_delete_task","params":{"id":42}}' ); ?></pre>
			</div>
		</div>

		<!-- tsubakuro_add_comment -->
		<div class="tsubakuro-guide-tool">
			<h3><code>tsubakuro_add_comment</code> &mdash; <?php esc_html_e( 'コメント追加', 'tsubakuro' ); ?></h3>
			<p><?php esc_html_e( 'タスクにコメントを追加します。コメントの投稿者は認証済みユーザーになります。', 'tsubakuro' ); ?></p>
			<table class="widefat tsubakuro-guide-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'パラメータ', 'tsubakuro' ); ?></th>
						<th><?php esc_html_e( '型', 'tsubakuro' ); ?></th>
						<th><?php esc_html_e( '必須', 'tsubakuro' ); ?></th>
						<th><?php esc_html_e( '説明', 'tsubakuro' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<tr>
						<td><code>id</code></td>
						<td>integer</td>
						<td>&#10003;</td>
						<td><?php esc_html_e( 'タスク ID', 'tsubakuro' ); ?></td>
					</tr>
					<tr>
						<td><code>comment</code></td>
						<td>string</td>
						<td>&#10003;</td>
						<td><?php esc_html_e( 'コメント本文', 'tsubakuro' ); ?></td>
					</tr>
				</tbody>
			</table>
			<div class="tsubakuro-code-block-wrap">
				<button type="button" class="button tsubakuro-copy-btn" data-target="tsubakuro-ex-comment">
					<?php esc_html_e( 'コピー', 'tsubakuro' ); ?>
				</button>
				<pre id="tsubakuro-ex-comment" class="tsubakuro-code-block"><?php echo esc_html( '{"jsonrpc":"2.0","id":6,"method":"tsubakuro_add_comment","params":{"id":42,"comment":"クライアント確認が完了しました。"}}' ); ?></pre>
			</div>
		</div>
	</div><!-- .tsubakuro-guide-card (ツール一覧) -->

	<!-- ==================================================================
		Section 4 – JSON-RPC 2.0 エラーコード
		================================================================== -->
	<div class="tsubakuro-guide-card">
		<h2><?php esc_html_e( 'JSON-RPC 2.0 エラーコード', 'tsubakuro' ); ?></h2>
		<p>
			<?php
			echo wp_kses(
				__( 'バッチリクエスト（配列で複数のツールを同時呼び出し）にも対応しています。<code>id</code> を省略すると通知（notification）扱いになります。', 'tsubakuro' ),
				array( 'code' => array() )
			);
			?>
		</p>
		<table class="widefat tsubakuro-guide-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'コード', 'tsubakuro' ); ?></th>
					<th><?php esc_html_e( '意味', 'tsubakuro' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<tr>
					<td><code>-32700</code></td>
					<td><?php esc_html_e( 'Parse error — リクエストボディが JSON として解析できない', 'tsubakuro' ); ?></td>
				</tr>
				<tr>
					<td><code>-32600</code></td>
					<td><?php esc_html_e( 'Invalid Request — method フィールドが空', 'tsubakuro' ); ?></td>
				</tr>
				<tr>
					<td><code>-32601</code></td>
					<td><?php esc_html_e( 'Method not found — 存在しないツール名', 'tsubakuro' ); ?></td>
				</tr>
				<tr>
					<td><code>-32602</code></td>
					<td><?php esc_html_e( 'Invalid params — 必須パラメータが不足', 'tsubakuro' ); ?></td>
				</tr>
				<tr>
					<td><code>404</code></td>
					<td><?php esc_html_e( 'タスクが見つからない', 'tsubakuro' ); ?></td>
				</tr>
				<tr>
					<td><code>500</code></td>
					<td><?php esc_html_e( 'サーバー内部エラー', 'tsubakuro' ); ?></td>
				</tr>
			</tbody>
		</table>
	</div>

	<!-- ==================================================================
		Section 5 – 生成 AI との接続フロー
		================================================================== -->
	<div class="tsubakuro-guide-card">
		<h2><?php esc_html_e( '生成 AI との接続フロー', 'tsubakuro' ); ?></h2>
		<p><?php esc_html_e( 'AI クライアント（例: Claude Desktop や Cursor の MCP 拡張）を接続した場合に想定される典型的なフローです。', 'tsubakuro' ); ?></p>

		<div class="tsubakuro-guide-flows">

			<div class="tsubakuro-guide-flow">
				<h3><?php esc_html_e( 'タスク状況のサマリーを依頼する', 'tsubakuro' ); ?></h3>
				<ol class="tsubakuro-flow-steps">
					<li><?php esc_html_e( 'ユーザー: 「現在進行中のタスクを教えて」', 'tsubakuro' ); ?></li>
					<li>
						<?php
						echo wp_kses(
							__( 'AI: <code>tsubakuro_list_tasks</code> を呼び出す（status: "in_progress"）', 'tsubakuro' ),
							array( 'code' => array() )
						);
						?>
					</li>
					<li><?php esc_html_e( 'AI: 取得結果をもとに自然言語でサマリーを返す', 'tsubakuro' ); ?></li>
				</ol>
			</div>

			<div class="tsubakuro-guide-flow">
				<h3><?php esc_html_e( '新しいタスクを言葉で追加する', 'tsubakuro' ); ?></h3>
				<ol class="tsubakuro-flow-steps">
					<li><?php esc_html_e( 'ユーザー: 「トップページのバナーを差し替えるタスクを作成して」', 'tsubakuro' ); ?></li>
					<li>
						<?php
						echo wp_kses(
							__( 'AI: タイトル・内容を解釈して <code>tsubakuro_create_task</code> を呼び出す', 'tsubakuro' ),
							array( 'code' => array() )
						);
						?>
					</li>
					<li><?php esc_html_e( 'AI: 作成されたタスク情報を確認メッセージとして返す', 'tsubakuro' ); ?></li>
				</ol>
			</div>

			<div class="tsubakuro-guide-flow">
				<h3><?php esc_html_e( 'タスクのステータスを更新する', 'tsubakuro' ); ?></h3>
				<ol class="tsubakuro-flow-steps">
					<li><?php esc_html_e( 'ユーザー: 「タスク ID 42 を完了にして」', 'tsubakuro' ); ?></li>
					<li>
						<?php
						echo wp_kses(
							__( 'AI: <code>tsubakuro_update_task</code>（id: 42, status: "completed"）を呼び出す', 'tsubakuro' ),
							array( 'code' => array() )
						);
						?>
					</li>
					<li><?php esc_html_e( 'AI: 更新後のタスク内容を返す', 'tsubakuro' ); ?></li>
				</ol>
			</div>

			<div class="tsubakuro-guide-flow">
				<h3><?php esc_html_e( 'タスクにコメントを残す', 'tsubakuro' ); ?></h3>
				<ol class="tsubakuro-flow-steps">
					<li><?php esc_html_e( 'ユーザー: 「タスク 42 に「デザイン確認済み」とメモを追加して」', 'tsubakuro' ); ?></li>
					<li>
						<?php
						echo wp_kses(
							__( 'AI: <code>tsubakuro_add_comment</code>（id: 42, comment: "デザイン確認済み"）を呼び出す', 'tsubakuro' ),
							array( 'code' => array() )
						);
						?>
					</li>
					<li><?php esc_html_e( 'AI: コメント追加完了を伝える', 'tsubakuro' ); ?></li>
				</ol>
			</div>

			<div class="tsubakuro-guide-flow">
				<h3><?php esc_html_e( '特定ページに紐づくタスクを確認する', 'tsubakuro' ); ?></h3>
				<ol class="tsubakuro-flow-steps">
					<li><?php esc_html_e( 'ユーザー: 「ページ ID 10 に関連するタスクを全部見せて」', 'tsubakuro' ); ?></li>
					<li>
						<?php
						echo wp_kses(
							__( 'AI: <code>tsubakuro_list_tasks</code>（related_page: 10）を呼び出す', 'tsubakuro' ),
							array( 'code' => array() )
						);
						?>
					</li>
					<li><?php esc_html_e( 'AI: 一覧を整形して返す', 'tsubakuro' ); ?></li>
				</ol>
			</div>

		</div><!-- .tsubakuro-guide-flows -->
	</div>

	<!-- ==================================================================
		Section 6 – MCP クライアントの設定例
		================================================================== -->
	<div class="tsubakuro-guide-card">
		<h2><?php esc_html_e( 'MCP クライアントの設定例', 'tsubakuro' ); ?></h2>
		<p>
			<?php esc_html_e( 'MCP 対応クライアントでは、クライアントがサポートする設定形式に合わせてサーバー URL と認証ヘッダーを設定します。', 'tsubakuro' ); ?>
		</p>
		<h3><?php esc_html_e( 'URL とヘッダーを直接指定できるクライアント', 'tsubakuro' ); ?></h3>
		<div class="tsubakuro-code-block-wrap">
			<button type="button" class="button tsubakuro-copy-btn" data-target="tsubakuro-guide-config">
				<?php esc_html_e( 'コピー', 'tsubakuro' ); ?>
			</button>
			<pre id="tsubakuro-guide-config" class="tsubakuro-code-block">
<?php
$example = '{
  "mcpServers": {
    "tsubakuro": {
      "url": "' . esc_js( $mcp_url ) . '",
      "headers": {
        "Authorization": "Basic <Base64エンコードした認証情報>"
      }
    }
  }
}';
echo esc_html( $example );
?>
			</pre>
		</div>
		<h3><?php esc_html_e( 'ローカルブリッジ経由で接続するクライアント', 'tsubakuro' ); ?></h3>
		<div class="tsubakuro-code-block-wrap">
			<button type="button" class="button tsubakuro-copy-btn" data-target="tsubakuro-guide-bridge-config">
				<?php esc_html_e( 'コピー', 'tsubakuro' ); ?>
			</button>
			<pre id="tsubakuro-guide-bridge-config" class="tsubakuro-code-block">
<?php
$bridge_example = '{
  "mcpServers": {
    "tsubakuro": {
      "command": "npx",
      "args": [
        "-y",
        "@modelcontextprotocol/server-fetch",
        "--url",
        "' . esc_js( $mcp_url ) . '",
        "--header",
        "Authorization: Basic <Base64エンコードした認証情報>"
      ]
    }
  }
}';
echo esc_html( $bridge_example );
?>
			</pre>
		</div>
		<h3><?php esc_html_e( '環境変数からヘッダーを組み立てる運用', 'tsubakuro' ); ?></h3>
		<div class="tsubakuro-code-block-wrap">
			<button type="button" class="button tsubakuro-copy-btn" data-target="tsubakuro-guide-env-config">
				<?php esc_html_e( 'コピー', 'tsubakuro' ); ?>
			</button>
			<pre id="tsubakuro-guide-env-config" class="tsubakuro-code-block">
<?php
$env_example = 'TSUBAKURO_MCP_URL="' . esc_js( $mcp_url ) . '"
TSUBAKURO_MCP_AUTH="Basic <Base64エンコードした認証情報>"';
echo esc_html( $env_example );
?>
			</pre>
		</div>
		<p class="description">
			<?php
			$settings_link = sprintf(
				'<a href="%s">%s</a>',
				esc_url( admin_url( 'admin.php?page=tsubakuro-settings' ) ),
				esc_html__( '設定ページ', 'tsubakuro' )
			);
			echo wp_kses(
				sprintf(
					/* translators: %s: link to settings page */
					__( 'アプリケーションパスワードの発行手順は %s をご覧ください。', 'tsubakuro' ),
					$settings_link
				),
				array(
					'a' => array( 'href' => array() ),
				)
			);
			?>
		</p>
		<p class="description">
			<?php
			$settings_oauth_link = sprintf(
				'<a href="%s">%s</a>',
				esc_url( admin_url( 'admin.php?page=tsubakuro-settings' ) ),
				esc_html__( '設定ページ', 'tsubakuro' )
			);
			echo wp_kses(
				sprintf(
					/* translators: %s: link to settings page */
					__( 'claude.ai Custom Connector で接続する場合は、OAuth 2.0 認可コードフローを使用します。%s でクライアントを登録し、認可エンドポイントとトークンエンドポイントの URL を claude.ai に設定してください。', 'tsubakuro' ),
					$settings_oauth_link
				),
				array( 'a' => array( 'href' => array() ) )
			);
			?>
		</p>
	</div>

</div><!-- .wrap -->
