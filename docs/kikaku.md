# セントラルID 企画書

## 概要

セントラルIDは、複数のサービスを横断して利用できる**共通ユーザー認証基盤**です。
「セントラルIDさえあれば、さまざまなサービスが使える」をコンセプトに、認証・ユーザー管理・イベント履歴の一元管理を提供します。

- **ベースURL**: `https://centralid.win`
- **バックエンド**: Laravel（PHP）/ MySQL
- **ホスティング**: セントラルID専用ドメインで各サービスと分離

---

## 認証の基本方針

- **パスワードは保存しない**
- **メールアドレスは認証手段のひとつ**であり、必須項目ではない
- メール認証では**6桁のワンタイムパスワード（OTP）**をメールに送り、コード入力でログインする（パスワードレス）
- ユーザーの主キーはセントラルIDが発行する**独自形式のID**（例: `2026-xxxx-xxxx-xxxx-xxxx`）であり、メールアドレスや外部IDには依存しない
- 認証手段（メール・Google・GitHub）は複数紐付け可能（`user_identities` テーブルで管理）

---

## 主要機能

### 1. 認証 API

外部サービスに対して認証機能をAPIとして提供します。

- **メールアドレス認証**（パスワードレス・6桁OTP）
- **ソーシャルログイン**（Google・GitHub、`identity` パラメータで切り替え）
- **セッション管理**
  - アクセストークン発行・検証（有効期限: 60分）
  - リフレッシュトークンによるトークンローテーション（有効期限: 30日）
  - セッション切れ時のエラーレスポンス
- **ログアウト**（トークン失効・`user_events` にイベント記録）

### 2. ユーザー管理

- ユーザー登録・照合（`findOrCreate` パターン）
- ユーザーネーム・表示名の管理
- **サービス連携記録**
  - `service_id`（任意）を渡すことで、どのサービス経由での認証かを `user_events` に記録
- **ユーザー分類**（`user_groups` / `user_tags` テーブルでグループ・タグ付け）
- **プロフィール拡張**（`user_profiles` テーブル: 氏名・国・地域・bio・SNSアカウント）

### 3. イベント履歴の記録

`user_events` テーブルにすべての認証活動を記録します。

- いつ発生したか（`created_at`）
- どのサービスで発生したか（`service_id`）
- どのイベントか（`event_type`: login / logout など）
- どの認証手段を使ったか（`identity_type`: email / google / github など）
- どこからのアクセスか（`ip_address` / `user_agent`）

### 4. 管理者向け管理ページ（検討中）

認証基盤の状況を可視化・操作するための管理UIを将来提供予定。

- ダッシュボード：ユーザー数の推移、ログイン数の推移など
- ユーザー一覧・検索・エクスポート
- ユーザー分類：グループ・タグの管理
- イベント履歴の閲覧

---

## 認証フロー

### メールOTP認証

```
1. POST /auth/mail        → OTP（6桁）をメール送信（service_id は任意）
2. PUT  /auth/mail        → OTP 再送信（前回発行から一定時間経過後のみ可）
3. POST /auth/mail/login  → OTPを検証し、アクセストークン + リフレッシュトークンを発行
                            初回登録の場合は is_new_user: true を返す
```

### OAuthソーシャル認証（Google / GitHub）

OAuth は 2 ステップで完結します（ブラウザリダイレクト + サーバー間通信）。

```
1. GET  /oauth/{google|github}           → OAuth プロバイダーの認証画面へリダイレクト
                                            （redirect_uri 必須、service_id は任意）
                                            一時ステートを one_time_states テーブルに保存

2. GET  /oauth/{google|github}/callback  → OAuth コールバック受付
                                            ステートを検証し、ユーザーを登録または照合
                                            一時コード（auth_code）を発行して redirect_uri へリダイレクト

3. POST /oauth/{google|github}/login     → auth_code をトークンに交換
                                            アクセストークン + リフレッシュトークンを発行
```

### セッション管理

```
通常アクセス:
  Authorization: Bearer {access_token} でリクエスト
  GET /auth/token でユーザー情報と有効性を確認

アクセストークン期限切れ（60分）:
  PATCH /auth/token にリフレッシュトークンを渡してローテーション
  → 旧トークンは即時失効、新しいトークンペアを発行

リフレッシュトークン期限切れ（30日）:
  TOKEN_EXPIRED エラーを返す → 再ログインを要求

ログアウト:
  DELETE /auth/token でトークンを失効（revoked_at を記録）
```

---

## データ設計

| テーブル | 主なカラム | 用途 |
|---|---|---|
| `users` | `central_id`（PK）、`public_id`、`user_name`、`display_name` | ユーザー基本情報 |
| `user_profiles` | `central_id`（PK）、`full_name`、`country`、`region`、`bio`、`social_account_1〜4` | ユーザー拡張プロフィール |
| `user_identities` | `central_id`、`identity_type`（email/google/github）、`identity`、`credential` | 認証手段の紐付け |
| `user_groups` | `group_id`、`central_id` | グループ分類 |
| `user_tags` | `tag_id`、`central_id` | タグ分類 |
| `tokens` | `central_id`、`access_token`、`refresh_token`、有効期限、`revoked_at` | トークン管理 |
| `one_time_passwords` | `email`、`code`（6桁）、`attempts`、`expires_at`、`used_at` | メールOTP |
| `one_time_codes` | `central_id`、`auth_code`、`expires_at`、`used_at` | OAuthコールバック用一時コード |
| `one_time_states` | `state`、`provider`、`redirect_uri`、`service_id`、`expires_at` | OAuthステート管理 |
| `user_events` | `central_id`、`service_id`、`event_type`、`identity_type`、`ip_address`、`user_agent` | イベント履歴 |
| `configs` | `config_name`、`config_value`（JSON） | システム設定（サービス登録など） |

### `central_id` の形式

```
{year}-{uuid-like-string}
例: 2026-xxxx-xxxx-xxxx-xxxx
```

ユーザー登録年を先頭に付与した独自形式。外部に公開する識別子としては `public_id`（連番整数）を使用する。

---

## API レスポンス形式

### ログイン成功時

```json
{
  "access_token":  "xxxxxxxx...（64文字）",
  "refresh_token": "xxxxxxxx...（64文字）",
  "expires_in":    3600,
  "is_new_user":   false,
  "user": {
    "central_id":   "2026-xxxx-xxxx-xxxx-xxxx",
    "public_id":    1001,
    "user_name":    "sugichan",
    "display_name": "Sugi",
    "created_at":   "2026-01-01 00:00:00"
  }
}
```

> トークンはJWTではなく、ランダム64文字の不透明文字列（opaque token）としてDBに保存する。

### エラー時

```json
{ "error": "ERROR_CODE" }
```

主なエラーコード:

| コード | 意味 |
|---|---|
| `VALIDATION_ERROR` | リクエストパラメータが不正 |
| `INVALID_SERVICE` | service_id が登録されていない |
| `RESEND_TOO_SOON` | OTP 再送信の間隔が短すぎる |
| `INVALID_CODE` | OTP またはauth_code が不正 |
| `CODE_EXPIRED` | OTP またはauth_code が期限切れ |
| `INVALID_STATE` | OAuthステートが不正または期限切れ |
| `TOKEN_EXPIRED` | アクセストークンまたはリフレッシュトークンが期限切れ |

---

## 技術方針

- **バックエンド**: Laravel（PHP）
- **データベース**: MySQL
- **セッション・キャッシュ**: ファイルストレージ（`SESSION_DRIVER=file` / `CACHE_DRIVER=file`）
- **トークン形式**: ランダム64文字の不透明文字列（DBに保存・検証）
- **ORM禁止**: DBアクセスは `DB` ファサード + メソッドチェーンのみ使用

---

## 今後の検討事項

- 管理者向け管理ページの実装（フロントエンド技術選定含む）
- OTPの試行回数制限・ロックアウトポリシーの実装
- レート制限・ブルートフォース対策
- メールアドレスを後から追加・変更する際のフロー
- ログアウト範囲の選択（現デバイスのみ / 全デバイス）
- `user_events` を活用した利用履歴閲覧 API の提供
