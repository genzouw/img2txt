## 背景

公開リポジトリにおいて意図しないAPIキー等のシークレット漏洩を防ぐため、gitleaks を用いたコミット前検知を導入していますが、Resend などの一部SaaSトークンの検知条件に改善の余地がありました。

## このPRで導入するもの

- ツール名: gitleaks v8.x
- 導入箇所: `.gitleaks.toml` および `docs/security/leak-prevention.md`
- 期待される効果: コミット前にローカルで Resend API トークン（変数への代入なども含む）の混入を厳格に検出して拒否します。

## 検知漏れリスクと補完策

- 検知できないケース: プレースホルダー（例: `<REDACTED>`）以外の、一般的な文字列（ランダムな英数字）を使用している場合の未検知
- 補完策: GitHub の Secret Scanning および Push Protection を併用することで、ローカルフックをすり抜けた場合でもプッシュ時にブロックします。

## マージ前に必要な手動作業（チェックリスト）

レビュアーは PR をマージする前に必ず以下を実施してください。
本 PR の CI は手動作業完了を前提に通る設計です。

- [ ] GitHub リポジトリの Settings から Secret Scanning と Push Protection が有効になっていることを確認する
- [ ] developer 各自のローカルで `pre-commit install` を実行するよう周知する

## マージ後の確認手順

- [ ] 次の push / PR で導入した gitleaks ワークフローが green になることを確認
- [ ] ローカル環境で Resend API トークンを含むコミットが gitleaks フックによりブロックされることを確認

## ロールバック手順

万が一、既存のコードやアセットで誤検知が多発する場合は、`.gitleaks.toml` の `resend-api-key-strict` ルールをコメントアウトするか、本 PR のコミットを `git revert` してください。

## 参考情報

- 公式ドキュメント: https://github.com/gitleaks/gitleaks
- 比較検討した他案: 新たなツール（detect-secrets等）の導入も検討しましたが、重複を避けるため既存ツールの厳密化を優先しました。
- 直近の関連 PR / Issue: #82, #81 等
