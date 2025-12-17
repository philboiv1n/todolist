<?php

namespace TodoApp;

/**
 * Minimal view renderer.
 *
 * This app uses plain PHP templates for simplicity and performance.
 */
class View
{
    /**
     * Render a view file with extracted data.
     *
     * `$data` is trusted server-side data (built by controllers).
     */
    public static function render(string $viewPath, array $data = []): void
    {
        // Extract the data array to variables for the view file.
        extract($data);

        // Define the path to the views directory.
        // (When code lives outside the web root, bootstrap defines TODO_VIEWS_DIR.)
        $basePath = defined('TODO_VIEWS_DIR')
            ? rtrim((string)TODO_VIEWS_DIR, '/\\') . '/'
            : __DIR__ . '/../../views/';

        // Require the view file
        require $basePath . $viewPath;
    }

    /**
     * Render a view file and return the HTML as a string.
     *
     * Useful for partial updates (AJAX) while keeping templates in view files.
     */
    public static function renderToString(string $viewPath, array $data = []): string
    {
        ob_start();
        self::render($viewPath, $data);
        return (string)ob_get_clean();
    }
}
