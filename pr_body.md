## 背景

公開リポジトリにおいて、各種チャットボットのAPIトークン（Slack, Discordなど）や、AIエージェントのワークスペース設定、SQLダンプファイルなどの流出は重大なセキュリティリスクとなります。現状では一部の漏洩検知が行われていますが、更なる対象拡大と厳格化が必要です。

## このPRで導入するもの

- ツール名: gitleaks (既存ツールのカスタムルール拡張) および `.gitignore`, `.gitattributes`
- 導入箇所: `.gitleaks.toml`, `.gitignore`, `.gitattributes`, `.vscode/settings.json`, `docs/security/leak-prevention.md`
- 期待される効果: コミット前にローカルで Slack API トークン、Discord Bot トークン、GitLab PAT、Linear API キーの混入を検出して拒否します。また、AI エージェント（Gemini）のワークスペースや `.sql` ファイルが誤ってコミット・プッシュされるのを `.gitignore` と IDE 側から多層的に防ぎます。

## 検知漏れリスクと補完策

- 検知できないケース: パターンマッチに合致しない非定型の社内独自トークンや、`config.yaml` 以外の設定ファイルにハードコードされた値。
- 補完策: GitHub Actions に設定されている `gitleaks` と `TruffleHog` による CI 上での定期監査、および GitHub Secret Scanning によるプッシュ時の多重検知を併用します。

## マージ前に必要な手動作業（チェックリスト）

レビュアーは PR をマージする前に必ず以下を実施してください。
本 PR の CI は手動作業完了を前提に通る設計です。

- [ ] developer 各自のローカル環境にて `pre-commit install` が正しく実行されていることを周知する
- [ ] 各自の IDE (VS Code / Cursor など) で `.vscode/settings.json` の設定が正しく反映され、`.sql` ファイルが見えなくなっているか確認を促す

## マージ後の確認手順

- [ ] 次の push / PR で `.github/workflows/pre-commit.yml` などの CI が green になることを確認
- [ ] ローカルで `.sql` ファイルや `slack-api-token` に該当する文字列をコミットしようとした際、gitleaks が正しくフックとして動作しブロックすることを確認

## ロールバック手順

- 新規追加したカスタムルールにより過剰なブロックが発生した場合、`.gitleaks.toml` の追加分を `git revert` で取り消してください。
- ワークスペースや設定ファイルの非表示化に支障がある場合は、`.vscode/settings.json` の変更を戻してください。

## 参考情報

- 公式ドキュメント: https://github.com/gitleaks/gitleaks
- 直近の関連 PR: 過去の `chore(security): 🔒 gitleaks カスタムルールによる NPM/PyPI/Telegram トークンの漏洩防止強化` などの一連の検知拡張 PR
