# 漏洩防止の全体像と運用ルール

本リポジトリでは、インフラ情報・認証情報・シークレットが意図せず公開リポジトリへコミット・push されることを防ぐために、多層的な防御層を設けています。

## 防御層の全体像

1. **コミット前検知（ローカル開発・AIエージェント環境）**:
   - ツール: `pre-commit` framework + `gitleaks` hook + `pre-commit-hooks` (`detect-private-key` 等), および `.gitignore` の厳格な除外設定
   - 目的: 開発者がローカル環境で `git commit` を実行するタイミングで、gitleaks を用いてシークレットの混入をチェックし、検出された場合はコミットをブロックします。また、`.gitignore` を用いて、環境変数ファイルや秘密鍵が意図せずコミットされるのを防ぎ、`detect-private-key` などのフックによって水際対策を強化します。
   - 責任: 各開発者およびAIエージェントのローカル環境での水際対策。
   - カスタムルール: リポジトリルートの `.gitleaks.toml` にて、GCP Project ID（例: `<REDACTED>` 等）や Neon Postgres エンドポイント、Cloud Run エンドポイント（`*.run.app`）、Cloudflare Pages エンドポイント（`*.pages.dev`）、Workload Identity Federation プロバイダ文字列等のインフラ構成の過剰露出を検知・拒否する独自ルールを追加しています。また、追加のクラウド識別子（GCP Project Number, Service Account メールアドレス）、内部 IP アドレス、PII（本物のメールアドレス）、特定の API キー（Resend）についてもハードコードを禁止し、AI エージェントの作業跡（`.cursor/`, `.claude/` 等）や `.env` ファイル、`credentials.json` などの秘匿性が高いファイルそのものがコミットされることを防ぐため、また IaC の state ファイル（`cdktf.out/`, `*.tfstate` 等）を除外するため、ファイルパスベースの検知ルールを導入しています。開発時はこれらの識別子を直接コードに書かず、環境変数やシークレット管理サービスを経由して参照するようにしてください。
   - VS Code / Cursor 用の安全側プリセット: ローカルでの誤操作や AI エージェントへの意図せぬコンテキスト混入を防ぐため、`.vscode/settings.json` にて `.env` などのファイルがエクスプローラや検索結果から除外されるように設定しています。

1. **CI 検知（GitHub Actions環境）**:
   - ツール: `.github/workflows/gitleaks.yml` (GitHub Actions 上の gitleaks) および `.github/workflows/trufflehog.yml` (TruffleHog)
   - 目的: プルリクエスト作成時やブランチプッシュ時に、リモートリポジトリ側でシークレット混入がないかチェックします。TruffleHog は `--only-verified` を用い、実際に有効なクレデンシャルのみを厳格に検知・ブロックします（検証非対応のシークレットは検知されないため、gitleaks 等での多層防御が前提となります）。

1. **定期監査（スケジュール実行）**:
   - ツール: `.github/workflows/gitleaks.yml` 内の `schedule` トリガー
   - 目的: 定期的にリポジトリ全体（過去の履歴も含む）を再スキャンし、過去に漏洩したリスクや、シークレット検知パターンのアップデートに伴う新たな検知がないかを確認します。

## その他の推奨対策

GitHub リポジトリの設定から、**GitHub Secret Scanning** および **Push Protection** を有効にすることを強く推奨します。これにより、ローカルの検知をすり抜けたシークレットがプッシュされるのを防ぐ二重の防御となります。

## 運用ルール（コミット前検知のセットアップ）

リポジトリをクローン後、開発を開始する前に、必ずローカル環境で `pre-commit` のセットアップを行ってください。

### 手順

1. Python のパッケージマネージャ(`pip` など) または Homebrew を用いて `pre-commit` をインストールします。

```bash
# macOS の場合
brew install pre-commit

# Python環境の場合
pip install pre-commit

# または、システムのPython環境を汚染しない pipx の利用を推奨します
# （PEP 668 によりシステムPythonへの pip install がブロックされる環境にも対応できます）
pipx install pre-commit
```

1. リポジトリのルートディレクトリで以下のコマンドを実行し、Gitのフックをインストールします。

```bash
pre-commit install
```

1. 以降、`git commit` を実行する際に自動的に gitleaks によるスキャンが実行されます。もしシークレットの疑いがある文字列が検出された場合、コミットがブロックされます。

### 注意事項

- 本物のシークレット値や接続情報を誤ってコミットしてしまった場合は、GitHubにプッシュする前にローカルの履歴から修正・削除してください。
- どうしてもコミットをスキップしたい場合（誤検知など）は、セキュリティ担当に相談したうえで `SKIP=gitleaks git commit` を使用してください。これにより gitleaks のみをスキップし、他の pre-commit フック（静的解析・フォーマッタなど）の品質チェックは維持できます。
- 最終手段として `git commit --no-verify` も利用可能ですが、すべての pre-commit フックをスキップしてしまうため、原則として使用しないでください。
