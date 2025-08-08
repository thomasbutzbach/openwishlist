-- Schema/Migration: OpenWishlist (DOWN)
START TRANSACTION;

-- Drop in reverse order due to FKs
DROP TABLE IF EXISTS audit_log;
DROP TABLE IF EXISTS jobs;
DROP TABLE IF EXISTS settings;
DROP TABLE IF EXISTS wishes;
DROP TABLE IF EXISTS wishlists;
DROP TABLE IF EXISTS users;

COMMIT;
