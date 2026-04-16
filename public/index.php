<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/texts.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/src/autoload.php';

// -----------------------------------------------------------------------
// Bootstrap GameKit
// -----------------------------------------------------------------------
$kit = new GameKit($pdo);

// -----------------------------------------------------------------------
// Routing
// -----------------------------------------------------------------------
$allowedPages = ['dashboard', 'draws', 'stats', 'generator', 'validator', 'sync', 'import', 'cooccurrence'];
$page = isset($_GET['page']) ? trim($_GET['page']) : 'dashboard';
if (!in_array($page, $allowedPages, true)) {
    $page = 'dashboard';
}

$gameDef = $kit->gameFromRequest();
$game    = $gameDef->slug;

$gameNames = [];
foreach ($kit->registry()->allSlugs() as $_slug) {
    $gameNames[$_slug] = $kit->game($_slug)->name;
}
?>
<!DOCTYPE html>
<html lang="pl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>LottoAnalytics &mdash; <?= h($gameNames[$game]) ?></title>
<!-- Fonts -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<!-- Icons -->
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
<!-- Stylesheet -->
<link rel="stylesheet" href="css/style.css">
</head>
<body>

<!-- Top Navbar -->
<nav class="top-navbar">
    <div class="flex items-center gap-4">
        <button class="menu-toggle" onclick="document.body.classList.toggle('sidebar-open')" aria-label="Menu">
            <span class="material-symbols-outlined">menu</span>
        </button>
        <a href="?page=dashboard&game=<?= h($game) ?>" class="top-navbar__logo" style="text-decoration:none;color:inherit;">LottoAnalytics</a>
    </div>

    <div class="top-navbar__games">
        <?php foreach ($gameNames as $slug => $name): ?>
            <a href="<?= h('?page=' . urlencode($page) . '&game=' . urlencode($slug)) ?>"
               class="<?= $slug === $game ? 'active' : '' ?>">
                <?= h($name) ?>
            </a>
        <?php endforeach; ?>
    </div>

    <div class="top-navbar__actions">
        <div class="top-navbar__search">
            <span class="material-symbols-outlined search-icon" style="font-size:1.125rem;">search</span>
            <input type="text" placeholder="Szukaj wyników..." disabled>
        </div>
    </div>
</nav>

<!-- Sidebar Backdrop (mobile) -->
<div class="sidebar-backdrop" onclick="document.body.classList.remove('sidebar-open')" style="display:none;"></div>

<div class="app-layout">
    <!-- Sidebar -->
    <aside class="sidebar">
        <div class="sidebar__header">
            <div class="sidebar__header-icon">
                <span class="material-symbols-outlined">casino</span>
            </div>
            <div class="sidebar__header-text">
                <h3>Analiza Gry</h3>
                <p>Statystyki &amp; systemy</p>
            </div>
        </div>

        <nav class="sidebar__nav">
            <?php
            $sidebarLinks = [
                'dashboard'    => ['icon' => 'dashboard',   'label' => NAV_LABELS['dashboard']],
                'draws'        => ['icon' => 'event_note',  'label' => NAV_LABELS['draws']],
                'stats'        => ['icon' => 'analytics',   'label' => NAV_LABELS['stats']],
                'generator'    => ['icon' => 'casino',      'label' => NAV_LABELS['generator']],
                'cooccurrence' => ['icon' => 'hub',         'label' => NAV_LABELS['cooccurrence']],
                'validator'    => ['icon' => 'task_alt',     'label' => NAV_LABELS['validator']],
                'sync'         => ['icon' => 'sync',        'label' => NAV_LABELS['sync']],
                'import'       => ['icon' => 'download',    'label' => NAV_LABELS['import']],
            ];
            foreach ($sidebarLinks as $pg => $meta):
                $isActive = ($pg === $page);
                $url = '?page=' . urlencode($pg) . '&game=' . urlencode($game);
            ?>
            <a href="<?= h($url) ?>" class="sidebar__link<?= $isActive ? ' active' : '' ?>">
                <span class="material-symbols-outlined"><?= $meta['icon'] ?></span>
                <span><?= h($meta['label']) ?></span>
            </a>
            <?php endforeach; ?>
        </nav>

        <div class="sidebar__footer">
            <div class="sidebar__promo">
                <p>PRO TIP</p>
                <p>Wygenerowane liczby oparte są na trendach prawdopodobieństwa z ostatnich 10 lat.</p>
            </div>
        </div>
    </aside>

    <!-- Main Content -->
    <main class="main-content">
    <?php
    try {
        $pageFile = __DIR__ . '/' . $page . '.php';
        if (file_exists($pageFile)) {
            include $pageFile;
        } else {
            echo '<div class="alert alert-error">Nie znaleziono strony: ' . h($page) . '</div>';
        }
    } catch (PDOException $e) {
        echo '<div class="alert alert-error">Błąd bazy danych: ' . h($e->getMessage()) . '</div>';
    } catch (Exception $e) {
        echo '<div class="alert alert-error">Błąd: ' . h($e->getMessage()) . '</div>';
    }
    ?>
    </main>
</div>

<!-- Footer -->
<footer class="app-footer">
    <div class="app-footer__inner">
        <div>
            <div class="app-footer__brand">LottoAnalytics</div>
            <p class="app-footer__copy">&copy; <?= date('Y') ?> LottoAnalytics. Odpowiedzialna gra.</p>
        </div>
        <div class="app-footer__links">
            <a href="#">Polityka prywatności</a>
            <a href="#">Regulamin</a>
            <a href="#" style="font-weight:700;color:var(--primary);">Odpowiedzialna gra</a>
            <a href="#">Kontakt</a>
        </div>
    </div>
</footer>

<script>
// Mobile sidebar backdrop visibility
const backdrop = document.querySelector('.sidebar-backdrop');
const observer = new MutationObserver(function() {
    backdrop.style.display = document.body.classList.contains('sidebar-open') ? 'block' : 'none';
});
observer.observe(document.body, { attributes: true, attributeFilter: ['class'] });
</script>
</body>
</html>
