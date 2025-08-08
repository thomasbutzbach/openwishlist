<?php
declare(strict_types=1);

namespace OpenWishlist\Http\Controller;

use PDO;
use OpenWishlist\Http\Router;
use OpenWishlist\Support\Session;
use OpenWishlist\Support\View;

final class HomeController
{
    public function __construct(private PDO $pdo, private array $config) {}

    public function index(): void
    {
	if (!Session::userId()) Router::redirect('/login');
	Router::redirect('/wishlists');

        View::render('home', [
            'title' => 'My Lists',
            'userId' => Session::userId(),
        ]);
    }
}
