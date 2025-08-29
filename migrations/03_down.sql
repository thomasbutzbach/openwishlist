-- OpenWishlist Migration 03 (DOWN): remove jobs table extensions
START TRANSACTION;

-- Remove the index first
DROP INDEX idx_jobs_status_priority_runat ON jobs;

-- Remove the added columns
ALTER TABLE jobs
  DROP COLUMN finished_at,
  DROP COLUMN started_at,
  DROP COLUMN priority,
  DROP COLUMN status;

COMMIT;