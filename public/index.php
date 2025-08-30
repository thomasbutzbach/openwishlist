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

require __DIR__ . '/../vendor/autoload.php';

// Load config
$configFile = __DIR__ . '/../config/local.php';
if (!file_exists($configFile)) {
    $example = __DIR__ . '/../config/local.example.php';
    if (file_exists($example)) {
        copy($example, $configFile);
        echo "Created config/local.php from example. Please adjust DB credentials and reload.";
        exit;
    }
    die('Missing config/local.php');
}
$config = require $configFile;

// Start session with secure defaults
Session::start($config['session'] ?? []);

// Prepare DB (PDO)
$pdo = Db::connect($config['db']);

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

// public by slug
$router->get('/s/{slug}', [$wl, 'publicBySlug']);

$wish = new WishController($pdo, $config);
$router->get('/wishlists/{id}/wishes/new', [$wish, 'createForm']);
$router->post('/wishlists/{id}/wishes', [$wish, 'create']);
$router->get('/wishes/{id}/edit', [$wish, 'editForm']);
$router->post('/wishes/{id}', [$wish, 'update']);
$router->post('/wishes/{id}/delete', [$wish, 'delete']);

$admin = new AdminController($pdo, $config);
$router->get('/admin/jobs', [$admin, 'jobsPage']);
$router->post('/admin/jobs/run', [$admin, 'runJobs']);

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

// Dispatch
$router->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);
