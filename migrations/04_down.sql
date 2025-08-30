-- OpenWishlist Migration 04 (DOWN): Remove system metadata table
START TRANSACTION;

DROP TABLE IF EXISTS system_metadata;

COMMIT;