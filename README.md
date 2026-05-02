# tsubakuro
WordPress管理画面でのタスク管理プラグイン

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

`main` ブランチへpushすると、GitHub Actionsの `Build Release ZIP` workflow が `tsubakuro-latest` artifactとして最新ZIPを作成します。

タグ `v*` をpushすると、同じZIPをGitHub Releaseへ添付します。
