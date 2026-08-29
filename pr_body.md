## 背景

公開リポジトリにおいて、各種サービスの API キー等のシークレットを意図せずコミットしてしまうリスクが存在します。現状、一般的なソーシャルメディアやメッセージング基盤（Twitter/X、Facebook/Meta、LINE 等）の API トークンに対する専用の漏洩検知ルールが `.gitleaks.toml` に設定されておらず、水際でのブロックが手薄な状態でした。

## このPRで導入するもの

- ツール名: gitleaks v8.x (カスタムルールの追加)
- 導入箇所: `.gitleaks.toml` と `docs/security/leak-prevention.md`
- 期待される効果: Twitter/X (API Key/Bearer Token)、Facebook/Meta (Access Token/App Secret)、LINE (Channel Access Token/Channel Secret) 等の API トークンがコードにハードコードされた場合に、コミット前のローカル環境および CI 上で確実に検知して拒否します。

## 検知漏れリスクと補完策

- 検知できないケース: 上記ルールが想定する命名規則（変数名やヘッダ名の境界）から大きく外れた独自の変数名に代入されたトークンや、環境変数経由での間接的な漏洩。
- 補完策: 既存の GitHub Secret Scanning や TruffleHog (CI 監査) と組み合わせ、多重の防御層を維持します。また、コードレビューを通じて不自然な定数の埋め込みを抑止します。

## マージ前に必要な手動作業（チェックリスト）

レビュアーは PR をマージする前に必ず以下を実施してください。
本 PR の CI は手動作業完了を前提に通る設計です。

- [ ] GitHub repo settings → Code security → Push protection が有効になっていることを再確認
- [ ] developer 各自のローカル環境で `pre-commit install` が実施済みであることを周知し、追加されたフックルールが適用される状態にする

## マージ後の確認手順

- [ ] 次の push / PR で導入した workflow (pre-commit および gitleaks CI) が green になることを確認
- [ ] ローカル環境で Twitter/X や LINE のダミートークン（例: `twitter_api_key = "abc123..."`）をコミットしようとした際に gitleaks フックでブロックされることを確認

## ロールバック手順

本 PR の変更により過剰検知（False Positives）が多発するなど開発に支障をきたした場合は、本 PR を revert してください。

## 参考情報

- 公式ドキュメント: https://github.com/gitleaks/gitleaks
- 比較検討した他案: 新規のシークレットスキャンツール (例: detect-secrets) の導入も検討しましたが、すでに設定済みの gitleaks の機能を拡張する方がリポジトリ構成への影響が小さく、1ツールでの管理に寄与するため、今回は `.gitleaks.toml` へのルール追記を採用しました。
- 直近の関連 PR / Issue: なし
