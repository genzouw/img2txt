## 背景

公開リポジトリにおいて、意図せず SaaS の API トークンが漏洩するリスクを防ぐ必要があります。現状、いくつかの主要な API キーの検知ルールが `.gitleaks.toml` に不足していました。

## このPRで導入するもの

- ツール名: gitleaks v8.x (カスタムルールの追加)
- 導入箇所: `.gitleaks.toml` と `docs/security/leak-prevention.md`
- 期待される効果: コミット前にローカルで Auth0、Algolia、および Mailgun の API トークン形式の値を検出して拒否

## 検知漏れリスクと補完策

- 検知できないケース: 上記パターンの正規表現にマッチしない特殊なカスタム形式のトークン
- 補完策: 既存の GitHub Secret Scanning と組み合わせて二重化

## マージ前に必要な手動作業（チェックリスト）

レビュアーは PR をマージする前に必ず以下を実施してください。
本 PR の CI は手動作業完了を前提に通る設計です。

- [ ] GitHub repo settings → Code security → Push protection を有効化
- [ ] developer 各自のローカルで `pre-commit install` を実行する周知

## マージ後の確認手順

- [ ] 次の push / PR で導入した workflow が green になることを確認
- [ ] ローカルで gitleaks がフックとして動作することを確認

## ロールバック手順

問題が出た場合は、本 PR で追加した `.gitleaks.toml` の `[[rules]]` セクション（Auth0、Algolia、Mailgun）を削除またはコメントアウトしてコミット・プッシュしてください。

## 参考情報

- 公式ドキュメント: https://github.com/gitleaks/gitleaks
- 比較検討した他案: 新規ツール (detect-secrets 等) の導入も検討しましたが、重複防止の観点と、既に導入済みの gitleaks が十分な柔軟性を持つためルールの追加を選択しました。
- 直近の関連 PR / Issue: 過去の漏洩防止強化 PR と同様のアプローチです。
