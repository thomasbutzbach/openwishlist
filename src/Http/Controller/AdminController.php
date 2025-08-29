<?php
declare(strict_types=1);

namespace OpenWishlist\Http\Controller;

use PDO;
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

        // Erst alte/orphaned Jobs aufräumen, dann neue erstellen
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

        $message = "Cleaned $cleaned orphaned job(s), seeded $seeded job(s), processed $processed job(s).";
        if (!empty($errors)) {
            $message .= " Errors: " . implode('; ', $errors);
        }
        \OpenWishlist\Support\Session::flash('success', $message);
        header('Location: /admin/jobs'); exit;
    }

}