## 背景

近年、多様なAI系サービス（DeepSeek, Perplexity AI, Together AI）や新しいAIエージェント（Continue, PearAI, Trae, Cody）の普及が進んでいますが、それに伴いAPIキーやローカルのコンテキストログが意図せず公開リポジトリへ漏洩するリスクが高まっています。既存の設定ファイルにはこれらの新興ツールに対する保護が含まれていなかったため、水際対策（コミット前検知および差分非表示）のギャップを埋める必要がありました。

## このPRで導入するもの

- ツール名: gitleaks v8.x, git (gitignore / gitattributes)
- 導入箇所: `.gitleaks.toml`, `.gitignore`, `.gitattributes`, `.vscode/settings.json`, `docs/security/leak-prevention.md`
- 期待される効果: コミット前にローカルで DeepSeek, Perplexity AI, Together AI の API キーを検出してブロック。さらに Continue, PearAI, Trae, Cody の作業跡がコミット・差分表示・リリースアーカイブに含まれることを防止。

## 検知漏れリスクと補完策

- 検知できないケース: 正規表現にマッチしないカスタム形式のトークンや、未知の新しいAIエージェントの作業ディレクトリ。
- 補完策: 既存の GitHub Secret Scanning および `trufflehog` によるCI検知と組み合わせて二重化し、定期的なルールの見直しを行う。

## マージ前に必要な手動作業（チェックリスト）

レビュアーは PR をマージする前に必ず以下を実施してください。
本 PR の CI は手動作業完了を前提に通る設計です。

- [x] GitHub repo settings → Code security → Push protection を有効化
- [ ] 各開発者のローカルで `pre-commit install` を実行し、追加されたルールを有効化する周知

## マージ後の確認手順

- [ ] 次の push / PR で導入した pre-commit workflow が green になることを確認
- [ ] ローカルで `.gitleaks.toml` の新規ルールがフックとして動作することを確認

## ロールバック手順

問題が出た場合は、対象コミットを `git revert` して `.gitleaks.toml`, `.gitignore`, `.gitattributes`, `.vscode/settings.json`, `docs/security/leak-prevention.md` を元に戻してください。

## 参考情報

- 公式ドキュメント: https://github.com/gitleaks/gitleaks
- 比較検討した他案: 新たな secret-scan ツールを導入する案も検討しましたが、すでに `gitleaks` と `pre-commit` が整備されているため、既存ルールの拡充と IDE 設定の追加を優先しました。
- 直近の関連 PR / Issue: なし
