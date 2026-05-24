# セントラルID API 仕様書（概要）

## 前提

- **サブドメイン設計：** パス先頭に `/api` `/v1` は付けない（サブドメインで分離）
- **認証方式：** Bearer トークン（アクセストークン）
- **権限区分：** `公開`（認証不要）/ `ユーザー`（アクセストークン必須）/ `管理者`（管理者権限必須）

---

## API 一覧

### 認証（Auth）

| # | メソッド | パス | 権限 | 機能概要 |
|---|---|---|---|---|
| 1 | POST | `/auth/mail` | 公開 | OTPコード送信。指定メールアドレスに6桁コードを発行・送信する。同一メールの未使用コードは無効化してから新規発行する |
| 2 | POST | `/auth/mail/otp` | 公開 | OTPコード再送信。既存の未使用コードを無効化し新しいコードを発行・再送信する。一定時間内の連続再送を制限する |
| 3 | POST | `/auth/mail/verify` | 公開 | OTPコード検証・セッション開始。コード照合後、既存ユーザーはログイン、新規メールアドレスはアカウント登録してアクセストークン・リフレッシュトークンを返す |
| 4 | GET | `/auth/google` | 公開 | Google OAuthリダイレクト。Google の認可URLへリダイレクトする |
| 5 | GET | `/auth/google/callback` | 公開 | Google OAuthコールバック処理。Googleから返されたコードを検証し、既存ユーザーはログイン、新規はアカウント登録してトークンを返す |
| 6 | GET | `/auth/github` | 公開 | GitHub OAuthリダイレクト。GitHub の認可URLへリダイレクトする |
| 7 | GET | `/auth/github/callback` | 公開 | GitHub OAuthコールバック処理。GitHubから返されたコードを検証し、既存ユーザーはログイン、新規はアカウント登録してトークンを返す |
| 8 | POST | `/auth/token/access` | 公開 | アクセストークン確認。アクセストークンの有効性を判定し、有効な場合はユーザー情報を返す |
| 9 | POST | `/auth/token/refresh` | 公開 | アクセストークン更新。有効なリフレッシュトークンを受け取り、新しいアクセストークン・リフレッシュトークンを発行する |
| 10 | POST | `/auth/logout` | ユーザー | ログアウト。現在のトークンを失効（`revoked_at` を設定）し、`user_events` に `logout` イベントを記録する |

---

### ユーザー（Users）

| # | メソッド | パス | 権限 | 機能概要 |
|---|---|---|---|---|
| 7 | GET | `/users/me` | ユーザー | 自分のユーザー情報取得。`central_id` `public_id` `user_name` `display_name` `created_at` を返す |
| 8 | PATCH | `/users/me` | ユーザー | 自分のユーザー情報更新。`user_name` / `display_name` を変更する。変更時は対応する `_updated_at` も更新する |
| 9 | DELETE | `/users/me` | ユーザー | 退会。`deleted_at` を設定して論理削除する。CASCADE により関連レコードも削除される |
| 10 | GET | `/users` | 管理者 | ユーザー一覧取得。グループ・タグ・キーワードでフィルタリング可能。ページネーション対応 |
| 11 | GET | `/users/{central_id}` | 管理者 | 指定ユーザーの情報取得 |

---

### プロフィール（Profile）

| # | メソッド | パス | 権限 | 機能概要 |
|---|---|---|---|---|
| 12 | GET | `/users/me/profile` | ユーザー | 自分のプロフィール取得。`user_profiles` のレコードが存在しない場合は空オブジェクトを返す |
| 13 | PUT | `/users/me/profile` | ユーザー | 自分のプロフィール更新（upsert）。`full_name` `country` `region` `bio` `social_account_1〜4` を更新する。初回はINSERT |

---

### 認証手段（Auth Providers）

| # | メソッド | パス | 権限 | 機能概要 |
|---|---|---|---|---|
| 14 | GET | `/users/me/auth-providers` | ユーザー | 自分の認証手段一覧取得。`provider_type` と `id` の一覧を返す（`credential` は返さない） |
| 15 | POST | `/users/me/auth-providers` | ユーザー | 認証手段追加。OTPまたはOAuth経由で本人確認を完了したうえで `user_auth_providers` にレコードを追加し `auth_provider_add` イベントを記録する |
| 16 | DELETE | `/users/me/auth-providers/{provider_type}` | ユーザー | 認証手段削除。最後の1件の場合は削除不可とする。削除後に `auth_provider_remove` イベントを記録する |

---

### グループ（Groups）

| # | メソッド | パス | 権限 | 機能概要 |
|---|---|---|---|---|
| 17 | GET | `/users/{central_id}/groups` | 管理者 | 指定ユーザーのグループ一覧取得 |
| 18 | POST | `/users/{central_id}/groups` | 管理者 | グループ付与。`group` 名を指定して `user_groups` にレコードを追加する |
| 19 | DELETE | `/users/{central_id}/groups/{group}` | 管理者 | グループ削除。指定グループを `user_groups` から削除する |

---

### タグ（Tags）

| # | メソッド | パス | 権限 | 機能概要 |
|---|---|---|---|---|
| 20 | GET | `/users/{central_id}/tags` | 管理者 | 指定ユーザーのタグ一覧取得 |
| 21 | POST | `/users/{central_id}/tags` | 管理者 | タグ付与。`tag` 名を指定して `user_tags` にレコードを追加する |
| 22 | DELETE | `/users/{central_id}/tags/{tag}` | 管理者 | タグ削除。指定タグを `user_tags` から削除する |

---

### イベント履歴（Events）

| # | メソッド | パス | 権限 | 機能概要 |
|---|---|---|---|---|
| 23 | GET | `/users/me/events` | ユーザー | 自分のイベント履歴取得。`event_type` `service_id` `ip_address` `created_at` 等を返す。ページネーション対応 |
| 24 | GET | `/users/{central_id}/events` | 管理者 | 指定ユーザーのイベント履歴取得。`event_type` によるフィルタリング・ページネーション対応 |

---

### サービス（Services）

| # | メソッド | パス | 権限 | 機能概要 |
|---|---|---|---|---|
| 25 | GET | `/services` | 管理者 | サービス一覧取得 |
| 26 | POST | `/services` | 管理者 | サービス登録。`name` を受け取り UUID を発行して `services` に登録する |
| 27 | GET | `/services/{id}` | 管理者 | 指定サービスの詳細取得 |
| 28 | DELETE | `/services/{id}` | 管理者 | サービス削除。関連する `user_events.service_id` は SET NULL となる |
