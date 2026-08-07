## 背景

公開リポジトリである本プロジェクトにおいて、広く利用されている SaaS（Zendesk、Okta、HubSpot、Grafana、Shopify）の API トークンやキーが誤ってコミットされ漏洩するリスクを防ぐ必要があります。現在これらの特定のフォーマットに対する厳密な検知ルールが `.gitleaks.toml` に不足していたため、これを補完します。

## このPRで導入するもの

- ツール名: gitleaks v8.x (設定の追加)
- 導入箇所: `.gitleaks.toml` と `docs/security/leak-prevention.md`
- 期待される効果: Zendesk, Okta, HubSpot, Grafana, Shopify の各 API キー/トークンがコミットされる前に、ローカルの pre-commit フックおよび CI 上で検出してコミット・プッシュを拒否します。

## 検知漏れリスクと補完策

- 検知できないケース: 上記ルールで定義した正規表現パターンから外れる、古い形式やカスタム形式のトークン、またはダミー値が意図的に難読化されているケース。
- 補完策: 既存の GitHub Secret Scanning と Push Protection の二重化、および TruffleHog による有効性検証との併用。

## マージ前に必要な手動作業（チェックリスト）

レビュアーは PR をマージする前に必ず以下を実施してください。
本 PR の CI は手動作業完了を前提に通る設計です。

- [ ] GitHub repo settings → Code security → Push protection が有効化されていることを再確認
- [ ] developer 各自のローカル環境で `pre-commit install` が既に実行されていることの周知（ルール追加は pull 時に自動適用されます）

## マージ後の確認手順

- [ ] 次の push / PR で導入した workflow (Gitleaks) が green になることを確認
- [ ] ローカルで対象 SaaS のダミートークン（例: `zendesk_api_token: 0123456789012345678901234567890123456789`）を書き込もうとした際にフックとして動作・ブロックされることを確認

## ロールバック手順

万が一、追加した正規表現により大規模な誤検知が発生し開発がブロックされる場合は、PR を Revert するか、`.gitleaks.toml` から該当する `[[rules]]` ブロックを削除してください。

## 参考情報

- 公式ドキュメント: https://github.com/gitleaks/gitleaks
- 比較検討した他案: 新規のシークレット検知ツールの導入も検討しましたが、運用コストと既存資産の有効活用を考慮し、既に導入済みの Gitleaks のカスタムルール拡張を採用しました。
- 直近の関連 PR / Issue: 特になし
