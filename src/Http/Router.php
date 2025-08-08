<?php
declare(strict_types=1);

namespace OpenWishlist\Http;

final class Router
{
    /** @var array<string,array<string,callable>> */
    private array $routes = ['GET'=>[], 'POST'=>[], 'PUT'=>[], 'PATCH'=>[], 'DELETE'=>[]];

    public function get(string $path, callable $handler): void { $this->routes['GET'][$path] = $handler; }
    public function post(string $path, callable $handler): void { $this->routes['POST'][$path] = $handler; }
    public function put(string $path, callable $handler): void { $this->routes['PUT'][$path] = $handler; }
    public function patch(string $path, callable $handler): void { $this->routes['PATCH'][$path] = $handler; }
    public function delete(string $path, callable $handler): void { $this->routes['DELETE'][$path] = $handler; }

    public function dispatch(string $method, string $uri): void
    {
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';
        $handler = $this->routes[$method][$path] ?? null;

        // Simple param routing: /wishes/{id}
        if (!$handler) {
            foreach ($this->routes[$method] as $route => $h) {
                $pattern = '#^' . preg_replace('#\{([a-zA-Z_][a-zA-Z0-9_]*)\}#', '(?P<$1>[^/]+)', $route) . '$#';
                if (preg_match($pattern, $path, $m)) {
                    $params = array_filter($m, 'is_string', ARRAY_FILTER_USE_KEY);
                    $handler = fn() => $h($params);
                    break;
                }
            }
        }

        if (!$handler) {
            self::status(404);
            self::json(['error' => 'Not Found']);
            return;
        }

        // Handle request
        $handler();
    }

    public static function inputJson(): array
    {
        $raw = file_get_contents('php://input') ?: '';
        $data = json_decode($raw, true);
        return is_array($data) ? $data : [];
    }

    public static function json($data, int $status = 200): void
    {
        self::status($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    public static function redirect(string $to): void
    {
        header('Location: ' . $to, true, 302);
        exit;
    }

    public static function status(int $status): void
    {
        http_response_code($status);
    }
}
