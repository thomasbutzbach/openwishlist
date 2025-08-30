-- OpenWishlist Migration 04 (UP): Add system metadata table for version tracking
START TRANSACTION;

CREATE TABLE IF NOT EXISTS system_metadata (
  `key`       VARCHAR(60)   NOT NULL,
  `value`     TEXT          NOT NULL,
  updated_at  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Record initial schema version
INSERT INTO system_metadata (`key`, `value`) VALUES 
  ('schema_version', '4'),
  ('app_version', '0.9.0')
ON DUPLICATE KEY UPDATE 
  `value` = VALUES(`value`);

COMMIT;