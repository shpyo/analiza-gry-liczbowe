<?php
declare(strict_types=1);

// Load .env from project root (one directory above public/)
$envFile = dirname(__DIR__) . '/.env';
$envVars = [];

if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') {
            continue;
        }
        $eqPos = strpos($line, '=');
        if ($eqPos === false) {
            continue;
        }
        $key   = trim(substr($line, 0, $eqPos));
        $value = trim(substr($line, $eqPos + 1));
        // Strip surrounding quotes if present
        if (strlen($value) >= 2 &&
            (($value[0] === '"' && $value[-1] === '"') ||
             ($value[0] === "'" && $value[-1] === "'"))) {
            $value = substr($value, 1, -1);
        }
        $envVars[$key] = $value;
    }
}

$dbHost = $envVars['DB_HOST'] ?? '127.0.0.1';
$dbName = $envVars['DB_NAME'] ?? 'lotto_db';
$dbUser = $envVars['DB_USER'] ?? 'root';
$dbPass = $envVars['DB_PASS'] ?? '';

try {
    $pdo = new PDO(
        "mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4",
        $dbUser,
        $dbPass,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    http_response_code(500);
    echo '<div class="alert alert-error">Database connection failed: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</div>';
    exit;
}

const GAME_TABLES = [
    'lotto'      => 'lotto_draws',
    'lotto_plus' => 'lotto_plus_draws',
    'mini_lotto' => 'mini_lotto_draws',
];

const PROFILE_TABLES = [
    'lotto'      => 'lotto_draw_profiles',
    'lotto_plus' => 'lotto_plus_draw_profiles',
    'mini_lotto' => 'mini_lotto_draw_profiles',
];

const GAMES = ['lotto', 'lotto_plus', 'mini_lotto'];
