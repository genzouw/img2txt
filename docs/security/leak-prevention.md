# 漏洩防止の全体像と運用ルール

本リポジトリでは、インフラ情報・認証情報・シークレットが意図せず公開リポジトリへコミット・push されることを防ぐために、多層的な防御層を設けています。

本ドキュメントは**現在の状態**を記述します。ルールを追加・変更した場合は、下記「検知対象一覧」の該当行を更新してください。追加の経緯・変更履歴は git log と Pull Request に残るため、本ドキュメントには転記しません。

## 防御層の全体像

1. **コミット前検知（ローカル開発・AIエージェント環境）**:
   - ツール: `pre-commit` framework + `gitleaks` hook + `TruffleHog` hook + `pre-commit-hooks` (`detect-private-key` 等), および `.gitignore` と `.gitattributes` の厳格な除外・保護設定
   - 目的: 開発者がローカル環境で `git commit` を実行するタイミングで、gitleaks および TruffleHog を用いてシークレットの混入をチェックし、検出された場合はコミットをブロックします。また、`.gitignore` を用いて環境変数ファイルや秘密鍵が意図せずコミットされるのを防ぎ、`.gitattributes` により万が一コミットされた場合でも diff が露出しないように保護します。さらに `detect-private-key` などのフックによって水際対策を強化します。
   - 責任: 各開発者およびAIエージェントのローカル環境での水際対策。
   - カスタムルール: リポジトリルートの `.gitleaks.toml` にて、gitleaks 標準ルールに加えて独自の検知ルールを定義しています。何を検知するかは本ドキュメントの「検知対象一覧」を参照してください。ダミー値やマスクが必要な場合は、プレースホルダーとして `<REDACTED>` を使用してください。開発時はこれらの識別子を直接コードに書かず、環境変数やシークレット管理サービスを経由して参照してください。
   - `.gitignore` と `.gitattributes`: 環境変数ファイルや秘密鍵に加えて、新たにSSH秘密鍵、ローカル認証・クラウド設定ファイル（`.npmrc`, `.netrc`, `.aws/`, `.docker/`, `.kube/`, `.git-credentials` 等）、各種キーストアも追跡および diff 表示の対象から厳密に除外しています。これにより、万が一誤操作が発生した場合の多重防御を強化しています。
   - VS Code / Cursor 用の安全側プリセット: ローカルでの誤操作や AI エージェントへの意図せぬコンテキスト混入を防ぐため、`.vscode/settings.json` にて `.env` や上記の各種秘密鍵・認証設定ファイルがエクスプローラや検索結果から除外されるように設定しています。

1. **CI 検知（GitHub Actions環境）**:
   - ツール: `.github/workflows/pre-commit.yml` (GitHub Actions 上の pre-commit), `.github/workflows/gitleaks.yml` (GitHub Actions 上の gitleaks), `.github/workflows/trufflehog.yml` (TruffleHog) および `.github/workflows/codeql.yml` (CodeQL)
   - 目的: プルリクエスト作成時やブランチプッシュ時に、リモートリポジトリ側でシークレット混入がないかチェックします。特に `.github/workflows/pre-commit.yml` によって CI 環境でも `pre-commit run --all-files` を実行し、開発者がローカルで `--no-verify` を用いてコミットを強制した場合でも、確実に追加のフック（`detect-private-key` など）が適用される水際対策の確実なブロックを構成しています。TruffleHog は `--only-verified` を用い、実際に有効なクレデンシャルのみを厳格に検知・ブロックします（検証非対応のシークレットは検知されないため、gitleaks 等での多層防御が前提となります）。
   - CodeQL: さらに、JavaScript / TypeScript のコード（フロントエンドおよびインフラの CDKTF コード等）を対象に、CodeQL によるセマンティック解析を実行します。これにより、変数へのシークレットのハードコードや、外部へのデータフローなど、静的なパターンマッチング（gitleaks等）では検知が難しいロジックレベルの漏洩リスクを検知します。
   - Trivy (コンテナイメージスキャン): `ci.yml` にて、ビルドされた Docker イメージに対して Trivy を実行し、OS パッケージやライブラリの脆弱性だけでなく、イメージ内に混入したシークレット（`.env` ファイルの誤ったコピーやハードコードされた認証情報など）をスキャンしてブロックします。

1. **定期監査（スケジュール実行）**:
   - ツール: `.github/workflows/pre-commit.yml`, `.github/workflows/gitleaks.yml`, `.github/workflows/trufflehog.yml`, `.github/workflows/trivy.yml`, `.github/workflows/actionlint.yml`, `.github/workflows/osv-scanner.yml`, `.github/workflows/zizmor.yml` 内の `schedule` トリガー
   - 目的: 定期的にリポジトリ全体（過去の履歴も含む）を再スキャンし、セキュリティリスクを継続的に監視します。
     - pre-commit / Gitleaks / TruffleHog / Trivy: 過去に漏洩したリスクや、シークレット検知パターンのアップデートに伴う新たな検知、外部依存関係の新たな脆弱性や漏洩リスクの検知。特に pre-commit ワークフローのスケジュール実行により、リポジトリの最新状態に対する各種フックによる漏洩チェックが定期的に自動実行されます。
     - Dependabot: 日次スケジュール（Daily）とグループ化機能による、依存パッケージの定期的な棚卸しと更新。
     - actionlint / zizmor: GitHub Actions ワークフロー自体の定期 lint や、ワークフローのセキュリティ脆弱性パターンの定期監視（不適切なインジェクションや過剰な権限設定の検知）。
     - osv-scanner: ソースコード上の依存パッケージに潜む OSS 脆弱性（OSV データベースに基づく）の定期監査。

## 検知対象一覧

`.gitleaks.toml` に定義しているカスタムルールの一覧です（152 件）。**正となる定義は `.gitleaks.toml` 側**で、本表はその索引です。ルールを追加したら該当カテゴリに1行足してください。

`[extend] useDefault = true` により、gitleaks 標準ルールも併用されます。本表はそこに上乗せしている独自ルールのみを扱います。

### インフラ・クラウド識別子 / 接続情報（28 件）

| ルール ID | 検知対象 |
| :--- | :--- |
| `aws-internal-endpoint` | Hardcoded AWS internal endpoints (e.g. \*.rds.amazonaws.com, \*.cache.amazonaws.com) are not allowed |
| `aws-resource-arn` | Hardcoded AWS Resource ARNs containing 12-digit account IDs are not allowed |
| `cloud-run-url` | Hardcoded Cloud Run URLs (\*.run.app) are not allowed |
| `cloudflare-pages-url` | Hardcoded Cloudflare Pages URLs (\*.pages.dev) are not allowed |
| `digitalocean-pat` | DigitalOcean Personal Access Tokens are not allowed |
| `gcp-app-engine-url` | Hardcoded GCP App Engine URLs (\*.appspot.com) are not allowed |
| `gcp-cloud-functions-url` | Hardcoded GCP Cloud Functions URLs (\*.cloudfunctions.net) are not allowed |
| `gcp-cloud-sql-unix-socket` | Hardcoded Cloud SQL Unix Socket paths (/cloudsql/...) are not allowed |
| `gcp-oauth-client-secret` | GCP OAuth Client Secrets are not allowed |
| `gcp-project-id` | Hardcoded GCP Project ID (toique-app-\*) is not allowed |
| `gcp-project-number` | Hardcoded GCP Project Numbers (12 digits) are not allowed |
| `gcp-secret-manager` | Hardcoded GCP Secret Manager resource paths are not allowed |
| `gcp-service-account` | GCP Service Account emails are not allowed |
| `gcp-service-account-key-json` | GCP Service Account Key JSON files are not allowed, regardless of filename |
| `gcp-storage-url` | Hardcoded GCS Bucket URLs (storage.googleapis.com/\*) are not allowed |
| `gcp-workload-identity` | Hardcoded GCP Workload Identity Federation provider strings are not allowed |
| `generic-private-key-content` | Private keys are not allowed (detects content, overriding filename obfuscation) |
| `generic-uri-credentials` | Hardcoded credentials in generic URIs (ftp, amqp, etc.) are not allowed |
| `hardcoded-connection-string` | Hardcoded database or cache connection strings (postgres://, redis://, etc.) are not allowed |
| `hashicorp-vault-token` | HashiCorp Vault Tokens are not allowed |
| `http-basic-auth` | HTTP/HTTPS URLs with embedded basic auth credentials are not allowed |
| `internal-domain` | Internal domain names (\*.internal, \*.corp, \*.local) are not allowed to prevent infrastructure exposure |
| `internal-ip` | Internal IPv4 addresses (10.x.x.x, 172.16-31.x.x, 192.168.x.x) are not allowed |
| `jwt-token` | Generic JWT Tokens are not allowed |
| `neon-postgres-endpoint` | Hardcoded Neon Postgres endpoints (including connection strings) are strictly not allowed |
| `saas-backend-url` | Hardcoded SaaS backend URLs (Supabase, Firebase, Vercel, Netlify) are not allowed |
| `tailscale-auth-key` | Tailscale Auth Keys are not allowed |
| `terraform-cloud-api-token` | Terraform Cloud API Tokens are not allowed |

### PII（個人情報）（3 件）

| ルール ID | 検知対象 |
| :--- | :--- |
| `pii-credit-card` | Credit Card Numbers (PII) are not allowed |
| `pii-email` | Real email addresses (PII) are not allowed. Use @example.com/org for dummies. |
| `pii-japanese-phone` | Japanese phone numbers (PII) are not allowed |

### AI / LLM プロバイダのキー（15 件）

| ルール ID | 検知対象 |
| :--- | :--- |
| `anthropic-api-key` | Anthropic API Keys are not allowed (AI Agent protection) |
| `cohere-api-key` | Cohere API Keys are not allowed (AI Agent protection) |
| `deepseek-api-key` | DeepSeek API Keys are not allowed (AI Agent protection) |
| `gemini-api-key` | Gemini API Keys are not allowed (AI Agent protection) |
| `groq-api-key` | Groq API Keys are not allowed (AI Agent protection) |
| `huggingface-token` | HuggingFace Access Tokens are not allowed (AI Agent protection) |
| `langsmith-api-key` | LangSmith API Keys are not allowed (AI Agent protection) |
| `mistral-api-key` | Mistral API Keys are not allowed (AI Agent protection) |
| `openai-api-key-strict` | OpenAI API Keys are not allowed (AI Agent protection) |
| `perplexity-api-key` | Perplexity AI API Keys are not allowed (AI Agent protection) |
| `pinecone-api-key` | Pinecone API Keys are not allowed (AI Agent protection) |
| `replicate-api-token` | Replicate API Tokens are not allowed (AI Agent protection) |
| `tavily-api-key` | Tavily API Keys are not allowed (AI Agent protection) |
| `together-api-key` | Together AI API Keys are not allowed (AI Agent protection) |
| `wandb-api-key` | Weights & Biases (WandB) API Keys are not allowed (AI Agent protection) |

### SaaS・開発ツールの API キー / トークン（86 件）

| ルール ID | 検知対象 |
| :--- | :--- |
| `algolia-api-key` | Algolia API Keys are not allowed |
| `amplitude-api-key` | Amplitude API Keys are not allowed |
| `asana-personal-access-token` | Asana Personal Access Tokens are not allowed |
| `atlassian-api-token` | Atlassian API Tokens (Jira, Confluence) are not allowed |
| `auth0-management-api-token` | Auth0 Management API Tokens are not allowed |
| `bitbucket-client-id` | Discovered a potential Bitbucket Client ID, risking unauthorized repository access and potential codebase exposure. |
| `bitbucket-client-secret` | Discovered a potential Bitbucket Client Secret, posing a risk of compromised code repositories and unauthorized access. |
| `box-developer-token` | Box Developer and API Tokens are not allowed |
| `braintree-access-token` | Braintree Access Tokens are not allowed |
| `braze-api-key` | Braze API and REST Keys are not allowed |
| `buildkite-api-token` | Buildkite API Access Tokens are not allowed |
| `circleci-api-token` | CircleCI API Tokens are not allowed |
| `clerk-secret-key` | Clerk Secret Keys are not allowed |
| `cloudflare-api-key` | Cloudflare API Keys and Tokens are strictly not allowed |
| `cloudinary-api-url` | Cloudinary API URLs (including key/secret) are not allowed |
| `codecov-api-token` | Codecov API Tokens are not allowed |
| `contentful-delivery-api-token` | Discovered a Contentful delivery API token, posing a risk to content management systems and data integrity. |
| `customerio-api-key` | Customer.io API, App, and Tracking Keys are not allowed |
| `databricks-api-token` | Databricks Personal Access Tokens are not allowed |
| `datadog-access-token` | Datadog Access Tokens and API Keys are not allowed |
| `discord-bot-token` | Discord Bot tokens are not allowed |
| `discord-webhook` | Discord Webhook URLs are not allowed |
| `docker-hub-pat` | Docker Hub Personal Access Tokens are not allowed |
| `doppler-api-token` | Doppler API Tokens are not allowed |
| `dropbox-api-token` | Dropbox API and Access Tokens are not allowed |
| `facebook-access-token` | Facebook/Meta Access Tokens are not allowed |
| `fastly-api-token-custom` | Fastly Personal Access Tokens and API Tokens are not allowed |
| `figma-pat` | Figma Personal Access Tokens are not allowed |
| `fly-io-api-token` | Fly.io API Tokens are not allowed |
| `github-pat-strict` | GitHub Personal Access Tokens are strictly not allowed |
| `github-runner-token` | GitHub Actions Runner Tokens are not allowed |
| `gitlab-pat` | GitLab Personal Access Tokens are not allowed |
| `grafana-api-token` | Grafana API Tokens are not allowed |
| `heroku-api-key` | Heroku API Keys are not allowed |
| `hubspot-api-token` | HubSpot API Tokens are not allowed |
| `klaviyo-api-key` | Klaviyo Private API Keys are not allowed |
| `launchdarkly-api-key` | LaunchDarkly API Keys and Access Tokens are not allowed |
| `line-channel-access-token` | LINE Channel Access Tokens are not allowed |
| `linear-api-key` | Linear API keys are not allowed |
| `mailchimp-api-key` | Mailchimp API Keys are not allowed |
| `mailgun-api-key` | Mailgun API Keys are not allowed |
| `mapbox-api-token-custom` | Mapbox API tokens are not allowed |
| `mixpanel-project-token` | Mixpanel Project Tokens and API Secrets are not allowed |
| `msteams-webhook` | Microsoft Teams Webhook URLs are not allowed |
| `newrelic-api-key` | New Relic API Keys and License Keys are not allowed |
| `ngrok-auth-token` | Ngrok Auth Tokens are not allowed |
| `notion-api-key` | Notion API keys are not allowed |
| `npm-access-token` | NPM access tokens are not allowed |
| `okta-api-token` | Okta API Tokens are not allowed |
| `onesignal-api-key` | OneSignal API, REST, and App Keys are not allowed |
| `pagerduty-api-key` | PagerDuty API Keys are not allowed |
| `paypal-client-id-secret` | PayPal Client IDs and Secrets are not allowed |
| `planetscale-password` | PlanetScale passwords or tokens are not allowed |
| `posthog-api-key` | PostHog API Keys are not allowed |
| `postman-api-key` | Postman API Keys are not allowed |
| `pulumi-access-token` | Pulumi Access Tokens are not allowed |
| `pusher-api-key` | Pusher API Keys, App IDs, and Secrets are not allowed |
| `pypi-api-token` | PyPI API tokens are not allowed |
| `render-api-key` | Render API Keys are not allowed |
| `resend-api-key` | Resend API keys are strictly not allowed |
| `resend-api-key-strict` | Resend API keys (strict detection) are strictly not allowed |
| `segment-api-key` | Segment Write Keys / Public API Keys are not allowed |
| `sendgrid-api-key` | SendGrid API keys are not allowed |
| `sendinblue-api-token` | Brevo (formerly Sendinblue) API Keys are not allowed |
| `sentry-auth-token` | Sentry Auth Tokens are not allowed |
| `shopify-api-token` | Shopify API Tokens are not allowed |
| `slack-api-token` | Slack API tokens (xoxb, xoxp, xapp) are not allowed |
| `slack-webhook` | Slack Webhook URLs are not allowed |
| `snowflake-account-password` | Snowflake credentials or tokens are not allowed |
| `snyk-api-token` | Uncovered a Snyk API token, potentially compromising software vulnerability scanning and code security. |
| `sonar-api-token` | Uncovered a Sonar API token, potentially compromising software vulnerability scanning and code security. |
| `square-access-token-custom` | Square Access Tokens are not allowed |
| `stripe-api-key` | Stripe API keys (Secret and Restricted) are strictly not allowed |
| `stripe-webhook-secret` | Stripe Webhook Secrets are strictly not allowed |
| `supabase-api-key` | Supabase API keys (JWT tokens) are not allowed |
| `telegram-bot-token` | Telegram Bot tokens are not allowed |
| `travisci-access-token` | Identified a Travis CI Access Token, potentially compromising continuous integration services and codebase security. |
| `trello-api-key` | Trello API Keys are not allowed |
| `trello-api-token` | Trello API Tokens are not allowed |
| `twilio-api-key` | Twilio API Keys are not allowed |
| `twitter-api-key` | Twitter/X API Keys are not allowed |
| `typeform-api-token-custom` | Typeform API tokens (Personal Access Tokens) are not allowed |
| `upstash-api-token` | Upstash API tokens are not allowed |
| `vercel-access-token` | Vercel Access Tokens are not allowed |
| `zendesk-api-token` | Zendesk API Tokens are not allowed |
| `zoom-api-token` | Zoom API Keys, Secrets, and OAuth Tokens are not allowed |

### 秘匿ファイルそのもののコミット（パスベース検知）（18 件）

| ルール ID | 検知対象 |
| :--- | :--- |
| `forbidden-file-ai-agent` | Detects AI agent workspace directories |
| `forbidden-file-ai-agent-logs` | Detects AI agent chat logs and MCP settings files (no allowlist: must be blocked even under .cursor/rules/) |
| `forbidden-file-api-client` | Detects API client export files (Postman, Insomnia) which should not be committed |
| `forbidden-file-api-client-modern` | Detects modern API client workspaces (Thunder Client, Bruno) which should not be committed |
| `forbidden-file-cloud-config` | Detects cloud and tool auth configs which should not be committed |
| `forbidden-file-credentials` | Detects credentials files |
| `forbidden-file-database-dump` | Detects database dumps, local SQLite DBs, and log files which should not be committed as they often contain PII or secrets |
| `forbidden-file-env` | Detects .env files which should not be committed |
| `forbidden-file-env-local` | Detects local environment variable files (e.g., .env.local, .env.development.local, .env.test.local) specifically to prevent AI agent sample leaks |
| `forbidden-file-ide-history` | Detects IDE workspaces and local history directories which should not be committed |
| `forbidden-file-keystore` | Detects keystores and certificates which should not be committed |
| `forbidden-file-local-auth` | Detects local authentication or configuration files (.npmrc, .netrc, .aws/credentials, etc.) |
| `forbidden-file-local-overrides` | Detects local Docker overrides which should not be committed |
| `forbidden-file-macos-keychain` | Detects macOS Keychain files which should not be committed |
| `forbidden-file-shell-history` | Detects shell history files which should not be committed |
| `forbidden-file-ssh-keys` | Detects SSH private keys which should not be committed |
| `forbidden-file-tfstate` | Detects IaC state, variable files, and build artifact directories which should not be committed |
| `forbidden-file-vpn-network` | Detects VPN configurations and network captures which should not be committed |

### AI エージェントが残しがちなダミー値（2 件）

| ルール ID | 検知対象 |
| :--- | :--- |
| `ai-debug-placeholder` | AI agent debug placeholders (YOUR_API_KEY, dummy_secret, CHANGEME, REPLACE_ME, etc.) are not allowed. Use <REDACTED> instead. |
| `ai-debug-placeholder-extended` | Extended AI agent debug placeholders (CHANGE_ME, XXX_SECRET_XXX, etc.) are not allowed |

## 運用ルール（コミット前検知のセットアップ）

リポジトリをクローン後、開発を開始する前に、必ずローカル環境で `pre-commit` のセットアップを行ってください。

**レビュアーは、`.gitleaks.toml` に検知ルールを追加する Pull Request をマージする前に、各開発者のローカル環境で `pre-commit install` が実施済みであることを周知してください。** ルール追加ごとに個別の告知を書き足す必要はありません。

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
- ダミー値やマスクが必要な場合は、プレースホルダーとして `<REDACTED>` を使用してください。
- どうしてもコミットをスキップしたい場合（誤検知など）は、セキュリティ担当に相談したうえで `SKIP=gitleaks git commit` を使用してください。これにより gitleaks のみをスキップし、他の pre-commit フック（静的解析・フォーマッタなど）の品質チェックは維持できます。
- 最終手段として `git commit --no-verify` も利用可能ですが、すべての pre-commit フックをスキップしてしまうため、原則として使用しないでください。

## その他の推奨対策

GitHub リポジトリの設定から、**GitHub Secret Scanning** および **Push Protection** を有効にすることを強く推奨します。これにより、ローカルの検知をすり抜けたシークレットがプッシュされるのを防ぐ二重の防御となります。
