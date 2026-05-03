# tsubakuro

WordPress管理画面でのタスク管理プラグイン

## ドキュメント

- MCP エンドポイント、利用可能なツール、生成AI接続フローは WordPress 管理画面の「Tsubakuro」→「MCP ガイド」で確認できます。

## Development

```sh
npm install
npm test
npm run test:wp
npm run build:zip
```

- `npm test`: PHP構文チェックと軽量ユニットテストを実行します。
- `npm run test:wp`: `wp-env` 上でプラグインを有効化し、WordPress統合スモークテストを実行します。
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
