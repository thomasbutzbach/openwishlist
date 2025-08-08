-- Schema/Migration: OpenWishlist (UP)
-- MySQL 8+ / MariaDB 10.4+, InnoDB, utf8mb4_unicode_ci
START TRANSACTION;

-- 1) users
CREATE TABLE IF NOT EXISTS users (
  id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  email          VARCHAR(190)     NOT NULL,
  password_hash  VARCHAR(255)     NOT NULL,
  role           ENUM('user','admin') NOT NULL DEFAULT 'user',
  created_at     TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2) wishlists
CREATE TABLE IF NOT EXISTS wishlists (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id     BIGINT UNSIGNED NOT NULL,
  title       VARCHAR(190)    NOT NULL,
  description TEXT            NULL,
  is_public   TINYINT(1)      NOT NULL DEFAULT 0,
  share_slug  VARCHAR(60)     NULL,           -- only for public lists
  created_at  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_wishlists_user_id (user_id),
  UNIQUE KEY uq_wishlists_share_slug (share_slug),
  CONSTRAINT fk_wishlists_user
    FOREIGN KEY (user_id) REFERENCES users(id)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3) wishes
CREATE TABLE IF NOT EXISTS wishes (
  id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  wishlist_id    BIGINT UNSIGNED NOT NULL,
  title          VARCHAR(190)    NOT NULL,
  url            TEXT            NULL,
  price_cents    INT UNSIGNED    NULL,
  priority       TINYINT UNSIGNED NULL,  -- 1 (high) … 5 (low)
  notes          TEXT            NULL,

  -- Image fields
  image_mode     ENUM('link','local') NOT NULL,
  image_url      TEXT            NULL,
  image_path     VARCHAR(255)    NULL,
  image_mime     VARCHAR(100)    NULL,
  image_bytes    INT UNSIGNED    NULL,
  image_width    INT UNSIGNED    NULL,
  image_height   INT UNSIGNED    NULL,
  image_hash     CHAR(64)        NULL,  -- SHA-256
  image_status   ENUM('pending','ok','failed') NOT NULL DEFAULT 'pending',
  image_last_error TEXT          NULL,

  created_at     TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (id),
  KEY idx_wishes_wishlist_id (wishlist_id),
  KEY idx_wishes_wishlist_priority (wishlist_id, priority),
  KEY idx_wishes_wishlist_title (wishlist_id, title),
  KEY idx_wishes_image_hash (image_hash),

  CONSTRAINT fk_wishes_wishlist
    FOREIGN KEY (wishlist_id) REFERENCES wishlists(id)
    ON DELETE CASCADE,

  -- Enforced on MySQL 8+ / MariaDB 10.4+
  CONSTRAINT chk_priority_range CHECK (priority IS NULL OR (priority BETWEEN 1 AND 5))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4) settings (admin-editable configuration)
CREATE TABLE IF NOT EXISTS settings (
  `key`        VARCHAR(120)  NOT NULL,      -- e.g., uploads.maxBytes
  `type`       ENUM('string','int','bool','json','url','email','secret') NOT NULL,
  `value`      TEXT          NOT NULL,
  `group_name` VARCHAR(60)   NULL,          -- optional grouping label
  updated_at   TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5) jobs (simple queue)
CREATE TABLE IF NOT EXISTS jobs (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `type`      VARCHAR(60)     NOT NULL,       -- e.g., image.fetch
  payload     JSON            NOT NULL,       -- MariaDB stores JSON as LONGTEXT
  run_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  attempts    INT UNSIGNED    NOT NULL DEFAULT 0,
  locked_at   DATETIME        NULL,
  last_error  TEXT            NULL,
  created_at  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_jobs_type_runat (`type`, run_at),
  KEY idx_jobs_locked_at (locked_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6) audit_log (optional but recommended)
CREATE TABLE IF NOT EXISTS audit_log (
  id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id    BIGINT UNSIGNED NULL,        -- system events: NULL allowed
  action     VARCHAR(60)     NOT NULL,    -- e.g., settings.update
  entity     VARCHAR(60)     NULL,        -- e.g., wishlist
  entity_id  BIGINT UNSIGNED NULL,
  meta       JSON            NULL,
  created_at TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_audit_created (created_at),
  KEY idx_audit_entity (entity, entity_id),
  CONSTRAINT fk_audit_user
    FOREIGN KEY (user_id) REFERENCES users(id)
    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

COMMIT;
