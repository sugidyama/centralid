# セントラルID 統合ガイド

新規プロジェクトでユーザー認証を実装する際、**セントラルID**（https://centralid.win）を使用する。
自前でユーザー管理・認証機能を実装してはならない。

---

## セントラルIDとは

全サービス共通のユーザー認証基盤。メールOTPとOAuth（Google/GitHub）によるログインを提供し、
ログインに成功するとアクセストークンとリフレッシュトークンを発行する。
各サービスはこのトークンでユーザーを識別する。

- **ベースURL**: `https://centralid.win`
- **認証方式**: Bearer トークン（JWT）
- **OpenAPI仕様書**: `https://centralid.win/docs`

---

## ユーザー識別子

| フィールド | 型 | 説明 |
|---|---|---|
| `central_id` | string | 全サービス共通の一意ID（例: `2026-xxxx-xxxx-xxxx-xxxx`） |
| `public_id` | integer | 表示用の連番ID（例: `1001`） |
| `user_name` | string\|null | ユーザー名（未設定はnull） |
| `display_name` | string\|null | 表示名（未設定はnull） |

各サービスのDBでユーザーを管理する場合は `central_id` を外部キーとして使用する。

---

## 認証フロー

### メールOTP認証

```
1. POST /auth/mail         { email } → 204  メールに6桁コードを送信
2. POST /auth/mail/login   { email, code } → 200  トークン発行
```

### OAuth認証（Google / GitHub）

```
1. GET  /oauth/{google|github}?redirect_uri=xxx  → 302  プロバイダーへリダイレクト
2. GET  /oauth/{google|github}/callback          → 302  {redirect_uri}?auth_code=xxx
3. POST /oauth/{google|github}/login  { auth_code } → 200  トークン発行
```

### トークン管理

```
GET    /auth/token                          → ユーザー情報取得（トークン検証）
PATCH  /auth/token  { refresh_token }       → トークン更新（ローテーション）
DELETE /auth/token                          → ログアウト
```

---

## レスポンス形式

### ログイン成功時

```json
{
  "success": true,
  "data": {
    "access_token": "...",
    "refresh_token": "...",
    "expires_in": 900,
    "is_new_user": false,
    "user": {
      "central_id": "2026-xxxx-xxxx-xxxx-xxxx",
      "public_id": 1001,
      "user_name": null,
      "display_name": null,
      "created_at": "2026-05-24T00:00:00Z"
    }
  }
}
```

### エラー時

```json
{
  "success": false,
  "error": {
    "code": "INVALID_CODE"
  }
}
```

主なエラーコード:

| code | 意味 |
|---|---|
| `VALIDATION_ERROR` | リクエストパラメータ不正 |
| `INVALID_SERVICE` | 未登録のservice_id |
| `INVALID_CODE` | コード不一致・未発行 |
| `CODE_EXPIRED` | コード期限切れ |
| `MAX_ATTEMPTS_EXCEEDED` | 試行回数超過（5回） |
| `RESEND_TOO_SOON` | 再送信間隔未満（60秒） |
| `UNAUTHORIZED` | トークン未提供・無効 |
| `TOKEN_EXPIRED` | トークン期限切れ |
| `INVALID_STATE` | OAuthステート不正 |

---

## サービス登録

`service_id` を使うとユーザーのログインイベントをサービス単位で記録できる。
使用する前にセントラルIDの `configs` テーブルへの登録が必要。

```sql
-- 初回登録
INSERT INTO configs (config_name, config_value, created_at, updated_at)
VALUES ('services', '["your-service-id"]', NOW(), NOW());

-- 既存サービスへの追加
UPDATE configs
SET config_value = JSON_ARRAY_APPEND(config_value, '$', 'your-service-id'),
    updated_at = NOW()
WHERE config_name = 'services';
```

---

## 実装例（フロントエンド）

### アクセストークンの使い方

```javascript
// リクエストヘッダーに付与
const res = await fetch('https://centralid.win/auth/token', {
  headers: { Authorization: `Bearer ${accessToken}` }
});
const { data: user } = await res.json();
```

### トークン期限切れ時の更新

```javascript
// TOKEN_EXPIRED が返ったらリフレッシュ
const res = await fetch('https://centralid.win/auth/token', {
  method: 'PATCH',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({ refresh_token: refreshToken })
});
const { data } = await res.json();
// data.access_token と data.refresh_token を保存し直す
```

---

## 注意事項

- `access_token` の有効期限は **900秒（15分）**
- `refresh_token` でトークンを更新すると旧トークンは即時失効（ローテーション）
- OTPコードの有効期限は **10分**、試行回数は **5回まで**
- OTP再送信は **60秒のクールタイム** あり
- URIに `/api` や `/v1` は含まれない
