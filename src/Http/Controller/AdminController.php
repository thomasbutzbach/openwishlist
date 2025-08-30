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
            
            \OpenWishlist\Support\Settings::save($this->pdo, $settings);
            
            Router::json(['message' => 'Settings updated successfully.']);
        } catch (\Throwable $e) {
            Router::json(['type' => 'about:blank', 'title' => 'Internal Server Error', 'status' => 500, 'detail' => 'Failed to update settings.'], 500);
        }
    }

}