## 背景

本プロジェクトは公開リポジトリであり、広く利用されている CI/CD ツールやモニタリング・アラートサービス（CircleCI, New Relic, PagerDuty, Codecov）の API トークンやライセンスキーが誤ってコードベースに混入し、意図せず流出するリスクを防ぐ必要があります。現在これらの特定のフォーマットに対する厳密な検知ルールが `.gitleaks.toml` に不足していたため、ローカル環境でのコミット前検知の仕組みを強化します。

## このPRで導入するもの

- ツール名: gitleaks v8.x (既存のカスタムルール拡張)
- 導入箇所: `.gitleaks.toml` および `docs/security/leak-prevention.md`
- 期待される効果: コミット前にローカルおよび CI 上で、CircleCI, New Relic, PagerDuty, Codecov の各 API キーやトークンが検出された際に自動的にコミットやマージをブロックします。

## 検知漏れリスクと補完策

- 検知できないケース: 今回追加した正規表現パターンから外れる、古い形式やカスタム形式のトークン、または一般的な文字列を使用した非標準のシークレット。
- 補完策: 既存の GitHub Secret Scanning と Push Protection を併用し、多重防御を維持します。また、TruffleHog による動的検証も組み合わせて監視します。

## マージ前に必要な手動作業（チェックリスト）

レビュアーは PR をマージする前に必ず以下を実施してください。
本 PR の CI は手動作業完了を前提に通る設計です。

- [ ] GitHub リポジトリの Settings から Secret Scanning と Push Protection が有効になっていることを確認する
- [ ] 各開発者のローカル環境で再度 `pre-commit install` を実行済みであることの周知

## マージ後の確認手順

- [ ] 次の push / PR で既存の CI ワークフロー (pre-commit, gitleaks) が green になることを確認
- [ ] ローカルでテスト的に `circleci_token = "a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4e5f6a1b2"` などのダミートークンをファイルに記述してコミットしようとした際、gitleaks フックによって正しくブロックされることを確認

## ロールバック手順

万が一、追加した正規表現により大規模な誤検知が発生し、正常な開発業務がブロックされる場合は、本 PR を revert し、`.gitleaks.toml` を以前の状態に戻してください。

## 参考情報

- 公式ドキュメント: https://github.com/gitleaks/gitleaks
- 比較検討した他案: 新規シークレットスキャンツール (例: detect-secrets) の導入も検討しましたが、すでに `gitleaks` と `pre-commit` の多層防御が整備・運用されているため、既存ツールのルールの拡充（現状のギャップを埋める）が最も安全で保守性が高いと判断しました。
