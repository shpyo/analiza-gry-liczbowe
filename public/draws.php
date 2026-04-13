<?php
declare(strict_types=1);

/**
 * draws.php - Paginated list of draws with filters
 * Included by index.php; $pdo, $game are available.
 */

$gameConfig = get_game_config($pdo, $game);
$gameName   = $gameConfig['name'];
$drawsTable = GAME_TABLES[$game];
$pickCount  = (int)$gameConfig['pick_count'];

// -----------------------------------------------------------------------
// Filters
// -----------------------------------------------------------------------
$dateFrom = isset($_GET['date_from']) ? trim($_GET['date_from']) : '';
$dateTo   = isset($_GET['date_to'])   ? trim($_GET['date_to'])   : '';

// Validate dates
if ($dateFrom !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
    $dateFrom = '';
}
if ($dateTo !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
    $dateTo = '';
}

// -----------------------------------------------------------------------
// Pagination
// -----------------------------------------------------------------------
$perPage = 50;
$currentPage = max(1, (int)($_GET['p'] ?? 1));

// -----------------------------------------------------------------------
// Build query
// -----------------------------------------------------------------------
$where  = [];
$params = [];

if ($dateFrom !== '') {
    $where[]  = 'draw_date >= ?';
    $params[] = $dateFrom;
}
if ($dateTo !== '') {
    $where[]  = 'draw_date <= ?';
    $params[] = $dateTo;
}

$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM `{$drawsTable}` {$whereSql}");
$countStmt->execute($params);
$totalRows = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalRows / $perPage));
$currentPage = min($currentPage, $totalPages);
$offset = ($currentPage - 1) * $perPage;

$dataParams = array_merge($params, [$perPage, $offset]);
$dataStmt = $pdo->prepare(
    "SELECT * FROM `{$drawsTable}` {$whereSql}
     ORDER BY draw_number DESC
     LIMIT ? OFFSET ?"
);
$dataStmt->execute($dataParams);
$rows = $dataStmt->fetchAll();

// -----------------------------------------------------------------------
// Helper: current URL with modified params
// -----------------------------------------------------------------------
function draws_url(array $overrides = []): string
{
    global $game, $page, $dateFrom, $dateTo, $currentPage;
    $params = [
        'page'      => 'draws',
        'game'      => $game,
        'date_from' => $dateFrom,
        'date_to'   => $dateTo,
        'p'         => $currentPage,
    ];
    foreach ($overrides as $k => $v) {
        $params[$k] = $v;
    }
    return '?' . http_build_query(array_filter($params, fn($v) => $v !== ''));
}
?>
<h1><?= h($gameName) ?> &mdash; Losowania</h1>

<form method="get" action="">
    <input type="hidden" name="page" value="draws">
    <input type="hidden" name="game" value="<?= h($game) ?>">
    <label>Od: <input type="date" name="date_from" value="<?= h($dateFrom) ?>"></label>
    <label>Do: <input type="date" name="date_to"   value="<?= h($dateTo) ?>"></label>
    <input type="submit" value="Filtruj">
    <?php if ($dateFrom !== '' || $dateTo !== ''): ?>
        <a href="?page=draws&game=<?= h($game) ?>">Wyczyść</a>
    <?php endif; ?>
</form>

<p>Znaleziono: <strong><?= h((string)$totalRows) ?></strong> losowań &mdash;
   Strona <?= h((string)$currentPage) ?> / <?= h((string)$totalPages) ?></p>

<?php if (empty($rows)): ?>
<div class="alert alert-error">Brak losowań spełniających kryteria.</div>
<?php else: ?>

<table>
    <thead>
        <tr>
            <th>#</th>
            <th>Data</th>
            <th>Liczby</th>
            <?php if ($game === 'lotto_plus'): ?>
            <th>Plus</th>
            <?php endif; ?>
            <th>Suma</th>
            <th><?= render_tooltip('even_count', $game) ?></th>
            <th><?= render_tooltip('low_count', $game) ?></th>
            <th><?= render_tooltip('range_spread', $game) ?></th>
            <th><?= render_tooltip('profile_hash', $game) ?></th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($rows as $row): ?>
        <tr>
            <td><?= h((string)$row['draw_number']) ?></td>
            <td><?= h($row['draw_date']) ?></td>
            <td>
                <?php for ($i = 1; $i <= $pickCount; $i++): ?>
                    <span class="ball"><?= h((string)$row["n{$i}"]) ?></span>
                <?php endfor; ?>
            </td>
            <?php if ($game === 'lotto_plus'): ?>
            <td>
                <?php if ($row['plus_ball'] !== null): ?>
                    <span class="ball plus"><?= h((string)$row['plus_ball']) ?></span>
                <?php endif; ?>
            </td>
            <?php endif; ?>
            <td><?= h((string)$row['sum_total']) ?></td>
            <td><?= h((string)$row['even_count']) ?></td>
            <td><?= h((string)$row['low_count']) ?></td>
            <td><?= h((string)$row['range_spread']) ?></td>
            <td><small><?= h(describe_profile_short((string)$row['profile_hash'])) ?></small></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>

<?php if ($totalPages > 1): ?>
<div class="pagination" style="margin-top:15px;">
    <?php
    $range = range(max(1, $currentPage - 4), min($totalPages, $currentPage + 4));
    if ($currentPage > 1):
    ?>
        <a href="<?= h(draws_url(['p' => 1])) ?>">&laquo;</a>
        <a href="<?= h(draws_url(['p' => $currentPage - 1])) ?>">&lsaquo;</a>
    <?php endif; ?>

    <?php foreach ($range as $p): ?>
        <a href="<?= h(draws_url(['p' => $p])) ?>"
           class="<?= $p === $currentPage ? 'active' : '' ?>">
            <?= h((string)$p) ?>
        </a>
    <?php endforeach; ?>

    <?php if ($currentPage < $totalPages): ?>
        <a href="<?= h(draws_url(['p' => $currentPage + 1])) ?>">&rsaquo;</a>
        <a href="<?= h(draws_url(['p' => $totalPages])) ?>">&raquo;</a>
    <?php endif; ?>
</div>
<?php endif; ?>
<?php endif; ?>
