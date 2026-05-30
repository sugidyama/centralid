# centralid API テスト

`https://centralid.win` に対して OpenAPI 仕様（`docs/v3/openapi.yaml`）の充足を検証する E2E テストプロジェクト。

## 技術スタック

- **pytest** — テストランナー
- **httpx** — HTTP クライアント
- **pydantic v2** — レスポンススキーマ検証
- **python-dotenv** — 環境変数管理
- **uv** — パッケージ管理

## セットアップ（Mac）

```bash
# 1. uv のインストール（未インストールの場合）
brew install uv

# 2. test/ ディレクトリに移動して環境構築
cd centralid/test
uv venv && source .venv/bin/activate.fish
uv pip install -e .

# 3. .env を準備
cp .env.example .env
# .env の TEST_ACCESS_TOKEN / TEST_REFRESH_TOKEN に実際のトークンを設定

# 4. テスト実行
pytest -v
```

## テスト用トークンの取得方法

`TEST_ACCESS_TOKEN` / `TEST_REFRESH_TOKEN` は、メール OTP フローで取得する。

```bash
# 1. OTP 発行
curl -X POST https://centralid.win/auth/mail \
  -H "Content-Type: application/json" \
  -d '{"email": "your@email.com"}'

# 2. メールで受け取った 6 桁コードでログイン
curl -X POST https://centralid.win/auth/mail/login \
  -H "Content-Type: application/json" \
  -d '{"email": "your@email.com", "code": "123456"}'

# → レスポンスの data.access_token / data.refresh_token を .env に設定
```

## ディレクトリ構成

```
test/
├── pyproject.toml
├── .env.example
├── README.md
└── tests/
    ├── conftest.py          # fixture（クライアント・トークン管理）
    ├── schemas/
    │   └── user.py          # Pydantic レスポンスモデル
    ├── test_auth_mail.py    # OTP 発行・再送信・ログイン
    ├── test_auth_token.py   # トークン検証・更新・ログアウト
    └── test_oauth.py        # OAuth リダイレクト・コールバック・ログイン
```
