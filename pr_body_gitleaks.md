## 背景

本リポジトリは公開リポジトリであり、AIエージェントの作業跡や各種開発ツールのトークン、ならびにSaaS APIキーが意図せず流出するリスクを抱えています。
プロダクトアナリティクス系SaaSのAPIトークン（Amplitude, Mixpanel, PostHog, Segment）は、一度漏洩するとデータの汚染や意図しないトラッキング設定の変更といったリスクがあるため、未然にブロックする仕組みが求められていました。

## このPRで導入するもの

- ツール名: gitleaks v8.x (設定の追加)
- 導入箇所: `.gitleaks.toml` および `docs/security/leak-prevention.md`
- 期待される効果: コミット前にローカル環境 (`pre-commit` hook) および CI で、Amplitude、Mixpanel、PostHog、Segment の API トークン・プロジェクトキーが混入していることを検出し、コミット/プッシュを自動的にブロックします。

## 検知漏れリスクと補完策

- 検知できないケース: 上記4サービス以外のアナリティクスSaaS（例えばGoogle Analyticsの測定ID `G-XXXX` のみなど、フォーマットが緩いもの）や、トークンのフォーマットが変わった場合、または環境変数等に動的に組み立てられて格納される場合。
- 補完策: 引き続き既存の GitHub Secret Scanning を併用し、二重で漏洩を防ぎます。必要に応じて後続のPRで他のSaaSトークンも追加拡充します。

## マージ前に必要な手動作業（チェックリスト）

レビュアーは PR をマージする前に必ず以下を実施してください。
本 PR の CI は手動作業完了を前提に通る設計です。

- [ ] GitHub repo settings → Code security → Push protection が有効になっていることを確認する
- [ ] developer 各自のローカル環境にて `pre-commit install` が実施済みであることを引き続き周知する

## マージ後の確認手順

- [ ] 次の push / PR で導入した workflow (`gitleaks`) が green になることを確認
- [ ] ローカル環境で Amplitude 等のダミートークン（例: `phc_ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmno`）を含むファイルをコミットしようとした際、gitleaks がフックとして動作して拒否することを確認

## ロールバック手順

過剰検知（false positive）等により開発に重大な支障が出た場合は、本 PR を revert し、再度 `.gitleaks.toml` を調整した上で出し直してください。

## 参考情報

- 公式ドキュメント: https://github.com/gitleaks/gitleaks
- 比較検討した他案: `detect-secrets` の新規追加（既に `gitleaks` と `trufflehog` が稼働中のため、重複追加を避けて既存ツールの設定強化を優先しました）
- 直近の関連 PR / Issue: #96 (Braintree/PayPal/Square等トークン追加), #94 (Twitter/Facebook/LINE等追加)
