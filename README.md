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

- MCP エンドポイント、利用可能なツール、生成AI接続フローは WordPress 管理画面の「Tsubakuro」→「MCP ガイド」で確認できます。

## MCP リモートサーバー

このプラグインは WordPress REST API 上に Streamable HTTP 形式の MCP エンドポイントを公開します。

- URL: `https://gaichubase.com/wp-json/tsubakuro/v1/mcp`
- Transport: Streamable HTTP
- MCP Protocol Version: `2025-11-25`
- JSON-RPC: `2.0`
- 認証: `Authorization: Basic <Base64(username:application_password)>`
- サポートメソッド: `initialize`, `notifications/initialized`, `tools/list`, `tools/call`
- ツール: `tsubakuro_list_tasks`, `tsubakuro_get_task`, `tsubakuro_create_task`, `tsubakuro_update_task`, `tsubakuro_delete_task`, `tsubakuro_add_comment`

WordPress の Application Passwords を使う場合は、`ユーザー名:アプリケーションパスワード` を Base64 エンコードして `Authorization` ヘッダーに設定します。

```sh
curl -X POST https://gaichubase.com/wp-json/tsubakuro/v1/mcp \
  -H "Content-Type: application/json" \
  -H "Accept: application/json, text/event-stream" \
  -H "MCP-Protocol-Version: 2025-11-25" \
  -H "Authorization: Basic <Base64エンコードした認証情報>" \
  -d '{"jsonrpc":"2.0","id":1,"method":"initialize","params":{"protocolVersion":"2025-11-25","capabilities":{},"clientInfo":{"name":"curl-test","version":"0.1.0"}}}'
```

```sh
curl -X POST https://gaichubase.com/wp-json/tsubakuro/v1/mcp \
  -H "Content-Type: application/json" \
  -H "Accept: application/json, text/event-stream" \
  -H "MCP-Protocol-Version: 2025-11-25" \
  -H "Authorization: Basic <Base64エンコードした認証情報>" \
  -d '{"jsonrpc":"2.0","id":2,"method":"tools/list","params":{}}'
```

タスク一覧取得:

```sh
curl -X POST https://gaichubase.com/wp-json/tsubakuro/v1/mcp \
  -H "Content-Type: application/json" \
  -H "Accept: application/json, text/event-stream" \
  -H "MCP-Protocol-Version: 2025-11-25" \
  -H "Authorization: Basic <Base64エンコードした認証情報>" \
  -d '{"jsonrpc":"2.0","id":3,"method":"tools/call","params":{"name":"tsubakuro_list_tasks","arguments":{"status":"in_progress","per_page":10}}}'
```

タスク更新:

```sh
curl -X POST https://gaichubase.com/wp-json/tsubakuro/v1/mcp \
  -H "Content-Type: application/json" \
  -H "Accept: application/json, text/event-stream" \
  -H "MCP-Protocol-Version: 2025-11-25" \
  -H "Authorization: Basic <Base64エンコードした認証情報>" \
  -d '{"jsonrpc":"2.0","id":4,"method":"tools/call","params":{"name":"tsubakuro_update_task","arguments":{"id":42,"status":"completed"}}}'
```

コメント追加:

```sh
curl -X POST https://gaichubase.com/wp-json/tsubakuro/v1/mcp \
  -H "Content-Type: application/json" \
  -H "Accept: application/json, text/event-stream" \
  -H "MCP-Protocol-Version: 2025-11-25" \
  -H "Authorization: Basic <Base64エンコードした認証情報>" \
  -d '{"jsonrpc":"2.0","id":5,"method":"tools/call","params":{"name":"tsubakuro_add_comment","arguments":{"id":42,"comment":"クライアント確認が完了しました。"}}}'
```

### Codex CLI

`~/.codex/config.toml` に追加します。

```toml
[mcp_servers.tsubakuro]
url = "https://gaichubase.com/wp-json/tsubakuro/v1/mcp"
http_headers = { "Authorization" = "Basic <Base64エンコードした認証情報>" }
```

環境変数から渡す場合:

```toml
[mcp_servers.tsubakuro]
url = "https://gaichubase.com/wp-json/tsubakuro/v1/mcp"
env_http_headers = { "Authorization" = "TSUBAKURO_MCP_AUTH" }
```

```sh
export TSUBAKURO_MCP_AUTH="Basic <Base64エンコードした認証情報>"
codex mcp list
```

### Claude Code

HTTP transport と `Authorization` ヘッダーを指定します。

```sh
claude mcp add --transport http tsubakuro \
  https://gaichubase.com/wp-json/tsubakuro/v1/mcp \
  --header "Authorization: Basic <Base64エンコードした認証情報>"
```

JSON で追加する場合:

```sh
claude mcp add-json tsubakuro \
  '{"type":"http","url":"https://gaichubase.com/wp-json/tsubakuro/v1/mcp","headers":{"Authorization":"Basic <Base64エンコードした認証情報>"}}'
```

### GitHub Copilot / VS Code

ワークスペースの `.vscode/mcp.json` に追加します。

```json
{
  "servers": {
    "tsubakuro": {
      "type": "http",
      "url": "https://gaichubase.com/wp-json/tsubakuro/v1/mcp",
      "headers": {
        "Authorization": "Basic ${input:tsubakuro-basic-auth}"
      }
    }
  },
  "inputs": [
    {
      "type": "promptString",
      "id": "tsubakuro-basic-auth",
      "description": "Authorization header value for Tsubakuro MCP",
      "password": true
    }
  ]
}
```

GitHub Copilot CLI を使う場合:

```sh
copilot mcp add tsubakuro \
  --type http \
  --url https://gaichubase.com/wp-json/tsubakuro/v1/mcp \
  --header "Authorization=Basic <Base64エンコードした認証情報>"
```

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
