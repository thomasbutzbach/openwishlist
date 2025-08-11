-- OpenWishlist Migration 02 (DOWN): revert "no image"
START TRANSACTION;

-- Map "none" back to a safe state (treat as link with no URL)
UPDATE wishes
SET image_mode = 'link',
    image_status = 'ok'
WHERE image_mode = 'none';

ALTER TABLE wishes
  MODIFY image_status ENUM('pending','ok','failed') NOT NULL DEFAULT 'pending',
  MODIFY image_mode ENUM('link','local') NOT NULL;

COMMIT;