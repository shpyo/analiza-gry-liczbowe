<?php
// Router dla wbudowanego serwera PHP (`php -S`).
// Zwraca false dla istniejących plików (CSS, JS, itp.) żeby serwer podał je bezpośrednio;
// w pozostałych przypadkach przekazuje request do index.php (jak mod_rewrite w .htaccess).

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$file = __DIR__ . $path;

if ($path !== '/' && is_file($file)) {
    return false;
}

require __DIR__ . '/index.php';
