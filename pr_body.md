## 背景

公開リポジトリにおいて、ファイル名や拡張子を変更してコミットされた秘密鍵や認証情報を検知する仕組みが不十分でした。

## このPRで導入するもの

- ツール名: gitleaks v8.x (設定の厳格化)
- 導入箇所: `.gitleaks.toml` と `docs/security/leak-prevention.md`
- 期待される効果: コミット前にローカルで GCP Service Account Key (JSON形式)、RSA/DSA等の汎用的な秘密鍵 (ヘッダ検知)、および http 以外の汎用プロトコルでの Basic認証URI を内容ベースで検出して拒否。

## 検知漏れリスクと補完策

- 検知できないケース: ヘッダを持たない非標準フォーマットの秘密鍵や、特殊なエンコードが施されたトークン。
- 補完策: TruffleHog (CI) での動的検証や、GitHub Secret Scanning と組み合わせて二重化。

## マージ前に必要な手動作業（チェックリスト）

レビュアーは PR をマージする前に必ず以下を実施してください。
本 PR の CI は手動作業完了を前提に通る設計です。

- [ ] 各開発者のローカル環境で `pre-commit install` が実施済みであることを周知
- [ ] GitHub repo settings → Code security → Push protection が有効であることを確認

## マージ後の確認手順

- [ ] 次の push / PR で `pre-commit` および `gitleaks` ワークフローが green になることを確認
- [ ] ローカルでわざと `-----BEGIN PRIVATE KEY-----` (実運用時は適宜難読化) を含むダミーファイルをコミットしようとした際、フックとして動作することを確認

## ロールバック手順

問題が出た場合は、`.gitleaks.toml` の `generic-private-key-content` などの新規ルールブロックを削除するか、本 PR を revert してください。

## 参考情報

- 公式ドキュメント: https://github.com/gitleaks/gitleaks
- 比較検討した他案: `detect-secrets` の導入を検討しましたが、既存ツールの設定拡張（`.gitleaks.toml`）を優先しました。
