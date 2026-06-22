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

- MCP 接続方式と設定方法は `mcp-adapter` プラグインの接続方式に準拠します。

## MCP リモートサーバー（mcp-adapter 前提）

このプラグインは `wordpress/mcp-adapter` を使って MCP ツールを公開します。

## Development

```sh
npm install
npx playwright install-deps
npm run test
npm run build:zip
```

- `npm run test`: ユニットテスト（`composer test`）と integration テスト（`npm run wp-env:test`）を連続実行します。
- `npm run build:zip`: `dist/tsubakuro.zip` を作成します。

## Distribution

### GitHub Releasesによる更新

公開GitHub Releaseを更新元として、WordPress管理画面から通常のプラグイン更新を実行できます。更新チェックには同梱した
[`plugin-update-checker`](https://github.com/YahnisElsts/plugin-update-checker) v5.7を使用します。

リリース時は、次の4か所を同じ `X.Y.Z` に更新してください。

- `tsubakuro.php` のプラグインヘッダー `Version`
- `tsubakuro.php` の `TSUBAKURO_VERSION`
- `package.json` と `package-lock.json` の `version`
- `readme.txt` の `Stable tag`

その後、lint・test・ZIP作成を実行し、対応する `vX.Y.Z` タグをpushします。

```sh
npm run lint
npm run test
npm run build:zip
git tag vX.Y.Z
git push origin vX.Y.Z
```

タグpushによりRelease workflowが `dist/tsubakuro.zip` をGitHub Releaseへ添付します。タグと各バージョンが一致しない場合や、
ZIPに更新処理の実行ファイルが含まれない場合、workflowはRelease作成前に失敗します。プレリリースは更新対象になりません。

WordPressは定期的に更新を確認します。すぐに確認する場合は、プラグイン一覧の「更新を確認」を使用してください。

`main` ブランチの `Tests` workflow が成功すると、GitHub Actionsの `Build Test ZIP` workflow が `tsubakuro-test-build` artifactとしてテスト用ZIPを作成します。

`Tests` workflow は `main` へのpush、Pull Request、手動実行、毎日 18:00 UTC の定期実行で動きます。

`Build Test ZIP` workflow は作成したZIPを展開し、次のGitHub Secretsを使って展開済みプラグインをrsyncで共有先へ同期します。

- `RSYNC_HOST`
- `RSYNC_USER`
- `RSYNC_DESTINATION`
- `RSYNC_SSH_PRIVATE_KEY`
- `RSYNC_PORT` optional, default `22`
- `RSYNC_KNOWN_HOSTS` optional
