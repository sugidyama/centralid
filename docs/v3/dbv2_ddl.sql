-- ============================================================
-- セントラルID データベース DDL v2
-- DBMS    : MySQL 8.0+
-- charset : utf8mb4 / utf8mb4_unicode_ci
-- ============================================================

-- ------------------------------------------------------------
-- users
-- ------------------------------------------------------------
CREATE TABLE `users` (
  `central_id`              CHAR(40)        NOT NULL COMMENT 'セントラルID（登録年-UUID）',
  `public_id`               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '公開ID',
  `user_name`               VARCHAR(40)         NULL DEFAULT NULL COMMENT 'ユーザーネーム（英数字アンダースコア）',
  `display_name`            VARCHAR(40)         NULL DEFAULT NULL COMMENT '表示名',
  `user_name_updated_at`    DATETIME        NOT NULL COMMENT 'ユーザーネーム更新日',
  `display_name_updated_at` DATETIME        NOT NULL COMMENT '表示名更新日',
  `created_at`              DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '登録日時',
  `deleted_at`              DATETIME            NULL DEFAULT NULL COMMENT '論理削除日時',
  PRIMARY KEY (`central_id`),
  UNIQUE KEY `uq_public_id`    (`public_id`),
  INDEX `idx_user_name`        (`user_name`),
  INDEX `idx_display_name`     (`display_name`),
  INDEX `idx_created_at`       (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='セントラルIDアカウント';

-- ------------------------------------------------------------
-- user_profiles
-- ------------------------------------------------------------
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
  `created_at`       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '作成日時',
  `updated_at`       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新日時',
  PRIMARY KEY (`central_id`),
  CONSTRAINT `fk_user_profiles_central_id`
    FOREIGN KEY (`central_id`) REFERENCES `users` (`central_id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='ユーザープロフィール';

-- ------------------------------------------------------------
-- user_identities
-- ------------------------------------------------------------
CREATE TABLE `user_identities` (
  `central_id`    CHAR(40)     NOT NULL COMMENT 'セントラルID',
  `identity_type` VARCHAR(50)  NOT NULL COMMENT 'アイデンティティ種別（email / google / github 等）',
  `identity`      VARCHAR(255) NOT NULL COMMENT 'アイデンティティ（email の場合はメールアドレス）',
  `credential`    VARCHAR(255)     NULL DEFAULT NULL COMMENT 'クレデンシャル（email の場合は NULL）',
  `created_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '紐付け日時',
  PRIMARY KEY (`central_id`, `identity_type`),
  UNIQUE KEY `uq_identity_type_identity` (`identity_type`, `identity`(191)),
  CONSTRAINT `fk_user_identities_central_id`
    FOREIGN KEY (`central_id`) REFERENCES `users` (`central_id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='ユーザーアイデンティティ（認証手段）';

-- ------------------------------------------------------------
-- user_groups
-- ------------------------------------------------------------
CREATE TABLE `user_groups` (
  `group_id`   VARCHAR(100) NOT NULL COMMENT 'グループ名',
  `central_id` CHAR(40)     NOT NULL COMMENT 'セントラルID',
  `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '付与日時',
  PRIMARY KEY (`group_id`, `central_id`),
  INDEX `idx_central_id` (`central_id`),
  CONSTRAINT `fk_user_groups_central_id`
    FOREIGN KEY (`central_id`) REFERENCES `users` (`central_id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='ユーザーグループ付与';

-- ------------------------------------------------------------
-- user_tags
-- ------------------------------------------------------------
CREATE TABLE `user_tags` (
  `tag_id`     VARCHAR(100) NOT NULL COMMENT 'タグ名',
  `central_id` CHAR(40)     NOT NULL COMMENT 'セントラルID',
  `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '付与日時',
  PRIMARY KEY (`tag_id`, `central_id`),
  INDEX `idx_central_id` (`central_id`),
  CONSTRAINT `fk_user_tags_central_id`
    FOREIGN KEY (`central_id`) REFERENCES `users` (`central_id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='ユーザー分類タグ';

-- ------------------------------------------------------------
-- tokens
-- ------------------------------------------------------------
CREATE TABLE `tokens` (
  `id`                       BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主キー',
  `central_id`               CHAR(40)        NOT NULL COMMENT 'セントラルID',
  `access_token`             VARCHAR(512)    NOT NULL COMMENT 'アクセストークン',
  `refresh_token`            VARCHAR(512)    NOT NULL COMMENT 'リフレッシュトークン',
  `access_token_expires_at`  DATETIME        NOT NULL COMMENT 'アクセストークン有効期限',
  `refresh_token_expires_at` DATETIME        NOT NULL COMMENT 'リフレッシュトークン有効期限',
  `revoked_at`               DATETIME            NULL DEFAULT NULL COMMENT '失効日時（ログアウト時に設定）',
  `created_at`               DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '発行日時',
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

-- ------------------------------------------------------------
-- one_time_passwords
-- ------------------------------------------------------------
CREATE TABLE `one_time_passwords` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主キー',
  `email`      VARCHAR(255)    NOT NULL COMMENT '送信先メールアドレス',
  `code`       CHAR(6)         NOT NULL COMMENT '6桁数字コード',
  `attempts`   TINYINT         NOT NULL DEFAULT 0 COMMENT '検証試行回数',
  `expires_at` DATETIME        NOT NULL COMMENT '有効期限',
  `used_at`    DATETIME            NULL DEFAULT NULL COMMENT '使用済み日時（NULL は未使用）',
  `created_at` DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '発行日時',
  PRIMARY KEY (`id`),
  INDEX `idx_email`      (`email`),
  INDEX `idx_expires_at` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='メール認証ワンタイムパスワード';

-- ------------------------------------------------------------
-- one_time_codes
-- ------------------------------------------------------------
CREATE TABLE `one_time_codes` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主キー',
  `central_id` CHAR(40)        NOT NULL COMMENT 'セントラルID（OAuth完了時点で確定）',
  `auth_code`  VARCHAR(128)    NOT NULL COMMENT '一時認証コード（ランダム文字列）',
  `expires_at` DATETIME        NOT NULL COMMENT '有効期限（発行から5分）',
  `used_at`    DATETIME            NULL DEFAULT NULL COMMENT '使用済み日時（NULL は未使用）',
  `created_at` DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '発行日時',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_auth_code`  (`auth_code`(128)),
  INDEX `idx_central_id`     (`central_id`),
  INDEX `idx_expires_at`     (`expires_at`),
  CONSTRAINT `fk_one_time_codes_central_id`
    FOREIGN KEY (`central_id`) REFERENCES `users` (`central_id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='OAuthワンタイムコード';

-- ------------------------------------------------------------
-- one_time_states
-- ------------------------------------------------------------
CREATE TABLE `one_time_states` (
  `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主キー',
  `state`        VARCHAR(128)    NOT NULL COMMENT 'ステートトークン',
  `provider`     VARCHAR(20)     NOT NULL COMMENT 'プロバイダー（google / github 等）',
  `redirect_uri` VARCHAR(512)    NOT NULL COMMENT '認証後リダイレクトURI',
  `service_id`   VARCHAR(36)         NULL DEFAULT NULL COMMENT 'サービスID（FKなし・configs管理）',
  `expires_at`   DATETIME        NOT NULL COMMENT '有効期限（発行から10分）',
  `created_at`   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '発行日時',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_state_provider` (`state`, `provider`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='OAuth認証ステート（CSRF防止）';

-- ------------------------------------------------------------
-- user_events
-- ------------------------------------------------------------
CREATE TABLE `user_events` (
  `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主キー',
  `central_id`    CHAR(40)        NOT NULL COMMENT 'セントラルID',
  `service_id`    VARCHAR(36)         NULL DEFAULT NULL COMMENT 'サービスID（FKなし・configs管理）',
  `event_type`    VARCHAR(50)     NOT NULL COMMENT 'イベント種別（register / login / logout / token_refresh 等）',
  `identity_type` VARCHAR(50)         NULL DEFAULT NULL COMMENT '認証手段種別（login / register イベント時のみ）',
  `ip_address`    VARCHAR(45)         NULL DEFAULT NULL COMMENT 'IPアドレス（IPv6対応）',
  `user_agent`    TEXT                NULL DEFAULT NULL COMMENT 'ユーザーエージェント',
  `created_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '発生日時',
  PRIMARY KEY (`id`),
  INDEX `idx_central_id` (`central_id`),
  INDEX `idx_service_id` (`service_id`),
  INDEX `idx_event_type` (`event_type`),
  INDEX `idx_created_at` (`created_at`),
  CONSTRAINT `fk_user_events_central_id`
    FOREIGN KEY (`central_id`) REFERENCES `users` (`central_id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='ユーザーイベント（認証・操作履歴）';

-- ------------------------------------------------------------
-- configs
-- ------------------------------------------------------------
CREATE TABLE `configs` (
  `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主キー',
  `config_name`  VARCHAR(191)    NOT NULL COMMENT '設定名（例: services, groups, countries）',
  `config_value` JSON            NOT NULL COMMENT '設定値（JSON）',
  `created_at`   TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '作成日時',
  `updated_at`   TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新日時',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_config_name` (`config_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='共通設定（EAV）';
