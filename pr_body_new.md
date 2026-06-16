## 背景

公開リポジトリでの漏洩リスクとして、Stripe や Cloudflare といった API キーおよびトークンが誤ってコミット・push されるリスクが存在していました。既存の `gitleaks` 設定では Resend API キーなどは検知可能でしたが、これらのカバー範囲が不足していたため、現状のギャップを埋める必要があります。

## このPRで導入するもの

- ツール名: gitleaks v8.x のカスタムルール
- 導入箇所: `.gitleaks.toml` と `docs/security/leak-prevention.md`
- 期待される効果: コミット前にローカルおよび CI 上で Stripe API キーと Cloudflare API トークンを検出して拒否

## 検知漏れリスクと補完策

- 検知できないケース: 指定した正規表現（`sk_live_*` や 40文字のトークン）に合致しない古い形式やカスタム形式のトークン
- 補完策: 既存の GitHub Secret Scanning と組み合わせて二重化し、検知を補完する

## マージ前に必要な手動作業（チェックリスト）

レビュアーは PR をマージする前に必ず以下を実施してください。
本 PR の CI は手動作業完了を前提に通る設計です。

- [ ] GitHub repo settings → Code security → Push protection が有効になっていることを確認
- [ ] developer 各自のローカルで `pre-commit install` を実行し直すか、最新の設定を利用する周知

## マージ後の確認手順

- [ ] 次の push / PR で導入した workflow (gitleaks) が green になることを確認
- [ ] ローカル環境でダミーの Stripe API キー (`sk_live_...`) を含めたコミットが `pre-commit` でブロックされることを確認

## ロールバック手順

- 本PRのコミットをリバートするか、`.gitleaks.toml` から `stripe-api-key` および `cloudflare-api-token` ルールを削除する。

## 参考情報

- 公式ドキュメント: https://github.com/gitleaks/gitleaks
- 比較検討した他案: `detect-secrets` などの別ツールの導入も検討しましたが、既存の `gitleaks` の設定拡張の方が管理コストが低く、リポジトリに馴染むため採用しました。
