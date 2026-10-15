## 背景

本リポジトリは公開リポジトリであり、インフラ構成情報や各種シークレットの漏洩リスクを継続的に低減する必要があります。現状の CI 検知では、GitHub Actions ワークフロー自体の設定不備や不適切な権限付与（例: actionlint で検知できるリスク）を定期的に監査する仕組みが欠如していました。

## このPRで導入するもの

- ツール名: actionlint (既存)
- 導入箇所: `.github/workflows/actionlint.yml` に `schedule` トリガーを追加
- 期待される効果: 毎週の定期実行により、ワークフロー定義に潜むセキュリティリスクや構文エラーを自動かつ継続的に検知します。

## 検知漏れリスクと補完策

- 検知できないケース: ワークフロー内の外部スクリプトの脆弱性や、動的に注入されるシークレット自体の漏洩
- 補完策: 既存の Gitleaks および TruffleHog の定期監査、ならびに GitHub Secret Scanning を併用して二重防御とします。

## マージ前に必要な手動作業（チェックリスト）

レビュアーは PR をマージする前に必ず以下を実施してください。
本 PR の CI は手動作業完了を前提に通る設計です。

- [ ] GitHub リポジトリの設定から Code security -> Push protection が有効になっていることを確認する
- [ ] developer 各自のローカル環境で `pre-commit install` を最新状態にしておくよう周知する

## マージ後の確認手順

- [ ] 次のスケジュール時刻（毎週月曜 06:00 JST）で workflow が green になることを確認
- [ ] actionlint の実行結果に警告がないことを確認

## ロールバック手順

- `.github/workflows/actionlint.yml` から `schedule` ブロックを削除して push してください。

## 参考情報

- 公式ドキュメント: https://github.com/rhysd/actionlint
- 比較検討した他案: zizmor (既に導入・スケジュール設定済みのため併用)
