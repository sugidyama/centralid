# セントラルID

複数のサービスを横断して利用できる共通ユーザー認証基盤です。

- **ベースURL**: `https://centralid.win`
- **認証方式**: メールOTP（パスワードレス）/ OAuthソーシャルログイン（Google・GitHub）
- **トークン**: ランダム64文字の不透明文字列（アクセストークン: 60分 / リフレッシュトークン: 30日）

---

## ドキュメント

| ドキュメント | 内容 |
|---|---|
| [企画書](docs/kikaku.md) | サービス概要・認証フロー・データ設計 |
| [統合ガイド](docs/integration-guide.md) | 外部サービスからの組み込み手順 |
| [OAuthセットアップ](docs/oauth-setup.md) | Google・GitHub OAuth設定手順 |

---

## API エンドポイント

### メールOTP認証

| メソッド | パス | 説明 |
|---|---|---|
| `POST` | `/auth/mail` | OTPを発行してメール送信 |
| `PUT` | `/auth/mail` | OTPを再送信 |
| `POST` | `/auth/mail/login` | OTPを検証してトークン発行 |

### OAuthソーシャル認証

| メソッド | パス | 説明 |
|---|---|---|
| `GET` | `/oauth/{google\|github}` | OAuth認証画面へリダイレクト |
| `GET` | `/oauth/{google\|github}/callback` | OAuthコールバック受付・auth_code発行 |
| `POST` | `/oauth/{google\|github}/login` | auth_codeをトークンに交換 |

### セッション・トークン管理

| メソッド | パス | 説明 |
|---|---|---|
| `GET` | `/auth/token` | トークン検証・ユーザー情報取得 |
| `PATCH` | `/auth/token` | アクセストークンのローテーション |
| `DELETE` | `/auth/token` | ログアウト（トークン失効） |

---

## ディレクトリ構成

```
centralid/
├── backend/               # Laravel バックエンド
│   ├── app/
│   │   ├── Http/Controllers/Auth/   # EmailAuth / OAuth / TokenAuth
│   │   ├── Services/                # OtpService / TokenService / UserService / ConfigService
│   │   ├── Models/                  # DB クエリ（DB ファサード使用）
│   │   └── DataObjects/             # 値オブジェクト（readonly class）
│   ├── database/migrations/         # DBスキーマ定義
│   └── routes/api.php               # ルート定義
└── docs/                  # ドキュメント
```

## バックエンド開発

```bash
cd backend
composer install
cp .env.example .env
php artisan key:generate
php artisan serve
```

### 必須 .env 設定

```dotenv
DB_CONNECTION=mysql
SESSION_DRIVER=file
CACHE_DRIVER=file
QUEUE_CONNECTION=sync
```

---

## コーディングルール

詳細は [CLAUDE.md](CLAUDE.md) および [.claude/backend.md](.claude/backend.md) を参照してください。
