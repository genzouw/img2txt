# 百人一首 アプリケーション

このリポジトリは、PHPとDockerを使用して構築された百人一首のWebアプリケーションを含んでいます。

## 開発環境のセットアップ

アプリケーションを実行するには、DockerとDocker Composeが必要です。

1. リポジトリをクローンします。
2. 以下のコマンドを実行してコンテナを起動します。

```bash
docker-compose up -d
```

3. ブラウザで `http://localhost:10001` にアクセスしてアプリケーションを確認します。

## CI/CD と コード品質

このリポジトリでは、以下のツールを使用してコード品質を維持し、セキュリティを確保しています。

- **GitHub Actions**:
  - プルリクエストおよび `main` ブランチへのプッシュ時に、以下のチェックを自動的に実行します。
    - **PHP Lint**: PHPコードの構文エラーをチェックします。
    - **Hadolint**: `Dockerfile` のベストプラクティスをチェックします。
    - **Trivy**: DockerイメージのOSおよびライブラリの脆弱性をスキャンします。

- **Dependabot**:
  - 毎週定期的にDockerおよびGitHub Actionsの依存関係をチェックし、アップデートが必要な場合は自動的にプルリクエストを作成します。

- **CodeRabbit**:
  - AIを利用した自動コードレビューを行います。レビューコメントは日本語で提供されます。

- **Gemini Code Assist**:
  - プルリクエスト作成時に、AIによるコードレビューを行い、中程度（MEDIUM）以上の重大度を持つ問題についてコメントを提供します。

## ディレクトリ構造

- `html/`: Webアプリケーションのソースコード（PHPファイルなど）が含まれています。
- `db/`: データベースファイルが格納されるディレクトリです。
- `Dockerfile`: アプリケーションを実行するためのDockerイメージの構築手順を定義しています。
- `docker-compose.yml`: サービスとボリュームの設定を定義しています。
- `.github/workflows/ci.yml`: GitHub ActionsのCIワークフローを定義しています。
- `.github/dependabot.yml`: Dependabotの設定ファイルです。
- `.coderabbit.yaml`: CodeRabbitの設定ファイルです。
- `.gemini/config.yaml`: Gemini Code Assistの設定ファイルです。
