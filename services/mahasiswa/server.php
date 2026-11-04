<?php

// Router for PHP's development server. Equivalent to artisan serve; no child process.
$path = rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
if ($path !== '/' && is_file(__DIR__.'/public'.$path)) {
    return false;
}
require_once __DIR__.'/public/index.php';
