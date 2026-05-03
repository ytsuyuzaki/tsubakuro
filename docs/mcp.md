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

認証方法として以下が使えます（WordPress 標準の REST API 認証と同じです）。

- **Application Passwords**（推奨）: WordPress 管理画面 → ユーザー → プロフィール → アプリケーションパスワードを発行し、Basic 認証で使用します。
- **Cookie 認証**: ブラウザ上の WordPress セッションを利用する場合（`X-WP-Nonce` ヘッダーが必要）。

`GET` によるディスカバリーは認証不要です。

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

MCP 対応クライアントでは、以下のようにサーバー URL を設定します（クライアントによって設定方法は異なります）。

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

> **注意**: Application Password は WordPress 管理画面 → ユーザー → プロフィール の「アプリケーションパスワード」セクションから発行してください。
