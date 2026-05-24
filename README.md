# img2txt

このリポジトリは、画像ファイルをUnixターミナルで表示可能な色付きのアスキーアートに変換するWeb API（img2txt）のソースコードを含んでいます。
PHPとDockerを使用して構築されています。

## 特徴

- 画像ファイルをUnix端末に表示できる色付きのアスキーアート（テキスト）に変換します。
- `w` クエリパラメータを使用して、出力テキストの拡大率を1%から200%まで指定できます。
- `tl`, `tr`, `tt`, `tb` クエリパラメータを使用して、出力テキストの上下左右の文字を任意の数だけトリミングできます。
- `c` クエリパラメータを使用して、出力されるアスキーアートを構成する文字を指定できます（デフォルトは `0`）。

## 使い方

`curl` コマンドを使用して、Web APIを呼び出すことができます。

```bash
# 画像ファイルURLをアスキーアートに変換する場合
curl -sS 'http://localhost:10001?url=https://www.google.com/images/branding/googlelogo/2x/googlelogo_color_272x92dp.png'
```

## 開発環境のセットアップ

アプリケーションを実行するには、DockerとDocker Composeが必要です。

1. リポジトリをクローンします。
1. 以下のコマンドを実行してコンテナを起動します。

```bash
docker compose up -d
```

1. ブラウザで `http://localhost:10001` にアクセスするか、`curl`コマンド等でAPIエンドポイントを確認します。

## CI/CD と コード品質

このリポジトリでは、以下のツールを使用してコード品質を維持し、セキュリティを確保しています。

- **GitHub Actions**:
  - プルリクエストおよび `main` ブランチへのプッシュ時に、以下のチェックを自動的に実行します。
    - **PHP Lint**: PHPコードの構文エラーをチェックします。
    - **Hadolint**: `Dockerfile` のベストプラクティスをチェックします。
    - **Trivy**: DockerイメージのOSおよびライブラリの脆弱性をスキャンします。
    - **markdownlint**: Markdown ドキュメントの構文・スタイルを `markdownlint-cli2` でチェックします。

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
- `compose.yaml`: サービスとボリュームの設定を定義しています。
- `.github/workflows/ci.yml`: GitHub ActionsのCIワークフローを定義しています。
- `.github/dependabot.yml`: Dependabotの設定ファイルです。
- `.coderabbit.yaml`: CodeRabbitの設定ファイルです。
- `.gemini/config.yaml`: Gemini Code Assistの設定ファイルです。

## ライセンス

このプロジェクトは [MITライセンス](LICENSE) の下で公開されているオープンソースソフトウェアです。

## セキュリティ報告窓口

セキュリティ上の脆弱性や、意図しない認証情報・シークレットの漏洩を発見した場合は、**公開 Issue で報告せず**、リポジトリの管理者へ直接プライベートな手段（もしあれば）でご連絡いただくか、GitHub の [Private vulnerability reporting](https://docs.github.com/ja/code-security/security-advisories/guidance-on-reporting-and-writing-information-about-vulnerabilities/privately-reporting-a-security-vulnerability) を利用して報告してください。
シークレット漏洩を未然に防ぐための取り組みについては、[docs/security/leak-prevention.md](docs/security/leak-prevention.md) を参照してください。
