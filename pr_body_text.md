## 背景

公開リポジトリでの漏洩リスクとして、CI/CDツールやパッケージマネージャ、Auth系SaaSのAPIトークンが意図せずコミットされるケースがあります。既存の `gitleaks` カスタムルールでは SonarQube や NuGet, RubyGems 等のトークン検知ルールが不足していました。

## このPRで導入するもの

- ツール名: gitleaks カスタムルール追加
- 導入箇所: `.gitleaks.toml` と `docs/security/leak-prevention.md`
- 期待される効果: コミット前にローカルおよびCI上で SonarQube, RubyGems, NuGet, Kaggle, WorkOS, Stytch, Infisical の APIトークンやシークレットを検出して拒否

## 検知漏れリスクと補完策

- 検知できないケース: 今回追加した正規表現のパターン（接頭辞や特定の長さなど）に合致しない旧形式のトークンや、独自の形式のシークレット
- 補完策: CodeQL 等のセマンティック解析や TruffleHog による検証、GitHub Secret Scanning と組み合わせて二重化

## マージ前に必要な手動作業（チェックリスト）

レビュアーは PR をマージする前に必ず以下を実施してください。
本 PR の CI は手動作業完了を前提に通る設計です。

- [ ] GitHub repo settings → Code security → Push protection を有効化されていることを確認
- [ ] developer 各自のローカルで `pre-commit install` が実施済みであることを周知
- [ ] （該当ツールの仕様変更があった場合）ルールの微調整

## マージ後の確認手順

- [ ] 次の push / PR で導入した workflow (gitleaks) が green になることを確認
- [ ] ローカルで `pre-commit run --all-files` が正常に通過することを確認

## ロールバック手順

過剰検知（false positive）が頻発して開発に支障が出る場合は、本 PR を revert し、該当ルールに allowlist を追加した上で再提出してください。

## 参考情報

- 比較検討した他案: `detect-secrets` などの別ツールの導入も検討しましたが、リポジトリのルールに則り、既存の `gitleaks.toml` にカスタムルールを追加する方針（既存ツールの厳格化）を優先しました。
