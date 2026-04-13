<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

// -----------------------------------------------------------------------
// Routing
// -----------------------------------------------------------------------
$allowedPages = ['dashboard', 'draws', 'stats', 'generator', 'validator', 'sync', 'import'];
$page = isset($_GET['page']) ? trim($_GET['page']) : 'dashboard';
if (!in_array($page, $allowedPages, true)) {
    $page = 'dashboard';
}

$game = isset($_GET['game']) ? trim($_GET['game']) : 'lotto';
if (!in_array($game, GAMES, true)) {
    $game = 'lotto';
}

$gameNames = [
    'lotto'      => 'Lotto',
    'lotto_plus' => 'Lotto Plus',
    'mini_lotto' => 'Mini Lotto',
];

function nav_link(string $label, string $page, string $game, string $currentPage, string $currentGame): string
{
    $active = ($page === $currentPage && $game === $currentGame);
    $url = '?page=' . urlencode($page) . '&game=' . urlencode($game);
    $class = $active ? ' style="color:#ffd700;"' : '';
    return '<a href="' . h($url) . '"' . $class . '>' . h($label) . '</a>';
}
?>
<!DOCTYPE html>
<html lang="pl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Analiza Gier Liczbowych &mdash; <?= h($gameNames[$game]) ?></title>
<style>
body { font-family: Arial, sans-serif; margin: 0; padding: 0; background: #f5f5f5; }
.container { max-width: 1200px; margin: 0 auto; padding: 20px; }
nav { background: #1a3a6b; padding: 10px 20px; }
nav a { color: #fff; text-decoration: none; margin-right: 15px; font-weight: bold; }
nav a:hover { color: #ffd700; }
nav .game-selector { margin-left: 30px; }
h1, h2 { color: #1a3a6b; }
table { border-collapse: collapse; width: 100%; background: #fff; }
th { background: #1a3a6b; color: #fff; padding: 8px 12px; text-align: left; }
td { padding: 6px 12px; border-bottom: 1px solid #ddd; }
tr:hover { background: #f0f4ff; }
.hot { color: #c0392b; font-weight: bold; }
.cold { color: #2980b9; font-weight: bold; }
form { background: #fff; padding: 15px; margin-bottom: 20px; border: 1px solid #ddd; }
label { display: inline-block; min-width: 200px; }
input[type=number], input[type=text], select { padding: 4px 8px; margin: 3px 0; }
button, input[type=submit] { background: #1a3a6b; color: #fff; border: none; padding: 8px 18px; cursor: pointer; border-radius: 3px; }
button:hover, input[type=submit]:hover { background: #2a5a9b; }
.pagination a { display: inline-block; padding: 5px 10px; margin: 2px; background: #fff; border: 1px solid #ccc; text-decoration: none; color: #1a3a6b; }
.pagination a.active { background: #1a3a6b; color: #fff; }
.ball { display: inline-block; width: 28px; height: 28px; border-radius: 50%; background: #1a3a6b; color: #fff; text-align: center; line-height: 28px; font-size: 12px; font-weight: bold; margin: 1px; }
.ball.plus { background: #c0392b; }
.alert { padding: 10px 15px; border-radius: 3px; margin-bottom: 15px; }
.alert-success { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; }
.alert-error { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; }
.coupon { background: #fff; border: 2px solid #1a3a6b; padding: 10px; margin: 5px 0; border-radius: 5px; }
</style>
</head>
<body>
<nav>
    <span>
        <?= nav_link('Dashboard', 'dashboard', $game, $page, $game) ?>
        <?= nav_link('Losowania', 'draws', $game, $page, $game) ?>
        <?= nav_link('Statystyki', 'stats', $game, $page, $game) ?>
        <?= nav_link('Generator', 'generator', $game, $page, $game) ?>
        <?= nav_link('Weryfikator', 'validator', $game, $page, $game) ?>
        <?= nav_link('Sync', 'sync', $game, $page, $game) ?>
        <?= nav_link('Import', 'import', $game, $page, $game) ?>
    </span>
    <span class="game-selector">
        <?php foreach ($gameNames as $slug => $name): ?>
            <?php $active = ($slug === $game); ?>
            <a href="<?= h('?page=' . urlencode($page) . '&game=' . urlencode($slug)) ?>"
               <?= $active ? 'style="color:#ffd700;"' : '' ?>>
                <?= h($name) ?>
            </a>
        <?php endforeach; ?>
    </span>
</nav>
<div class="container">
<?php
try {
    $pageFile = __DIR__ . '/' . $page . '.php';
    if (file_exists($pageFile)) {
        include $pageFile;
    } else {
        echo '<div class="alert alert-error">Page not found: ' . h($page) . '</div>';
    }
} catch (PDOException $e) {
    echo '<div class="alert alert-error">Database error: ' . h($e->getMessage()) . '</div>';
} catch (Exception $e) {
    echo '<div class="alert alert-error">Error: ' . h($e->getMessage()) . '</div>';
}
?>
</div>
</body>
</html>
