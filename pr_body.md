## 背景

公開リポジトリにおいて、意図しないシークレットや認証情報の混入を防ぐための水際対策およびCI環境でのパターンマッチング（gitleaks, TruffleHog）は既に整備されています。しかし、変数を通じたデータフローや難読化されたハードコードなど、ロジックの文脈（セマンティクス）に基づく漏洩や脆弱性の検知層が不足していました。

## このPRで導入するもの

- ツール名: CodeQL
- 導入箇所: `.github/workflows/codeql.yml` の `language` マトリックスへの `javascript` の追加、および `docs/security/leak-prevention.md` への記述追記。
- 期待される効果: フロントエンド（もし今後拡張された場合）や、インフラ構成を定義するCDKTFコード（TypeScript/JavaScript）に対してセマンティック解析をCI上で実行し、高度なクレデンシャルのハードコードやロジック起因の脆弱性を検知・ブロックできるようになります。

## 検知漏れリスクと補完策

- 検知できないケース: CodeQLはパターンではなくデータフローを分析するため、コンテキストからシークレットと判定できない独自の文字列は検知が漏れる可能性があります。また、CodeQLがサポートしていない言語（PHP等）のロジックは解析できません。
- 補完策: 既存の `gitleaks` および `TruffleHog` による厳密な正規表現パターンマッチングと組み合わせて多層防御を構築しています。

## マージ前に必要な手動作業（チェックリスト）

レビュアーは PR をマージする前に必ず以下を実施してください。
本 PR の CI は手動作業完了を前提に通る設計です。

- [x] CodeQLによるJavaScript/TypeScript解析が不要なエラーを出さず正常に完了していることを確認する。

## マージ後の確認手順

- [ ] 次のプッシュ時に `.github/workflows/codeql.yml` の `javascript` ジョブが成功することを確認する。

## ロールバック手順

- CodeQLの解析に問題が生じた場合は、本PRをリバートするか、`.github/workflows/codeql.yml` の `language` マトリックスから `javascript` を削除してください。

## 参考情報

- 公式ドキュメント: https://docs.github.com/ja/code-security/code-scanning/introduction-to-code-scanning/about-code-scanning-with-codeql
- 漏洩防止の全体像: `docs/security/leak-prevention.md`
- 比較検討した他案: 新規のサードパーティ製アクションの導入も検討しましたが、公式ツールであり無料で利用できる CodeQL の言語設定を拡張する方針を選択しました。
