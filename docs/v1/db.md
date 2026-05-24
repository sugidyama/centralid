# セントラルID データベース仕様書

## 前提

- **DBMS:** MySQL 8.0 以上
- **ストレージエンジン:** InnoDB
- **文字コード:** `utf8mb4`
- **照合順序:** `utf8mb4_unicode_ci`
- **タイムゾーン:** アプリケーション側で UTC に統一し、DATETIME 型で保存

---

## テーブル一覧

| テーブル名 | 概要 |
|---|---|
| `apps` | 連携アプリ |
| `users` | セントラルIDアカウント |
| `auth_providers` | ユーザーに紐づく認証手段 |
| `one_time_codes` | メール認証用ワンタイムコード |
| `tokens` | アクセストークン・リフレッシュトークン |
| `login_histories` | ログイン・認証履歴 |
| `user_tags` | ユーザー分類タグ |
| `user_groups` | ユーザーグループ |
| `user_profiles` | ユーザープロフィール詳細 |

---

## ER 図

```
apps ──────────────────────────────────────────┐
 │id                                            │
 │                                              │
 └─< users                                      │
       │id (UUID)                               │
       │public_id (表示用連番)                  │
       │registered_app_id → apps.id             │
       │                                        │
       ├─< auth_providers                       │
       │     user_id → users.id                 │
       │     provider (email/google/...)        │
       │     provider_uid                       │
       │                                        │
       ├─< tokens                               │
       │     user_id → users.id                 │
       │                                        │
       ├─< login_histories                      │
       │     user_id → users.id                 │
       │     app_id → apps.id ──────────────────┘
       │
       ├─< user_tags
       │     user_id → users.id
       │
       ├─< user_groups
       │     user_id → users.id
       │
       └─1 user_profiles
             user_id → users.id（1対1）

one_time_codes（users とは直接紐づかない・メールアドレスで管理）
```

---

## DDL

### `apps`

連携アプリの登録情報。新規ユーザー登録時に登録元として参照される。

```sql
CREATE TABLE `apps` (
  `id`         CHAR(36)     NOT NULL COMMENT 'UUID',
  `name`       VARCHAR(255) NOT NULL COMMENT 'アプリ名',
  `created_at` DATETIME     NOT NULL COMMENT '登録日時',
  `updated_at` DATETIME     NOT NULL COMMENT '更新日時',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='連携アプリ';
```

---

### `users`

セントラルIDアカウントの本体。認証手段（メール・Google等）には依存しない。
ユーザーの識別子は UUID であり、メールアドレスはこのテーブルには持たない。

- `id`：システム内部 UUID。外部に露出しない。
- `public_id`：ユーザーに表示する連番ID。DB の AUTO_INCREMENT で自動採番。
- `username`：英数字・アンダースコアのみ。任意項目。
- `is_admin`：管理者フラグ。DB 直接操作で付与する。

```sql
CREATE TABLE `users` (
  `id`                 CHAR(36)         NOT NULL COMMENT 'UUID（主キー・システム内部ID）',
  `public_id`          BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT COMMENT '表示用連番ID',
  `username`           VARCHAR(100)         NULL DEFAULT NULL COMMENT 'ユーザーネーム（英数字アンダースコアのみ）',
  `registered_app_id`  CHAR(36)             NULL DEFAULT NULL COMMENT '登録元アプリID',
  `is_admin`           TINYINT(1)       NOT NULL DEFAULT 0 COMMENT '管理者フラグ',
  `created_at`         DATETIME         NOT NULL COMMENT '登録日時',
  `updated_at`         DATETIME         NOT NULL COMMENT '更新日時',
  `deleted_at`         DATETIME             NULL DEFAULT NULL COMMENT '論理削除日時',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_public_id`             (`public_id`),
  INDEX `idx_username`                  (`username`),
  INDEX `idx_registered_app_id`         (`registered_app_id`),
  INDEX `idx_created_at`                (`created_at`),
  CONSTRAINT `fk_users_registered_app_id`
    FOREIGN KEY (`registered_app_id`) REFERENCES `apps` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='セントラルIDアカウント';
```

---

### `auth_providers`

ユーザーに紐づく認証手段。1ユーザーが複数の認証手段を持てる。
メールアドレスは `provider = 'email'`、`provider_uid = メールアドレス` として格納する。

```sql
CREATE TABLE `auth_providers` (
  `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主キー',
  `user_id`      CHAR(36)        NOT NULL COMMENT 'ユーザーID',
  `provider`     VARCHAR(50)     NOT NULL COMMENT '認証手段種別（email / google / github 等）',
  `provider_uid` VARCHAR(255)    NOT NULL COMMENT 'プロバイダー側UID（email の場合はメールアドレス）',
  `created_at`   DATETIME        NOT NULL COMMENT '紐付け日時',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_provider_uid` (`provider`, `provider_uid`),
  INDEX `idx_user_id` (`user_id`),
  CONSTRAINT `fk_auth_providers_user_id`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='ユーザー認証手段';
```

---

### `one_time_codes`

メール認証で送信する6桁コードの管理。`users` とは直接紐づけず、メールアドレスで管理する。
コード照合成功後にアカウント照合・作成を行う。

- `attempts`：検証失敗回数。5回を超えると `MAX_ATTEMPTS_EXCEEDED` エラーとなる。

```sql
CREATE TABLE `one_time_codes` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主キー',
  `email`      VARCHAR(255)    NOT NULL COMMENT '送信先メールアドレス',
  `code`       CHAR(6)         NOT NULL COMMENT '6桁数字コード',
  `attempts`   TINYINT         NOT NULL DEFAULT 0 COMMENT '検証試行回数',
  `expires_at` DATETIME        NOT NULL COMMENT '有効期限',
  `used_at`    DATETIME            NULL DEFAULT NULL COMMENT '使用済み日時（NULL は未使用）',
  `created_at` DATETIME        NOT NULL COMMENT '発行日時',
  PRIMARY KEY (`id`),
  INDEX `idx_email`      (`email`),
  INDEX `idx_expires_at` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='メール認証ワンタイムコード';
```

---

### `tokens`

アクセストークンとリフレッシュトークンの管理。ログアウト時は `revoked_at` を設定して無効化する。

```sql
CREATE TABLE `tokens` (
  `id`                        BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主キー',
  `user_id`                   CHAR(36)        NOT NULL COMMENT 'ユーザーID',
  `access_token`              VARCHAR(512)    NOT NULL COMMENT 'アクセストークン',
  `refresh_token`             VARCHAR(512)    NOT NULL COMMENT 'リフレッシュトークン',
  `access_token_expires_at`   DATETIME        NOT NULL COMMENT 'アクセストークン有効期限',
  `refresh_token_expires_at`  DATETIME        NOT NULL COMMENT 'リフレッシュトークン有効期限',
  `revoked_at`                DATETIME            NULL DEFAULT NULL COMMENT '失効日時（ログアウト時に設定）',
  `created_at`                DATETIME        NOT NULL COMMENT '発行日時',
  PRIMARY KEY (`id`),
  INDEX `idx_user_id`                    (`user_id`),
  INDEX `idx_access_token`               (`access_token`(191)),
  INDEX `idx_refresh_token`              (`refresh_token`(191)),
  INDEX `idx_refresh_token_expires_at`   (`refresh_token_expires_at`),
  CONSTRAINT `fk_tokens_user_id`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='アクセス・リフレッシュトークン';
```

---

### `login_histories`

ログイン・認証の履歴。どのユーザーが、いつ、どこから、どのアプリで、どの認証手段でログインしたかを記録する。

```sql
CREATE TABLE `login_histories` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主キー',
  `user_id`    CHAR(36)        NOT NULL COMMENT 'ユーザーID',
  `app_id`     CHAR(36)            NULL DEFAULT NULL COMMENT '利用アプリID',
  `provider`   VARCHAR(50)     NOT NULL COMMENT '使用した認証手段（email / google 等）',
  `ip_address` VARCHAR(45)         NULL DEFAULT NULL COMMENT 'IPアドレス（IPv6対応）',
  `user_agent` TEXT                NULL DEFAULT NULL COMMENT 'ユーザーエージェント',
  `created_at` DATETIME        NOT NULL COMMENT 'ログイン日時',
  PRIMARY KEY (`id`),
  INDEX `idx_user_id`    (`user_id`),
  INDEX `idx_app_id`     (`app_id`),
  INDEX `idx_created_at` (`created_at`),
  CONSTRAINT `fk_login_histories_user_id`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_login_histories_app_id`
    FOREIGN KEY (`app_id`) REFERENCES `apps` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='ログイン・認証履歴';
```

---

### `user_tags`

管理者によるユーザー分類。1ユーザーに複数タグを付与できる。

```sql
CREATE TABLE `user_tags` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主キー',
  `user_id`    CHAR(36)        NOT NULL COMMENT 'ユーザーID',
  `tag`        VARCHAR(100)    NOT NULL COMMENT 'タグ名',
  `created_at` DATETIME        NOT NULL COMMENT '付与日時',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_user_tag` (`user_id`, `tag`),
  INDEX `idx_tag` (`tag`),
  CONSTRAINT `fk_user_tags_user_id`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='ユーザー分類タグ';
```

---

### `user_groups`

管理者によるユーザーグループ管理。1ユーザーに複数グループを付与できる。

```sql
CREATE TABLE `user_groups` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主キー',
  `user_id`    CHAR(36)        NOT NULL COMMENT 'ユーザーID',
  `group`      VARCHAR(100)    NOT NULL COMMENT 'グループ名',
  `created_at` DATETIME        NOT NULL COMMENT '付与日時',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_user_group` (`user_id`, `group`),
  INDEX `idx_group` (`group`),
  CONSTRAINT `fk_user_groups_user_id`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='ユーザーグループ';
```

---

### `user_profiles`

ユーザーが任意で登録するプロフィール詳細。1ユーザーに1レコード。未登録の場合はレコードが存在しない。

- `display_name`：日本語を含む任意の表示名（`username` とは別）。
- `social_account_1`〜`4`：URLやハンドルなど形式不問。

```sql
CREATE TABLE `user_profiles` (
  `id`               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主キー',
  `user_id`          CHAR(36)        NOT NULL COMMENT 'ユーザーID',
  `display_name`     VARCHAR(100)        NULL DEFAULT NULL COMMENT '表示名（日本語可）',
  `full_name`        VARCHAR(100)        NULL DEFAULT NULL COMMENT '本名',
  `country`          VARCHAR(100)        NULL DEFAULT NULL COMMENT '国',
  `region`           VARCHAR(100)        NULL DEFAULT NULL COMMENT '都道府県・州',
  `bio`              TEXT                NULL DEFAULT NULL COMMENT '自己紹介（最大500文字）',
  `social_account_1` VARCHAR(255)        NULL DEFAULT NULL COMMENT 'ソーシャルアカウント1',
  `social_account_2` VARCHAR(255)        NULL DEFAULT NULL COMMENT 'ソーシャルアカウント2',
  `social_account_3` VARCHAR(255)        NULL DEFAULT NULL COMMENT 'ソーシャルアカウント3',
  `social_account_4` VARCHAR(255)        NULL DEFAULT NULL COMMENT 'ソーシャルアカウント4',
  `created_at`       DATETIME        NOT NULL COMMENT '作成日時',
  `updated_at`       DATETIME        NOT NULL COMMENT '更新日時',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_user_id` (`user_id`),
  CONSTRAINT `fk_user_profiles_user_id`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='ユーザープロフィール';
```

---

## 設計上の注意点

### 文字コード
`utf8mb4` を全テーブルに適用する。MySQL の `utf8` は絵文字非対応のため使用しない。
照合順序は `utf8mb4_unicode_ci`（大文字小文字・アクセント記号を区別しない）。

### UUID の形式
`users.id` と `apps.id` は `CHAR(36)` 形式（`xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx`）。
アプリケーション側で UUID v4 を生成して INSERT する。

### users.public_id について
`public_id` は `BIGINT UNSIGNED AUTO_INCREMENT` で MySQL が自動採番する表示用ID。
`id`（UUID）はシステム内部専用とし、`public_id` をユーザー向けの識別子として使う。
`primary_id` ではなく `UNIQUE KEY` として定義することで、`id`（UUID）を主キーに保ちつつ採番できる。

### トークンの長さと INDEX
`VARCHAR(512)` のカラムに対する INDEX は `(191)` の前置インデックスを使用する。
`utf8mb4` では1文字最大4バイトのため、`191 × 4 = 764 bytes < 767 bytes`（InnoDB の INDEX 上限）に収まる。

### ON DELETE の方針
| 参照先削除時 | 参照テーブル | 方針 |
|---|---|---|
| `apps` 削除 | `users.registered_app_id` | SET NULL（ユーザーは残す） |
| `apps` 削除 | `login_histories.app_id` | SET NULL（履歴は残す） |
| `users` 削除 | auth_providers / tokens / login_histories / user_tags / user_groups / user_profiles | CASCADE（関連データも削除） |

### 論理削除
`users` テーブルのみ `deleted_at` による論理削除を採用。
`deleted_at IS NULL` を条件に加えることで有効ユーザーのみを取得する。

### `one_time_codes` のクリーンアップ
期限切れ・使用済みのレコードは定期バッチで削除する。
コード発行時に同一メールの未使用コードを `used_at = NOW()` で無効化してから新規発行する。
