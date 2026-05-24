| メソッド | パス | 機能名 | 概要 |
|---|---|---|---|
| POST | `/auth/mail` | OTPコード送信 | 指定メールアドレスに6桁コードを発行・送信する。同一メールの未使用コードは無効化してから新規発行する |
| POST | `/auth/mail/verify` | OTPコード検証・セッション開始 | コード照合後、既存ユーザーはログイン、新規メールアドレスはアカウント登録してアクセストークン・リフレッシュトークンを返す |
| GET | `/auth/google` | Google OAuthリダイレクト | Google の認可URLへリダイレクトする |
| GET | `/auth/google/callback` | Google OAuthコールバック処理 | Googleから返されたコードを検証し、既存ユーザーはログイン、新規はアカウント登録してトークンを返す |
| GET | `/auth/github` | GitHub OAuthリダイレクト | GitHub の認可URLへリダイレクトする |
| GET | `/auth/github/callback` | GitHub OAuthコールバック処理 | GitHubから返されたコードを検証し、既存ユーザーはログイン、新規はアカウント登録してトークンを返す |
| POST | `/auth/token/access` | アクセストークン確認 | アクセストークンの有効性を判定し、有効な場合はユーザー情報を返す |
| POST | `/auth/token/refresh` | アクセストークン更新 | 有効なリフレッシュトークンを受け取り、新しいアクセストークン・リフレッシュトークンを発行する |
| POST | `/auth/logout` | ログアウト | 現在のトークンを失効（`revoked_at` を設定）し、`user_events` に `logout` イベントを記録する |
| GET | `/me` | 自分のユーザー情報取得 | `central_id` `public_id` `user_name` `display_name` `created_at` を返す |
| PATCH | `/me` | 自分のユーザー情報更新 | `user_name` / `display_name` を変更する。変更時は対応する `_updated_at` も更新する |
| DELETE | `/me` | 退会 | `deleted_at` を設定して論理削除する。CASCADE により関連レコードも削除される |
| GET | `/users` | ユーザー一覧取得 | グループ・タグ・キーワードでフィルタリング可能。ページネーション対応 |
| GET | `/users/{central_id}` | 指定ユーザー情報取得 | 指定ユーザーの情報を取得する |
| GET | `/users/me/profile` | 自分のプロフィール取得 | `user_profiles` のレコードが存在しない場合は空オブジェクトを返す |
| PUT | `/users/me/profile` | 自分のプロフィール更新 | `full_name` `country` `region` `bio` `social_account_1〜4` を更新する。初回はINSERT |
| GET | `/users/me/auth-providers` | 認証手段一覧取得 | `provider_type` と `id` の一覧を返す（`credential` は返さない） |
| POST | `/users/me/auth-providers` | 認証手段追加 | OTPまたはOAuth経由で本人確認を完了したうえで `user_auth_providers` にレコードを追加し `auth_provider_add` イベントを記録する |
| DELETE | `/users/me/auth-providers/{provider_type}` | 認証手段削除 | 最後の1件の場合は削除不可とする。削除後に `auth_provider_remove` イベントを記録する |
| GET | `/users/{central_id}/groups` | グループ一覧取得 | 指定ユーザーのグループ一覧を取得する |
| POST | `/users/{central_id}/groups` | グループ付与 | `group` 名を指定して `user_groups` にレコードを追加する |
| DELETE | `/users/{central_id}/groups/{group}` | グループ削除 | 指定グループを `user_groups` から削除する |
| GET | `/users/{central_id}/tags` | タグ一覧取得 | 指定ユーザーのタグ一覧を取得する |
| POST | `/users/{central_id}/tags` | タグ付与 | `tag` 名を指定して `user_tags` にレコードを追加する |
| DELETE | `/users/{central_id}/tags/{tag}` | タグ削除 | 指定タグを `user_tags` から削除する |
| GET | `/users/me/events` | 自分のイベント履歴取得 | `event_type` `service_id` `ip_address` `created_at` 等を返す。ページネーション対応 |
| GET | `/users/{central_id}/events` | 指定ユーザーのイベント履歴取得 | `event_type` によるフィルタリング・ページネーション対応 |
| GET | `/services` | サービス一覧取得 | 登録されているサービスの一覧を取得する |
| POST | `/services` | サービス登録 | `name` を受け取りUUIDを発行して `services` に登録する |
| GET | `/services/{id}` | サービス詳細取得 | 指定サービスの詳細情報を取得する |
| DELETE | `/services/{id}` | サービス削除 | 指定サービスを削除する。関連する `user_events.service_id` は SET NULL となる |
