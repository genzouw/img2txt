## 背景

公開リポジトリにおいて、国内で頻繁に利用される日本発およびローカルなSaaS・開発ツール（Chatwork、Backlog、Qiita、esa.io、Kintone等）のAPIトークンが意図せずコミットされるリスクが存在します。現状の gitleaks デフォルトルールではこれらを十分に検知できないというギャップがあります。

## このPRで導入するもの

- ツール名: gitleaks v8.x (カスタムルールの追加)
- 導入箇所: `.gitleaks.toml` と `docs/security/leak-prevention.md`
- 期待される効果: コミット前にローカルで Chatwork / Backlog / Qiita / esa.io / Kintone / LINE Notify の API トークン形式の値を検出して拒否

## 検知漏れリスクと補完策

- 検知できないケース: 上記ツールの環境変数名を全く伴わず、かつフォーマットが汎用的な文字列（Base64 など）である場合、誤検知を防ぐためのキーワード制約により検知から漏れる可能性があります。
- 補完策: GitHub Actions に設定済みの TruffleHog (検証可能シークレットの動的スキャン) や GitHub Secret Scanning を組み合わせて多重に保護します。

## マージ前に必要な手動作業（チェックリスト）

レビュアーは PR をマージする前に必ず以下を実施してください。
本 PR の CI は手動作業完了を前提に通る設計です。

- [ ] ローカル開発環境の各自において `pre-commit install` が実施済みであることをチームに周知する

## マージ後の確認手順

- [ ] 次の push / PR で導入した workflow (pre-commit, gitleaks CI) が green になることを確認
- [ ] ローカルでテスト用にダミートークン（例: `chatwork_api_token = "<ダミー値>"`）をコミットしようとし、gitleaks フックで弾かれることを確認

## ロールバック手順

誤検知により開発進行に著しい支障をきたした場合は、本 PR を Revert し、再度 `.gitleaks.toml` の正規表現を緩和する PR を作成してください。

## 参考情報

- 公式ドキュメント: https://github.com/gitleaks/gitleaks
- 比較検討した他案: 新たな汎用シークレット検知ツール（detect-secrets等）の導入も検討しましたが、既存の gitleaks ルール拡張の方が運用コストが低く、同様の機能を満たせるため不採用としました。
