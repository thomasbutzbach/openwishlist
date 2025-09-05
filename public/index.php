<?php
declare(strict_types=1);

use OpenWishlist\Http\Router;
use OpenWishlist\Http\Controller\AuthController;
use OpenWishlist\Http\Controller\HomeController;
use OpenWishlist\Http\Controller\WishlistController;
use OpenWishlist\Http\Controller\WishController;
use OpenWishlist\Http\Controller\AdminController;
use OpenWishlist\Support\Session;
use OpenWishlist\Support\Db;
use OpenWishlist\Support\View;

// Health Check: Vendor directory and autoloader
$vendorPath = __DIR__ . '/../vendor';
$autoloaderPath = $vendorPath . '/autoload.php';

if (!is_dir($vendorPath)) {
    http_response_code(500);
    die('<!DOCTYPE html><html><head><title>System Error</title></head><body>
        <h1>🚨 System Error</h1>
        <p><strong>Missing vendor directory</strong></p>
        <p>The composer dependencies are not installed. Please run:</p>
        <pre>composer install</pre>
        <hr><small>Health Check Failed: vendor directory not found</small>
    </body></html>');
}

if (!file_exists($autoloaderPath)) {
    http_response_code(500);
    die('<!DOCTYPE html><html><head><title>System Error</title></head><body>
        <h1>🚨 System Error</h1>
        <p><strong>Missing autoloader</strong></p>
        <p>The composer autoloader is not available. Please run:</p>
        <pre>composer install</pre>
        <hr><small>Health Check Failed: autoloader not found</small>
    </body></html>');
}

// Health Check: PHP Version
$minPhpVersion = '8.2';
if (version_compare(PHP_VERSION, $minPhpVersion, '<')) {
    http_response_code(500);
    die('<!DOCTYPE html><html><head><title>System Error</title></head><body>
        <h1>🚨 System Error</h1>
        <p><strong>PHP Version too old</strong></p>
        <p>Current: ' . PHP_VERSION . ' | Required: ' . $minPhpVersion . '+</p>
        <p>Please upgrade your PHP version.</p>
        <hr><small>Health Check Failed: PHP version compatibility</small>
    </body></html>');
}

// Health Check: Required PHP Extensions
$requiredExtensions = ['pdo', 'pdo_mysql', 'json', 'mbstring', 'session'];
$missingExtensions = [];

foreach ($requiredExtensions as $ext) {
    if (!extension_loaded($ext)) {
        $missingExtensions[] = $ext;
    }
}

if (!empty($missingExtensions)) {
    http_response_code(500);
    die('<!DOCTYPE html><html><head><title>System Error</title></head><body>
        <h1>🚨 System Error</h1>
        <p><strong>Missing PHP Extensions</strong></p>
        <p>The following extensions are required but not available:</p>
        <ul>' . implode('', array_map(fn($ext) => "<li>$ext</li>", $missingExtensions)) . '</ul>
        <p>Please install these PHP extensions and restart your web server.</p>
        <hr><small>Health Check Failed: PHP extensions</small>
    </body></html>');
}

require $autoloaderPath;

// Load config
$configFile = __DIR__ . '/../config/local.php';
if (!file_exists($configFile)) {
    // Check if installer exists
    $installerFile = __DIR__ . '/install.php';
    if (file_exists($installerFile)) {
        header('Location: /install.php');
        exit;
    }
    
    // Fallback: copy example (old behavior)
    $example = __DIR__ . '/../config/local.example.php';
    if (file_exists($example)) {
        copy($example, $configFile);
        echo "Created config/local.php from example. Please adjust DB credentials and reload.";
        exit;
    }
    die('Missing config/local.php and installer');
}
$config = require $configFile;

// Start session with secure defaults
Session::start($config['session'] ?? []);

// Prepare DB (PDO)
$pdo = Db::connect($config['db']);

// Set PDO for global settings access in templates
View::setPdo($pdo);

// Set PDO for version access
\OpenWishlist\Support\Version::setPdo($pdo);

// Check for updates (redirect admins to update page if needed)
$versionFile = __DIR__ . '/../VERSION';
if (file_exists($versionFile)) {
    $fileVersion = trim(file_get_contents($versionFile));
    
    // Get current app version from database
    $stmt = $pdo->prepare("SELECT value FROM system_metadata WHERE `key` = 'app_version'");
    $stmt->execute();
    $dbVersion = $stmt->fetchColumn();
    
    if ($dbVersion && version_compare($fileVersion, $dbVersion, '>')) {
        // Update available - redirect admins to update page
        $updateFile = __DIR__ . '/update.php';
        if (file_exists($updateFile) && strpos($_SERVER['REQUEST_URI'], '/admin') === 0) {
            // Check if user is admin
            $uid = Session::userId();
            if ($uid) {
                $role = $pdo->prepare('SELECT role FROM users WHERE id=:id');
                $role->execute(['id'=>$uid]);
                if (($role->fetchColumn() ?: 'user') === 'admin') {
                    header('Location: /update.php?from=' . urlencode($dbVersion) . '&to=' . urlencode($fileVersion));
                    exit;
                }
            }
        }
        
        // For non-admin routes, store update info for admin banner
        if ($dbVersion && $fileVersion && $dbVersion !== $fileVersion) {
            $_SESSION['update_available'] = [
                'from' => $dbVersion,
                'to' => $fileVersion
            ];
        }
    } else {
        // Versions are equal, clear any session update info
        unset($_SESSION['update_available']);
    }
}

// Router
$router = new Router();

// System / Health
$router->get('/health', fn() => Router::json(['status' => 'ok', 'time' => date(DATE_ATOM)]));

// Auth
$auth = new AuthController($pdo, $config);
$router->get('/login', [$auth, 'showLogin']);
$router->post('/login', [$auth, 'login']);
$router->get('/register', [$auth, 'showRegister']);
$router->post('/register', [$auth, 'register']);
$router->post('/logout', [$auth, 'logout']);

// Home
$home = new HomeController($pdo, $config);
$router->get('/', [$home, 'index']);

// Wishlist
$wl = new WishlistController($pdo, $config);
$router->get('/wishlists', [$wl, 'index']);
$router->get('/wishlists/create', [$wl, 'createForm']);
$router->post('/wishlists', [$wl, 'create']);
$router->get('/wishlists/{id}', [$wl, 'show']);
$router->get('/wishlists/{id}/edit', [$wl, 'editForm']);
$router->post('/wishlists/{id}', [$wl, 'update']);
$router->post('/wishlists/{id}/delete', [$wl, 'delete']);
$router->post('/wishlists/{id}/toggle-public', [$wl, 'togglePublic']);
$router->get('/wishlists/{id}/export/csv', [$wl, 'exportCsv']);
$router->get('/wishlists/{id}/export/json', [$wl, 'exportJson']);
$router->get('/wishlists/{id}/export/pdf', [$wl, 'exportPdf']);
$router->get('/wishlists/{id}/import', [$wl, 'importForm']);
$router->post('/wishlists/{id}/import', [$wl, 'importCsv']);

// public by slug
$router->get('/s/{slug}', [$wl, 'publicBySlug']);

$wish = new WishController($pdo, $config);
$router->get('/wishlists/{id}/wishes/new', [$wish, 'createForm']);
$router->post('/wishlists/{id}/wishes', [$wish, 'create']);
$router->get('/wishes/{id}/edit', [$wish, 'editForm']);
$router->post('/wishes/{id}', [$wish, 'update']);
$router->post('/wishes/{id}/delete', [$wish, 'delete']);

$admin = new AdminController($pdo, $config);
$router->get('/admin', [$admin, 'dashboard']);
$router->get('/admin/jobs', [$admin, 'jobsPage']);
$router->post('/admin/jobs/run', [$admin, 'runJobs']);
$router->get('/admin/settings', [$admin, 'settingsPage']);
$router->post('/admin/convert-links-to-local', [$admin, 'convertLinksToLocal']);

// === API Routes ===

// API Auth
$router->post('/api/register', [$auth, 'apiRegister']);
$router->post('/api/login', [$auth, 'apiLogin']);
$router->post('/api/logout', [$auth, 'apiLogout']);

// API Wishlists
$router->get('/api/wishlists', [$wl, 'apiIndex']);
$router->post('/api/wishlists', [$wl, 'apiCreate']);
$router->get('/api/wishlists/{id}', [$wl, 'apiShow']);
$router->put('/api/wishlists/{id}', [$wl, 'apiUpdate']);
$router->delete('/api/wishlists/{id}', [$wl, 'apiDelete']);

// API Wishes
$router->post('/api/wishlists/{id}/wishes', [$wish, 'apiCreate']);
$router->get('/api/wishes/{id}', [$wish, 'apiShow']);
$router->put('/api/wishes/{id}', [$wish, 'apiUpdate']);
$router->delete('/api/wishes/{id}', [$wish, 'apiDelete']);
$router->post('/api/wishes/{id}/image/refetch', [$wish, 'apiRefetchImage']);

// API Public
$router->get('/api/public/lists/{slug}', [$wl, 'apiPublicBySlug']);

// API Admin
$router->get('/api/admin/settings', [$admin, 'apiGetSettings']);
$router->put('/api/admin/settings', [$admin, 'apiUpdateSettings']);
$router->delete('/api/admin/jobs/{id}', [$admin, 'apiDeleteJob']);
$router->post('/api/admin/jobs/cleanup', [$admin, 'apiCleanupJobs']);
$router->post('/api/admin/jobs/cleanup-by-status', [$admin, 'apiCleanupJobsByStatus']);

// Dispatch
$router->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);
