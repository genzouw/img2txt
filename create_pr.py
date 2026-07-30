import urllib.request
import urllib.parse
import json
import os

branch_name = "feat/gcp-infrastructure-exposure-rules"
repo_url = "https://api.github.com/repos/genzouw/img2txt/pulls"
token = os.environ.get("GITHUB_TOKEN", "")

pr_title = "chore(security): 🔒 gitleaks カスタムルールによる GCPインフラ構成の過剰露出防止強化"
pr_body = """## 背景

公開リポジトリにおいて、インフラ構成情報（バックエンドのエンドポイントやデータベースの接続パスなど）が意図せずコミットされることは、攻撃者に対する過剰な情報開示となりリスクを高めます。現在 Cloud Run などの一部エンドポイントは検知対象ですが、App Engine、Cloud Functions、GCS バケット、Cloud SQL の Unix ソケットパスといった主要な GCP インフラ構成の漏洩を未然に防ぐ仕組みが不足していました。

## このPRで導入するもの

- ツール名: gitleaks v8.x (既存ツールのカスタムルール拡張)
- 導入箇所: `.gitleaks.toml` および `docs/security/leak-prevention.md`
- 期待される効果: 開発者のローカル環境およびCI (pre-commitフック) にて、App Engine (`*.appspot.com`)、Cloud Functions (`*.cloudfunctions.net`)、GCS バケット (`storage.googleapis.com/*`)、および Cloud SQL Unix ソケットパス (`/cloudsql/...`) のソースコードへの意図せぬ混入を検知してコミット・マージをブロックします。

## 検知漏れリスクと補完策

- 検知できないケース: 上記ルールに合致しないカスタムドメインのエンドポイントや、文字列結合で難読化・分割されたパス。
- 補完策: 既存の GitHub Secret Scanning と Push Protection を併用し、多層防御を構成しています。また、IaC 等で仕様上必要な定義ファイル等については必要に応じて `.gitleaks.toml` に allowlist を追加して対応します。

## マージ前に必要な手動作業（チェックリスト）

レビュアーは PR をマージする前に必ず以下を実施してください。
本 PR の CI は手動作業完了を前提に通る設計です。

- [ ] GitHub repo settings → Code security → Push protection が有効になっていることの確認（運用ルールとして）
- [ ] 各開発者へローカルで `pre-commit install` を実行し、フックを最新状態にするよう周知すること

## マージ後の確認手順

- [ ] 次の push または PR で `pre-commit` ワークフローが green になることを確認
- [ ] ローカルでテスト用に App Engine などのエンドポイントを含むコミットを試み、`gitleaks` フックで正しくブロックされることを確認

## ロールバック手順

本 PR のコミットを `git revert` してマージすることで、ルールのロールバックが可能です。

## 参考情報

- 公式ドキュメント: https://github.com/gitleaks/gitleaks
- 比較検討した他案: 新規の秘密情報スキャナ（例: detect-secrets）の追加も検討しましたが、プロンプトの制約「既存資産との整合」に従い、無駄なツールの乱立を避けるため既存の gitleaks のルールを厳格化（追記）するアプローチを採用しました。
"""

data = {
    "title": pr_title,
    "body": pr_body,
    "head": branch_name,
    "base": "main"
}
req = urllib.request.Request(repo_url, data=json.dumps(data).encode("utf-8"), headers={
    "Authorization": f"Bearer {token}",
    "Accept": "application/vnd.github.v3+json",
    "Content-Type": "application/json"
})
try:
    with urllib.request.urlopen(req) as response:
        print("PR created successfully:", json.loads(response.read().decode())["html_url"])
except Exception as e:
    print("Error creating PR:", e)
