<?php
declare(strict_types=1);

namespace OpenWishlist\Support;

final class View
{
    public static function render(string $template, array $data = []): void
    {
        extract($data, EXTR_SKIP);
        $flashError = Session::flash('error');
        $flashSuccess = Session::flash('success');
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
