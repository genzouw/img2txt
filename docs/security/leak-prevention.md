# 漏洩防止の全体像と運用ルール

本リポジトリでは、インフラ情報・認証情報・シークレットが意図せず公開リポジトリへコミット・push されることを防ぐために、多層的な防御層を設けています。

## 防御層の全体像

1. **コミット前検知（ローカル開発・AIエージェント環境）**:
   - ツール: `pre-commit` framework + `gitleaks` hook + `TruffleHog` hook + `pre-commit-hooks` (`detect-private-key` 等), および `.gitignore` と `.gitattributes` の厳格な除外・保護設定
   - 目的: 開発者がローカル環境で `git commit` を実行するタイミングで、gitleaks および TruffleHog を用いてシークレットの混入をチェックし、検出された場合はコミットをブロックします。また、`.gitignore` を用いて環境変数ファイルや秘密鍵が意図せずコミットされるのを防ぎ、`.gitattributes` により万が一コミットされた場合でも diff が露出しないように保護します。さらに `detect-private-key` などのフックによって水際対策を強化します。
   - 責任: 各開発者およびAIエージェントのローカル環境での水際対策。
   - カスタムルール: リポジトリルートの `.gitleaks.toml` にて、GCP Project ID（例: `<REDACTED>` 等）や Neon Postgres エンドポイント、Cloud Run エンドポイント（`*.run.app`）、Cloudflare Pages エンドポイント（`*.pages.dev`）、Workload Identity Federation プロバイダ文字列等のインフラ構成の過剰露出を検知・拒否する独自ルールを追加しています。また、追加のクラウド識別子（GCP Project Number, Service Account メールアドレス）、内部 IP アドレス、内部ドメイン (`*.corp.*`, `*.internal.*`)、PII（本物のメールアドレスや日本の電話番号）、特定の API キー（Resend, Stripe, Cloudflare, Notion, SendGrid, Supabase, Datadog, Twilio, Ngrok, Sentry等）、GCP Secret Manager のリソースパスについてもハードコードを禁止し、AI エージェントの作業跡（`.cursor/`, `.claude/` 等）や `.env` ファイル、`credentials.json` などの秘匿性が高いファイルそのものがコミットされることを防ぐため、また IaC の state ファイル（`cdktf.out/`, `*.tfstate` 等）や変数ファイル（`*.tfvars` 等）を除外するため、ファイルパスベースの検知ルールを導入しています（ただし `.cursor/rules/` のみ AI への指示用として許可）。今回、新たに SSH 秘密鍵（`id_rsa`, `id_ed25519` 等）、ローカル認証設定ファイル（`.npmrc`, `.netrc`, `.aws/credentials`, `.docker/config.json`, `.kube/config`, `application_default_credentials.json` 等）、キーストアと証明書（`*.p12`, `*.jks`, `*.keystore`, `*.kdbx`, `htpasswd`）、およびPostmanやInsomniaなどのAPIクライアントエクスポートファイルの混入を厳格に防ぐカスタムルールを追記しました。さらに、Postgres や Redis などの完全な接続文字列、SaaSバックエンドURL（Supabase, Firebase, Vercel等）やAWS内部エンドポイントのハードコードを禁止し、AI エージェントが残しがちなデバッグ用の一時値（`YOUR_API_KEY` や `dummy_secret`, `CHANGEME`, `CHANGE_ME`, `REPLACE_ME`, `XXX_SECRET_XXX` など）も検知・拒否対象としています。直近のアップデートにて、**HTTP/HTTPS URLへのBasic認証情報の埋め込み（`https://user:pass@domain`）**、**Stripe / Cloudflare API キー**、**各種AIプロバイダーAPIキー（OpenAI, Anthropic, Gemini, HuggingFace）**、**GitHub Personal Access Tokens (PAT)**、および開発ツールやボットの認証情報である **NPM トークン**、**PyPI トークン**、**Telegram ボットトークン**、**Slack API トークン**、**Discord Bot トークン**、**GitLab PAT**、**Linear API キー** を厳格に検知・拒否するルールを追加しました。ダミー値やマスクが必要な場合は、プレースホルダーとして `<REDACTED>` を使用してください。開発時はこれらの識別子を直接コードに書かず、環境変数やシークレット管理サービスを経由して参照するようにしてください。また、AI エージェントが作業中に生成しがちなローカルデータベースファイル（`.sqlite`, `.db` など）やログファイル、ダンプファイル（`.dump`, `.sql`）についても、`.gitignore` と `.gitattributes` によって追跡・diff 表示を厳格に除外し、`.gitleaks.toml` のパスベースルールによってコミット前検知でブロックするように設定しています。
   - `.gitignore` と `.gitattributes`: 環境変数ファイルや秘密鍵に加えて、新たにSSH秘密鍵、ローカル認証・クラウド設定ファイル（`.npmrc`, `.netrc`, `.aws/`, `.docker/`, `.kube/`, `.git-credentials` 等）、各種キーストアも追跡および diff 表示の対象から厳密に除外しています。これにより、万が一誤操作が発生した場合の多重防御を強化しています。
   - VS Code / Cursor 用の安全側プリセット: ローカルでの誤操作や AI エージェントへの意図せぬコンテキスト混入を防ぐため、`.vscode/settings.json` にて `.env` や上記の各種秘密鍵・認証設定ファイルがエクスプローラや検索結果から除外されるように設定しています。

1. **CI 検知（GitHub Actions環境）**:
   - ツール: `.github/workflows/pre-commit.yml` (GitHub Actions 上の pre-commit), `.github/workflows/gitleaks.yml` (GitHub Actions 上の gitleaks), `.github/workflows/trufflehog.yml` (TruffleHog) および `.github/workflows/codeql.yml` (CodeQL)
   - 目的: プルリクエスト作成時やブランチプッシュ時に、リモートリポジトリ側でシークレット混入がないかチェックします。特に `.github/workflows/pre-commit.yml` によって CI 環境でも `pre-commit run --all-files` を実行し、開発者がローカルで `--no-verify` を用いてコミットを強制した場合でも、確実に追加のフック（`detect-private-key` など）が適用される水際対策の確実なブロックを構成しています。TruffleHog は `--only-verified` を用い、実際に有効なクレデンシャルのみを厳格に検知・ブロックします（検証非対応のシークレットは検知されないため、gitleaks 等での多層防御が前提となります）。
   - CodeQL: さらに、JavaScript / TypeScript のコード（フロントエンドおよびインフラの CDKTF コード等）を対象に、CodeQL によるセマンティック解析を実行します。これにより、変数へのシークレットのハードコードや、外部へのデータフローなど、静的なパターンマッチング（gitleaks等）では検知が難しいロジックレベルの漏洩リスクを検知します。
   - Trivy (コンテナイメージスキャン): `ci.yml` にて、ビルドされた Docker イメージに対して Trivy を実行し、OS パッケージやライブラリの脆弱性だけでなく、イメージ内に混入したシークレット（`.env` ファイルの誤ったコピーやハードコードされた認証情報など）をスキャンしてブロックします。

1. **定期監査（スケジュール実行）**:
   - ツール: `.github/workflows/gitleaks.yml`, `.github/workflows/trufflehog.yml`, `.github/workflows/trivy.yml`, `.github/workflows/actionlint.yml`, `.github/workflows/osv-scanner.yml`, `.github/workflows/zizmor.yml` 内の `schedule` トリガー
   - 目的: 定期的にリポジトリ全体（過去の履歴も含む）を再スキャンし、セキュリティリスクを継続的に監視します。
     - Gitleaks / TruffleHog / Trivy: 過去に漏洩したリスクや、シークレット検知パターンのアップデートに伴う新たな検知、外部依存関係の新たな脆弱性や漏洩リスクの検知。
     - Dependabot: 日次スケジュール（Daily）とグループ化機能による、依存パッケージの定期的な棚卸しと更新。
     - actionlint / zizmor: GitHub Actions ワークフロー自体の定期 lint や、ワークフローのセキュリティ脆弱性パターンの定期監視（不適切なインジェクションや過剰な権限設定の検知）。
     - osv-scanner: ソースコード上の依存パッケージに潜む OSS 脆弱性（OSV データベースに基づく）の定期監査。

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

### 新規追加の対策（マージ前手動作業）

今回、新たに追加された `detect-aws-credentials` フックの実行や Cloudflare API Token などの漏洩防止をローカルで効果的に行うため、設定ファイルの変更は次回のコミット時に自動的に適用されます（すでに `pre-commit install` を実施済みの場合は、再インストールの必要はありません）。

また、GitHub リポジトリの **Settings → Code security and analysis** より、**Secret scanning** と **Push protection** が有効になっていることを確認してください。

### 今回の追加対策（マージ前手動作業）

今回、新たに以下の漏洩検知・保護ルールを追加しました。

1. **GitHub Fine-grained PAT の検知強化**: 従来の classic PAT に加え、`github_pat_` で始まる Fine-grained PAT を厳格に検知対象としました。
2. **GCP OAuth クライアントシークレットの検知**: `GOCSPX-` で始まる GCP OAuth のシークレットを検知対象としました。
3. **クレジットカード情報（PII）の検知**: 主要なクレジットカード番号形式のハードコードを検知対象としました。
4. **AWS リソース ARN（12桁のAWSアカウントID）の検知**: インフラ構成の過剰露出を防ぐため、12桁のアカウントIDを含む ARN のハードコードを検知対象としました。
5. **ローカル履歴・上書きファイルの保護**: シェル履歴ファイル（`.*_history`）およびローカルの Docker Compose 上書きファイル（`docker-compose.override.yml`, `docker-compose.override.yaml`）が誤ってコミットされないよう、`.gitignore`、`.gitattributes`（diff非表示）、および VS Code / Cursor の設定（エクスプローラ非表示）で厳格に保護しました。
6. **Dependabot による定期監査の強化**: 依存パッケージの脆弱性や情報漏洩リスクに迅速に対応するため、Dependabot の実行間隔を `weekly` から `daily` に変更し、同時に PR の乱立を防ぐために `groups` 機能を導入して定期監査の頻度と品質を向上させました。
7. **チャットボットトークンと各種APIキーの検知**: Slack API トークン、Discord Bot トークン、GitLab PAT、Linear API キー、Datadog アクセストークン、Twilio API キー、Ngrok 認証トークン、Sentry 認証トークンを検知対象としました。
8. **AI エージェントワークスペースの保護強化**: `.gemini/` ディレクトリ (ただし `config.yaml` を除く) の除外ルールを強化し、`.gitattributes` による diff 保護を明示しました。
9. **データベースダンプの保護拡張**: `*.sql` ファイルについてもコミット前ブロック、diff非表示、および VS Code 上の非表示設定を行いました。

レビュアーは本 PR をマージする前に、各開発者のローカル環境にて上記ファイルが正しく除外されていること、および `pre-commit install` が実施済みであることを周知してください。

### 今回（最新）の追加対策（マージ前手動作業）

今回、ファイル拡張子や名前に依存しない、ファイル内容（構造）ベースの漏洩検知ルールを `.gitleaks.toml` に追加し、コミット前検知の水際対策をさらに強化しました。

1. **GCP Service Account Key の内容検知**: ファイル名が `credentials.json` でない場合でも、JSON 内の構造（`"type": "service_account"` 等）からサービスアカウントキーを確実に検知・拒否します。
2. **汎用的な Private Key の内容検知**: `<REDACTED>` などのヘッダを持つ RSA/DSA/EC/OPENSSH/PGP の秘密鍵ファイルを、ファイル名や拡張子に関わらず検知・拒否します（`detect-private-key` フックとの多層防御）。
3. **汎用 URI 認証情報の検知**: `http(s)` に限らず、`ftp`, `sftp`, `amqp`, `amqps` などの汎用プロトコルスキームでハードコードされた Basic 認証情報（例: `ftp://user:pass@domain`）を検知・拒否します。

レビュアーは本 PR をマージする前に、各開発者のローカル環境にて上記ファイルが正しく除外されていること、および `pre-commit install` が実施済みであることを引き続き周知してください。
