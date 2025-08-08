<?php
declare(strict_types=1);

namespace OpenWishlist\Support;

final class Csrf
{
    public static function token(): string
    {
        if (!isset($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_csrf'];
    }

    public static function field(): string
    {
        $t = self::token();
        return '<input type="hidden" name="csrf" value="'.htmlspecialchars($t, ENT_QUOTES, 'UTF-8').'">';
    }

    public static function assert(): void
    {
        $sent = $_POST['csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        $valid = hash_equals($_SESSION['_csrf'] ?? '', (string)$sent);
        if (!$valid) {
            http_response_code(403);
            header('Content-Type: text/plain; charset=utf-8');
            echo 'Forbidden (CSRF)';
            exit;
        }
    }
}
