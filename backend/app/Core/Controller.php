<?php

declare(strict_types=1);

namespace App\Core;

class Controller
{
    protected function view(string $view, array $data = [], string $layout = 'main'): void
    {
        $viewPath = __DIR__ . '/../Views/' . $view . '.php';
        if (!file_exists($viewPath)) {
            throw new \RuntimeException("View not found: $view");
        }

        extract($data);

        ob_start();
        require $viewPath;
        $content = ob_get_clean();

        if ($layout === 'none') {
            echo $content;
            return;
        }

        $layoutPath = __DIR__ . '/../Views/layouts/' . $layout . '.php';
        if (!file_exists($layoutPath)) {
            echo $content;
            return;
        }

        require $layoutPath;
    }

    protected function redirect(string $path): void
    {
        header("Location: $path");
        exit;
    }
}
