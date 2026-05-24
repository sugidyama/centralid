# セントラルID API 仕様書

## 基本仕様

| 項目 | 内容 |
|---|---|
| ベースURL | `https://centralid.win` |
| データ形式 | JSON (`Content-Type: application/json`) |
| 文字コード | UTF-8 |
| 認証方式 | Bearer トークン（`Authorization: Bearer {access_token}`） |
| URI prefix | なし（サブドメインで外部アプリと分離） |

### 共通レスポンス形式

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
| 401 | `TOKEN_EXPIRED` | アクセストークン期限切れ |
| 403 | `FORBIDDEN` | 権限なし |
| 404 | `NOT_FOUND` | リソース未存在 |
| 429 | `RATE_LIMIT_EXCEEDED` | レート制限超過 |
| 500 | `INTERNAL_SERVER_ERROR` | サーバー内部エラー |

---

## 1. 認証 API

### 1-1. メールアドレスにワンタイムコードを送信

```
POST /auth/email/send-code
```

アプリからメールアドレスを受け取り、6桁のワンタイムコードを送信する。登録・ログイン共通で使用する。

**リクエストボディ**

| フィールド | 型 | 必須 | 説明 |
|---|---|---|---|
| `email` | string | ○ | メールアドレス |
| `app_id` | string | ○ | 呼び出し元アプリID |

```json
{
  "email": "user@example.com",
  "app_id": "app_abc123"
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
| `VALIDATION_ERROR` | email 形式不正 / app_id 未指定 |
| `INVALID_APP` | 存在しないアプリID |
| `RATE_LIMIT_EXCEEDED` | 短時間に複数回送信を試みた |

---

### 1-2. ワンタイムコードの検証（メール認証）

```
POST /auth/email/verify
```

入力されたコードを検証し、認証成功時にトークンを発行する。ユーザーが未登録の場合は自動的に新規アカウントを作成する。

**リクエストボディ**

| フィールド | 型 | 必須 | 説明 |
|---|---|---|---|
| `email` | string | ○ | メールアドレス |
| `code` | string | ○ | 6桁のワンタイムコード |
| `app_id` | string | ○ | 呼び出し元アプリID |

```json
{
  "email": "user@example.com",
  "code": "483920",
  "app_id": "app_abc123"
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
      "uuid": "550e8400-e29b-41d4-a716-446655440000",
      "username": null,
      "registered_app_id": "app_abc123",
      "created_at": "2026-05-22T10:00:00Z"
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
| `user.uuid` | string | セントラルID内部UUID |
| `user.username` | string\|null | ユーザーネーム（未設定の場合 null） |
| `user.registered_app_id` | string | 登録元アプリID |
| `is_new_user` | boolean | 新規登録の場合 true |

**エラーレスポンス**

| コード | 説明 |
|---|---|
| `INVALID_CODE` | コード不一致 |
| `CODE_EXPIRED` | コード期限切れ |
| `CODE_ALREADY_USED` | 使用済みコード |
| `MAX_ATTEMPTS_EXCEEDED` | 試行回数超過 |

---

### 1-3. ソーシャル認証リダイレクト

```
GET /auth/social/{provider}
```

指定したプロバイダーの OAuth 認証画面へリダイレクトする。

**パスパラメータ**

| パラメータ | 説明 |
|---|---|
| `provider` | プロバイダー名（`google` / `github` など） |

**クエリパラメータ**

| パラメータ | 型 | 必須 | 説明 |
|---|---|---|---|
| `app_id` | string | ○ | 呼び出し元アプリID |
| `redirect_uri` | string | ○ | 認証完了後の呼び出し元アプリのコールバックURL |

**レスポンス** `302 Found`

OAuth プロバイダーの認証画面へリダイレクト。

---

### 1-4. ソーシャル認証コールバック

```
GET /auth/social/{provider}/callback
```

OAuth プロバイダーからのコールバックを受け取り、トークンを発行する。ユーザーが未登録の場合は自動的に新規アカウントを作成する。

**パスパラメータ**

| パラメータ | 説明 |
|---|---|
| `provider` | プロバイダー名（`google` / `github` など） |

**クエリパラメータ（プロバイダーが付与）**

| パラメータ | 型 | 説明 |
|---|---|---|
| `code` | string | 認可コード |
| `state` | string | CSRF対策ステートトークン |

**処理後のリダイレクト**

呼び出し元アプリの `redirect_uri` にトークン等のパラメータを付与してリダイレクトする。

```
{redirect_uri}?access_token=eyJ...&refresh_token=dGhp...&expires_in=900&is_new_user=true
```

**エラーレスポンス**

| コード | 説明 |
|---|---|
| `INVALID_PROVIDER` | 未対応のプロバイダー |
| `OAUTH_ERROR` | プロバイダー側の認証エラー |
| `INVALID_STATE` | state 不一致（CSRF 疑い） |

---

### 1-5. アクセストークン更新

```
POST /auth/token/refresh
```

リフレッシュトークンを使って新しいアクセストークンを発行する。

**リクエストボディ**

| フィールド | 型 | 必須 | 説明 |
|---|---|---|---|
| `refresh_token` | string | ○ | リフレッシュトークン |

```json
{
  "refresh_token": "dGhp..."
}
```

**レスポンス** `200 OK`

```json
{
  "success": true,
  "data": {
    "access_token": "eyJ...",
    "token_type": "Bearer",
    "expires_in": 900
  }
}
```

**エラーレスポンス**

| コード | 説明 |
|---|---|
| `INVALID_REFRESH_TOKEN` | リフレッシュトークン無効 |
| `REFRESH_TOKEN_EXPIRED` | リフレッシュトークン期限切れ → 再ログインが必要 |

---

### 1-6. ログアウト

```
POST /auth/logout
```

アクセストークンおよびリフレッシュトークンを無効化する。

**リクエストヘッダー**

```
Authorization: Bearer {access_token}
```

**リクエストボディ**

| フィールド | 型 | 必須 | 説明 |
|---|---|---|---|
| `refresh_token` | string | ○ | リフレッシュトークン |
| `all_devices` | boolean | - | `true` の場合、全デバイスのセッションを破棄する（デフォルト: `false`） |

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

### 2-1. 自分のプロフィール取得

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
    "uuid": "550e8400-e29b-41d4-a716-446655440000",
    "username": "taro_yamada",
    "registered_app_id": "app_abc123",
    "providers": [
      {
        "provider": "email",
        "provider_uid": "user@example.com"
      },
      {
        "provider": "google",
        "provider_uid": "google-uid-12345"
      }
    ],
    "created_at": "2026-05-22T10:00:00Z",
    "updated_at": "2026-05-22T12:00:00Z"
  }
}
```

---

### 2-2. 自分のプロフィール更新

```
PATCH /users/me
```

**リクエストヘッダー**

```
Authorization: Bearer {access_token}
```

**リクエストボディ**（更新したいフィールドのみ送信）

| フィールド | 型 | 必須 | 説明 |
|---|---|---|---|
| `username` | string | - | ユーザーネーム |

```json
{
  "username": "new_name"
}
```

**レスポンス** `200 OK`

```json
{
  "success": true,
  "data": {
    "uuid": "550e8400-e29b-41d4-a716-446655440000",
    "username": "new_name",
    "updated_at": "2026-05-22T13:00:00Z"
  }
}
```

**エラーレスポンス**

| コード | 説明 |
|---|---|
| `USERNAME_ALREADY_TAKEN` | ユーザーネームが既に使用されている |

---

### 2-3. アカウント削除

```
DELETE /users/me
```

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

### 2-4. 紐付き認証プロバイダー一覧取得

```
GET /users/me/providers
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
    "providers": [
      {
        "provider": "email",
        "provider_uid": "user@example.com",
        "linked_at": "2026-05-22T10:00:00Z"
      }
    ]
  }
}
```

---

### 2-5. 認証プロバイダーの紐付け解除

```
DELETE /users/me/providers/{provider}
```

**リクエストヘッダー**

```
Authorization: Bearer {access_token}
```

**パスパラメータ**

| パラメータ | 説明 |
|---|---|
| `provider` | 解除するプロバイダー名（`email` / `google` など） |

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

## 3. 利用履歴 API

### 3-1. 自分のログイン履歴取得

```
GET /users/me/history
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

**レスポンス** `200 OK`

```json
{
  "success": true,
  "data": {
    "total": 42,
    "items": [
      {
        "id": 1001,
        "app_id": "app_abc123",
        "provider": "email",
        "ip_address": "203.0.113.1",
        "user_agent": "Mozilla/5.0 ...",
        "logged_in_at": "2026-05-22T10:00:00Z"
      }
    ]
  }
}
```

---

## 4. 管理者 API

> すべての管理者 API は管理者権限を持つアクセストークンが必要。

### 4-1. ユーザー一覧取得

```
GET /admin/users
```

**クエリパラメータ**

| パラメータ | 型 | デフォルト | 説明 |
|---|---|---|---|
| `limit` | integer | 20 | 取得件数（最大100） |
| `offset` | integer | 0 | オフセット |
| `app_id` | string | - | 登録元アプリIDで絞り込み |
| `tag` | string | - | タグで絞り込み |
| `group` | string | - | グループで絞り込み |
| `q` | string | - | ユーザーネームの部分一致検索 |

**レスポンス** `200 OK`

```json
{
  "success": true,
  "data": {
    "total": 1280,
    "items": [
      {
        "uuid": "550e8400-e29b-41d4-a716-446655440000",
        "username": "taro_yamada",
        "registered_app_id": "app_abc123",
        "tags": ["premium", "beta"],
        "groups": ["group_a"],
        "created_at": "2026-05-22T10:00:00Z"
      }
    ]
  }
}
```

---

### 4-2. ユーザー詳細取得

```
GET /admin/users/{uuid}
```

**パスパラメータ**

| パラメータ | 説明 |
|---|---|
| `uuid` | ユーザーの内部UUID |

**レスポンス** `200 OK`

```json
{
  "success": true,
  "data": {
    "uuid": "550e8400-e29b-41d4-a716-446655440000",
    "username": "taro_yamada",
    "registered_app_id": "app_abc123",
    "providers": [
      { "provider": "email", "provider_uid": "user@example.com" }
    ],
    "tags": ["premium"],
    "groups": ["group_a"],
    "created_at": "2026-05-22T10:00:00Z",
    "updated_at": "2026-05-22T12:00:00Z"
  }
}
```

---

### 4-3. ユーザー強制削除

```
DELETE /admin/users/{uuid}
```

**パスパラメータ**

| パラメータ | 説明 |
|---|---|
| `uuid` | ユーザーの内部UUID |

**レスポンス** `200 OK`

```json
{
  "success": true,
  "data": null
}
```

---

### 4-4. ユーザーにタグ付与

```
POST /admin/users/{uuid}/tags
```

**リクエストボディ**

| フィールド | 型 | 必須 | 説明 |
|---|---|---|---|
| `tag` | string | ○ | タグ名 |

```json
{
  "tag": "premium"
}
```

**レスポンス** `200 OK`

```json
{
  "success": true,
  "data": {
    "uuid": "550e8400-e29b-41d4-a716-446655440000",
    "tags": ["premium"]
  }
}
```

---

### 4-5. ユーザーのタグ削除

```
DELETE /admin/users/{uuid}/tags/{tag}
```

**パスパラメータ**

| パラメータ | 説明 |
|---|---|
| `uuid` | ユーザーの内部UUID |
| `tag` | 削除するタグ名 |

**レスポンス** `200 OK`

```json
{
  "success": true,
  "data": null
}
```

---

### 4-6. ユーザーにグループ付与

```
POST /admin/users/{uuid}/groups
```

**リクエストボディ**

| フィールド | 型 | 必須 | 説明 |
|---|---|---|---|
| `group` | string | ○ | グループ名 |

```json
{
  "group": "group_a"
}
```

**レスポンス** `200 OK`

```json
{
  "success": true,
  "data": {
    "uuid": "550e8400-e29b-41d4-a716-446655440000",
    "groups": ["group_a"]
  }
}
```

---

### 4-7. ユーザーのグループ削除

```
DELETE /admin/users/{uuid}/groups/{group}
```

**パスパラメータ**

| パラメータ | 説明 |
|---|---|
| `uuid` | ユーザーの内部UUID |
| `group` | 削除するグループ名 |

**レスポンス** `200 OK`

```json
{
  "success": true,
  "data": null
}
```

---

### 4-8. 全ユーザーのログイン履歴取得

```
GET /admin/history
```

**クエリパラメータ**

| パラメータ | 型 | デフォルト | 説明 |
|---|---|---|---|
| `limit` | integer | 20 | 取得件数（最大100） |
| `offset` | integer | 0 | オフセット |
| `user_uuid` | string | - | ユーザーUUIDで絞り込み |
| `app_id` | string | - | アプリIDで絞り込み |
| `provider` | string | - | 認証手段で絞り込み（`email` / `google` など） |
| `from` | string | - | 開始日時（ISO 8601） |
| `to` | string | - | 終了日時（ISO 8601） |

**レスポンス** `200 OK`

```json
{
  "success": true,
  "data": {
    "total": 5000,
    "items": [
      {
        "id": 1001,
        "user_uuid": "550e8400-e29b-41d4-a716-446655440000",
        "app_id": "app_abc123",
        "provider": "email",
        "ip_address": "203.0.113.1",
        "user_agent": "Mozilla/5.0 ...",
        "logged_in_at": "2026-05-22T10:00:00Z"
      }
    ]
  }
}
```

---

### 4-9. ダッシュボード統計取得

```
GET /admin/stats
```

**クエリパラメータ**

| パラメータ | 型 | デフォルト | 説明 |
|---|---|---|---|
| `period` | string | `7d` | 集計期間（`7d` / `30d` / `90d`） |

**レスポンス** `200 OK`

```json
{
  "success": true,
  "data": {
    "total_users": 1280,
    "new_users_in_period": 45,
    "total_logins_in_period": 3200,
    "daily_new_users": [
      { "date": "2026-05-16", "count": 5 },
      { "date": "2026-05-17", "count": 8 }
    ],
    "daily_logins": [
      { "date": "2026-05-16", "count": 420 },
      { "date": "2026-05-17", "count": 480 }
    ],
    "provider_breakdown": {
      "email": 800,
      "google": 480
    }
  }
}
```

---

## 5. アプリ管理 API

### 5-1. アプリ一覧取得

```
GET /admin/apps
```

**レスポンス** `200 OK`

```json
{
  "success": true,
  "data": {
    "items": [
      {
        "app_id": "app_abc123",
        "app_name": "My Service",
        "created_at": "2026-01-01T00:00:00Z"
      }
    ]
  }
}
```

---

### 5-2. アプリ登録

```
POST /admin/apps
```

**リクエストボディ**

| フィールド | 型 | 必須 | 説明 |
|---|---|---|---|
| `app_name` | string | ○ | アプリ名 |

```json
{
  "app_name": "My New Service"
}
```

**レスポンス** `201 Created`

```json
{
  "success": true,
  "data": {
    "app_id": "app_xyz789",
    "app_name": "My New Service",
    "created_at": "2026-05-22T10:00:00Z"
  }
}
```

---

### 5-3. アプリ削除

```
DELETE /admin/apps/{app_id}
```

**パスパラメータ**

| パラメータ | 説明 |
|---|---|
| `app_id` | アプリID |

**レスポンス** `200 OK`

```json
{
  "success": true,
  "data": null
}
```

---

## エンドポイント一覧

| メソッド | パス | 認証 | 説明 |
|---|---|---|---|
| POST | `/auth/email/send-code` | 不要 | ワンタイムコード送信 |
| POST | `/auth/email/verify` | 不要 | コード検証・トークン発行 |
| GET | `/auth/social/{provider}` | 不要 | ソーシャル認証リダイレクト |
| GET | `/auth/social/{provider}/callback` | 不要 | ソーシャル認証コールバック |
| POST | `/auth/token/refresh` | 不要 | アクセストークン更新 |
| POST | `/auth/logout` | Bearer | ログアウト |
| GET | `/users/me` | Bearer | プロフィール取得 |
| PATCH | `/users/me` | Bearer | プロフィール更新 |
| DELETE | `/users/me` | Bearer | アカウント削除 |
| GET | `/users/me/providers` | Bearer | 認証プロバイダー一覧 |
| DELETE | `/users/me/providers/{provider}` | Bearer | 認証プロバイダー解除 |
| GET | `/users/me/history` | Bearer | 自分のログイン履歴 |
| GET | `/admin/users` | Bearer（管理者） | ユーザー一覧 |
| GET | `/admin/users/{uuid}` | Bearer（管理者） | ユーザー詳細 |
| DELETE | `/admin/users/{uuid}` | Bearer（管理者） | ユーザー強制削除 |
| POST | `/admin/users/{uuid}/tags` | Bearer（管理者） | タグ付与 |
| DELETE | `/admin/users/{uuid}/tags/{tag}` | Bearer（管理者） | タグ削除 |
| POST | `/admin/users/{uuid}/groups` | Bearer（管理者） | グループ付与 |
| DELETE | `/admin/users/{uuid}/groups/{group}` | Bearer（管理者） | グループ削除 |
| GET | `/admin/history` | Bearer（管理者） | 全ユーザー履歴 |
| GET | `/admin/stats` | Bearer（管理者） | ダッシュボード統計 |
| GET | `/admin/apps` | Bearer（管理者） | アプリ一覧 |
| POST | `/admin/apps` | Bearer（管理者） | アプリ登録 |
| DELETE | `/admin/apps/{app_id}` | Bearer（管理者） | アプリ削除 |
