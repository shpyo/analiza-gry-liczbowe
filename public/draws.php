<?php
declare(strict_types=1);

/**
 * draws.php - Paginated list of draws with filters
 * Included by index.php; $pdo, $game are available.
 */

$pickCount  = $gameDef->pickCount;
$drawsTable = $gameDef->drawsTable;

// -----------------------------------------------------------------------
// Filters
// -----------------------------------------------------------------------
$dateFrom = isset($_GET['date_from']) ? trim($_GET['date_from']) : '';
$dateTo   = isset($_GET['date_to'])   ? trim($_GET['date_to'])   : '';
if ($dateFrom !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
    $dateFrom = '';
}
if ($dateTo !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
    $dateTo = '';
}

// -----------------------------------------------------------------------
// Pagination
// -----------------------------------------------------------------------
$perPage = AnalysisConfig::DRAWS_PER_PAGE;
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

<!-- Page Header -->
<header class="page-header">
    <div class="page-header__row">
        <div>
            <span class="text-label-md text-primary mb-2" style="display:block;">HISTORIA LOSOWAŃ</span>
            <h1 class="page-header__title"><?= h($gameDef->name) ?> &mdash; Losowania</h1>
            <p class="page-header__desc">Pełna historia losowań z metrykami statystycznymi i profilami strukturalnymi.</p>
        </div>
    </div>
</header>

<!-- Date Filter -->
<form method="get" action="" class="filter-card">
    <input type="hidden" name="page" value="draws">
    <input type="hidden" name="game" value="<?= h($game) ?>">
    <div class="form-row">
        <div class="form-group">
            <label class="form-label">Od</label>
            <input type="date" name="date_from" value="<?= h($dateFrom) ?>" class="form-input" style="width:auto;">
        </div>
        <div class="form-group">
            <label class="form-label">Do</label>
            <input type="date" name="date_to" value="<?= h($dateTo) ?>" class="form-input" style="width:auto;">
        </div>
        <button type="submit" class="btn btn--primary btn--sm">
            <?= render_material_icon('filter_list') ?> Filtruj
        </button>
        <?php if ($dateFrom !== '' || $dateTo !== ''): ?>
            <a href="?page=draws&game=<?= h($game) ?>" class="btn btn--ghost btn--sm">Wyczyść</a>
        <?php endif; ?>
    </div>
    <p class="form-hint" style="margin-top:0.5rem;">
        Znaleziono: <strong><?= h((string)$totalRows) ?></strong> losowań &mdash;
        Strona <?= h((string)$currentPage) ?> / <?= h((string)$totalPages) ?>
    </p>
</form>

<?php if (empty($rows)): ?>
    <div class="alert alert-error">Brak losowań spełniających kryteria.</div>
<?php else: ?>

<div class="card">
    <table class="data-table">
        <thead>
            <tr>
                <th>#</th>
                <th>Data</th>
                <th>Liczby</th>
                <?php if ($gameDef->hasBonus): ?>
                <th>Plus</th>
                <?php endif; ?>
                <th><?= $kit->texts()->renderTooltip('sum_total', $gameDef) ?></th>
                <th><?= $kit->texts()->renderTooltip('even_count', $gameDef) ?></th>
                <th><?= $kit->texts()->renderTooltip('low_count', $gameDef) ?></th>
                <th><?= $kit->texts()->renderTooltip('range_spread', $gameDef) ?></th>
                <th><?= $kit->texts()->renderTooltip('profile_hash', $gameDef) ?></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $row): ?>
            <tr>
                <td><strong><?= h((string)$row['draw_number']) ?></strong></td>
                <td style="white-space:nowrap;"><?= h($row['draw_date']) ?></td>
                <td>
                    <div class="balls-row">
                        <?php for ($i = 1; $i <= $pickCount; $i++): ?>
                            <?= render_ball((int)$row["n{$i}"]) ?>
                        <?php endfor; ?>
                    </div>
                </td>
                <?php if ($gameDef->hasBonus): ?>
                <td>
                    <?php if ($row['plus_ball'] !== null): ?>
                        <?= render_ball((int)$row['plus_ball'], 'plus') ?>
                    <?php endif; ?>
                </td>
                <?php endif; ?>
                <td><?= h((string)$row['sum_total']) ?></td>
                <td><?= h((string)$row['even_count']) ?></td>
                <td><?= h((string)$row['low_count']) ?></td>
                <td><?= h((string)$row['range_spread']) ?></td>
                <td><span class="text-body-sm text-on-surface-variant"><?= h($kit->describer()->describeShort((string)$row['profile_hash'])) ?></span></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php if ($totalPages > 1): ?>
<div class="pagination">
    <?php
    $pgRange = AnalysisConfig::PAGINATION_RANGE;
    $range = range(max(1, $currentPage - $pgRange), min($totalPages, $currentPage + $pgRange));
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
