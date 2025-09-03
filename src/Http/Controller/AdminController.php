<?php
declare(strict_types=1);

namespace OpenWishlist\Http\Controller;

use PDO;
use OpenWishlist\Http\Router;
use OpenWishlist\Support\Session;
use OpenWishlist\Support\Csrf;
use OpenWishlist\Support\View;
use OpenWishlist\Support\Jobs;

final class AdminController
{
    public function __construct(private PDO $pdo, private array $config) {}

    private function requireAdmin(): int
    {
        $uid = Session::userId();
        if (!$uid) header('Location: /login') || exit;
        $role = $this->pdo->prepare('SELECT role FROM users WHERE id=:id');
        $role->execute(['id'=>$uid]);
        if (($role->fetchColumn() ?: 'user') !== 'admin') {
            http_response_code(403); echo 'Forbidden'; exit;
        }
        return $uid;
    }

    public function dashboard(): void
    {
        $this->requireAdmin();
        
        // System statistics
        $userStats = $this->pdo->query('
            SELECT 
                COUNT(*) as total_users,
                SUM(role = "admin") as admin_users,
                SUM(role = "user") as regular_users
            FROM users
        ')->fetch(\PDO::FETCH_ASSOC);
        
        $wishlistStats = $this->pdo->query('
            SELECT 
                COUNT(*) as total_wishlists,
                SUM(is_public = 1) as public_wishlists,
                SUM(is_public = 0) as private_wishlists
            FROM wishlists
        ')->fetch(\PDO::FETCH_ASSOC);
        
        $wishStats = $this->pdo->query('
            SELECT 
                COUNT(*) as total_wishes,
                SUM(image_status = "ok") as wishes_with_images,
                SUM(price_cents > 0) as wishes_with_price
            FROM wishes
        ')->fetch(\PDO::FETCH_ASSOC);
        
        // Image mode statistics
        $imageStats = $this->pdo->query('
            SELECT 
                image_mode,
                COUNT(*) as count,
                SUM(CASE WHEN image_status = "ok" THEN 1 ELSE 0 END) as successful,
                SUM(CASE WHEN image_status = "failed" THEN 1 ELSE 0 END) as failed,
                SUM(CASE WHEN image_status = "pending" THEN 1 ELSE 0 END) as pending
            FROM wishes 
            GROUP BY image_mode
            ORDER BY image_mode
        ')->fetchAll(\PDO::FETCH_ASSOC);
        
        // Job statistics
        $jobs = new \OpenWishlist\Support\Jobs($this->pdo);
        $jobStats = $jobs->getStats();
        
        // Recent activity (last 10 wishlists)
        $recentWishlists = $this->pdo->query('
            SELECT w.title, w.created_at, u.email
            FROM wishlists w
            JOIN users u ON w.user_id = u.id
            ORDER BY w.created_at DESC
            LIMIT 10
        ')->fetchAll(\PDO::FETCH_ASSOC);
        
        View::render('admin/dashboard', [
            'title' => 'Admin Dashboard',
            'userStats' => $userStats,
            'wishlistStats' => $wishlistStats,
            'wishStats' => $wishStats,
            'imageStats' => $imageStats,
            'jobStats' => $jobStats,
            'recentWishlists' => $recentWishlists
        ]);
    }

    public function settingsPage(): void
    {
        $this->requireAdmin();
        
        try {
            $settings = \OpenWishlist\Support\Settings::load($this->pdo);
            
            View::render('admin/settings', [
                'title' => 'Settings',
                'settings' => $settings
            ]);
        } catch (\Throwable $e) {
            View::render('admin/settings', [
                'title' => 'Settings',
                'error' => 'Failed to load settings: ' . $e->getMessage(),
                'settings' => []
            ]);
        }
    }

    public function jobsPage(): void
    {
        $this->requireAdmin();
        
        $jobs = new \OpenWishlist\Support\Jobs($this->pdo);
        $stats = $jobs->getStats();
        
        // Get recent jobs with details
        $stmt = $this->pdo->query('
            SELECT id, type, status, run_at, attempts, last_error, created_at
            FROM jobs 
            ORDER BY created_at DESC, id DESC 
            LIMIT 20
        ');
        $recentJobs = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        View::render('admin/jobs', [
            'title' => 'Jobs',
            'stats' => $stats,
            'recentJobs' => $recentJobs
        ]);
    }

    public function runJobs(): void
    {
        $this->requireAdmin();
        \OpenWishlist\Support\Csrf::assert();

        // Small batch to avoid HTTP timeouts
        $maxJobs = 5;
        $maxSeconds = 8;
        $started = microtime(true);

        $jobs = new \OpenWishlist\Support\Jobs($this->pdo);
        $settings = \OpenWishlist\Support\Settings::load($this->pdo);
        $processor = new \OpenWishlist\Domain\ImageProcessor($this->pdo, $settings);

        // Erst zombies/orphaned Jobs aufräumen, dann neue erstellen
        $zombies = $jobs->reclaimZombies(5);
        $cleaned = $jobs->cleanupOrphanedJobs();
        $seeded = $jobs->seedImageFetchBatch(50);
        
        $processed = 0;
        $errors = [];
        while ($processed < $maxJobs && (microtime(true) - $started) < $maxSeconds) {
            $job = $jobs->claimNext('image.fetch');
            if (!$job) {
                $errors[] = "No more jobs found after processing $processed";
                break;
            }

            try {
                $wishId = (int)($job['payload']['wishId'] ?? 0);
                if ($wishId <= 0) throw new \RuntimeException('missing wishId');
                $processor->processWish($wishId);
                $jobs->complete($job['id']);
                $processed++;
            } catch (\Throwable $e) {
                $errorMsg = substr($e->getMessage(), 0, 500);
                $errors[] = "Job {$job['id']}: $errorMsg";
                $jobs->fail($job['id'], $errorMsg, 120);
            }
        }

        $message = "Reclaimed $zombies zombie job(s), cleaned $cleaned orphaned job(s), seeded $seeded job(s), processed $processed job(s).";
        if (!empty($errors)) {
            $message .= " Errors: " . implode('; ', $errors);
        }
        \OpenWishlist\Support\Session::flash('success', $message);
        header('Location: /admin/jobs'); exit;
    }

    // === API Methods ===

    public function apiGetSettings(): void
    {
        $this->requireAdmin();
        
        try {
            $settings = \OpenWishlist\Support\Settings::load($this->pdo);
            
            // Only return safe/public settings, not internal ones
            $publicSettings = [
                'app_name' => $settings['app_name'] ?? 'OpenWishlist',
                'max_file_size' => $settings['max_file_size'] ?? 5242880,
                'allowed_domains' => $settings['allowed_domains'] ?? [],
                'public_registration' => $settings['public_registration'] ?? true
            ];
            
            Router::json($publicSettings);
        } catch (\Throwable $e) {
            Router::json(['type' => 'about:blank', 'title' => 'Internal Server Error', 'status' => 500, 'detail' => 'Failed to load settings.'], 500);
        }
    }

    public function apiUpdateSettings(): void
    {
        $this->requireAdmin();
        
        try {
            $input = Router::inputJson();
            $settings = \OpenWishlist\Support\Settings::load($this->pdo);
            
            // Update only allowed settings
            if (isset($input['app_name'])) {
                $settings['app_name'] = trim($input['app_name']);
            }
            if (isset($input['max_file_size'])) {
                $maxSize = (int)$input['max_file_size'];
                if ($maxSize > 0 && $maxSize <= 52428800) { // Max 50MB
                    $settings['max_file_size'] = $maxSize;
                }
            }
            if (isset($input['public_registration'])) {
                $settings['public_registration'] = (bool)$input['public_registration'];
            }
            if (isset($input['allowed_domains']) && is_array($input['allowed_domains'])) {
                $settings['allowed_domains'] = array_filter($input['allowed_domains'], 'is_string');
            }
            
            // Save each setting individually
            \OpenWishlist\Support\Settings::set($this->pdo, 'app_name', $settings['app_name'], 'string');
            \OpenWishlist\Support\Settings::set($this->pdo, 'max_file_size', $settings['max_file_size'], 'int');
            \OpenWishlist\Support\Settings::set($this->pdo, 'public_registration', $settings['public_registration'], 'bool');
            \OpenWishlist\Support\Settings::set($this->pdo, 'allowed_domains', $settings['allowed_domains'], 'json');
            
            Router::json(['message' => 'Settings updated successfully.']);
        } catch (\Throwable $e) {
            Router::json(['type' => 'about:blank', 'title' => 'Internal Server Error', 'status' => 500, 'detail' => 'Failed to update settings.'], 500);
        }
    }

    public function apiDeleteJob(array $params = []): void
    {
        $this->requireAdmin();
        
        try {
            $id = (int)($params['id'] ?? 0);
            if ($id <= 0) {
                Router::json(['type' => 'about:blank', 'title' => 'Bad Request', 'status' => 400, 'detail' => 'Invalid job ID.'], 400);
                return;
            }
            
            $stmt = $this->pdo->prepare('DELETE FROM jobs WHERE id = :id');
            $success = $stmt->execute(['id' => $id]);
            
            if ($stmt->rowCount() > 0) {
                Router::json(['message' => 'Job deleted successfully.']);
            } else {
                Router::json(['type' => 'about:blank', 'title' => 'Not Found', 'status' => 404, 'detail' => 'Job not found.'], 404);
            }
        } catch (\Throwable $e) {
            Router::json(['type' => 'about:blank', 'title' => 'Internal Server Error', 'status' => 500, 'detail' => 'Failed to delete job.'], 500);
        }
    }

    public function apiCleanupJobs(): void
    {
        $this->requireAdmin();
        
        try {
            // Delete completed jobs older than 7 days
            $stmt = $this->pdo->prepare("
                DELETE FROM jobs 
                WHERE status = 'completed' 
                AND created_at < NOW() - INTERVAL 7 DAY
            ");
            $stmt->execute();
            $completedDeleted = $stmt->rowCount();
            
            // Delete failed jobs older than 30 days  
            $stmt = $this->pdo->prepare("
                DELETE FROM jobs 
                WHERE status = 'failed' 
                AND created_at < NOW() - INTERVAL 30 DAY
            ");
            $stmt->execute();
            $failedDeleted = $stmt->rowCount();
            
            $total = $completedDeleted + $failedDeleted;
            Router::json(['message' => "Cleaned up $total old jobs ($completedDeleted completed, $failedDeleted failed)."]);
        } catch (\Throwable $e) {
            Router::json(['type' => 'about:blank', 'title' => 'Internal Server Error', 'status' => 500, 'detail' => 'Failed to cleanup jobs.'], 500);
        }
    }

    public function convertLinksToLocal(): void
    {
        $this->requireAdmin();
        Csrf::assert();

        try {
            // Get all wishes with image_mode = 'link' and valid image_url
            $stmt = $this->pdo->prepare('
                SELECT id, image_url 
                FROM wishes 
                WHERE image_mode = :mode AND image_url IS NOT NULL AND image_url != ""
            ');
            $stmt->execute(['mode' => 'link']);
            $linkWishes = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($linkWishes)) {
                Session::flash('info', 'No wishes with linked images found.');
                Router::redirect('/admin');
                return;
            }

            // Update all wishes from 'link' to 'local' and set status to 'pending'
            $updateStmt = $this->pdo->prepare('
                UPDATE wishes 
                SET image_mode = "local", image_status = "pending", image_path = NULL
                WHERE image_mode = "link" AND image_url IS NOT NULL AND image_url != ""
            ');
            $updated = $updateStmt->execute();

            if ($updated) {
                $count = $updateStmt->rowCount();
                
                // Create image fetch jobs for all converted wishes
                $jobs = new Jobs($this->pdo);
                foreach ($linkWishes as $wish) {
                    $jobs->enqueue('image.fetch', ['wishId' => (int)$wish['id']]);
                }

                Session::flash('success', "Successfully converted {$count} wishes from 'link' to 'local' mode. Image fetch jobs have been queued.");
            } else {
                Session::flash('error', 'Failed to convert image modes.');
            }

        } catch (\Throwable $e) {
            Session::flash('error', 'Error during conversion: ' . $e->getMessage());
        }

        Router::redirect('/admin');
    }

}