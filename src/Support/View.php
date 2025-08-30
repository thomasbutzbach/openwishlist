<?php
declare(strict_types=1);

namespace OpenWishlist\Support;

final class View
{
    private static ?\PDO $pdo = null;
    
    public static function setPdo(\PDO $pdo): void
    {
        self::$pdo = $pdo;
    }
    
    public static function render(string $template, array $data = []): void
    {
        extract($data, EXTR_SKIP);
        $flashError = Session::flash('error');
        $flashSuccess = Session::flash('success');
        
        // Load global settings
        $appSettings = [];
        if (self::$pdo) {
            try {
                $appSettings = Settings::load(self::$pdo);
            } catch (\Throwable $e) {
                // Ignore settings errors in templates
            }
        }
        
        $tpl = __DIR__ . '/../../templates/' . $template . '.php';
        $layout = __DIR__ . '/../../templates/layout.php';
        if (!file_exists($tpl)) {
            http_response_code(500);
            echo "Template not found: " . htmlspecialchars($template);
            return;
        }
        require $layout;
    }
}
