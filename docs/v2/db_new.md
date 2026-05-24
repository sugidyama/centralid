# セントラルID データベース仕様書（改訂版）

## 前提

- **DBMS:** MySQL 8.0 以上
- **ストレージエンジン:** InnoDB
- **文字コード:** `utf8mb4`
- **照合順序:** `utf8mb4_unicode_ci`
- **タイムゾーン:** アプリケーション側で UTC に統一し、DATETIME 型で保存

---

## テーブル一覧

| 項番 | 区分 | 論理名 | 物理名 | 概要 |
|---|---|---|---|---|
| 1 | トランザクション | ユーザーテーブル | `users` | セントラルIDアカウントの本体 |
| 2 | トランザクション | ユーザープロフィールテーブル | `user_profiles` | ユーザーが任意で登録するプロフィール詳細 |
| 3 | トランザクション | ユーザー認証手段テーブル | `user_auth_providers` | ユーザーに紐づく認証手段（メール・Google等） |
| 4 | マスタ | ユーザーグループテーブル | `user_groups` | ユーザーへのグループ付与 |
| 5 | トランザクション | ユーザータグテーブル | `user_tags` | ユーザーへのタグ付与 |
| 6 | トランザクション | トークンテーブル | `tokens` | アクセス・リフレッシュトークンの管理 |
| 7 | トランザクション | ワンタイムパスワードテーブル | `one_time_passwords` | メール認証用ワンタイムパスワードの管理 |
| 9 | トランザクション | ユーザーイベントテーブル | `user_events` | 認証・操作イベントの記録 |
| 10 | トランザクション | OAuthワンタイムコードテーブル | `one_time_codes` | OAuthコールバック後に発行する一時認証コード |

---

## ER 図

```
services
  │id
  │
  └─< user_events
        central_id → users.central_id
        service_id → services.id

users
  │central_id (CHAR(40): 登録年-UUID)
  │public_id (表示用連番 AUTO_INCREMENT)
  │
  ├─< user_auth_providers
  │     ＊複合PK (central_id, provider_type)
  │     central_id → users.central_id
  │
  ├─< tokens
  │     central_id → users.central_id
  │
  ├─< user_events
  │     central_id → users.central_id
  │
  ├─< user_tags
  │     ＊複合PK (tag, central_id)
  │     central_id → users.central_id
  │
  ├─< user_groups
  │     ＊複合PK (group, central_id)
  │     central_id → users.central_id
  │
  └─1 user_profiles
        central_id → users.central_id（1対1）

one_time_passwords（users とは直接紐づかない・メールアドレスで管理）

one_time_codes
  central_id → users.central_id（OAuthコールバック完了時点でユーザーが確定）
```

---

## DDL

### `services`

連携サービスの登録情報。

```sql
CREATE TABLE `services` (
  `id`         CHAR(36)     NOT NULL COMMENT 'サービスID（UUID）',
  `name`       VARCHAR(255) NOT NULL COMMENT 'サービス名',
  `created_at` DATETIME     NOT NULL COMMENT '登録日時',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='利用サービス';
```

---

### `users`

セントラルIDアカウントの本体。認証手段（メール・Google等）には依存しない。
メールアドレスはこのテーブルには持たず、`user_auth_providers` で管理する。

- `central_id`：登録年と UUID を組み合わせた識別子（例：`2026-xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx`）。
- `public_id`：表示用連番ID。DB の AUTO_INCREMENT で自動採番。PK は `central_id`、`public_id` は UNIQUE KEY。
- `user_name`・`display_name`：任意項目（NULL 許可）。
- `user_name_updated_at`・`display_name_updated_at`：初回登録時は `created_at` と同値でアプリ側が INSERT する。

```sql
CREATE TABLE `users` (
  `central_id`              CHAR(40)        NOT NULL COMMENT 'セントラルID（登録年-UUID）',
  `public_id`               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '公開ID',
  `user_name`               VARCHAR(40)         NULL DEFAULT NULL COMMENT 'ユーザーネーム（英数字アンダースコア）',
  `display_name`            VARCHAR(40)         NULL DEFAULT NULL COMMENT '表示名',
  `user_name_updated_at`    DATETIME        NOT NULL COMMENT 'ユーザーネーム更新日',
  `display_name_updated_at` DATETIME        NOT NULL COMMENT '表示名更新日',
  `created_at`              DATETIME        NOT NULL COMMENT '登録日時',
  `deleted_at`              DATETIME            NULL DEFAULT NULL COMMENT '論理削除日時',
  PRIMARY KEY (`central_id`),
  UNIQUE KEY `uq_public_id`    (`public_id`),
  INDEX `idx_user_name`        (`user_name`),
  INDEX `idx_display_name`     (`display_name`),
  INDEX `idx_created_at`       (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='セントラルIDアカウント';
```

---

### `user_profiles`

ユーザーが任意で登録するプロフィール詳細。1ユーザーに1レコード。未登録の場合はレコードが存在しない。

> **設計メモ（型修正）:** 提供仕様では `central_id bigint unsigned AUTO_INCREMENT PRI` となっているが、`users.central_id` が `CHAR(40)` であるため型不整合が発生する。1対1関係の標準設計として `central_id CHAR(40) PRI FK` に修正した（AUTO_INCREMENT なし）。

```sql
CREATE TABLE `user_profiles` (
  `central_id`       CHAR(40)     NOT NULL COMMENT 'セントラルID（users への FK・1対1）',
  `full_name`        VARCHAR(100)     NULL DEFAULT NULL COMMENT '本名',
  `country`          VARCHAR(100)     NULL DEFAULT NULL COMMENT '国',
  `region`           VARCHAR(100)     NULL DEFAULT NULL COMMENT '都道府県・州',
  `bio`              TEXT             NULL DEFAULT NULL COMMENT '自己紹介（最大500文字）',
  `social_account_1` VARCHAR(255)     NULL DEFAULT NULL COMMENT 'ソーシャルアカウント1',
  `social_account_2` VARCHAR(255)     NULL DEFAULT NULL COMMENT 'ソーシャルアカウント2',
  `social_account_3` VARCHAR(255)     NULL DEFAULT NULL COMMENT 'ソーシャルアカウント3',
  `social_account_4` VARCHAR(255)     NULL DEFAULT NULL COMMENT 'ソーシャルアカウント4',
  `created_at`       DATETIME     NOT NULL COMMENT '作成日時',
  `updated_at`       DATETIME     NOT NULL COMMENT '更新日時',
  PRIMARY KEY (`central_id`),
  CONSTRAINT `fk_user_profiles_central_id`
    FOREIGN KEY (`central_id`) REFERENCES `users` (`central_id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='ユーザープロフィール';
```

---

### `user_auth_providers`

ユーザーに紐づく認証手段。複合主キー `(central_id, provider_type)` により、1ユーザーが同一手段を重複登録できない。
メールアドレスは `provider_type = 'email'`、`id = メールアドレス` として格納する。

```sql
CREATE TABLE `user_auth_providers` (
  `central_id`    CHAR(40)     NOT NULL COMMENT 'セントラルID',
  `provider_type` VARCHAR(50)  NOT NULL COMMENT '認証手段種別（email / google / github 等）',
  `id`            VARCHAR(255) NOT NULL COMMENT 'プロバイダー側ID（email の場合はメールアドレス）',
  `credential`    VARCHAR(255)     NULL DEFAULT NULL COMMENT 'プロバイダー側クレデンシャル（email の場合は NULL）',
  `created_at`    DATETIME     NOT NULL COMMENT '紐付け日時',
  PRIMARY KEY (`central_id`, `provider_type`),
  UNIQUE KEY `uq_provider_type_id` (`provider_type`, `id`(191)),
  CONSTRAINT `fk_user_auth_providers_central_id`
    FOREIGN KEY (`central_id`) REFERENCES `users` (`central_id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='ユーザー認証手段';
```

> **`uq_provider_type_id` について:** ログイン時に `(provider_type, id)` でユーザーを引くクエリが必須となるため、検索性能と一意性の両立のため UNIQUE KEY として追加した。

---

### `user_groups`

ユーザーへのグループ付与。複合主キー `(group, central_id)` で重複付与を防ぐ。
グループ名は値として直接持つ（グループマスタテーブルなし）。

```sql
CREATE TABLE `user_groups` (
  `group`      VARCHAR(100) NOT NULL COMMENT 'グループ名',
  `central_id` CHAR(40)     NOT NULL COMMENT 'セントラルID',
  `created_at` DATETIME     NOT NULL COMMENT '付与日時',
  PRIMARY KEY (`group`, `central_id`),
  INDEX `idx_central_id` (`central_id`),
  CONSTRAINT `fk_user_groups_central_id`
    FOREIGN KEY (`central_id`) REFERENCES `users` (`central_id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='ユーザーグループ付与';
```

---

### `user_tags`

ユーザーへのタグ付与。複合主キー `(tag, central_id)` で重複付与を防ぐ。

```sql
CREATE TABLE `user_tags` (
  `tag`        VARCHAR(100) NOT NULL COMMENT 'タグ名',
  `central_id` CHAR(40)     NOT NULL COMMENT 'セントラルID',
  `created_at` DATETIME     NOT NULL COMMENT '付与日時',
  PRIMARY KEY (`tag`, `central_id`),
  INDEX `idx_central_id` (`central_id`),
  CONSTRAINT `fk_user_tags_central_id`
    FOREIGN KEY (`central_id`) REFERENCES `users` (`central_id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='ユーザー分類タグ';
```

---

### `tokens`

アクセストークンとリフレッシュトークンの管理。ログアウト時は `revoked_at` を設定して無効化する。

```sql
CREATE TABLE `tokens` (
  `id`                       BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主キー',
  `central_id`               CHAR(40)        NOT NULL COMMENT 'セントラルID',
  `access_token`             VARCHAR(512)    NOT NULL COMMENT 'アクセストークン',
  `refresh_token`            VARCHAR(512)    NOT NULL COMMENT 'リフレッシュトークン',
  `access_token_expires_at`  DATETIME        NOT NULL COMMENT 'アクセストークン有効期限',
  `refresh_token_expires_at` DATETIME        NOT NULL COMMENT 'リフレッシュトークン有効期限',
  `revoked_at`               DATETIME            NULL DEFAULT NULL COMMENT '失効日時（ログアウト時に設定）',
  `created_at`               DATETIME        NOT NULL COMMENT '発行日時',
  PRIMARY KEY (`id`),
  INDEX `idx_central_id`               (`central_id`),
  INDEX `idx_access_token`             (`access_token`(191)),
  INDEX `idx_refresh_token`            (`refresh_token`(191)),
  INDEX `idx_refresh_token_expires_at` (`refresh_token_expires_at`),
  CONSTRAINT `fk_tokens_central_id`
    FOREIGN KEY (`central_id`) REFERENCES `users` (`central_id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='アクセス・リフレッシュトークン';
```

---

### `one_time_passwords`

メール認証で送信する6桁コードの管理。`users` とは直接紐づけず、メールアドレスで管理する。
コード照合成功後にアカウント照合・作成を行う。

- `attempt`：検証失敗回数。5回を超えると `MAX_ATTEMPTS_EXCEEDED` エラーとなる。

```sql
CREATE TABLE `one_time_passwords` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主キー',
  `email`      VARCHAR(255)    NOT NULL COMMENT '送信先メールアドレス',
  `code`       CHAR(6)         NOT NULL COMMENT '6桁数字コード',
  `attempt`   TINYINT         NOT NULL DEFAULT 0 COMMENT '検証試行回数',
  `expires_at` DATETIME        NOT NULL COMMENT '有効期限',
  `used_at`    DATETIME            NULL DEFAULT NULL COMMENT '使用済み日時（NULL は未使用）',
  `created_at` DATETIME        NOT NULL COMMENT '発行日時',
  PRIMARY KEY (`id`),
  INDEX `idx_email`      (`email`),
  INDEX `idx_expires_at` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='メール認証ワンタイムパスワード';
```

---

### `one_time_codes`

OAuth コールバック（Google / GitHub）完了後に発行する一時認証コード。
クライアントはこのコードを `POST /auth/token/exchange` に送信してアクセストークンを取得する。

- コールバック完了時点でユーザーが確定しているため `central_id` と紐づけて保存する。
- `used_at` が NULL のものだけ有効とし、交換後は即時 `used_at = NOW()` で無効化する。
- 有効期間は5分。期限切れ・使用済みレコードは定期バッチで削除する。

```sql
CREATE TABLE `one_time_codes` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主キー',
  `central_id` CHAR(40)        NOT NULL COMMENT 'セントラルID（OAuth完了時点で確定）',
  `auth_code`  VARCHAR(128)    NOT NULL COMMENT '一時認証コード（ランダム文字列）',
  `expires_at` DATETIME        NOT NULL COMMENT '有効期限（発行から5分）',
  `used_at`    DATETIME            NULL DEFAULT NULL COMMENT '使用済み日時（NULL は未使用）',
  `created_at` DATETIME        NOT NULL COMMENT '発行日時',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_auth_code`  (`auth_code`(128)),
  INDEX `idx_central_id`     (`central_id`),
  INDEX `idx_expires_at`     (`expires_at`),
  CONSTRAINT `fk_one_time_codes_central_id`
    FOREIGN KEY (`central_id`) REFERENCES `users` (`central_id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='OAuthワンタイムコード';
```

---

### `user_events`（設計案）

ユーザーの認証・操作イベントの記録。どのユーザーが、いつ、どのサービスで、何をしたかを記録する。
旧設計の `login_histories` を汎用イベントログとして再設計し、`event_type` で種別を区別する。

#### カラム定義

| # | コメント | 列名 | データ型 | NOT NULL | AI | KEY | DEFAULT |
|---|---|---|---|---|---|---|---|
| 1 | 主キー | `id` | bigint unsigned | TRUE | TRUE | PRI | [NULL] |
| 2 | セントラルID | `central_id` | char(40) | TRUE | FALSE | MUL | [NULL] |
| 3 | サービスID | `service_id` | char(36) | FALSE | FALSE | MUL | [NULL] |
| 4 | イベント種別 | `event_type` | varchar(50) | TRUE | FALSE | MUL | [NULL] |
| 5 | 認証手段種別 | `provider_type` | varchar(50) | FALSE | FALSE | — | [NULL] |
| 6 | IPアドレス | `ip_address` | varchar(45) | FALSE | FALSE | — | [NULL] |
| 7 | ユーザーエージェント | `user_agent` | text | FALSE | FALSE | — | [NULL] |
| 8 | 発生日時 | `created_at` | datetime | TRUE | FALSE | MUL | [NULL] |

#### `event_type` の種別定義

| 値 | 意味 | `provider_type` | `service_id` |
|---|---|---|---|
| `register` | 新規アカウント登録 | 登録に使用した認証手段 | 登録元サービス |
| `login` | ログイン成功 | 使用した認証手段 | ログイン先サービス |
| `logout` | ログアウト | NULL | ログアウト元サービス |
| `token_refresh` | アクセストークン更新 | NULL | 対象サービス |
| `auth_provider_add` | 認証手段の追加 | 追加した認証手段 | NULL |
| `auth_provider_remove` | 認証手段の削除 | 削除した認証手段 | NULL |

#### DDL

```sql
CREATE TABLE `user_events` (
  `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主キー',
  `central_id`    CHAR(40)        NOT NULL COMMENT 'セントラルID',
  `service_id`    CHAR(36)            NULL DEFAULT NULL COMMENT 'サービスID',
  `event_type`    VARCHAR(50)     NOT NULL COMMENT 'イベント種別（register / login / logout / token_refresh 等）',
  `provider_type` VARCHAR(50)         NULL DEFAULT NULL COMMENT '認証手段種別（login / register イベント時のみ）',
  `ip_address`    VARCHAR(45)         NULL DEFAULT NULL COMMENT 'IPアドレス（IPv6対応）',
  `user_agent`    TEXT                NULL DEFAULT NULL COMMENT 'ユーザーエージェント',
  `created_at`    DATETIME        NOT NULL COMMENT '発生日時',
  PRIMARY KEY (`id`),
  INDEX `idx_central_id` (`central_id`),
  INDEX `idx_service_id` (`service_id`),
  INDEX `idx_event_type` (`event_type`),
  INDEX `idx_created_at` (`created_at`),
  CONSTRAINT `fk_user_events_central_id`
    FOREIGN KEY (`central_id`) REFERENCES `users` (`central_id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_user_events_service_id`
    FOREIGN KEY (`service_id`) REFERENCES `services` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='ユーザーイベント（認証・操作履歴）';
```

---

## 設計上の注意点

### `central_id` の形式
`users.central_id` は `CHAR(40)` 形式（登録年-UUID、例：`2026-xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx`）。
アプリケーション側で生成して INSERT する。

### `users.public_id` について
`BIGINT UNSIGNED AUTO_INCREMENT` で MySQL が自動採番する表示用ID。
`central_id` が主キー、`public_id` は UNIQUE KEY として定義することで両立させる。

### `user_profiles` の PK について
`users` との1対1関係において `central_id` を PK かつ FK とする設計を採用する。
`users` レコード削除時に CASCADE で `user_profiles` も削除される。

### `user_auth_providers` の UNIQUE KEY について
ログイン時は `(provider_type, id)` でユーザーを検索するため、`uq_provider_type_id` を付与する。
これにより同一プロバイダーアカウントを複数ユーザーに紐づけることも防止できる。

### トークンの長さと INDEX
`VARCHAR(512)` に対する INDEX は `(191)` の前置インデックスを使用する。
`utf8mb4` では1文字最大4バイトのため、`191 × 4 = 764 bytes < 767 bytes`（InnoDB の INDEX 上限）に収まる。

### ON DELETE の方針
| 参照先削除時 | 参照テーブル・カラム | 方針 |
|---|---|---|
| `services` 削除 | `user_events.service_id` | SET NULL（イベント履歴は残す） |
| `users` 削除 | `user_profiles.central_id` | CASCADE |
| `users` 削除 | `user_auth_providers.central_id` | CASCADE |
| `users` 削除 | `user_groups.central_id` | CASCADE |
| `users` 削除 | `user_tags.central_id` | CASCADE |
| `users` 削除 | `tokens.central_id` | CASCADE |
| `users` 削除 | `user_events.central_id` | CASCADE |
| `users` 削除 | `one_time_codes.central_id` | CASCADE |

### 論理削除
`users` テーブルのみ `deleted_at` による論理削除を採用。
クエリには常に `deleted_at IS NULL` を加えて有効ユーザーのみを対象とする。

### `one_time_passwords` のクリーンアップ
期限切れ・使用済みのレコードは定期バッチで削除する。
コード発行時に同一メールの未使用コードを `used_at = NOW()` で無効化してから新規発行する。

### `one_time_codes` のクリーンアップ
期限切れ・使用済みのレコードは定期バッチで削除する。
1ユーザーにつき同時に発行できる有効なコードは原則1件とし、コールバック処理時に既存の未使用コードを `used_at = NOW()` で無効化してから新規発行する。

### `users` の登録元サービス記録
旧設計の `registered_app_id` は廃止し、`user_events` に `event_type = 'register'`、`service_id = 登録元サービスID` のレコードとして記録する設計に変更した。
