-- OpenWishlist Migration 03 (UP): extend jobs table with status and timing columns
START TRANSACTION;

ALTER TABLE jobs
  ADD COLUMN status ENUM('queued','processing','completed','failed') NOT NULL DEFAULT 'queued' AFTER payload,
  ADD COLUMN priority INT UNSIGNED NOT NULL DEFAULT 100 AFTER status,
  ADD COLUMN started_at DATETIME NULL AFTER locked_at,
  ADD COLUMN finished_at DATETIME NULL AFTER started_at;

-- Add index for efficient job queue queries
CREATE INDEX idx_jobs_status_priority_runat ON jobs (status, priority, run_at);

COMMIT;