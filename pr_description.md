## 背景

本リポジトリではローカル開発環境およびAIエージェントの作業によるシークレット漏洩を防ぐため、`.pre-commit-config.yaml` を用いた水際対策を導入しています。しかし、開発者が `--no-verify` を用いてコミットを強制した場合、CI上で実行される `gitleaks` や `TruffleHog` のジョブは機能するものの、`detect-private-key` や `check-added-large-files` といったその他の重要なフックはスキップされたままリポジトリに取り込まれてしまうというギャップが存在しました。

## このPRで導入するもの

- ツール名: pre-commit (GitHub Actions ワークフロー)
- 導入箇所: `.github/workflows/pre-commit.yml` および `docs/security/leak-prevention.md`
- 期待される効果: CI環境でも `pre-commit run --all-files` を実行することで、ローカルでの強制コミット等による水際対策のすり抜けを確実にブロックします。

## 検知漏れリスクと補完策

- 検知できないケース: 未検証のサードパーティアクション等はセキュリティポリシー違反のため直接導入できないため、今回は `pip install pre-commit` にて標準的にフックを実行する方式をとっています。もし将来的に特定のフックが環境依存で失敗する場合は除外設定が必要になる可能性があります。
- 補完策: 既に導入されている専用の `gitleaks.yml` や `trufflehog.yml` によって多重防御が行われているため、万が一 `pre-commit.yml` に問題が発生しても検知レベルは維持されます。

## マージ前に必要な手動作業（チェックリスト）

レビュアーは PR をマージする前に必ず以下を実施してください。
本 PR の CI は手動作業完了を前提に通る設計です。

- [ ] ローカルで `--no-verify` を使って意図的にコミットされた変更が、本PRのCIでブロックされることを確認する

## マージ後の確認手順

- [ ] 次の push / PR で `pre-commit` workflow が green になることを確認

## ロールバック手順

問題が出た場合、`.github/workflows/pre-commit.yml` ファイルを削除するか、コミットをリバートしてください。

## 参考情報

- 公式ドキュメント: https://pre-commit.com/
- 比較検討した他案: `pre-commit/action` はサードパーティアクションの利用ポリシーにより使用せず、直接 `pip` コマンドで導入する方針を選択しました。
