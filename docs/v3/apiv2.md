# セントラルID API 仕様書 v2

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
| 401 | `TOKEN_EXPIRED` | アクセストークン期限切れ（リフレッシュが必要） |
| 403 | `FORBIDDEN` | 権限なし |
| 404 | `NOT_FOUND` | リソース未存在 |
| 429 | `RATE_LIMIT_EXCEEDED` | レート制限超過 |
| 500 | `INTERNAL_SERVER_ERROR` | サーバー内部エラー |

### トークン仕様

| 種別 | 有効期間 | 用途 |
|---|---|---|
| アクセストークン | 15分 | API認証 |
| リフレッシュトークン | 30日 | アクセストークン更新 |

---

## エンドポイント一覧

| メソッド | パス | 機能 | 概要 |
|---|---|---|---|
| POST | `/auth/mail` | OTP発行・送信 | 指定メールアドレスに6桁コードを発行・送信する |
| PUT | `/auth/mail` | OTP再送信 | 既存コードを無効化して新規発行する（60秒制限あり） |
| POST | `/auth/mail/login` | OTP検証・ログイン | コード照合後、ログインまたは新規登録してトークンを返す |
| GET | `/oauth/{identity}` | OAuth認証画面へリダイレクト | 各プロバイダーの認可URLへリダイレクトする |
| GET | `/oauth/{identity}/callback` | OAuthコールバック受付 | 認証後に一時コードを発行してリダイレクトする |
| POST | `/oauth/{identity}/login` | 一時コードによるログイン | 一時コードをアクセストークンに交換する |
| GET | `/auth/token` | トークン検証・ユーザー情報取得 | アクセストークンを検証し、有効なら自分の情報を返す |
| PATCH | `/auth/token` | アクセストークン更新 | リフレッシュトークンで新しいアクセストークンを発行する |
| DELETE | `/auth/token` | ログアウト | トークンを無効化する |

---

## メールOTP認証

### POST /auth/mail　OTP発行・送信

指定メールアドレスに6桁のワンタイムコードを発行して送信する。
同一メールの未使用コードがある場合は無効化してから新規発行する。

**リクエストボディ**

| フィールド | 型 | 必須 | 説明 |
|---|---|---|---|
| `email` | string | ○ | 送信先メールアドレス |
| `service_id` | string | - | 呼び出し元サービスID（`services.id`）。`user_events` の記録に使用する |

```json
{
  "email": "user@example.com",
  "service_id": "lunchmap"
}
```

**レスポンス**

| ステータス | 説明 |
|---|---|
| 204 | 発行・送信成功 |
| 400 | `VALIDATION_ERROR` - メールアドレス形式不正 |
| 400 | `INVALID_SERVICE` - 存在しないサービスID |

---

### PUT /auth/mail　OTP再送信

既存の未使用コードを無効化し、新規コードを発行して再送信する。
前回の再送信から60秒以内は拒否される。

**リクエストボディ**

| フィールド | 型 | 必須 | 説明 |
|---|---|---|---|
| `email` | string | ○ | 送信先メールアドレス |
| `service_id` | string | - | 呼び出し元サービスID |

```json
{
  "email": "user@example.com"
}
```

**レスポンス**

| ステータス | 説明 |
|---|---|
| 204 | 再送信成功 |
| 400 | `RESEND_TOO_SOON` - 前回再送信から60秒未満 |
| 400 | `INVALID_SERVICE` - 存在しないサービスID |

---

### POST /auth/mail/login　OTP検証・ログイン

コードを照合し、一致した場合はアクセストークンとリフレッシュトークンを発行する。
メールアドレスに紐づくアカウントが存在しない場合は新規登録する。

**リクエストボディ**

| フィールド | 型 | 必須 | 説明 |
|---|---|---|---|
| `email` | string | ○ | メールアドレス |
| `code` | string | ○ | 6桁数字コード |
| `service_id` | string | - | 呼び出し元サービスID |

```json
{
  "email": "user@example.com",
  "code": "123456",
  "service_id": "lunchmap"
}
```

**レスポンス 200**

```json
{
  "success": true,
  "data": {
    "access_token": "...",
    "refresh_token": "...",
    "expires_in": 900,
    "is_new_user": false,
    "user": {
      "central_id": "2026-xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx",
      "public_id": 1001,
      "user_name": null,
      "display_name": null,
      "created_at": "2026-05-24T00:00:00Z"
    }
  }
}
```

| ステータス | 説明 |
|---|---|
| 200 | 検証成功・トークン発行 |
| 400 | `INVALID_CODE` - コード不一致または未発行 |
| 400 | `CODE_EXPIRED` - コード期限切れ |
| 400 | `MAX_ATTEMPTS_EXCEEDED` - 試行回数が5回以上 |

---

## 外部SNS認証（OAuth）

`{identity}` は `google` または `github` を指定する。

### GET /oauth/{identity}　認証画面へリダイレクト

CSRF防止用のステートを `one_time_states` に保存し、各プロバイダーの認可URLへリダイレクトする。

**クエリパラメータ**

| パラメータ | 型 | 必須 | 説明 |
|---|---|---|---|
| `redirect_uri` | string | ○ | 認証完了後のリダイレクト先URI |
| `service_id` | string | - | 呼び出し元サービスID |

**レスポンス**

| ステータス | 説明 |
|---|---|
| 302 | プロバイダーの認可URLへリダイレクト |
| 400 | `INVALID_SERVICE` - 存在しないサービスID |

---

### GET /oauth/{identity}/callback　コールバック受付

プロバイダーからのコールバックを受け付ける。
ステートを照合し、成功した場合は `one_time_codes` に一時コードを発行して `redirect_uri` へリダイレクトする。
アカウントが存在しない場合は新規登録する。

**クエリパラメータ**（プロバイダーから自動付与）

| パラメータ | 型 | 説明 |
|---|---|---|
| `code` | string | プロバイダーが発行した認可コード |
| `state` | string | CSRF防止用ステート |

**レスポンス**

| ステータス | 説明 |
|---|---|
| 302 | `{redirect_uri}?auth_code={code}` へリダイレクト |
| 400 | `INVALID_STATE` - ステート不一致・期限切れ |
| 500 | `INTERNAL_SERVER_ERROR` - プロバイダー通信失敗 |

---

### POST /oauth/{identity}/login　一時コードによるログイン

`callback` で発行された一時コードをアクセストークンに交換する。
コードは一度使用したら無効になる。

**リクエストボディ**

| フィールド | 型 | 必須 | 説明 |
|---|---|---|---|
| `auth_code` | string | ○ | コールバックで取得した一時コード |

```json
{
  "auth_code": "xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx"
}
```

**レスポンス 200**

```json
{
  "success": true,
  "data": {
    "access_token": "...",
    "refresh_token": "...",
    "expires_in": 900,
    "is_new_user": false,
    "user": {
      "central_id": "2026-xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx",
      "public_id": 1001,
      "user_name": null,
      "display_name": null,
      "created_at": "2026-05-24T00:00:00Z"
    }
  }
}
```

| ステータス | 説明 |
|---|---|
| 200 | トークン発行成功 |
| 400 | `INVALID_CODE` - 一時コード不一致・使用済み |
| 400 | `CODE_EXPIRED` - 一時コード期限切れ |

---

## セッション・トークン管理

### GET /auth/token　トークン検証・ユーザー情報取得

アクセストークンを検証し、有効な場合はログインユーザーの情報を返す。

**リクエストヘッダー**

```
Authorization: Bearer {access_token}
```

**レスポンス 200**

```json
{
  "success": true,
  "data": {
    "central_id": "2026-xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx",
    "public_id": 1001,
    "user_name": null,
    "display_name": null,
    "created_at": "2026-05-24T00:00:00Z"
  }
}
```

| ステータス | 説明 |
|---|---|
| 200 | トークン有効・ユーザー情報返却 |
| 401 | `UNAUTHORIZED` - トークン未提供・無効 |
| 401 | `TOKEN_EXPIRED` - アクセストークン期限切れ |

---

### PATCH /auth/token　アクセストークン更新

有効なリフレッシュトークンを受け取り、新しいアクセストークンとリフレッシュトークンを発行する（トークンローテーション）。
旧トークンは即時失効する。

**リクエストボディ**

| フィールド | 型 | 必須 | 説明 |
|---|---|---|---|
| `refresh_token` | string | ○ | 現在のリフレッシュトークン |

```json
{
  "refresh_token": "..."
}
```

**レスポンス 200**

```json
{
  "success": true,
  "data": {
    "access_token": "...",
    "refresh_token": "...",
    "expires_in": 900
  }
}
```

| ステータス | 説明 |
|---|---|
| 200 | トークン更新成功 |
| 401 | `UNAUTHORIZED` - リフレッシュトークン無効・失効済み |
| 401 | `TOKEN_EXPIRED` - リフレッシュトークン期限切れ |

---

### DELETE /auth/token　ログアウト

アクセストークンを失効させる（`revoked_at` を設定）。
`user_events` に `logout` イベントを記録する。

**リクエストヘッダー**

```
Authorization: Bearer {access_token}
```

**レスポンス**

| ステータス | 説明 |
|---|---|
| 204 | ログアウト成功 |
| 401 | `UNAUTHORIZED` - トークン未提供・無効 |
