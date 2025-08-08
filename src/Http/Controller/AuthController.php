<?php
declare(strict_types=1);

namespace OpenWishlist\Http\Controller;

use PDO;
use OpenWishlist\Http\Router;
use OpenWishlist\Support\Csrf;
use OpenWishlist\Support\Session;
use OpenWishlist\Support\View;

final class AuthController
{
    public function __construct(private PDO $pdo, private array $config) {}

    public function showLogin(): void
    {
        if (Session::userId()) {
            Router::redirect('/');
        }
        View::render('login', ['title' => 'Login']);
    }

    public function login(): void
    {
        Csrf::assert(); // throws on failure
        $email = trim($_POST['email'] ?? '');
        $password = (string)($_POST['password'] ?? '');

        if ($email === '' || $password === '') {
            Session::flash('error', 'Email and password are required.');
            Router::redirect('/login');
        }

        $stmt = $this->pdo->prepare('SELECT id, password_hash FROM users WHERE email = :email');
        $stmt->execute(['email' => $email]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row || !password_verify($password, $row['password_hash'])) {
            // Avoid user enumeration
            Session::flash('error', 'Invalid credentials.');
            Router::redirect('/login');
        }

        // Optional rehash
        if (password_needs_rehash($row['password_hash'], PASSWORD_ARGON2ID)) {
            $new = password_hash($password, PASSWORD_ARGON2ID);
            $upd = $this->pdo->prepare('UPDATE users SET password_hash=:h WHERE id=:id');
            $upd->execute(['h' => $new, 'id' => $row['id']]);
        }

        Session::login((int)$row['id']);
        Router::redirect('/');
    }

    public function showRegister(): void
    {
        if (Session::userId()) {
            Router::redirect('/');
        }
        View::render('register', ['title' => 'Register']);
    }

    public function register(): void
    {
        Csrf::assert();
        $email = trim($_POST['email'] ?? '');
        $password = (string)($_POST['password'] ?? '');
        $confirm = (string)($_POST['passwordConfirm'] ?? '');

        // Basic validation (expand later)
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Session::flash('error', 'Please provide a valid email.');
            Router::redirect('/register');
        }
        if (strlen($password) < 10) {
            Session::flash('error', 'Password must be at least 10 characters.');
            Router::redirect('/register');
        }
        if ($password !== $confirm) {
            Session::flash('error', 'Passwords do not match.');
            Router::redirect('/register');
        }

        // Unique email
        $exists = $this->pdo->prepare('SELECT 1 FROM users WHERE email=:email');
        $exists->execute(['email' => $email]);
        if ($exists->fetchColumn()) {
            Session::flash('error', 'This email is already registered.');
            Router::redirect('/register');
        }

        $hash = password_hash($password, PASSWORD_ARGON2ID);
        $ins = $this->pdo->prepare('INSERT INTO users (email, password_hash, role) VALUES (:e, :h, "user")');
        $ins->execute(['e' => $email, 'h' => $hash]);

        Session::flash('success', 'Registration successful. Please log in.');
        Router::redirect('/login');
    }

    public function logout(): void
    {
        Csrf::assert();
        Session::logout();
        Router::redirect('/login');
    }
}
