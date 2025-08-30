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

    // === API Methods ===

    public function apiRegister(): void
    {
        try {
            $input = Router::inputJson();
            $email = trim($input['email'] ?? '');
            $password = (string)($input['password'] ?? '');
            $confirm = (string)($input['passwordConfirm'] ?? '');

            // Validation
            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                Router::json(['type' => 'about:blank', 'title' => 'Validation Error', 'status' => 400, 'detail' => 'Please provide a valid email.'], 400);
                return;
            }
            if (strlen($password) < 10) {
                Router::json(['type' => 'about:blank', 'title' => 'Validation Error', 'status' => 400, 'detail' => 'Password must be at least 10 characters.'], 400);
                return;
            }
            if ($password !== $confirm) {
                Router::json(['type' => 'about:blank', 'title' => 'Validation Error', 'status' => 400, 'detail' => 'Passwords do not match.'], 400);
                return;
            }

            // Check existing user
            $stmt = $this->pdo->prepare('SELECT id FROM users WHERE email = :email');
            $stmt->execute(['email' => $email]);
            if ($stmt->fetch()) {
                Router::json(['type' => 'about:blank', 'title' => 'Conflict', 'status' => 409, 'detail' => 'User already exists.'], 409);
                return;
            }

            // Create user
            $hash = password_hash($password, PASSWORD_ARGON2ID);
            $stmt = $this->pdo->prepare('INSERT INTO users (email, password_hash, role) VALUES (:e, :h, "user")');
            $stmt->execute(['e' => $email, 'h' => $hash]);
            $userId = (int)$this->pdo->lastInsertId();

            // Login user
            Session::login($userId);

            Router::json(['id' => $userId, 'email' => $email], 201);
        } catch (\Throwable $e) {
            Router::json(['type' => 'about:blank', 'title' => 'Internal Server Error', 'status' => 500, 'detail' => 'Registration failed.'], 500);
        }
    }

    public function apiLogin(): void
    {
        try {
            $input = Router::inputJson();
            $email = trim($input['email'] ?? '');
            $password = (string)($input['password'] ?? '');

            if ($email === '' || $password === '') {
                Router::json(['type' => 'about:blank', 'title' => 'Validation Error', 'status' => 400, 'detail' => 'Email and password are required.'], 400);
                return;
            }

            $stmt = $this->pdo->prepare('SELECT id, email, password_hash FROM users WHERE email = :email');
            $stmt->execute(['email' => $email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user || !password_verify($password, $user['password_hash'])) {
                Router::json(['type' => 'about:blank', 'title' => 'Unauthorized', 'status' => 401, 'detail' => 'Invalid credentials.'], 401);
                return;
            }

            Session::login((int)$user['id']);
            Router::json(['id' => (int)$user['id'], 'email' => $user['email']]);
        } catch (\Throwable $e) {
            Router::json(['type' => 'about:blank', 'title' => 'Internal Server Error', 'status' => 500, 'detail' => 'Login failed.'], 500);
        }
    }

    public function apiLogout(): void
    {
        Session::logout();
        Router::json(['message' => 'Logged out successfully.']);
    }
}
