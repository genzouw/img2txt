## 背景

公開リポジトリである本プロジェクトにおいて、開発時にシークレット（`.env` ファイルなど）が誤ってDockerビルドのコンテキストに含まれた場合、ビルド済みのコンテナイメージ内に機密情報が混入し、そのままデプロイ・公開されてしまうリスクがあります。

## このPRで導入するもの

- ツール名: Trivy (既存の CI ワークフローへの設定追加)
- 導入箇所: `.github/workflows/ci.yml`
- 期待される効果: プルリクエストおよび push 時の CI において、ビルドされた Docker イメージに対する脆弱性スキャンだけでなく、イメージ内に混入したシークレットも検知し、該当する場合は CI をブロック（exit-code 1）します。

## 検知漏れリスクと補完策

- 検知できないケース: Trivy のシークレットシグネチャに該当しない、非標準のトークン形式や短すぎるランダム文字列。
- 補完策: 既存の `gitleaks` および `TruffleHog` による履歴全体の走査、ローカルの pre-commit hook での水際対策、および GitHub Secret Scanning による多層防御により補完します。

## マージ前に必要な手動作業（チェックリスト）

レビュアーは PR をマージする前に必ず以下を実施してください。
本 PR の CI は手動作業完了を前提に通る設計です。

- [ ] GitHub repo settings → Code security → Push protection が有効化されていることを確認（推奨）
- [ ] 万が一 `.env` 等の漏洩が発生しないよう、ローカル環境で `.dockerignore` が適切に運用されているか（または後日追加を検討するか）確認

## マージ後の確認手順

- [ ] 次の push / PR で `ci.yml` 内の "Run Trivy on built image" ステップが正常に動作し、シークレットスキャンが行われていることを確認

## ロールバック手順

万が一CIが誤検知などで常に失敗するようになった場合は、`.github/workflows/ci.yml` の `trivy image` の引数 `--scanners vuln,secret` を元の `--vuln-type os,library` に戻すコミットを作成し、マージしてください。

## 参考情報

- 公式ドキュメント: <https://aquasecurity.github.io/trivy/>
