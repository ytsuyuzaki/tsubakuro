# tsubakuro

WordPress管理画面でのタスク管理プラグイン

## 開発のきっかけ

このプラグインは、GitHub Copilot のエージェント機能を活用した AI 駆動の開発フローの実験として生まれました。
OpenAI の [Symphony](https://github.com/openai/symphony) が示すマルチエージェント・オーケストレーションのフローを WordPress 上で実現することを目指しており、現状は生成 AI との連携を前提とした設計になっています。

## 今後の対応予定

- **エージェント実行のサブスクリプション提供**: 管理画面から別途開発したサブスクリプションに登録することで、タスクを自律実行するエージェント機能を提供できるようにする予定です。
- **RAG 対応**: 既存記事の内容や情報を文脈として参照し、記事の作成・修正を行う RAG（Retrieval-Augmented Generation）に対応する予定です。
- **アナリティクス MCP 連携**: アナリティクスツールを MCP サーバーとして接続し、データに基づく改善提案をエージェントが行えるようにする予定です。
- **SEO フローの自動化**: 以下のサイクルをタスク管理ツールとして自動化することを目指しています。
  1. アクセス獲得のための計画立案
  2. 競合ページとの比較・コンテンツ強化
  3. 変更履歴の記録
  4. 経過観測
  5. 効果が出た場合の実施項目と結果の記録

## ドキュメント

- MCP エンドポイント、JSON-RPC メッセージ形式、接続要件は docs/mcp-message-format.md を参照してください。

## MCP リモートサーバー（mcp-adapter 前提）

このプラグインは `wordpress/mcp-adapter` を使って MCP ツールを公開します。

- mcp-adapter デフォルトサーバー: `https://your-site.test/wp-json/mcp/mcp-adapter-default-server`
- Transport: Streamable HTTP
- MCP Protocol Version: `2025-11-25`
- 認証: WordPress Application Passwords (`Authorization: Basic <Base64(username:application_password)>`)

Tsubakuro のカスタムツールは次を公開します（mcp-adapter により MCP 名へ変換）。

- `tsubakuro-list-tasks`
- `tsubakuro-get-task`
- `tsubakuro-create-task`
- `tsubakuro-update-task`
- `tsubakuro-delete-task`
- `tsubakuro-add-comment`

### mcp.json 例（ご提示フォーマット準拠）

```json
{
  "mcpServers": {
    "wordpress-http-default": {
      "command": "npx",
      "args": [
        "-y",
        "@automattic/mcp-wordpress-remote@latest"
      ],
      "env": {
        "WP_API_URL": "http://your-site.test/wp-json/mcp/mcp-adapter-default-server",
        "LOG_FILE": "/path/to/logs/mcp-adapter.log",
        "WP_API_USERNAME": "your-username",
        "WP_API_PASSWORD": "your-application-password"
      }
    }
  }
}
```

### curl での接続確認

```sh
curl -X POST https://your-site.test/wp-json/mcp/mcp-adapter-default-server \
  -H "Content-Type: application/json" \
  -H "Accept: application/json, text/event-stream" \
  -H "Authorization: Basic <Base64エンコードした認証情報>" \
  -d '{"jsonrpc":"2.0","id":1,"method":"initialize","params":{"protocolVersion":"2025-11-25","capabilities":{},"clientInfo":{"name":"curl-test","version":"0.1.0"}}}'
```

```sh
curl -X POST https://your-site.test/wp-json/mcp/mcp-adapter-default-server \
  -H "Content-Type: application/json" \
  -H "Accept: application/json, text/event-stream" \
  -H "MCP-Protocol-Version: 2025-11-25" \
  -H "Authorization: Basic <Base64エンコードした認証情報>" \
  -d '{"jsonrpc":"2.0","id":2,"method":"tools/list","params":{}}'
```

### Codex CLI

`~/.codex/config.toml` に追加します。

```toml
[mcp_servers.tsubakuro]
url = "https://your-site.test/wp-json/mcp/mcp-adapter-default-server"
http_headers = { "Authorization" = "Basic <Base64エンコードした認証情報>" }
```

### Claude Code

```sh
claude mcp add --transport http tsubakuro \
  https://your-site.test/wp-json/mcp/mcp-adapter-default-server \
  --header "Authorization: Basic <Base64エンコードした認証情報>"
```

### GitHub Copilot / VS Code

ワークスペースの `.vscode/mcp.json` へ設定する場合は、上記 `mcpServers` 例の `wordpress-http-default` を利用してください。

## Development

```sh
npm install
composer test
npm run wp-env:test
npm run build:zip
```

- `composer test`: 軽量ユニットテストを実行します。
- `npm run wp-env:test`: `wp-env` 上でプラグインを有効化し、WordPress統合スモークテストを実行します。
- `npm run build:zip`: `dist/tsubakuro.zip` を作成します。

## Distribution

`main` ブランチの `Tests` workflow が成功すると、GitHub Actionsの `Build Test ZIP` workflow が `tsubakuro-test-build` artifactとしてテスト用ZIPを作成します。

`Tests` workflow は `main` へのpush、Pull Request、手動実行、毎日 18:00 UTC の定期実行で動きます。

`Build Test ZIP` workflow は作成したZIPを展開し、次のGitHub Secretsを使って展開済みプラグインをrsyncで共有先へ同期します。

- `RSYNC_HOST`
- `RSYNC_USER`
- `RSYNC_DESTINATION`
- `RSYNC_SSH_PRIVATE_KEY`
- `RSYNC_PORT` optional, default `22`
- `RSYNC_KNOWN_HOSTS` optional
