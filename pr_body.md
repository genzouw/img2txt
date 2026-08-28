## 背景

本プロジェクトは公開リポジトリであり、ソースコードやドキュメントが常に一般に公開されています。開発の過程で、利用頻度の高いマーケティングおよびメディア管理系の SaaS (Mailchimp, Typeform, Cloudinary, Brevo) の API キーやトークンが誤ってコミット・プッシュされてしまうと、不正利用やデータ漏洩といった重大なインシデントに直結するリスクがあります。

## このPRで導入するもの

- ツール名: gitleaks v8.x (設定の追加・厳格化)
- 導入箇所: `.gitleaks.toml` と `docs/security/leak-prevention.md`
- 期待される効果: ローカルの `pre-commit` フックおよび CI 上での gitleaks 実行により、Mailchimp, Typeform, Cloudinary, Brevo (旧 Sendinblue) の API トークンや認証 URL を確実かつ自動的に検出し、コミットを拒否することで漏洩を未然に防止します。

## 検知漏れリスクと補完策

- 検知できないケース: 上記パターンの正規表現（長さやプレフィックス）に合致しない古い形式のトークンや、環境変数名を経由した動的な値の混入。
- 補完策: 既に設定済みの GitHub Secret Scanning および Push Protection による多層防御を活用し、サーバーサイドでも漏洩を防ぎます。

## マージ前に必要な手動作業（チェックリスト）

レビュアーは PR をマージする前に必ず以下を実施してください。
本 PR の CI は手動作業完了を前提に通る設計です。

- [ ] GitHub リポジトリ設定 (Settings → Code security and analysis) で Secret scanning と Push protection が有効であることを確認
- [ ] 各開発者のローカル環境にて `pre-commit install` が実施済みであることを周知（既にインストール済みの場合は再実施不要で自動適用されます）

## マージ後の確認手順

- [ ] 次の push / PR で `gitleaks` を含む `pre-commit` ワークフローが正常に green になることを確認
- [ ] ローカル環境で、ダミーの Mailchimp または Typeform のトークンを含んだファイルをコミットしようとした際、`pre-commit` によってブロックされることを確認

## ロールバック手順

万が一誤検知（False Positive）が多発し開発に重大な支障が出た場合は、本 PR で `.gitleaks.toml` に追加した `mailchimp-api-key`, `typeform-api-token-custom`, `cloudinary-api-url`, `brevo-sendinblue-api-key` の各 `[[rules]]` ブロックを削除またはコメントアウトし、PR を作成・マージしてください。

## 参考情報

- 公式ドキュメント: https://github.com/gitleaks/gitleaks
- 比較検討した他案: 新規に `detect-secrets` 等の追加も検討しましたが、既に本リポジトリで運用・定着している `gitleaks` のカスタムルールを拡張する方が、重複設定や CI の遅延を避けられるため最適と判断しました。
