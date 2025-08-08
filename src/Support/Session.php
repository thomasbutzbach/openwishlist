<?php
declare(strict_types=1);

namespace OpenWishlist\Support;

final class Session
{
    public static function start(array $cfg): void
    {
        $cookieParams = [
            'lifetime' => (int)($cfg['cookie_lifetime'] ?? 0),
            'path' => '/',
            'domain' => '',
            'secure' => (bool)($cfg['cookie_secure'] ?? false),
            'httponly' => true,
            'samesite' => $cfg['cookie_samesite'] ?? 'Lax',
        ];
        session_name($cfg['name'] ?? 'owl_session');
        session_set_cookie_params($cookieParams);
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        // Idle timeout (simple)
        $idle = (int)($cfg['idle_timeout_minutes'] ?? 60);
        $now = time();
        if (isset($_SESSION['_last']) && $now - (int)$_SESSION['_last'] > $idle * 60) {
            self::logout();
        }
        $_SESSION['_last'] = $now;
    }

    public static function userId(): ?int
    {
        return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
    }

    public static function login(int $userId): void
    {
        // Regenerate to prevent fixation
        session_regenerate_id(true);
        $_SESSION['user_id'] = $userId;
    }

    public static function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
    }

    public static function flash(string $key, ?string $value = null): ?string
    {
        if ($value === null) {
            $msg = $_SESSION['_flash'][$key] ?? null;
            unset($_SESSION['_flash'][$key]);
            return $msg;
        }
        $_SESSION['_flash'][$key] = $value;
        return null;
    }
}
