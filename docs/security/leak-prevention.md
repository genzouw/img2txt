# 漏洩防止の全体像と運用ルール

本リポジトリでは、インフラ情報・認証情報・シークレットが意図せず公開リポジトリへコミット・push されることを防ぐために、多層的な防御層を設けています。

## 防御層の全体像

1. **コミット前検知（ローカル開発・AIエージェント環境）**:
   - ツール: `pre-commit` framework + `gitleaks` hook + `TruffleHog` hook + `pre-commit-hooks` (`detect-private-key` 等), および `.gitignore` と `.gitattributes` の厳格な除外・保護設定
   - 目的: 開発者がローカル環境で `git commit` を実行するタイミングで、gitleaks および TruffleHog を用いてシークレットの混入をチェックし、検出された場合はコミットをブロックします。また、`.gitignore` を用いて環境変数ファイルや秘密鍵が意図せずコミットされるのを防ぎ、`.gitattributes` により万が一コミットされた場合でも diff が露出しないように保護します。さらに `detect-private-key` などのフックによって水際対策を強化します。
   - 責任: 各開発者およびAIエージェントのローカル環境での水際対策。
   - カスタムルール: リポジトリルートの `.gitleaks.toml` にて、GCP Project ID（例: `<REDACTED>` 等）や Neon Postgres エンドポイント、Cloud Run エンドポイント（`*.run.app`）、Cloudflare Pages エンドポイント（`*.pages.dev`）、Workload Identity Federation プロバイダ文字列等のインフラ構成の過剰露出を検知・拒否する独自ルールを追加しています。また、追加のクラウド識別子（GCP Project Number, Service Account メールアドレス）、内部 IP アドレス、内部ドメイン (`*.corp.*`, `*.internal.*`)、PII（本物のメールアドレスや日本の電話番号）、特定の API キー（Resend, Stripe, Cloudflare, Notion, SendGrid, Supabase, Mapbox, Datadog, Twilio, Ngrok, Sentry, Terraform Cloud, HashiCorp Vault, Fly.io, Render, Tailscale, Clerk, CircleCI, New Relic, PagerDuty, Codecov等）、GCP Secret Manager のリソースパスについてもハードコードを禁止し、AI エージェントの作業跡（`.cursor/`, `.claude/`, `.windsurf/`, `.cline/`, `.roo/`, `.codeium/` 等）や `.env` ファイル、`credentials.json` などの秘匿性が高いファイルそのものがコミットされることを防ぐため、また IaC の state ファイル（`cdktf.out/`, `*.tfstate` 等）や変数ファイル（`*.tfvars` 等）を除外するため、ファイルパスベースの検知ルールを導入しています（ただし `.cursor/rules/` のみ AI への指示用として許可）。今回、新たに SSH 秘密鍵（`id_rsa`, `id_ed25519` 等）、ローカル認証設定ファイル（`.npmrc`, `.netrc`, `.aws/credentials`, `.docker/config.json`, `.kube/config`, `application_default_credentials.json` 等）、キーストアと証明書（`*.p12`, `*.jks`, `*.keystore`, `*.kdbx`, `htpasswd`）、および IDEのローカル履歴・設定（`.idea/`, `.history/`, `.vscode/sftp.json` 等）、近代的なAPIクライアント（Thunder Client, Bruno）ならびにPostmanやInsomniaなどのエクスポートファイルの混入を厳格に防ぐカスタムルールを追記しました。さらに、Postgres や Redis などの完全な接続文字列、SaaSバックエンドURL（Supabase, Firebase, Vercel等）やAWS内部エンドポイントのハードコードを禁止し、AI エージェントが残しがちなデバッグ用の一時値（`YOUR_API_KEY` や `dummy_secret`, `CHANGEME`, `CHANGE_ME`, `REPLACE_ME`, `XXX_SECRET_XXX` など）も検知・拒否対象としています。直近のアップデートにて、**HTTP/HTTPS URLへのBasic認証情報の埋め込み（`https://user:pass@domain`）**、**Stripe / Cloudflare API キー**、**各種AIプロバイダーAPIキー（OpenAI, Anthropic, Gemini, HuggingFace）**、**GitHub Personal Access Tokens (PAT)**、および開発ツールやボットの認証情報である **NPM トークン**、**PyPI トークン**、**Telegram ボットトークン**、**Slack API トークン**、**Discord Bot トークン**、**GitLab PAT**、**Linear API キー** を厳格に検知・拒否するルールを追加しました。ダミー値やマスクが必要な場合は、プレースホルダーとして `<REDACTED>` を使用してください。開発時はこれらの識別子を直接コードに書かず、環境変数やシークレット管理サービスを経由して参照するようにしてください。また、AI エージェントが作業中に生成しがちなローカルデータベースファイル（`.sqlite`, `.db` など）やログファイル、ダンプファイル（`.dump`, `.sql`）についても、`.gitignore` と `.gitattributes` によって追跡・diff 表示を厳格に除外し、`.gitleaks.toml` のパスベースルールによってコミット前検知でブロックするように設定しています。
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

## その他の推奨対策

GitHub リポジトリの設定から、**GitHub Secret Scanning** および **Push Protection** を有効にすることを強く推奨します。これにより、ローカルの検知をすり抜けたシークレットがプッシュされるのを防ぐ二重の防御となります。

## 運用ルール（コミット前検知のセットアップ）

リポジトリをクローン後、開発を開始する前に、必ずローカル環境で `pre-commit` のセットアップを行ってください。

### 前回の追加対策（CI/CD・DevOps関連強化・マージ前手動作業）

今回、DevOpsやコンテンツ管理で広く利用されている Snyk, SonarQube, TravisCI, Contentful, および Bitbucket のAPIキーやトークンが意図せず公開リポジトリへコミット・プッシュされることを防ぐため、`.gitleaks.toml` へカスタムルールを新たに追加し、コミット前検知の水際対策をさらに強化しました。

1. **Snyk API Token の検知**: ソフトウェアの脆弱性スキャンツールである Snyk のAPIトークンを検知・拒否するルールを追加しました。
2. **Sonar API Token の検知**: SonarQube / SonarCloud の API トークンを検知対象としました。
3. **Travis CI Access Token の検知**: Travis CI の Access Token を検知対象としました。
4. **Contentful Delivery API Token の検知**: Contentful の API トークンを検知対象としました。
5. **Bitbucket Client ID / Secret の検知**: Bitbucket の Client ID および Client Secret を検知対象としました。

レビュアーは本 PR をマージする前に、各開発者のローカル環境にて `pre-commit install` が実施済みであることを周知してください。

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

### 前回の追加対策（CI/CD・モニタリングツール強化・マージ前手動作業）

今回、CI/CDツールおよびモニタリングツール（CircleCI, New Relic, PagerDuty, Codecov）の API トークンやキーが意図せず公開リポジトリへコミット・プッシュされることを防ぐため、`.gitleaks.toml` へカスタムルールを新たに追加し、コミット前検知の水際対策をさらに強化しました。

1. **CircleCI API Token の検知**: CircleCI の API トークンやパーソナルアクセストークンを検知・拒否するルールを追加しました。
2. **New Relic API Key の検知**: New Relic の API キーおよびライセンスキーを検知対象としました。
3. **PagerDuty API Key の検知**: PagerDuty の API キー（REST APIキーなど）を検知対象としました。
4. **Codecov API Token の検知**: Codecov の API トークンを検知対象としました。

レビュアーは本 PR をマージする前に、各開発者のローカル環境にて `pre-commit install` が実施済みであることを周知してください。

### 前回の追加対策（Resend強化・マージ前手動作業）

前回、Resend の API トークンが意図せず公開リポジトリへコミット・プッシュされることを防ぐため、`.gitleaks.toml` のカスタムルールを強化し、コミット前検知の水際対策をさらに厳格化しました。

1. **Resend API トークンの検知強化**: 既存の Resend API トークン検知ルールを見直し、変数への代入なども含めたより厳格なパターンマッチングを追加することで漏洩リスクを低減しました。

レビュアーは本 PR をマージする前に、各開発者のローカル環境にて `pre-commit install` が実施済みであることを周知してください。

### 前回の追加対策（Mapbox・地図SaaS強化・マージ前手動作業）

前回、Mapbox などの地図・位置情報サービスの API トークンが意図せず公開リポジトリへコミット・プッシュされることを防ぐため、`.gitleaks.toml` へカスタムルールを新たに追加し、コミット前検知の水際対策をさらに強化しました。

1. **Mapbox API トークンの検知**: Mapbox のパブリックトークン (`pk.eyJ...`) およびシークレットトークン (`sk.eyJ...`) を検知・拒否するルールを追加しました。

レビュアーは各開発者のローカル環境にて `pre-commit install` が実施済みであることを引き続き周知してください。

### 過去の追加対策（AIエージェント・MS Teams強化・マージ前手動作業）

今回、新興のAI系サービス（Groq, Mistral, Replicate, Cohere, Tavily, Pinecone）の API キーや、Microsoft Teams の Webhook URL が意図せず公開リポジトリへコミット・プッシュされることを防ぐため、`.gitleaks.toml` へカスタムルールを新たに追加し、コミット前検知の水際対策をさらに強化しました。

1. **AI系 API Key の検知**: Groq (`gsk_...`), Mistral, Replicate (`r8_...`), Cohere, Tavily (`tvly-...`), Pinecone の各種APIキー・トークンを検知・拒否するルールを追加しました。
2. **Microsoft Teams Webhook の検知**: Microsoft Teams の Incoming Webhook URL を厳密な形式で検知対象としました。

レビュアーは本 PR をマージする前に、各開発者のローカル環境にて `pre-commit install` が実施済みであることを周知してください。

### 前回の追加対策（プロジェクト管理SaaS強化・マージ前手動作業）

今回、Atlassian (Jira, Confluence)、Asana、Trello などのプロジェクト管理・コラボレーションツールの API キーやトークンが意図せず公開リポジトリへコミット・プッシュされることを防ぐため、`.gitleaks.toml` へカスタムルールを新たに追加し、コミット前検知の水際対策をさらに強化しました。

1. **Atlassian API Token の検知**: Jira や Confluence で使用される Atlassian API トークンを検知・拒否するルールを追加しました。
2. **Asana Personal Access Token の検知**: Asana の Personal Access Token (`0/` で始まるもの) を検知対象としました。
3. **Trello API Key / Token の検知**: Trello の API キーおよびトークンを検知対象としました。

レビュアーは本 PR をマージする前に、各開発者のローカル環境にて `pre-commit install` が実施済みであることを周知してください。

### 前回の追加対策（AWS認証情報・Cloudflare強化・マージ前手動作業）

今回、新たに追加された `detect-aws-credentials` フックの実行や Cloudflare API Token などの漏洩防止をローカルで効果的に行うため、設定ファイルの変更は次回のコミット時に自動的に適用されます（すでに `pre-commit install` を実施済みの場合は、再インストールの必要はありません）。

また、GitHub リポジトリの **Settings → Code security and analysis** より、**Secret scanning** と **Push protection** が有効になっていることを確認してください。

### 前回の追加対策（SaaS API強化・マージ前手動作業）

今回、Zendesk、Okta、HubSpot、Grafana、および Shopify などの広く利用されている SaaS やプラットフォームの API キーやトークンが意図せず公開リポジトリへコミット・プッシュされることを防ぐため、`.gitleaks.toml` へカスタムルールを新たに追加し、コミット前検知の水際対策をさらに強化しました。

1. **Zendesk API Token の検知**: Zendesk の API トークンを検知・拒否するルールを追加しました。
2. **Okta API Token の検知**: Okta の API トークンを検知対象としました。
3. **HubSpot API Token の検知**: HubSpot の Personal Access Token (`pat-na1-...` 等) を検知対象としました。
4. **Grafana API Token の検知**: Grafana Cloud API Key や Service Account Token を検知対象としました。
5. **Shopify API Token の検知**: Shopify の API トークン (`shpat_...`) を検知対象としました。

レビュアーは本 PR をマージする前に、各開発者のローカル環境にて `pre-commit install` が実施済みであることを周知してください。

### 前回の追加対策（マージ前手動作業）

今回、新たに以下の漏洩検知・保護ルールを追加しました。

1. **GitHub Fine-grained PAT の検知強化**: 従来の classic PAT に加え、`github_pat_` で始まる Fine-grained PAT を厳格に検知対象としました。
2. **GCP OAuth クライアントシークレットの検知**: `GOCSPX-` で始まる GCP OAuth のシークレットを検知対象としました。
3. **クレジットカード情報（PII）の検知**: 主要なクレジットカード番号形式のハードコードを検知対象としました。
4. **AWS リソース ARN（12桁のAWSアカウントID）の検知**: インフラ構成の過剰露出を防ぐため、12桁のアカウントIDを含む ARN のハードコードを検知対象としました。
5. **ローカル履歴・上書きファイルの保護**: シェル履歴ファイル（`.*_history`）およびローカルの Docker Compose 上書きファイル（`docker-compose.override.yml`, `docker-compose.override.yaml`）が誤ってコミットされないよう、`.gitignore`、`.gitattributes`（diff非表示）、および VS Code / Cursor の設定（エクスプローラ非表示）で厳格に保護しました。
6. **Dependabot による定期監査の強化**: 依存パッケージの脆弱性や情報漏洩リスクに迅速に対応するため、Dependabot の実行間隔を `weekly` から `daily` に変更し、同時に PR の乱立を防ぐために `groups` 機能を導入して定期監査の頻度と品質を向上させました。
7. **チャットボットトークンと各種APIキーの検知**: Slack API トークン、Discord Bot トークン、GitLab PAT、Linear API キー、Datadog アクセストークン、Twilio API キー、Ngrok 認証トークン、Sentry 認証トークンに加え、PaaS トークン (Fly.io, Render)、IaC トークン (Terraform Cloud, HashiCorp Vault)、ネットワーク・認証系トークン (Tailscale, Clerk) を検知対象としました。
8. **AI エージェントワークスペースの保護強化**: `.gemini/` ディレクトリの除外ルールを強化し、`.gitattributes` による diff 保護を明示しました。
9. **データベースダンプの保護拡張**: `*.sql` ファイルについてもコミット前ブロック、diff非表示、および VS Code 上の非表示設定を行いました。
10. **汎用JWTトークンの検知**: 汎用的な JSON Web Token (JWT) の漏洩を防ぐため、`eyJ` から始まる一般的なJWTの形式を検知対象としました。
11. **GitHub Runner トークンの検知**: GitHub Actions Runner の登録や実行に使用されるトークン (`ghs_` で始まるもの) を検知対象としました。

レビュアーは本 PR をマージする前に、各開発者のローカル環境にて上記ファイルが正しく除外されていること、および `pre-commit install` が実施済みであることを周知してください。

### 前回の追加対策その２（マージ前手動作業）

今回、ファイル拡張子や名前に依存しない、ファイル内容（構造）ベースの漏洩検知ルールを `.gitleaks.toml` に追加し、コミット前検知の水際対策をさらに強化しました。

1. **GCP Service Account Key の内容検知**: ファイル名が `credentials.json` でない場合でも、JSON 内の構造（`"type": "service_account"` 等）からサービスアカウントキーを確実に検知・拒否します。
2. **汎用的な Private Key の内容検知**: `-----BEGIN ... PRIVATE KEY-----` などのヘッダを持つ RSA/DSA/EC/OPENSSH/PGP の秘密鍵ファイルを、ファイル名や拡張子に関わらず検知・拒否します（`detect-private-key` フックとの多層防御）。
3. **汎用 URI 認証情報の検知**: `http(s)` に限らず、`ftp`, `sftp`, `amqp`, `amqps` などの汎用プロトコルスキームでハードコードされた Basic 認証情報（例: `ftp://user:pass@domain`）を検知・拒否します。

レビュアーは本 PR をマージする前に、各開発者のローカル環境にて上記ファイルが正しく除外されていること、および `pre-commit install` が実施済みであることを引き続き周知してください。

### 前回の追加対策その３（マージ前手動作業）

今回、Auth0、Algolia、および Mailgun などの各種 SaaS サービスの API キーやトークンが意図せず公開リポジトリへコミット・プッシュされることを防ぐため、`.gitleaks.toml` へカスタムルールを新たに追加し、コミット前検知の水際対策を強化しました。

1. **Auth0 API Token の検知**: Auth0 Management API Token など（JWT 形式の `ey...` で始まるもの）を厳格に検知・拒否するルールを追加しました。
2. **Algolia API Key の検知**: Algolia の API キー（32文字の英数字）を検知対象としました。
3. **Mailgun API Key の検知**: Mailgun の API キー（`key-` から始まる32文字の英数字）を検知対象としました。

レビュアーは本 PR をマージする前に、各開発者のローカル環境にて `pre-commit install` が実施済みであることを周知してください。

### 前回の追加対策その４（マージ前手動作業）

今回、AI エージェントのログや設定ファイル、および IaC（CDKTF）のテンポラリディレクトリに関する漏洩リスクを未然に防ぐため、`.gitleaks.toml`、`.gitignore`、`.gitattributes`、および `.vscode/settings.json` のルールを拡充しました。これにより、より高度なコミット前検知と差分非表示設定による漏洩防止を強化しています。

1. **AI エージェント関連ログの保護強化**: 既存の `.aider*` 等のワークスペースディレクトリに加え、生成されがちなチャット履歴ファイル (`*.aider.chat.history.md`) や、Cline の特定設定ファイル (`.cline_mcp_settings.json`) をコミット拒否・差分非表示の対象としました。
2. **IaC (CDKTF) 関連の保護強化**: 既存の `cdktf.out/` や `.terraform/` に加え、CDKTF が一時的に生成する状態ディレクトリ `.cdktf/` を各種設定の除外・拒否ルールに明示的に追加しました。

レビュアーは本 PR をマージする前に、各開発者のローカル環境にて上記ファイル・ディレクトリが正しく除外されていること、および `pre-commit install` が実施済みであることを周知してください。

### 前回の追加対策：IDE・APIクライアント・AIエージェント対策（マージ前手動作業）

以前、IDEやモダンなAPIクライアント、および最新のAIエージェントの作業跡や内部情報が意図せず公開リポジトリへコミット・pushされることを防ぐため、ファイルパスベースの検知ルールを拡充しました。あわせて、特定 SaaS の Webhook シークレットおよび PaaS プラットフォームの API トークンに関する漏洩検知の隙を埋めるため、`.gitleaks.toml` へカスタムルールをさらに拡充し、コミット前検知の水際対策を強化しました。

1. **IDEのワークスペース・ローカル履歴の保護**: `.idea/`, `.history/` などのローカル履歴ディレクトリ、および `.vscode/sftp.json`, `.vscode/ftp-sync.json` 等の認証情報を含みうる設定ファイルをコミット対象から除外・検知・ブロック対象としました。
2. **モダンAPIクライアントの保護**: Thunder Client (`thunder-tests/`, `thunder-environment.json`) や Bruno (`bruno.json`, `.bruno/`) などのモダンAPIクライアントのワークスペース設定やエクスポートファイルを除外・検知対象とし、機密情報の漏洩を防ぎました。
3. **新規AIエージェントの保護拡充**: Cursor や Claude に加え、Windsurf (`.windsurf/`), Cline (`.cline/`), Roo (`.roo/`), Codeium (`.codeium/`) などの作業ディレクトリを除外・検知・ブロック対象に追加し、AIのコンテキストデータや一時ファイルが流出するのを防ぎました。
4. **Stripe Webhook Secret の検知**: 既存の Stripe API Key（`sk_`, `rk_`）に加え、Stripe の Webhook シークレット（`whsec_`）を厳格に検知・拒否するルールを追加しました。
5. **Vercel Access Token の検知**: Vercel の API アクセストークン（`vc1_`）を検知対象としました。
6. **Heroku API Key の検知**: Heroku の API キー（UUID形式）を検知対象としました。

これらのファイル・ディレクトリ群は `.gitleaks.toml` によるコミット前検知（ブロック）だけでなく、`.gitignore` でのコミット防止、`.gitattributes` による diff 保護（`-diff`）とリリースアーカイブからの除外（`export-ignore`）、および `.vscode/settings.json` でのエクスプローラ・検索結果からの除外によって多層的に保護されています。また、これらのルールにより、PaaS および決済プロバイダーの重要なシークレットが誤って公開リポジトリへ混入するリスクも未然に防ぎます。

レビュアーは本 PR をマージする前に、各開発者のローカル環境にて上記ファイル群が正しく除外されていること、および `pre-commit install` が実施済みであることを周知してください。

### 過去の追加対策（マージ前手動作業）

インフラ構成情報とシークレットの峻別（仕様上公開して問題ないものと秘匿すべきものの分離）を強化するため、ルールの精緻化を行いました。また、CIの定期監査機能として `pre-commit` ワークフローに schedule トリガーを追加し、リポジトリの最新状態に対する漏洩チェックを自動化しました。

さらに、以下の主要クラウド・SaaSの識別子検知ルールを厳格化しました。また、特定の開発ツールにおけるパーソナルアクセストークン等の保護も引き続き維持されます。

1. **Neon Postgres エンドポイント**: 接続文字列を含むパターンや境界判定を厳格化しました。
2. **Cloudflare API Key**: 判定条件を厳格化し、漏れを防ぎます。
3. **Resend API Key**: 判定条件を厳格化し、漏れを防ぎます。
4. **Stripe API Key**: `sk_` および `rk_` キーの境界判定を厳格化しました。
5. **GCP識別子の峻別強化**: GCP Project ID（`toique-app-*`）、Project Number（12桁）、Service Account（`*.iam.gserviceaccount.com`）のハードコード検知ルールに対して `allowlist` を追加し、IaC（`infra/` 配下の TypeScript ファイル）およびドキュメント（`docs/` 配下の Markdown ファイル、`README.md`）での記述を許可しました。これにより、アプリケーションコードへの誤混入は引き続きブロックしつつ、必要なインフラ構成ファイルでの仕様上の記載は許容されるようになります。
6. **Docker Hub PAT の検知**: `dckr_pat_` で始まる Docker Hub の Personal Access Token を厳格に検知対象としました。
7. **Figma PAT の検知**: `figd_` で始まる Figma の Personal Access Token を検知対象としました。
8. **Postman API Key の検知**: `PMAK-` で始まる Postman の API Key を検知対象としました。
9. **Pulumi Access Token の検知**: `pul-` で始まる Pulumi の Access Token を検知対象としました。
10. **VPN設定・パケットキャプチャファイルの保護**: `.ovpn`, `.pcap`, `.pcapng` ファイルが誤ってコミットされないよう、`.gitignore`、`.gitattributes`（diff非表示）、および VS Code / Cursor の設定（エクスプローラ非表示）で厳格に保護しました。
11. **macOSキーチェーンの保護**: `.keychain`, `.keychain-db` ファイルが誤ってコミットされないよう、`.gitignore`、`.gitattributes`（diff非表示）、および VS Code / Cursor の設定（エクスプローラ非表示）で厳格に保護しました。
12. **GCPインフラ構成の過剰露出防止**: 内部や未公開のインフラエンドポイントが露出するのを防ぐため、Cloud Run (`*.run.app`) に加えて、App Engine (`*.appspot.com`)、Cloud Functions (`*.cloudfunctions.net`)、GCS バケット (`storage.googleapis.com/*`)、および Cloud SQL の Unix ソケットパス (`/cloudsql/...`) のハードコードを検知対象として追加しました。
13. **ローカル環境変数のサンプル化漏れ防止**: AI エージェントの作業跡として意図せず生成・残存しがちな `.env.local`, `.env.development.local`, `.env.test.local` 等（およびそれらの `.sample` 化されたもの）を `.gitleaks.toml` 上でより明示的かつ厳密にブロックする専用ルールを追加し、意図せぬ環境変数の漏洩水際対策を強化しました。

レビュアーは本 PR をマージする前に、各開発者のローカル環境にて上記ファイルが正しく除外されていること、および `pre-commit install` が実施済みであることを引き続き周知してください。

### 前回の追加対策（GCP/Neonインフラ識別子検知の厳格化・マージ前手動作業）

今回、本プロジェクトのインフラ基盤である Google Cloud Platform (GCP) および Neon Postgres データベースに対する認証情報やインフラ識別子が意図せず公開リポジトリへコミット・プッシュされることを防ぐため、`.gitleaks.toml` のカスタムルールを見直し、検知精度と厳格性を向上させました。

1. **Neon Postgres エンドポイントの検知厳格化**: これまでは `aws.neon.tech` サブドメインに限定していましたが、より汎用的な Neon エンドポイント (`ep-*.neon.tech`) の直接的なハードコードや接続文字列を含めて広範に検知・ブロックするよう正規表現を強化しました。
2. **GCP Project ID の境界判定厳格化**: GCP Project ID (`toique-app-*`) の検知ルールに単語境界 (`\b`) を導入し、意図せぬ部分一致を避けつつ確実なハードコード検知ができるよう精度を向上させました（IaCやドキュメントファイルは引き続き許可されます）。

レビュアーは本 PR をマージする前に、各開発者のローカル環境にて `pre-commit install` が実施済みであることを周知してください。

### 前回の追加対策（新興AIエージェント・APIキー強化・マージ前手動作業）

今回、新興のAI系サービス（DeepSeek, Perplexity AI, Together AI）の API キーや、新しい AI エージェント（Continue, PearAI, Trae, Cody）の作業ディレクトリが意図せず公開リポジトリへコミット・プッシュされることを防ぐため、`.gitleaks.toml` をはじめとする各種設定ファイルへルールを新たに追加し、漏洩防止をさらに強化しました。

1. **AI系 API Key の検知**: DeepSeek (`sk-...`), Perplexity AI (`pplx-...`), Together AI のAPIキーを検知・拒否するルールを追加しました。
2. **AI エージェントワークスペースの保護拡充**: Continue (`.continue/`), PearAI (`.pearai/`), Trae (`.trae/`), Cody (`.cody/`) などの作業ディレクトリを除外・検知・ブロック対象に追加し、AIのコンテキストデータや一時ファイルが流出するのを防ぎました。

これらのファイル・ディレクトリ群は `.gitleaks.toml` によるコミット前検知だけでなく、`.gitignore` でのコミット防止、`.gitattributes` による diff 保護（`-diff`）とリリースアーカイブからの除外（`export-ignore`）、および `.vscode/settings.json` でのエクスプローラ・検索結果からの除外によって多層的に保護されています。

レビュアーは本 PR をマージする前に、各開発者のローカル環境にて上記ファイル・ディレクトリ群が正しく除外されていること、および `pre-commit install` が実施済みであることを周知してください。

### 前回の追加対策（データプラットフォーム・クラウドインフラのトークン強化・マージ前手動作業）

今回、Snowflake、Databricks、DigitalOcean、PlanetScale、および Upstash などのデータプラットフォームやクラウドインフラの認証情報が意図せず公開リポジトリへコミット・プッシュされることを防ぐため、`.gitleaks.toml` へカスタムルールを新たに追加し、コミット前検知の水際対策をさらに強化しました。

1. **Snowflake アカウント・パスワードの検知**: Snowflake の認証情報やパスワードがハードコードされることを検知・拒否するルールを追加しました。
2. **Databricks API Token の検知**: Databricks の Personal Access Token (`dapi...`) を検知対象としました。
3. **DigitalOcean Personal Access Token の検知**: DigitalOcean の API トークン (`dop_v1_...`) を検知対象としました。
4. **PlanetScale Password の検知**: PlanetScale の接続パスワードやサービストークン (`pscale_pw_...`) を検知対象としました。
5. **Upstash API Token の検知**: Upstash の API トークン（JWT 形式の `ey...`）を検知対象としました。

レビュアーは本 PR をマージする前に、各開発者のローカル環境にて `pre-commit install` が実施済みであることを周知してください。

### 今回の追加対策（LangSmith・WandB・Airtableトークン強化・マージ前手動作業）

今回、LangSmith、Weights & Biases (WandB)、および Airtable の認証情報や API トークンが意図せず公開リポジトリへコミット・プッシュされることを防ぐため、`.gitleaks.toml` へカスタムルールを新たに追加し、コミット前検知の水際対策をさらに強化しました。

1. **LangSmith API Key の検知**: LangSmith の API キー (`lsv2_pt_...`) を検知対象としました。
2. **Weights & Biases (WandB) API Key の検知**: WandB の API キー（40 桁 16 進数）を、`key`/`token` キーワードとの結合を必須にした上で検知対象としました。あわせて、既存のグローバル allowlist（Git SHA-1 除外）が本ルールの検知結果まで無条件に握りつぶしていた問題を修正し、`.github/workflows/` 配下のみに除外範囲を限定しました。
3. **Airtable API Key / PAT について**: gitleaks の組み込みルール（`airtable-api-key` / `airtable-personnal-access-token`）が既に Airtable の API キーおよび Personal Access Token を検知対象としているため、本PRでは重複する専用ルールを追加していません。

レビュアーは本 PR をマージする前に、各開発者のローカル環境にて `pre-commit install` が実施済みであることを周知してください。
