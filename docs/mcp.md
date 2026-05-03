# MCP (Model Context Protocol) エンドポイント

Tsubakuro は [Model Context Protocol (MCP)](https://modelcontextprotocol.io/) に対応しており、ChatGPT・Claude・Gemini などの生成 AI クライアントからタスクを直接操作できます。

## エンドポイント概要

| 種別 | メソッド | パス |
|------|----------|------|
| ディスカバリー（マニフェスト取得） | `GET` | `/wp-json/tsubakuro/v1/mcp` |
| ツール呼び出し（JSON-RPC 2.0） | `POST` | `/wp-json/tsubakuro/v1/mcp` |

ベース URL はサイトのホスト名に依存します。例: `https://example.com/wp-json/tsubakuro/v1/mcp`

---

## 認証

`POST` リクエスト（ツール呼び出し）には WordPress の認証が必要です。
呼び出しユーザーに `edit_posts` 権限が必要です。

このプラグインでは、WordPress 標準の REST API 認証をそのまま使います。

- **Application Passwords（推奨）**: WordPress 管理画面 → ユーザー → プロフィール → アプリケーションパスワードを発行し、`Authorization: Basic <Base64(username:application_password)>` ヘッダーで使用します。
- **Cookie 認証**: ブラウザ上の WordPress セッションを利用する場合に使います（`X-WP-Nonce` ヘッダーが必要）。

`GET` によるディスカバリーは認証不要です。

---

## 接続方式の比較

複数の MCP クライアントに対応する場合も、まずは **Application Passwords を Authorization ヘッダーで送る方式** を使ってください。実装済みで、WordPress 標準の権限管理と連動し、ローカルブリッジや HTTP ヘッダー指定に対応したクライアントで扱いやすいためです。

推奨順は以下です。

1. `Authorization: Basic <Base64(username:application_password)>`
2. クライアントが対応していれば任意ヘッダー注入
3. ローカルブリッジ/STDIO 経由で環境変数からヘッダー生成
4. OAuth 2.1 は将来拡張または外部 proxy で対応
5. URL パス/クエリトークンは非推奨

- **Application Passwords + `Authorization: Basic ...`**: メイン推奨・実装済み。WordPress 標準機能です。ユーザー権限は `edit_posts` で判定されます。
- **任意ヘッダー値**: 実用パターン。クライアントやローカルブリッジが `Authorization` などのヘッダーを設定できる場合に使います。
- **OAuth 2.1 / Bearer token**: 将来拡張または外部 proxy。MCP の HTTP transport では標準寄りの方式です。ただし、このプラグイン単体では OAuth endpoint や Bearer token 検証を提供していません。
- **OAuth クライアント ID/シークレット**: OAuth 導入時の補助情報。ツール呼び出し時に直接送る認証情報ではなく、OAuth フローでクライアントを識別するための情報です。
- **URL パス/クエリトークン**: 非推奨。URL はサーバーログ、ブラウザ履歴、Referer などに残りやすいため、トークンを含めないでください。どうしても必要なクライアント向けの将来オプション扱いです。
- **Cookie + `X-WP-Nonce`**: ブラウザ内操作向け。WordPress 管理画面や同一ブラウザセッションから呼び出す場合に使います。外部 MCP クライアントの主方式にはしません。
- **ローカル STDIO/ブリッジ + 環境変数**: 補助パターン。Claude Desktop などで直接 HTTP ヘッダーを扱いにくい場合、ローカルプロセスが環境変数から認証情報を読み、MCP エンドポイントへヘッダー付きで中継します。
- **リバースプロキシ/API Gateway/OAuth proxy**: 運用オプション。プラグイン外で OAuth、IP 制限、監査ログ、レート制限を追加したい場合に使います。

MCP 公式仕様では、HTTP transport の認可として OAuth 2.1 / Bearer token / Protected Resource Metadata が中心に整理されています。一方、このプラグインは WordPress プラグインとして配布しやすいことを優先し、現時点では WordPress 標準の Application Passwords を採用しています。

参考:

- [MCP Authorization specification](https://modelcontextprotocol.io/specification/2025-11-25/basic/authorization)
- [MCP basic specification](https://modelcontextprotocol.io/specification/2025-11-25/basic)

---

## ディスカバリー

`GET /wp-json/tsubakuro/v1/mcp` を呼び出すと、利用可能なツールの一覧を含む MCP サーバーマニフェストが返ります。AI クライアントはこのマニフェストを読み取り、利用可能なツールを自動的に把握します。

### レスポンス例

```json
{
  "schema_version": "2024-11-05",
  "name": "tsubakuro-task-manager",
  "version": "1.0.0",
  "description": "WordPress task management plugin – manage tasks, comments, status, assignees and related pages.",
  "tools": [ ... ]
}
```

---

## ツール一覧

MCP ツールの呼び出しは JSON-RPC 2.0 形式で `POST` します。

```json
{
  "jsonrpc": "2.0",
  "id": 1,
  "method": "<ツール名>",
  "params": { ... }
}
```

### tsubakuro_list_tasks — タスク一覧取得

タスクの一覧を取得します。フィルタは省略可能です。

| パラメータ | 型 | 必須 | 説明 |
|-----------|-----|------|------|
| `status` | string | — | `todo` / `in_progress` / `completed` でフィルタ |
| `related_page` | integer | — | 関連ページ ID でフィルタ |
| `per_page` | integer | — | 取得件数（最大 100、デフォルト 50） |


```json
{
  "jsonrpc": "2.0",
  "id": 1,
  "method": "tsubakuro_list_tasks",
  "params": { "status": "in_progress", "per_page": 10 }
}
```


```json
{
  "jsonrpc": "2.0",
  "id": 1,
  "result": [
    {
      "id": 42,
      "title": "ランディングページのコピー修正",
      "content": "トップのキャッチコピーを変更する",
      "status": "in_progress",
      "assignee": 3,
      "related_pages": [10, 15]
    }
  ]
}
```

---

### tsubakuro_get_task — タスク詳細取得

指定した ID のタスク詳細をコメントも含めて取得します。

| パラメータ | 型 | 必須 | 説明 |
|-----------|-----|------|------|
| `id` | integer | ✓ | タスク ID |


```json
{
  "jsonrpc": "2.0",
  "id": 2,
  "method": "tsubakuro_get_task",
  "params": { "id": 42 }
}
```


```json
{
  "jsonrpc": "2.0",
  "id": 2,
  "result": {
    "id": 42,
    "title": "ランディングページのコピー修正",
    "content": "トップのキャッチコピーを変更する",
    "status": "in_progress",
    "assignee": 3,
    "related_pages": [10, 15],
    "comments": [
      {
        "id": 7,
        "task_id": 42,
        "user_id": 1,
        "comment": "デザイナーと確認済み",
        "created_at": "2024-11-05 12:00:00"
      }
    ]
  }
}
```

---

### tsubakuro_create_task — タスク作成

新しいタスクを作成します。

| パラメータ | 型 | 必須 | 説明 |
|-----------|-----|------|------|
| `title` | string | ✓ | タイトル |
| `content` | string | — | 内容・説明 |
| `status` | string | — | `todo` / `in_progress` / `completed`（デフォルト: `todo`） |
| `assignee` | integer | — | アサインする WordPress ユーザー ID |
| `related_pages` | integer[] | — | 関連ページ ID の配列 |


```json
{
  "jsonrpc": "2.0",
  "id": 3,
  "method": "tsubakuro_create_task",
  "params": {
    "title": "お問い合わせフォームの文言修正",
    "content": "送信完了メッセージを見直す",
    "status": "todo",
    "assignee": 2,
    "related_pages": [5]
  }
}
```

---

### tsubakuro_update_task — タスク更新

既存のタスクを更新します。指定したフィールドのみ上書きされます。

| パラメータ | 型 | 必須 | 説明 |
|-----------|-----|------|------|
| `id` | integer | ✓ | タスク ID |
| `title` | string | — | タイトル |
| `content` | string | — | 内容・説明 |
| `status` | string | — | `todo` / `in_progress` / `completed` |
| `assignee` | integer | — | アサインする WordPress ユーザー ID |
| `related_pages` | integer[] | — | 関連ページ ID の配列 |


```json
{
  "jsonrpc": "2.0",
  "id": 4,
  "method": "tsubakuro_update_task",
  "params": { "id": 42, "status": "completed" }
}
```

---

### tsubakuro_delete_task — タスク削除

指定したタスクを削除します。

| パラメータ | 型 | 必須 | 説明 |
|-----------|-----|------|------|
| `id` | integer | ✓ | タスク ID |


```json
{
  "jsonrpc": "2.0",
  "id": 5,
  "method": "tsubakuro_delete_task",
  "params": { "id": 42 }
}
```


```json
{
  "jsonrpc": "2.0",
  "id": 5,
  "result": { "deleted": true, "id": 42 }
}
```

---

### tsubakuro_add_comment — コメント追加

タスクにコメントを追加します。コメントの投稿者は認証済みユーザーになります。

| パラメータ | 型 | 必須 | 説明 |
|-----------|-----|------|------|
| `id` | integer | ✓ | タスク ID |
| `comment` | string | ✓ | コメント本文 |


```json
{
  "jsonrpc": "2.0",
  "id": 6,
  "method": "tsubakuro_add_comment",
  "params": { "id": 42, "comment": "クライアント確認が完了しました。" }
}
```

---

## JSON-RPC 2.0 について

MCP の `POST` エンドポイントは [JSON-RPC 2.0](https://www.jsonrpc.org/specification) に準拠しています。

- リクエストには必ず `"jsonrpc": "2.0"` を含めます。
- `id` は任意の文字列または数値です。省略すると通知（notification）扱いになります。
- 複数のツールをまとめて呼び出す **バッチリクエスト**（配列）にも対応しています。

### エラーコード

| コード | 意味 |
|--------|------|
| `-32700` | Parse error — リクエストボディが JSON として解析できない |
| `-32600` | Invalid Request — `method` フィールドが空 |
| `-32601` | Method not found — 存在しないツール名 |
| `-32602` | Invalid params — 必須パラメータが不足 |
| `404` | タスクが見つからない |
| `500` | サーバー内部エラー |

---

## 生成 AI との接続フロー

以下は AI クライアント（例: Claude Desktop や Cursor の MCP 拡張）を接続した場合に想定される典型的なフローです。

### フロー 1: タスク状況のサマリーを依頼する

```text
ユーザー: 「現在進行中のタスクを教えて」
  ↓
AI: tsubakuro_list_tasks を呼び出す（status: "in_progress"）
  ↓
AI: 取得結果をもとに自然言語でサマリーを返す
```

### フロー 2: 新しいタスクを言葉で追加する

```text
ユーザー: 「トップページのバナーを差し替えるタスクを作成して」
  ↓
AI: タイトル・内容を解釈して tsubakuro_create_task を呼び出す
  ↓
AI: 作成されたタスク情報を確認メッセージとして返す
```

### フロー 3: タスクのステータスを更新する

```text
ユーザー: 「タスク ID 42 を完了にして」
  ↓
AI: tsubakuro_update_task（id: 42, status: "completed"）を呼び出す
  ↓
AI: 更新後のタスク内容を返す
```

### フロー 4: タスクにコメントを残す

```text
ユーザー: 「タスク 42 に『デザイン確認済み』とメモを追加して」
  ↓
AI: tsubakuro_add_comment（id: 42, comment: "デザイン確認済み"）を呼び出す
  ↓
AI: コメント追加完了を伝える
```

### フロー 5: 特定ページに紐づくタスクを確認する

```text
ユーザー: 「ページ ID 10 に関連するタスクを全部見せて」
  ↓
AI: tsubakuro_list_tasks（related_page: 10）を呼び出す
  ↓
AI: 一覧を整形して返す
```

---

## MCP クライアントの設定例

MCP 対応クライアントでは、クライアントがサポートする設定形式に合わせてサーバー URL と認証ヘッダーを設定します。

### URL とヘッダーを直接指定できるクライアント

```json
{
  "mcpServers": {
    "tsubakuro": {
      "url": "https://example.com/wp-json/tsubakuro/v1/mcp",
      "headers": {
        "Authorization": "Basic <Base64(username:application_password)>"
      }
    }
  }
}
```

### ローカルブリッジ経由で接続するクライアント

クライアントが直接 HTTP ヘッダーを指定しにくい場合は、ローカルの MCP ブリッジや fetch サーバーにヘッダーを渡して中継します。

```json
{
  "mcpServers": {
    "tsubakuro": {
      "command": "npx",
      "args": [
        "-y",
        "@modelcontextprotocol/server-fetch",
        "--url",
        "https://example.com/wp-json/tsubakuro/v1/mcp",
        "--header",
        "Authorization: Basic <Base64(username:application_password)>"
      ]
    }
  }
}
```

### 環境変数からヘッダーを組み立てる運用

設定ファイルに認証値を直接書きたくない場合は、ローカルブリッジ側で環境変数から `Authorization` ヘッダーを生成します。実際の変数名や指定方法は利用するブリッジに合わせてください。

```sh
TSUBAKURO_MCP_URL="https://example.com/wp-json/tsubakuro/v1/mcp"
TSUBAKURO_MCP_AUTH="Basic <Base64(username:application_password)>"
```

> **注意**: Application Password は WordPress 管理画面 → ユーザー → プロフィール の「アプリケーションパスワード」セクションから発行してください。
> OAuth 2.1 / Bearer token は MCP の HTTP transport で標準寄りの方式ですが、このプラグイン単体ではまだ OAuth endpoint を提供していません。OAuth が必要な場合は、リバースプロキシや API Gateway で外側に追加する構成を検討してください。
