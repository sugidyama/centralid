# セントラルID データベース仕様書 v2

## 前提

- **DBMS:** MySQL 8.0 以上
- **ストレージエンジン:** InnoDB
- **文字コード:** `utf8mb4`
- **照合順序:** `utf8mb4_unicode_ci`
- **タイムゾーン:** アプリケーション側で UTC に統一し、`DATETIME` 型で保存

---

## テーブル一覧

| 項番 | 区分 | 論理名 | 物理名 | 概要 |
|---|---|---|---|---|
| 1 | トランザクション | 顧客テーブル | `users` | セントラルIDアカウントの本体 |
| 2 | マスタ | 顧客タグテーブル | `user_tags` | 顧客へのタグ付与 |
| 3 | トランザクション | 顧客プロフィールテーブル | `user_profiles` | 顧客が任意で登録するプロフィール詳細 |
| 4 | トランザクション | 顧客アイデンティティテーブル | `user_identities` | 顧客に紐づく外部認証手段（メール・Google等） |
| 5 | マスタ | 顧客グループテーブル | `user_groups` | 顧客へのグループ付与 |
| 6 | トランザクション | 顧客イベントテーブル | `user_events` | 認証・操作履歴 |
| 7 | トランザクション | トークンテーブル | `tokens` | アクセストークン・リフレッシュトークンの管理 |
| 8 | トランザクション | ワンタイムステートテーブル | `one_time_states` | 外部認証フローのCSRF防止用ステート |
| 9 | トランザクション | ワンタイムパスワードテーブル | `one_time_passwords` | メール認証用ワンタイムパスワードの管理 |
| 10 | トランザクション | ワンタイムコードテーブル | `one_time_codes` | 外部認証コールバック後に発行する一時認証コード |
| 11 | マスタ | 設定テーブル | `configs` | EAV形式の共通設定 |

---

## ER 図

```
users
  │central_id (CHAR(40): 登録年-UUID)
  │public_id  (表示用連番 UNIQUE)
  │
  ├─1 user_profiles
  │     central_id → users.central_id（1対1）
  │
  ├─< user_identities
  │     PK (central_id, identity_type)
  │     central_id → users.central_id
  │
  ├─< user_groups
  │     PK (group_id, central_id)
  │     central_id → users.central_id
  │
  ├─< user_tags
  │     PK (tag_id, central_id)
  │     central_id → users.central_id
  │
  ├─< tokens
  │     central_id → users.central_id
  │
  ├─< user_events
  │     central_id → users.central_id
  │     service_id → configs（FKなし・文字列管理）
  │
  └─< one_time_codes
        central_id → users.central_id（OAuthコールバック完了時点でユーザーが確定）

one_time_passwords（users とは直接紐づかない・メールアドレスで管理）

one_time_states（独立・service_id はFKなし・文字列管理）

configs（独立マスタ・FK なし）
```

---

## テーブル定義

---

### `users`

セントラルIDアカウントの本体。認証手段（メール・Google等）には依存しない。
メールアドレスはこのテーブルには持たず、`user_identities` で管理する。

- `central_id`：登録年と UUID を組み合わせた識別子（例：`2026-xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx`）。アプリケーション側で生成して INSERT する。
- `public_id`：表示用連番。DB の AUTO_INCREMENT で自動採番。PK は `central_id`、`public_id` は UNIQUE。
- `user_name`・`display_name`：任意項目（NULL 許可）。
- `user_name_updated_at`・`display_name_updated_at`：初回登録時は `created_at` と同値でアプリ側が INSERT する。

| # | コメント | 列名 | データ型 | NOT NULL | AI | KEY | DEFAULT |
|---|---|---|---|---|---|---|---|
| 1 | セントラルID（登録年-UUID） | `central_id` | CHAR(40) | TRUE | FALSE | PRI | [NULL] |
| 2 | 公開ID | `public_id` | BIGINT UNSIGNED | TRUE | TRUE | UNI | [NULL] |
| 3 | ユーザーネーム（英数字アンダースコア） | `user_name` | VARCHAR(40) | FALSE | FALSE | MUL | [NULL] |
| 4 | ユーザーネーム更新日 | `user_name_updated_at` | DATETIME | TRUE | FALSE | — | [NULL] |
| 5 | 表示名 | `display_name` | VARCHAR(40) | FALSE | FALSE | MUL | [NULL] |
| 6 | 表示名更新日 | `display_name_updated_at` | DATETIME | TRUE | FALSE | — | [NULL] |
| 7 | 登録日時 | `created_at` | DATETIME | TRUE | FALSE | MUL | CURRENT_TIMESTAMP |
| 8 | 論理削除日時 | `deleted_at` | DATETIME | FALSE | FALSE | — | [NULL] |

---

### `user_profiles`

ユーザーが任意で登録するプロフィール詳細。1ユーザーに1レコード。未登録の場合はレコードが存在しない。

> **設計メモ（型修正）：** 提供仕様では `central_id bigint unsigned AUTO_INCREMENT PRI` となっているが、`users.central_id` が `CHAR(40)` であるため型不整合が発生する。1対1関係の標準設計として `central_id CHAR(40) PRI FK` に修正した（AUTO_INCREMENT なし）。

| # | コメント | 列名 | データ型 | NOT NULL | AI | KEY | DEFAULT |
|---|---|---|---|---|---|---|---|
| 1 | セントラルID | `central_id` | CHAR(40) | TRUE | FALSE | PRI | [NULL] |
| 2 | 本名 | `full_name` | VARCHAR(100) | FALSE | FALSE | — | [NULL] |
| 3 | 国 | `country` | VARCHAR(100) | FALSE | FALSE | — | [NULL] |
| 4 | 都道府県・州 | `region` | VARCHAR(100) | FALSE | FALSE | — | [NULL] |
| 5 | 自己紹介（最大500文字） | `bio` | TEXT | FALSE | FALSE | — | [NULL] |
| 6 | ソーシャルアカウント1 | `social_account_1` | VARCHAR(255) | FALSE | FALSE | — | [NULL] |
| 7 | ソーシャルアカウント2 | `social_account_2` | VARCHAR(255) | FALSE | FALSE | — | [NULL] |
| 8 | ソーシャルアカウント3 | `social_account_3` | VARCHAR(255) | FALSE | FALSE | — | [NULL] |
| 9 | ソーシャルアカウント4 | `social_account_4` | VARCHAR(255) | FALSE | FALSE | — | [NULL] |
| 10 | 作成日時 | `created_at` | DATETIME | TRUE | FALSE | — | CURRENT_TIMESTAMP |
| 11 | 更新日時 | `updated_at` | DATETIME | TRUE | FALSE | — | CURRENT_TIMESTAMP ON UPDATE |

---

### `user_identities`

ユーザーに紐づく認証手段。複合主キー `(central_id, identity_type)` により、1ユーザーが同一手段を重複登録できない。
メールアドレスは `identity_type = 'email'`、`identity = メールアドレス` として格納する。

> **設計メモ（型修正）：** 提供仕様では `central_id char(36)` となっているが、`users.central_id` が `CHAR(40)` であるため `CHAR(40)` に統一する。

| # | コメント | 列名 | データ型 | NOT NULL | AI | KEY | DEFAULT |
|---|---|---|---|---|---|---|---|
| 1 | セントラルID | `central_id` | CHAR(40) | TRUE | FALSE | PRI | [NULL] |
| 2 | アイデンティティ種別（email / google / github 等） | `identity_type` | VARCHAR(50) | TRUE | FALSE | PRI | [NULL] |
| 3 | アイデンティティ（email の場合はメールアドレス） | `identity` | VARCHAR(255) | TRUE | FALSE | MUL | [NULL] |
| 4 | クレデンシャル（email の場合は NULL） | `credential` | VARCHAR(255) | FALSE | FALSE | — | [NULL] |
| 5 | 紐付け日時 | `created_at` | DATETIME | TRUE | FALSE | — | CURRENT_TIMESTAMP |

> **`uq_identity_type_identity` について：** ログイン時に `(identity_type, identity)` でユーザーを引くクエリが必須となるため、検索性能と一意性の両立のため UNIQUE KEY として追加する。

---

### `user_groups`

ユーザーへのグループ付与。複合主キー `(group, central_id)` で重複付与を防ぐ。
グループ名は値として直接持つ（グループマスタテーブルなし）。

> **設計メモ（型修正）：** 提供仕様では `central_id char(36)` となっているが `CHAR(40)` に統一する。

| # | コメント | 列名 | データ型 | NOT NULL | AI | KEY | DEFAULT |
|---|---|---|---|---|---|---|---|
| 1 | グループ名 | `group_id` | VARCHAR(100) | TRUE | FALSE | PRI | [NULL] |
| 2 | セントラルID | `central_id` | CHAR(40) | TRUE | FALSE | PRI | [NULL] |
| 3 | 付与日時 | `created_at` | DATETIME | TRUE | FALSE | — | CURRENT_TIMESTAMP |

---

### `user_tags`

ユーザーへのタグ付与。複合主キー `(tag, central_id)` で重複付与を防ぐ。

> **設計メモ（型修正）：** 提供仕様では `central_id char(36)` となっているが `CHAR(40)` に統一する。

| # | コメント | 列名 | データ型 | NOT NULL | AI | KEY | DEFAULT |
|---|---|---|---|---|---|---|---|
| 1 | タグ名 | `tag_id` | VARCHAR(100) | TRUE | FALSE | PRI | [NULL] |
| 2 | セントラルID | `central_id` | CHAR(40) | TRUE | FALSE | PRI | [NULL] |
| 3 | 付与日時 | `created_at` | DATETIME | TRUE | FALSE | — | CURRENT_TIMESTAMP |

---

### `tokens`

アクセストークンとリフレッシュトークンの管理。ログアウト時は `revoked_at` を設定して無効化する。

| # | コメント | 列名 | データ型 | NOT NULL | AI | KEY | DEFAULT |
|---|---|---|---|---|---|---|---|
| 1 | 主キー | `id` | BIGINT UNSIGNED | TRUE | TRUE | PRI | [NULL] |
| 2 | セントラルID | `central_id` | CHAR(40) | TRUE | FALSE | MUL | [NULL] |
| 3 | アクセストークン | `access_token` | VARCHAR(512) | TRUE | FALSE | MUL | [NULL] |
| 4 | リフレッシュトークン | `refresh_token` | VARCHAR(512) | TRUE | FALSE | MUL | [NULL] |
| 5 | アクセストークン有効期限 | `access_token_expires_at` | DATETIME | TRUE | FALSE | — | [NULL] |
| 6 | リフレッシュトークン有効期限 | `refresh_token_expires_at` | DATETIME | TRUE | FALSE | MUL | [NULL] |
| 7 | 失効日時（ログアウト時に設定） | `revoked_at` | DATETIME | FALSE | FALSE | — | [NULL] |
| 8 | 発行日時 | `created_at` | DATETIME | TRUE | FALSE | — | CURRENT_TIMESTAMP |

---

### `one_time_passwords`

メール認証で送信する6桁コードの管理。`users` とは直接紐づけず、メールアドレスで管理する。
コード照合成功後にアカウント照合・作成を行う。

- `POST /auth/mail` で新規発行、`PUT /auth/mail` で再送信、`POST /auth/mail/login` で照合する。
- `attempts`：検証失敗回数。5回以上で `MAX_ATTEMPTS_EXCEEDED` エラー。
- コード発行時に同一メールの未使用コードを `used_at = NOW()` で無効化してから新規発行する。

| # | コメント | 列名 | データ型 | NOT NULL | AI | KEY | DEFAULT |
|---|---|---|---|---|---|---|---|
| 1 | 主キー | `id` | BIGINT UNSIGNED | TRUE | TRUE | PRI | [NULL] |
| 2 | 送信先メールアドレス | `email` | VARCHAR(255) | TRUE | FALSE | MUL | [NULL] |
| 3 | 6桁数字コード | `code` | CHAR(6) | TRUE | FALSE | — | [NULL] |
| 4 | 検証試行回数 | `attempts` | TINYINT | TRUE | FALSE | — | 0 |
| 5 | 有効期限 | `expires_at` | DATETIME | TRUE | FALSE | MUL | [NULL] |
| 6 | 使用済み日時（NULL は未使用） | `used_at` | DATETIME | FALSE | FALSE | — | [NULL] |
| 7 | 発行日時 | `created_at` | DATETIME | TRUE | FALSE | — | CURRENT_TIMESTAMP |

---

### `one_time_codes`

OAuth コールバック（Google / GitHub）完了後に発行する一時認証コード。
クライアントはこのコードを `POST /oauth/{identity}/login` に送信してアクセストークンを取得する。

- コールバック完了時点でユーザーが確定しているため `central_id` と紐づけて保存する。
- `used_at` が NULL のものだけ有効とし、交換後は即時 `used_at = NOW()` で無効化する。
- 有効期間は5分。期限切れ・使用済みレコードは定期バッチで削除する。

| # | コメント | 列名 | データ型 | NOT NULL | AI | KEY | DEFAULT |
|---|---|---|---|---|---|---|---|
| 1 | 主キー | `id` | BIGINT UNSIGNED | TRUE | TRUE | PRI | [NULL] |
| 2 | セントラルID（OAuth完了時点で確定） | `central_id` | CHAR(40) | TRUE | FALSE | MUL | [NULL] |
| 3 | 一時認証コード（ランダム文字列） | `auth_code` | VARCHAR(128) | TRUE | FALSE | UNI | [NULL] |
| 4 | 有効期限（発行から5分） | `expires_at` | DATETIME | TRUE | FALSE | MUL | [NULL] |
| 5 | 使用済み日時（NULL は未使用） | `used_at` | DATETIME | FALSE | FALSE | — | [NULL] |
| 6 | 発行日時 | `created_at` | DATETIME | TRUE | FALSE | — | CURRENT_TIMESTAMP |

---

### `one_time_states`

OAuth 認証フローのCSRF防止用ステート。
`GET /oauth/{identity}` 時に生成し、`GET /oauth/{identity}/callback` 時に照合・削除する。

- `(state, provider)` の複合ユニークにより、同一ステート値を異なるプロバイダーで再利用できる。
- `service_id` はサービス識別子の文字列。FK は持たず、`configs` で管理する値と照合する。
- 有効期間は10分。期限切れレコードは定期バッチで削除する。

| # | コメント | 列名 | データ型 | NOT NULL | AI | KEY | DEFAULT |
|---|---|---|---|---|---|---|---|
| 1 | 主キー | `id` | BIGINT UNSIGNED | TRUE | TRUE | PRI | [NULL] |
| 2 | ステートトークン | `state` | VARCHAR(128) | TRUE | FALSE | — | [NULL] |
| 3 | プロバイダー（google / github 等） | `provider` | VARCHAR(20) | TRUE | FALSE | — | [NULL] |
| 4 | 認証後リダイレクトURI | `redirect_uri` | VARCHAR(512) | TRUE | FALSE | — | [NULL] |
| 5 | サービスID | `service_id` | VARCHAR(36) | FALSE | FALSE | — | [NULL] |
| 6 | 有効期限（発行から10分） | `expires_at` | DATETIME | TRUE | FALSE | — | [NULL] |
| 7 | 発行日時 | `created_at` | DATETIME | TRUE | FALSE | — | CURRENT_TIMESTAMP |
| — | 複合ユニーク | *(state, provider)* | — | — | — | UNI | — |

---

### `user_events`

ユーザーの認証・操作イベントの記録。どのユーザーが、いつ、どのサービスで、何をしたかを記録する。
`service_id` はサービス識別子の文字列。FK は持たず、`configs` で管理する値と照合する。

#### `event_type` 種別定義

| 値 | 意味 | `identity_type` | `service_id` |
|---|---|---|---|
| `register` | 新規アカウント登録 | 登録に使用した認証手段 | 登録元サービス |
| `login` | ログイン成功 | 使用した認証手段 | ログイン先サービス |
| `logout` | ログアウト（`DELETE /auth/token`） | NULL | ログアウト元サービス |
| `token_refresh` | アクセストークン更新（`PATCH /auth/token`） | NULL | 対象サービス |
| `auth_provider_add` | 認証手段の追加 | 追加した認証手段 | NULL |
| `auth_provider_remove` | 認証手段の削除 | 削除した認証手段 | NULL |

| # | コメント | 列名 | データ型 | NOT NULL | AI | KEY | DEFAULT |
|---|---|---|---|---|---|---|---|
| 1 | 主キー | `id` | BIGINT UNSIGNED | TRUE | TRUE | PRI | [NULL] |
| 2 | セントラルID | `central_id` | CHAR(40) | TRUE | FALSE | MUL | [NULL] |
| 3 | サービスID | `service_id` | VARCHAR(36) | FALSE | FALSE | MUL | [NULL] |
| 4 | イベント種別（register / login / logout 等） | `event_type` | VARCHAR(50) | TRUE | FALSE | MUL | [NULL] |
| 5 | 認証手段種別（login / register イベント時のみ） | `identity_type` | VARCHAR(50) | FALSE | FALSE | — | [NULL] |
| 6 | IPアドレス（IPv6対応） | `ip_address` | VARCHAR(45) | FALSE | FALSE | — | [NULL] |
| 7 | ユーザーエージェント | `user_agent` | TEXT | FALSE | FALSE | — | [NULL] |
| 8 | 発生日時 | `created_at` | DATETIME | TRUE | FALSE | MUL | CURRENT_TIMESTAMP |

---

### `configs`

EAV（Entity-Attribute-Value）形式の共通設定テーブル。
`config_name` をキーとして JSON 形式の設定値を格納する。
サービス一覧・グループ定義・国リスト等を管理する。

| # | コメント | 列名 | データ型 | NOT NULL | AI | KEY | DEFAULT |
|---|---|---|---|---|---|---|---|
| 1 | 主キー | `id` | BIGINT UNSIGNED | TRUE | TRUE | PRI | [NULL] |
| 2 | 設定名（例: services, groups, countries） | `config_name` | VARCHAR(191) | TRUE | FALSE | UNI | [NULL] |
| 3 | 設定値（JSON） | `config_value` | JSON | TRUE | FALSE | — | [NULL] |
| 4 | 作成日時 | `created_at` | TIMESTAMP | TRUE | FALSE | — | CURRENT_TIMESTAMP |
| 5 | 更新日時 | `updated_at` | TIMESTAMP | TRUE | FALSE | — | CURRENT_TIMESTAMP ON UPDATE |

---

## 設計上の注意点

### `central_id` の形式と長さ

`users.central_id` は `CHAR(40)` 形式（登録年4桁 + ハイフン + UUID36文字 = 合計41文字）。
アプリケーション側で生成して INSERT する。FK として参照する全テーブルの `central_id` カラムも `CHAR(40)` で統一する。

> **注：** 提供仕様書の `user_identities`・`user_groups`・`user_tags` では `char(36)` と記載されているが、FK 整合性のため `CHAR(40)` に修正した。

### `users.public_id` について

`BIGINT UNSIGNED AUTO_INCREMENT` で MySQL が自動採番する表示用ID。
`central_id` が主キー、`public_id` は UNIQUE KEY として定義することで両立させる。

### `user_profiles.central_id` について

`users` との1対1関係において `central_id` を PK かつ FK とする設計を採用する。
`users` レコード削除時に CASCADE で `user_profiles` も削除される。

### `user_identities` の UNIQUE KEY について

ログイン時は `(identity_type, identity)` でユーザーを検索するため、`uq_identity_type_identity` を付与する。
これにより同一プロバイダーアカウントを複数ユーザーに紐づけることも防止できる。

### トークンの長さと INDEX

`VARCHAR(512)` に対する INDEX は `(191)` の前置インデックスを使用する。
`utf8mb4` では1文字最大4バイトのため、`191 × 4 = 764 bytes < 767 bytes`（InnoDB の INDEX 上限）に収まる。

### `service_id` の管理方針

`user_events` および `one_time_states` の `service_id` は `configs` テーブルで管理するサービス一覧と照合する文字列カラムであり、DB レベルの FK は持たない。
サービスの追加・削除は `configs` の `config_name = 'services'` レコードの JSON を更新することで行う。

### ON DELETE の方針

| 参照先削除時 | 参照テーブル・カラム | 方針 |
|---|---|---|
| `users` 削除 | `user_profiles.central_id` | CASCADE |
| `users` 削除 | `user_identities.central_id` | CASCADE |
| `users` 削除 | `user_groups.central_id` | CASCADE |
| `users` 削除 | `user_tags.central_id` | CASCADE |
| `users` 削除 | `tokens.central_id` | CASCADE |
| `users` 削除 | `user_events.central_id` | CASCADE |
| `users` 削除 | `one_time_codes.central_id` | CASCADE |

### 論理削除

`users` テーブルのみ `deleted_at` による論理削除を採用する。
クエリには常に `WHERE deleted_at IS NULL` を加えて有効ユーザーのみを対象とする。

### 期限切れレコードのクリーンアップ

| テーブル | クリーンアップ対象 | 方式 |
|---|---|---|
| `one_time_passwords` | 期限切れ・使用済み | 定期バッチで削除 |
| `one_time_codes` | 期限切れ・使用済み | 定期バッチで削除 |
| `one_time_states` | 期限切れ | 定期バッチで削除 |
| `tokens` | 期限切れ・失効済み | 定期バッチで削除 |
