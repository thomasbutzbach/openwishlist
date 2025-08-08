<?php
declare(strict_types=1);

use OpenWishlist\Http\Router;
use OpenWishlist\Http\Controller\AuthController;
use OpenWishlist\Http\Controller\HomeController;
use OpenWishlist\Http\Controller\WishlistController;
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

// public by slug
$router->get('/s/{slug}', [$wl, 'publicBySlug']);

// Dispatch
$router->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);
