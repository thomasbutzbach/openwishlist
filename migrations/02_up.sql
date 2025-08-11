-- OpenWishlist Migration 02 (UP): allow "no image"
START TRANSACTION;

ALTER TABLE wishes
  MODIFY image_mode ENUM('none','link','local') NOT NULL DEFAULT 'none',
  MODIFY image_status ENUM('pending','ok','failed') NULL;

COMMIT;