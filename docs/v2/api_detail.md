# セントラルID API 詳細仕様書

---

## 共通仕様

### ベースURL

```
https://centralid.win
```

### リクエスト共通ヘッダー

| ヘッダー | 値 | 備考 |
|---|---|---|
| `Content-Type` | `application/json` | リクエストボディを持つ場合 |
| `Authorization` | `Bearer {access_token}` | 認証が必要なエンドポイントのみ |

### レスポンス共通形式

**成功時**

```json
{
  "success": true,
  "data": { ... }
}
```

**エラー時**

```json
{
  "success": false,
  "error": {
    "code": "ERROR_CODE",
    "message": "エラーの説明"
  }
}
```

### 共通エラーコード

| HTTPステータス | コード | 説明 |
|---|---|---|
| 400 | `VALIDATION_ERROR` | リクエストパラメータ不正 |
| 401 | `UNAUTHORIZED` | 認証トークン未提供・無効 |
| 401 | `TOKEN_EXPIRED` | アクセストークン期限切れ → リフレッシュが必要 |
| 403 | `FORBIDDEN` | 権限なし（管理者権限が必要） |
| 404 | `NOT_FOUND` | リソース未存在 |
| 409 | `CONFLICT` | 競合（重複登録など） |
| 429 | `RATE_LIMIT_EXCEEDED` | レート制限超過 |
| 500 | `INTERNAL_SERVER_ERROR` | サーバー内部エラー |

### トークン仕様

| 種別 | 有効期間 | 用途 |
|---|---|---|
| アクセストークン | 15分 | API認証 |
| リフレッシュトークン | 30日 | アクセストークン更新 |

---

## 1. 認証 API

### 1-1. メールアドレスにOTPコードを送信

```
POST /auth/mail
```

指定メールアドレスに6桁のワンタイムコードを送信する。同一メールの未使用コードがある場合は無効化してから新規発行する。

**リクエストボディ**

| フィールド | 型 | 必須 | 説明 |
|---|---|---|---|
| `email` | string | ○ | 送信先メールアドレス |
| `service_id` | string | - | 呼び出し元サービスID（`services.id`）。`user_events` への記録に使用する |

```json
{
  "email": "user@example.com",
  "service_id": "550e8400-e29b-41d4-a716-446655440000"
}
```

**レスポンス** `200 OK`

```json
{
  "success": true,
  "data": {
    "expires_in": 600
  }
}
```

| フィールド | 型 | 説明 |
|---|---|---|
| `expires_in` | integer | コード有効期間（秒） |

**エラーレスポンス**

| コード | 説明 |
|---|---|
| `VALIDATION_ERROR` | email 形式不正 |
| `INVALID_SERVICE` | 存在しないサービスID |
| `RATE_LIMIT_EXCEEDED` | 短時間に複数回送信を試みた |

---

### 1-2. OTPコードを再送信

```
POST /auth/mail/otp
```

既存の未使用コードを無効化し、新しいコードを発行・再送信する。前回送信から60秒以内の再送信はエラーとなる。

**リクエストボディ**

| フィールド | 型 | 必須 | 説明 |
|---|---|---|---|
| `email` | string | ○ | 送信先メールアドレス |

```json
{
  "email": "user@example.com"
}
```

**レスポンス** `200 OK`

```json
{
  "success": true,
  "data": {
    "expires_in": 600
  }
}
```

**エラーレスポンス**

| コード | 説明 |
|---|---|
| `VALIDATION_ERROR` | email 形式不正 |
| `RESEND_TOO_SOON` | 前回送信から60秒未満（再送信間隔制限） |
| `RATE_LIMIT_EXCEEDED` | 短時間に複数回試みた |

---

### 1-3. OTPコードを検証（メール認証）

```
POST /auth/mail/verify
```

入力されたコードを検証し、認証成功時にトークンを発行する。対象メールアドレスが未登録の場合は自動的に新規アカウントを作成する。

- 認証成功時：`user_events` に `login` または `register` イベントを記録する
- 検証失敗時：`attempt` をインクリメントし、5回超過で `MAX_ATTEMPTS_EXCEEDED` を返す

**リクエストボディ**

| フィールド | 型 | 必須 | 説明 |
|---|---|---|---|
| `email` | string | ○ | メールアドレス |
| `code` | string | ○ | 6桁のワンタイムコード |
| `service_id` | string | - | 呼び出し元サービスID |

```json
{
  "email": "user@example.com",
  "code": "483920",
  "service_id": "550e8400-e29b-41d4-a716-446655440000"
}
```

**レスポンス** `200 OK`

```json
{
  "success": true,
  "data": {
    "access_token": "eyJ...",
    "refresh_token": "dGhp...",
    "token_type": "Bearer",
    "expires_in": 900,
    "user": {
      "central_id": "2026-550e8400-e29b-41d4-a716-446655440000",
      "public_id": 1001,
      "user_name": null,
      "display_name": null,
      "created_at": "2026-05-23T10:00:00Z"
    },
    "is_new_user": true
  }
}
```

| フィールド | 型 | 説明 |
|---|---|---|
| `access_token` | string | アクセストークン |
| `refresh_token` | string | リフレッシュトークン |
| `token_type` | string | 常に `"Bearer"` |
| `expires_in` | integer | アクセストークン有効期間（秒） |
| `user.central_id` | string | セントラルID |
| `user.public_id` | integer | 公開ID（表示用連番） |
| `user.user_name` | string\|null | ユーザーネーム（未設定の場合 null） |
| `user.display_name` | string\|null | 表示名（未設定の場合 null） |
| `user.created_at` | string | アカウント作成日時（ISO 8601） |
| `is_new_user` | boolean | 新規登録の場合 true |

**エラーレスポンス**

| コード | 説明 |
|---|---|
| `INVALID_CODE` | コード不一致 |
| `CODE_EXPIRED` | コード期限切れ |
| `CODE_ALREADY_USED` | 使用済みコード |
| `MAX_ATTEMPTS_EXCEEDED` | 試行回数超過（5回） |

---

### 1-4. Google OAuthリダイレクト

```
GET /auth/google
```

Google の OAuth 認証画面へリダイレクトする。

**クエリパラメータ**

| パラメータ | 型 | 必須 | 説明 |
|---|---|---|---|
| `redirect_uri` | string | ○ | 認証完了後に呼び出し元アプリへ返すコールバックURL |
| `service_id` | string | - | 呼び出し元サービスID |

**例**

```
GET /auth/google?redirect_uri=https%3A%2F%2Fmyapp.com%2Fauth%2Fcallback&service_id=550e8400-...
```

**レスポンス** `302 Found`

Google の認証画面へリダイレクト。

---

### 1-5. Google OAuthコールバック処理

```
GET /auth/google/callback
```

Google からのコールバックを受け取り、トークンを発行する。未登録の場合は自動的に新規アカウントを作成する。

**クエリパラメータ（Google が付与）**

| パラメータ | 型 | 説明 |
|---|---|---|
| `code` | string | 認可コード |
| `state` | string | CSRF対策ステートトークン |

**処理後のリダイレクト**

認証成功時、`redirect_uri` に短命の一時認証コードを付与してリダイレクトする。
クライアントはこのコードを `POST /auth/token/exchange` に送信してトークンを取得する。

```
{redirect_uri}?auth_code=xxxxxxxxxxxxxxxx
```

| フィールド | 型 | 説明 |
|---|---|---|
| `auth_code` | string | 一時認証コード（有効期間5分・1回限り） |

**エラーレスポンス**

| コード | 説明 |
|---|---|
| `OAUTH_ERROR` | Google 側の認証エラー |
| `INVALID_STATE` | state 不一致（CSRF 疑い） |

---

### 1-6. GitHub OAuthリダイレクト

```
GET /auth/github
```

GitHub の OAuth 認証画面へリダイレクトする。

**クエリパラメータ**

| パラメータ | 型 | 必須 | 説明 |
|---|---|---|---|
| `redirect_uri` | string | ○ | 認証完了後に呼び出し元アプリへ返すコールバックURL |
| `service_id` | string | - | 呼び出し元サービスID |

**レスポンス** `302 Found`

GitHub の認証画面へリダイレクト。

---

### 1-7. GitHub OAuthコールバック処理

```
GET /auth/github/callback
```

GitHub からのコールバックを受け取り、トークンを発行する。未登録の場合は自動的に新規アカウントを作成する。

**クエリパラメータ（GitHub が付与）**

| パラメータ | 型 | 説明 |
|---|---|---|
| `code` | string | 認可コード |
| `state` | string | CSRF対策ステートトークン |

**処理後のリダイレクト**

認証成功時、`redirect_uri` に短命の一時認証コードを付与してリダイレクトする。
クライアントはこのコードを `POST /auth/token/exchange` に送信してトークンを取得する。

```
{redirect_uri}?auth_code=xxxxxxxxxxxxxxxx
```

| フィールド | 型 | 説明 |
|---|---|---|
| `auth_code` | string | 一時認証コード（有効期間5分・1回限り） |

**エラーレスポンス**

| コード | 説明 |
|---|---|
| `OAUTH_ERROR` | GitHub 側の認証エラー |
| `INVALID_STATE` | state 不一致（CSRF 疑い） |

---

### 1-8. トークン発行・更新

```
POST /auth/token
```

`grant_type` によって動作が切り替わる。

---

#### grant_type: authorization_code — OAuthコールバック後のトークン発行

OAuth コールバックで発行された一時認証コードをアクセストークンおよびリフレッシュトークンに交換する。コードは使用後に即時無効化される（1回限り）。

**リクエストボディ**

| フィールド | 型 | 必須 | 説明 |
|---|---|---|---|
| `grant_type` | string | ○ | `"authorization_code"` 固定 |
| `auth_code` | string | ○ | コールバックで取得した一時認証コード |

```json
{
  "grant_type": "authorization_code",
  "auth_code": "xxxxxxxxxxxxxxxx"
}
```

**レスポンス** `200 OK`

```json
{
  "success": true,
  "data": {
    "access_token": "eyJ...",
    "refresh_token": "dGhp...",
    "token_type": "Bearer",
    "expires_in": 900,
    "user": {
      "central_id": "2026-550e8400-e29b-41d4-a716-446655440000",
      "public_id": 1001,
      "user_name": null,
      "display_name": null,
      "created_at": "2026-05-23T10:00:00Z"
    },
    "is_new_user": false
  }
}
```

| フィールド | 型 | 説明 |
|---|---|---|
| `access_token` | string | アクセストークン |
| `refresh_token` | string | リフレッシュトークン |
| `token_type` | string | 常に `"Bearer"` |
| `expires_in` | integer | アクセストークン有効期間（秒） |
| `user` | object | 認証ユーザー情報 |
| `is_new_user` | boolean | 新規登録の場合 true |

**エラーレスポンス**

| コード | 説明 |
|---|---|
| `INVALID_AUTH_CODE` | 無効または使用済みの一時コード |
| `AUTH_CODE_EXPIRED` | 一時コード期限切れ（5分） |

---

#### grant_type: refresh_token — アクセストークン更新

リフレッシュトークンを使って新しいアクセストークン・リフレッシュトークンを発行する。
使用済みのリフレッシュトークンは無効化される（ローテーション方式）。

**リクエストボディ**

| フィールド | 型 | 必須 | 説明 |
|---|---|---|---|
| `grant_type` | string | ○ | `"refresh_token"` 固定 |
| `refresh_token` | string | ○ | リフレッシュトークン |

```json
{
  "grant_type": "refresh_token",
  "refresh_token": "dGhp..."
}
```

**レスポンス** `200 OK`

```json
{
  "success": true,
  "data": {
    "access_token": "eyJ...",
    "refresh_token": "xKjm...",
    "token_type": "Bearer",
    "expires_in": 900
  }
}
```

**エラーレスポンス**

| コード | 説明 |
|---|---|
| `INVALID_REFRESH_TOKEN` | リフレッシュトークン無効・使用済み |
| `REFRESH_TOKEN_EXPIRED` | リフレッシュトークン期限切れ → 再ログインが必要 |

---

#### 共通エラー（POST /auth/token）

| コード | 説明 |
|---|---|
| `VALIDATION_ERROR` | `grant_type` が未指定または不正な値 |

---

### 1-9. アクセストークン確認

```
GET /auth/token
```

アクセストークンの有効性を確認する。有効な場合はユーザー情報を返す。

**リクエストヘッダー**

```
Authorization: Bearer {access_token}
```

**レスポンス** `200 OK`

```json
{
  "success": true,
  "data": {
    "is_valid": true,
    "user": {
      "central_id": "2026-550e8400-e29b-41d4-a716-446655440000",
      "public_id": 1001,
      "user_name": "taro_yamada",
      "display_name": "山田太郎"
    },
    "expires_at": "2026-05-23T10:15:00Z"
  }
}
```

**エラーレスポンス**

| コード | 説明 |
|---|---|
| `UNAUTHORIZED` | トークン無効または未提供 |
| `TOKEN_EXPIRED` | トークン期限切れ |

---

### 1-10. ログアウト

```
POST /auth/logout
```

アクセストークンおよびリフレッシュトークンを無効化（`revoked_at` を設定）し、`user_events` に `logout` イベントを記録する。

**リクエストヘッダー**

```
Authorization: Bearer {access_token}
```

**リクエストボディ**

| フィールド | 型 | 必須 | 説明 |
|---|---|---|---|
| `refresh_token` | string | ○ | リフレッシュトークン |
| `all_devices` | boolean | - | `true` の場合、全デバイスのトークンを無効化する（デフォルト: `false`） |

```json
{
  "refresh_token": "dGhp...",
  "all_devices": false
}
```

**レスポンス** `200 OK`

```json
{
  "success": true,
  "data": null
}
```

---

## 2. ユーザー管理 API

### 2-1. 自分のユーザー情報取得

```
GET /users/me
```

**リクエストヘッダー**

```
Authorization: Bearer {access_token}
```

**レスポンス** `200 OK`

```json
{
  "success": true,
  "data": {
    "central_id": "2026-550e8400-e29b-41d4-a716-446655440000",
    "public_id": 1001,
    "user_name": "taro_yamada",
    "display_name": "山田太郎",
    "created_at": "2026-05-23T10:00:00Z"
  }
}
```

---

### 2-2. 自分のユーザー情報更新

```
PATCH /users/me
```

`user_name` または `display_name` を変更する。変更時は対応する `_updated_at` も更新する。

**リクエストヘッダー**

```
Authorization: Bearer {access_token}
```

**リクエストボディ**（変更したいフィールドのみ送信）

| フィールド | 型 | 必須 | 制約 | 説明 |
|---|---|---|---|---|
| `user_name` | string | - | 英数字・アンダースコア、最大40文字 | ユーザーネーム |
| `display_name` | string | - | 最大40文字 | 表示名 |

```json
{
  "user_name": "new_name",
  "display_name": "新しい表示名"
}
```

**レスポンス** `200 OK`

```json
{
  "success": true,
  "data": {
    "central_id": "2026-550e8400-e29b-41d4-a716-446655440000",
    "user_name": "new_name",
    "display_name": "新しい表示名"
  }
}
```

**エラーレスポンス**

| コード | 説明 |
|---|---|
| `USERNAME_ALREADY_TAKEN` | ユーザーネームが既に使用されている |
| `VALIDATION_ERROR` | フィールド形式不正 |

---

### 2-3. 退会（アカウント削除）

```
DELETE /users/me
```

`deleted_at` を設定して論理削除する。CASCADE により関連レコード（`user_auth_providers` `tokens` `user_groups` `user_tags` `user_events` `user_profiles`）も削除される。

**リクエストヘッダー**

```
Authorization: Bearer {access_token}
```

**レスポンス** `200 OK`

```json
{
  "success": true,
  "data": null
}
```

---

### 2-4. 自分のプロフィール取得

```
GET /users/me/profile
```

**リクエストヘッダー**

```
Authorization: Bearer {access_token}
```

**レスポンス** `200 OK`

プロフィール未登録の場合は空オブジェクトを返す。

```json
{
  "success": true,
  "data": {
    "full_name": "山田太郎",
    "country": "Japan",
    "region": "Tokyo",
    "bio": "よろしくお願いします。",
    "social_account_1": "https://twitter.com/example",
    "social_account_2": null,
    "social_account_3": null,
    "social_account_4": null,
    "updated_at": "2026-05-23T10:00:00Z"
  }
}
```

---

### 2-5. 自分のプロフィール更新（upsert）

```
PUT /users/me/profile
```

プロフィールを更新する。初回は INSERT、以降は UPDATE（upsert）。

**リクエストヘッダー**

```
Authorization: Bearer {access_token}
```

**リクエストボディ**

| フィールド | 型 | 必須 | 説明 |
|---|---|---|---|
| `full_name` | string | - | 本名（最大100文字） |
| `country` | string | - | 国（最大100文字） |
| `region` | string | - | 都道府県・州（最大100文字） |
| `bio` | string | - | 自己紹介（最大500文字） |
| `social_account_1` | string | - | ソーシャルアカウント1（URL） |
| `social_account_2` | string | - | ソーシャルアカウント2（URL） |
| `social_account_3` | string | - | ソーシャルアカウント3（URL） |
| `social_account_4` | string | - | ソーシャルアカウント4（URL） |

```json
{
  "full_name": "山田太郎",
  "country": "Japan",
  "region": "Tokyo",
  "bio": "よろしくお願いします。",
  "social_account_1": "https://twitter.com/example",
  "social_account_2": null,
  "social_account_3": null,
  "social_account_4": null
}
```

**レスポンス** `200 OK`

```json
{
  "success": true,
  "data": null
}
```

---

### 2-6. 自分の認証手段一覧取得

```
GET /users/me/auth-providers
```

紐付いている認証手段の一覧を返す。`credential` は返さない。

**リクエストヘッダー**

```
Authorization: Bearer {access_token}
```

**レスポンス** `200 OK`

```json
{
  "success": true,
  "data": {
    "providers": [
      {
        "provider_type": "email",
        "id": "user@example.com",
        "created_at": "2026-05-23T10:00:00Z"
      },
      {
        "provider_type": "google",
        "id": "google-uid-12345",
        "created_at": "2026-05-23T11:00:00Z"
      }
    ]
  }
}
```

---

### 2-7. 認証手段追加

```
POST /users/me/auth-providers
```

OTPまたはOAuth経由で本人確認を完了したうえで認証手段を追加する。`user_events` に `auth_provider_add` イベントを記録する。

**リクエストヘッダー**

```
Authorization: Bearer {access_token}
```

**リクエストボディ**

| フィールド | 型 | 必須 | 説明 |
|---|---|---|---|
| `provider_type` | string | ○ | 追加する認証手段（`email` / `google` / `github`） |
| `token` | string | ○ | 本人確認済みの一時トークン（OTP検証またはOAuth完了後に発行） |

```json
{
  "provider_type": "google",
  "token": "temp_verify_token_xxx"
}
```

**レスポンス** `200 OK`

```json
{
  "success": true,
  "data": null
}
```

**エラーレスポンス**

| コード | 説明 |
|---|---|
| `INVALID_TOKEN` | 一時トークン無効・期限切れ |
| `PROVIDER_ALREADY_LINKED` | 指定の認証手段が既に紐付いている |
| `PROVIDER_TAKEN_BY_OTHER` | その認証アカウントが別ユーザーに紐付き済み |

---

### 2-8. 認証手段削除

```
DELETE /users/me/auth-providers/{provider_type}
```

指定の認証手段を削除する。最後の1件の場合は削除不可。`user_events` に `auth_provider_remove` イベントを記録する。

**リクエストヘッダー**

```
Authorization: Bearer {access_token}
```

**パスパラメータ**

| パラメータ | 説明 |
|---|---|
| `provider_type` | 削除する認証手段（`email` / `google` / `github`） |

**レスポンス** `200 OK`

```json
{
  "success": true,
  "data": null
}
```

**エラーレスポンス**

| コード | 説明 |
|---|---|
| `LAST_PROVIDER` | 最後の認証手段は削除不可 |
| `PROVIDER_NOT_LINKED` | 指定プロバイダーが紐付いていない |

---

### 2-9. 自分のイベント履歴取得

```
GET /users/me/events
```

**リクエストヘッダー**

```
Authorization: Bearer {access_token}
```

**クエリパラメータ**

| パラメータ | 型 | デフォルト | 説明 |
|---|---|---|---|
| `limit` | integer | 20 | 取得件数（最大100） |
| `offset` | integer | 0 | オフセット |
| `event_type` | string | - | イベント種別でフィルタ（`login` / `logout` / `register` 等） |

**レスポンス** `200 OK`

```json
{
  "success": true,
  "data": {
    "total": 42,
    "items": [
      {
        "id": 1001,
        "event_type": "login",
        "provider_type": "email",
        "service_id": "550e8400-e29b-41d4-a716-446655440000",
        "ip_address": "203.0.113.1",
        "user_agent": "Mozilla/5.0 ...",
        "created_at": "2026-05-23T10:00:00Z"
      }
    ]
  }
}
```

---

## 3. 管理者 API

> すべての管理者 API は管理者権限を持つアクセストークンが必要。

### 3-1. ユーザー一覧取得

```
GET /users
```

**リクエストヘッダー**

```
Authorization: Bearer {access_token}
```

**クエリパラメータ**

| パラメータ | 型 | デフォルト | 説明 |
|---|---|---|---|
| `limit` | integer | 20 | 取得件数（最大100） |
| `offset` | integer | 0 | オフセット |
| `group` | string | - | グループ名でフィルタ |
| `tag` | string | - | タグ名でフィルタ |
| `q` | string | - | `user_name` / `display_name` の部分一致検索 |

**レスポンス** `200 OK`

```json
{
  "success": true,
  "data": {
    "total": 1280,
    "items": [
      {
        "central_id": "2026-550e8400-e29b-41d4-a716-446655440000",
        "public_id": 1001,
        "user_name": "taro_yamada",
        "display_name": "山田太郎",
        "groups": ["admin", "beta"],
        "tags": ["premium"],
        "created_at": "2026-05-23T10:00:00Z"
      }
    ]
  }
}
```

---

### 3-2. 指定ユーザー情報取得

```
GET /users/{central_id}
```

**リクエストヘッダー**

```
Authorization: Bearer {access_token}
```

**パスパラメータ**

| パラメータ | 説明 |
|---|---|
| `central_id` | セントラルID |

**レスポンス** `200 OK`

```json
{
  "success": true,
  "data": {
    "central_id": "2026-550e8400-e29b-41d4-a716-446655440000",
    "public_id": 1001,
    "user_name": "taro_yamada",
    "display_name": "山田太郎",
    "groups": ["admin"],
    "tags": ["premium"],
    "providers": [
      { "provider_type": "email", "id": "user@example.com" }
    ],
    "created_at": "2026-05-23T10:00:00Z",
    "deleted_at": null
  }
}
```

---

### 3-3. グループ一覧取得

```
GET /users/{central_id}/groups
```

**リクエストヘッダー**

```
Authorization: Bearer {access_token}
```

**レスポンス** `200 OK`

```json
{
  "success": true,
  "data": {
    "groups": ["admin", "beta"]
  }
}
```

---

### 3-4. グループ付与

```
POST /users/{central_id}/groups
```

**リクエストヘッダー**

```
Authorization: Bearer {access_token}
```

**リクエストボディ**

| フィールド | 型 | 必須 | 説明 |
|---|---|---|---|
| `group` | string | ○ | グループ名（最大100文字） |

```json
{
  "group": "beta"
}
```

**レスポンス** `200 OK`

```json
{
  "success": true,
  "data": null
}
```

**エラーレスポンス**

| コード | 説明 |
|---|---|
| `CONFLICT` | 既に付与済みのグループ |

---

### 3-5. グループ削除

```
DELETE /users/{central_id}/groups/{group}
```

**リクエストヘッダー**

```
Authorization: Bearer {access_token}
```

**パスパラメータ**

| パラメータ | 説明 |
|---|---|
| `central_id` | セントラルID |
| `group` | 削除するグループ名 |

**レスポンス** `200 OK`

```json
{
  "success": true,
  "data": null
}
```

---

### 3-6. タグ一覧取得

```
GET /users/{central_id}/tags
```

**リクエストヘッダー**

```
Authorization: Bearer {access_token}
```

**レスポンス** `200 OK`

```json
{
  "success": true,
  "data": {
    "tags": ["premium", "early_adopter"]
  }
}
```

---

### 3-7. タグ付与

```
POST /users/{central_id}/tags
```

**リクエストヘッダー**

```
Authorization: Bearer {access_token}
```

**リクエストボディ**

| フィールド | 型 | 必須 | 説明 |
|---|---|---|---|
| `tag` | string | ○ | タグ名（最大100文字） |

```json
{
  "tag": "premium"
}
```

**レスポンス** `200 OK`

```json
{
  "success": true,
  "data": null
}
```

**エラーレスポンス**

| コード | 説明 |
|---|---|
| `CONFLICT` | 既に付与済みのタグ |

---

### 3-8. タグ削除

```
DELETE /users/{central_id}/tags/{tag}
```

**リクエストヘッダー**

```
Authorization: Bearer {access_token}
```

**パスパラメータ**

| パラメータ | 説明 |
|---|---|
| `central_id` | セントラルID |
| `tag` | 削除するタグ名 |

**レスポンス** `200 OK`

```json
{
  "success": true,
  "data": null
}
```

---

### 3-9. 指定ユーザーのイベント履歴取得

```
GET /users/{central_id}/events
```

**リクエストヘッダー**

```
Authorization: Bearer {access_token}
```

**パスパラメータ**

| パラメータ | 説明 |
|---|---|
| `central_id` | セントラルID |

**クエリパラメータ**

| パラメータ | 型 | デフォルト | 説明 |
|---|---|---|---|
| `limit` | integer | 20 | 取得件数（最大100） |
| `offset` | integer | 0 | オフセット |
| `event_type` | string | - | イベント種別でフィルタ |

**レスポンス** `200 OK`

```json
{
  "success": true,
  "data": {
    "total": 10,
    "items": [
      {
        "id": 1001,
        "event_type": "login",
        "provider_type": "email",
        "service_id": "550e8400-e29b-41d4-a716-446655440000",
        "ip_address": "203.0.113.1",
        "user_agent": "Mozilla/5.0 ...",
        "created_at": "2026-05-23T10:00:00Z"
      }
    ]
  }
}
```

---

## 4. サービス管理 API

> すべてのサービス管理 API は管理者権限が必要。

### 4-1. サービス一覧取得

```
GET /services
```

**リクエストヘッダー**

```
Authorization: Bearer {access_token}
```

**レスポンス** `200 OK`

```json
{
  "success": true,
  "data": {
    "items": [
      {
        "id": "550e8400-e29b-41d4-a716-446655440000",
        "name": "My Service",
        "created_at": "2026-01-01T00:00:00Z"
      }
    ]
  }
}
```

---

### 4-2. サービス登録

```
POST /services
```

**リクエストヘッダー**

```
Authorization: Bearer {access_token}
```

**リクエストボディ**

| フィールド | 型 | 必須 | 説明 |
|---|---|---|---|
| `name` | string | ○ | サービス名（最大255文字） |

```json
{
  "name": "My New Service"
}
```

**レスポンス** `201 Created`

```json
{
  "success": true,
  "data": {
    "id": "7c9e6679-7425-40de-944b-e07fc1f90ae7",
    "name": "My New Service",
    "created_at": "2026-05-23T10:00:00Z"
  }
}
```

---

### 4-3. サービス詳細取得

```
GET /services/{id}
```

**リクエストヘッダー**

```
Authorization: Bearer {access_token}
```

**パスパラメータ**

| パラメータ | 説明 |
|---|---|
| `id` | サービスID（UUID） |

**レスポンス** `200 OK`

```json
{
  "success": true,
  "data": {
    "id": "550e8400-e29b-41d4-a716-446655440000",
    "name": "My Service",
    "created_at": "2026-01-01T00:00:00Z"
  }
}
```

---

### 4-4. サービス削除

```
DELETE /services/{id}
```

サービスを削除する。関連する `user_events.service_id` は SET NULL となる（イベント履歴は保持）。

**リクエストヘッダー**

```
Authorization: Bearer {access_token}
```

**パスパラメータ**

| パラメータ | 説明 |
|---|---|
| `id` | サービスID（UUID） |

**レスポンス** `200 OK`

```json
{
  "success": true,
  "data": null
}
```

---

## エンドポイント一覧

| メソッド | パス | 権限 | 説明 |
|---|---|---|---|
| POST | `/auth/mail` | 公開 | OTPコード送信 |
| POST | `/auth/mail/otp` | 公開 | OTPコード再送信 |
| POST | `/auth/mail/verify` | 公開 | OTPコード検証・トークン発行 |
| GET | `/auth/google` | 公開 | Google OAuthリダイレクト |
| GET | `/auth/google/callback` | 公開 | Google OAuthコールバック |
| GET | `/auth/github` | 公開 | GitHub OAuthリダイレクト |
| GET | `/auth/github/callback` | 公開 | GitHub OAuthコールバック |
| POST | `/auth/token` | 公開 | トークン発行・更新（grant_type で切り替え） |
| GET | `/auth/token` | 公開 | アクセストークン有効性確認 |
| POST | `/auth/logout` | ユーザー | ログアウト |
| GET | `/users/me` | ユーザー | 自分のユーザー情報取得 |
| PATCH | `/users/me` | ユーザー | 自分のユーザー情報更新 |
| DELETE | `/users/me` | ユーザー | 退会 |
| GET | `/users/me/profile` | ユーザー | 自分のプロフィール取得 |
| PUT | `/users/me/profile` | ユーザー | 自分のプロフィール更新 |
| GET | `/users/me/auth-providers` | ユーザー | 認証手段一覧取得 |
| POST | `/users/me/auth-providers` | ユーザー | 認証手段追加 |
| DELETE | `/users/me/auth-providers/{provider_type}` | ユーザー | 認証手段削除 |
| GET | `/users/me/events` | ユーザー | 自分のイベント履歴取得 |
| GET | `/users` | 管理者 | ユーザー一覧取得 |
| GET | `/users/{central_id}` | 管理者 | 指定ユーザー情報取得 |
| GET | `/users/{central_id}/groups` | 管理者 | グループ一覧取得 |
| POST | `/users/{central_id}/groups` | 管理者 | グループ付与 |
| DELETE | `/users/{central_id}/groups/{group}` | 管理者 | グループ削除 |
| GET | `/users/{central_id}/tags` | 管理者 | タグ一覧取得 |
| POST | `/users/{central_id}/tags` | 管理者 | タグ付与 |
| DELETE | `/users/{central_id}/tags/{tag}` | 管理者 | タグ削除 |
| GET | `/users/{central_id}/events` | 管理者 | 指定ユーザーのイベント履歴取得 |
| GET | `/services` | 管理者 | サービス一覧取得 |
| POST | `/services` | 管理者 | サービス登録 |
| GET | `/services/{id}` | 管理者 | サービス詳細取得 |
| DELETE | `/services/{id}` | 管理者 | サービス削除 |
