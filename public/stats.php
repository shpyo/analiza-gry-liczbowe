<?php
declare(strict_types=1);

/**
 * stats.php - Per-number frequency statistics
 * Included by index.php; $pdo, $game, $gameDef, $kit are available.
 */

$pickCount  = $gameDef->pickCount;
$poolSize   = $gameDef->poolSize;
$drawsTable = $gameDef->drawsTable;

// -----------------------------------------------------------------------
// Sort params
// -----------------------------------------------------------------------
$allowedSorts = ['num', 'total_freq', 'window_freq', 'last_seen_draw', 'current_gap', 'avg_interval', 'overdue_score'];
$sort = isset($_GET['sort']) && in_array($_GET['sort'], $allowedSorts, true) ? $_GET['sort'] : 'num';
$dir  = (isset($_GET['dir']) && strtolower($_GET['dir']) === 'asc') ? 'asc' : 'desc';

// -----------------------------------------------------------------------
// Date range filter
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
// Build number columns
// -----------------------------------------------------------------------
$numberCols = $gameDef->numberColumns();

// -----------------------------------------------------------------------
// Get total draws count (with optional date filter)
// -----------------------------------------------------------------------
$dateWhere  = [];
$dateParams = [];
if ($dateFrom !== '') {
    $dateWhere[]  = 'draw_date >= ?';
    $dateParams[] = $dateFrom;
}
if ($dateTo !== '') {
    $dateWhere[]  = 'draw_date <= ?';
    $dateParams[] = $dateTo;
}
$dateWhereSQL = $dateWhere ? ('WHERE ' . implode(' AND ', $dateWhere)) : '';

$cntStmt = $pdo->prepare("SELECT COUNT(*) FROM `{$drawsTable}` {$dateWhereSQL}");
$cntStmt->execute($dateParams);
$totalDraws = (int)$cntStmt->fetchColumn();

$maxDrawStmt = $pdo->prepare("SELECT COALESCE(MAX(draw_number),0) FROM `{$drawsTable}` {$dateWhereSQL}");
$maxDrawStmt->execute($dateParams);
$maxDrawNum = (int)$maxDrawStmt->fetchColumn();

// -----------------------------------------------------------------------
// Build frequency data for each number 1..pool_size
// -----------------------------------------------------------------------
$totalFreqMap = [];
$windowFreqMap = [];
$lastSeenMap   = [];

$unionTotalParts = [];
foreach ($numberCols as $col) {
    if ($dateWhereSQL) {
        $unionTotalParts[] = "SELECT `{$col}` AS num FROM `{$drawsTable}` {$dateWhereSQL}";
    } else {
        $unionTotalParts[] = "SELECT `{$col}` AS num FROM `{$drawsTable}`";
    }
}
$unionTotalSQL = implode(' UNION ALL ', $unionTotalParts);

$allDateParams = count($dateParams) > 0
    ? array_merge(...array_fill(0, $pickCount, $dateParams))
    : [];

$totalFreqStmt = $pdo->prepare(
    "SELECT num, COUNT(*) AS freq FROM ({$unionTotalSQL}) AS t WHERE num IS NOT NULL GROUP BY num"
);
$totalFreqStmt->execute($allDateParams);
foreach ($totalFreqStmt->fetchAll() as $row) {
    $totalFreqMap[(int)$row['num']] = (int)$row['freq'];
}

// Window frequency (last N draws)
$windowLimit = AnalysisConfig::WINDOW_SIZE;
$colList   = $gameDef->numberColumnsSql();
$unionWinSQL = $gameDef->unpivotNumbersSql('last_window');
$winFreqRows = $pdo->query(
    "WITH last_window AS (SELECT {$colList} FROM `{$drawsTable}` ORDER BY draw_number DESC LIMIT {$windowLimit})
     SELECT num, COUNT(*) AS freq FROM ({$unionWinSQL}) AS t WHERE num IS NOT NULL GROUP BY num"
)->fetchAll();
foreach ($winFreqRows as $row) {
    $windowFreqMap[(int)$row['num']] = (int)$row['freq'];
}

// Last seen draw number for each number
foreach ($numberCols as $col) {
    $lastSeenStmt = $pdo->prepare(
        "SELECT `{$col}` AS num, MAX(draw_number) AS last_draw
         FROM `{$drawsTable}` {$dateWhereSQL}
         WHERE `{$col}` IS NOT NULL
         GROUP BY `{$col}`"
    );
    $lastSeenStmt->execute($dateParams);
    foreach ($lastSeenStmt->fetchAll() as $row) {
        $n = (int)$row['num'];
        $d = (int)$row['last_draw'];
        if (!isset($lastSeenMap[$n]) || $d > $lastSeenMap[$n]) {
            $lastSeenMap[$n] = $d;
        }
    }
}

// -----------------------------------------------------------------------
// Build stats array
// -----------------------------------------------------------------------
$avgWindowFreq = count($windowFreqMap) > 0
    ? array_sum($windowFreqMap) / count($windowFreqMap)
    : 0;

// Standard deviation (binomial model): σ = √(n × p × (1-p))
$windowSize = min(AnalysisConfig::WINDOW_SIZE, $totalDraws);
$p = $poolSize > 0 ? $pickCount / $poolSize : 0;
$stdDevWindowFreq = $windowSize > 0 ? sqrt($windowSize * $p * (1 - $p)) : 1;
$zScore = AnalysisConfig::TEMPERATURE_Z_SCORE;

$stats = [];
for ($n = 1; $n <= $poolSize; $n++) {
    $tf  = $totalFreqMap[$n]  ?? 0;
    $wf  = $windowFreqMap[$n] ?? 0;
    $ls  = $lastSeenMap[$n]   ?? 0;
    $gap = $ls > 0 ? ($maxDrawNum - $ls) : $maxDrawNum;
    $avg = $tf > 0 ? ($totalDraws / $tf) : 0;
    $ods = $avg > 0 ? round($gap / $avg, 2) : 0;

    $stats[] = [
        'num'           => $n,
        'total_freq'    => $tf,
        'window_freq'   => $wf,
        'last_seen_draw'=> $ls,
        'current_gap'   => $gap,
        'avg_interval'  => round($avg, 2),
        'overdue_score' => $ods,
    ];
}

// Sort
usort($stats, function ($a, $b) use ($sort, $dir) {
    $cmp = $a[$sort] <=> $b[$sort];
    return $dir === 'asc' ? $cmp : -$cmp;
});

// -----------------------------------------------------------------------
// Sort link helper
// -----------------------------------------------------------------------
function stats_sort_url(string $col): string
{
    global $game, $sort, $dir, $dateFrom, $dateTo;
    $newDir = ($sort === $col && $dir === 'asc') ? 'desc' : 'asc';
    return '?' . http_build_query(array_filter([
        'page'      => 'stats',
        'game'      => $game,
        'sort'      => $col,
        'dir'       => $newDir,
        'date_from' => $dateFrom,
        'date_to'   => $dateTo,
    ], fn($v) => $v !== ''));
}

function sort_arrow(string $col): string
{
    global $sort, $dir;
    if ($sort !== $col) return '';
    return $dir === 'asc' ? ' <span class="sort-arrow">&#9650;</span>' : ' <span class="sort-arrow">&#9660;</span>';
}

// -----------------------------------------------------------------------
// Hot / Cold numbers for sidebar cards
// -----------------------------------------------------------------------
$sortedByFreq = $stats;
usort($sortedByFreq, fn($a, $b) => $b['window_freq'] <=> $a['window_freq']);
$topHot  = array_slice($sortedByFreq, 0, AnalysisConfig::DISPLAY_STATS_HOT);
$topCold = array_slice(array_reverse($sortedByFreq), 0, AnalysisConfig::DISPLAY_STATS_COLD);
$maxFreq = max(1, max(array_column($stats, 'window_freq')));

// Quintile thresholds for heatmap
$_heatFreqs = [];
for ($n = 1; $n <= $poolSize; $n++) {
    $_heatFreqs[] = $windowFreqMap[$n] ?? 0;
}
sort($_heatFreqs);
$_heatCnt = count($_heatFreqs);
$_heatQ = [];
$_quintiles = AnalysisConfig::HEATMAP_QUINTILE_COUNT;
for ($_qi = 1; $_qi < $_quintiles; $_qi++) {
    $idx = (int)floor($_heatCnt * $_qi / $_quintiles);
    $_heatQ[] = $_heatFreqs[min($idx, $_heatCnt - 1)];
}

function _heatmap_bucket(int $freq, array $q): int {
    for ($i = 0, $count = count($q); $i < $count; $i++) {
        if ($freq <= $q[$i]) return $i;
    }
    return count($q);
}

/**
 * Aggregate a number→frequency map into decade buckets (1-indexed: decade 1 = 1–10, etc.).
 * Works for any pool size; the last bucket may be smaller than 10.
 *
 * @param  array<int,int> $freqMap  number → frequency
 * @param  int            $poolSize largest number in pool
 * @return array<int,int>           decade index → total frequency
 */
function _decade_freq(array $freqMap, int $poolSize): array
{
    $count  = (int)ceil($poolSize / 10);
    $result = array_fill(1, $count, 0);
    for ($n = 1; $n <= $poolSize; $n++) {
        $result[(int)ceil($n / 10)] += $freqMap[$n] ?? 0;
    }
    return $result;
}

// Compute overall odd/even/low/high from last N draws
$evenTotal = 0;
$lowTotal  = 0;
$drawCount500 = min($windowLimit, $totalDraws);
if ($drawCount500 > 0) {
    $distRow = $pdo->query(
        "SELECT SUM(even_count) AS total_even, SUM(low_count) AS total_low
         FROM (SELECT even_count, low_count FROM `{$drawsTable}` ORDER BY draw_number DESC LIMIT {$windowLimit}) AS sub"
    )->fetch();
    $evenTotal = (int)($distRow['total_even'] ?? 0);
    $lowTotal  = (int)($distRow['total_low'] ?? 0);
}
$totalNums500 = $drawCount500 * $pickCount;
$evenPct = $totalNums500 > 0 ? round($evenTotal / $totalNums500 * 100) : 50;
$oddPct  = 100 - $evenPct;
$lowPct  = $totalNums500 > 0 ? round($lowTotal / $totalNums500 * 100) : 50;
$highPct = 100 - $lowPct;

// Frequency index for hot numbers (observed / expected ratio, NOT a probability prediction)
$freqIndex = $maxFreq > 0 ? round($topHot[0]['window_freq'] / ($windowLimit * $pickCount / $poolSize) * 100, 1) : 0;

// Decade frequency distribution (aggregated from $windowFreqMap, no extra SQL needed)
$decadeFreq    = _decade_freq($windowFreqMap, $poolSize);
$decadeCount   = count($decadeFreq);
$maxDecadeFreq = max(1, ...array_values($decadeFreq));
?>

<!-- Page Header -->
<header class="page-header">
    <div class="page-header__row">
        <div>
            <span class="text-label-md text-primary mb-2" style="display:block;">ZAAWANSOWANE METRYKI</span>
            <h1 class="page-header__title">Analiza statystyczna <?= h($gameDef->name) ?></h1>
            <p class="page-header__desc">Kompleksowe zestawienie częstości, rozkładów i zaległości dla każdej liczby w puli <?= h((string)$poolSize) ?> liczb.</p>
        </div>
    </div>
</header>

<!-- Date Filter -->
<form method="get" action="" class="filter-card">
    <input type="hidden" name="page" value="stats">
    <input type="hidden" name="game" value="<?= h($game) ?>">
    <input type="hidden" name="sort" value="<?= h($sort) ?>">
    <input type="hidden" name="dir" value="<?= h($dir) ?>">
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
            <a href="?page=stats&game=<?= h($game) ?>" class="btn btn--ghost btn--sm">Wyczyść</a>
        <?php endif; ?>
    </div>
    <p class="form-hint" style="margin-top:0.5rem;">Łącznie losowań: <strong><?= h((string)$totalDraws) ?></strong> &mdash; Ostatni numer: <strong><?= h((string)$maxDrawNum) ?></strong></p>
</form>

<!-- Bento Grid: Top Cards -->
<div style="display:flex;flex-direction:column;gap:2rem;margin-bottom:2rem;">
    <!-- Frequency Distribution -->
    <section class="card">
        <div class="flex justify-between items-center mb-6" style="flex-wrap:wrap;gap:0.5rem;">
            <div>
                <h2 class="text-headline-md">Rozkład częstości</h2>
                <p class="text-body-sm text-on-surface-variant">Liczba trafień na liczbę w ostatnich 500 losowaniach</p>
            </div>
        </div>
        <div class="freq-bars-wrap">
            <div class="bar-chart" style="height:10rem;min-width:<?= $poolSize * 1.2 ?>rem;">
                <?php for ($n = 1; $n <= $poolSize; $n++):
                    $freq = $windowFreqMap[$n] ?? 0;
                    $pct  = $maxFreq > 0 ? round($freq / $maxFreq * 100) : 0;
                ?>
                <div class="bar-chart__col">
                    <div class="bar-chart__bar" style="height:<?= max(2, $pct) ?>%;" title="<?= h("{$n}: {$freq}") ?>"></div>
                    <span class="bar-chart__label"><?= $n ?></span>
                </div>
                <?php endfor; ?>
            </div>
        </div>
    </section>

    <div style="display:flex;justify-content:space-between;">
        <!-- Hot Numbers (col-span-4) -->
        <section class="card card--accent" style="display:flex;flex-direction:column;">
            <div class="flex items-center gap-3 mb-4">
                <?= render_material_icon('local_fire_department', 'icon-filled') ?>
                <h2 class="text-headline-md" style="color:var(--on-tertiary-fixed-variant);">Gorące liczby</h2>
            </div>
            <div class="balls-row mb-4" style="justify-content:center;gap:0.75rem;">
                <?php foreach ($topHot as $h): ?>
                    <?= render_ball($h['num'], 'md ball--hot') ?>
                <?php endforeach; ?>
            </div>
            <div style="background:rgba(255,255,255,0.2);backdrop-filter:blur(8px);border-radius:var(--radius-xl);padding:1rem;margin-top:auto;">
                <span class="text-label-lg" style="color:var(--on-tertiary-fixed-variant);" title="Stosunek obserwowanej częstości do oczekiwanej (100% = zgodna ze średnią). Nie jest to predykcja przyszłych losowań.">WSKAŹNIK CZĘSTOŚCI</span>
                <div style="font-size:1.5rem;font-weight:900;font-family:var(--font-headline);color:var(--on-tertiary-fixed);"><?= h((string)$freqIndex) ?>%</div>
                <div class="progress-bar" style="margin-top:0.5rem;background:rgba(0,0,0,0.1);">
                    <div class="progress-bar__fill progress-bar__fill--tertiary" style="width:<?= min(100, $freqIndex) ?>%;"></div>
                </div>
                <p style="font-size:0.6875rem;color:var(--on-tertiary-fixed-variant);margin-top:0.375rem;opacity:0.8;">Obserwowana / oczekiwana częstość &mdash; nie jest predykcją</p>
            </div>
        </section>

        <!-- Cold Numbers (col-span-4) -->
        <section class="card card--surface-high">
            <div class="flex items-center gap-3 mb-4">
                <?= render_material_icon('ac_unit') ?>
                <h2 class="text-headline-md">Zimne liczby</h2>
            </div>
            <div style="display:flex;flex-direction:column;">
                <?php foreach ($topCold as $c): ?>
                <div class="cold-item">
                    <span class="cold-item__ball"><?= str_pad((string)$c['num'], 2, '0', STR_PAD_LEFT) ?></span>
                    <span class="cold-item__info">Przerwa: <?= h((string)$c['current_gap']) ?> losowań</span>
                    <?= render_badge('Rzadka', 'rare') ?>
                </div>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- Odd/Even Split (col-span-8) -->
        <section class="card">
            <h2 class="text-headline-md mb-6">Podział parzystych i niskich</h2>
            <div style="display:flex;flex-direction:column;gap:2rem;">
                <div>
                    <div class="dist-bars">
                        <div class="dist-bar">
                            <span class="dist-bar__label">Nieparzyste</span>
                            <div class="dist-bar__track">
                                <div class="dist-bar__fill dist-bar__fill--primary" style="width:<?= $oddPct ?>%;"><?= $oddPct ?>%</div>
                            </div>
                        </div>
                        <div class="dist-bar">
                            <span class="dist-bar__label">Parzyste</span>
                            <div class="dist-bar__track">
                                <div class="dist-bar__fill dist-bar__fill--secondary" style="width:<?= $evenPct ?>%;"><?= $evenPct ?>%</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div>
                    <div class="dist-bars">
                        <div class="dist-bar">
                            <span class="dist-bar__label">Wysokie (<?= ($gameDef->lowThreshold + 1) ?>-<?= $poolSize ?>)</span>
                            <div class="dist-bar__track">
                                <div class="dist-bar__fill dist-bar__fill--primary" style="width:<?= $highPct ?>%;"><?= $highPct ?>%</div>
                            </div>
                        </div>
                        <div class="dist-bar">
                            <span class="dist-bar__label">Niskie (1-<?= $gameDef->lowThreshold ?>)</span>
                            <div class="dist-bar__track">
                                <div class="dist-bar__fill dist-bar__fill--secondary" style="width:<?= $lowPct ?>%;"><?= $lowPct ?>%</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>
</div>

<div style="display:flex;gap:1.5rem;margin-bottom:2rem;align-items:flex-start;flex-wrap:wrap;">

<!-- Heatmap Section -->
<div class="card" style="flex:2;min-width:20rem;">
    <h2 class="text-headline-md mb-4">Heatmapa częstości &mdash; ostatnie 500 losowań</h2>
    <div class="hm-grid" style="margin-bottom:1rem;">
        <?php for ($n = 1; $n <= $poolSize; $n++):
            $freq   = $windowFreqMap[$n] ?? 0;
            $bucket = _heatmap_bucket($freq, $_heatQ);
            $clr    = heatmap_bucket_color($bucket);
        ?>
        <div class="hm-cell" style="background:<?= $clr['bg'] ?>;color:<?= $clr['text'] ?>;" title="<?= h("{$n}: {$freq}/500") ?>"><?= $n ?></div>
        <?php endfor; ?>
    </div>
    <div style="display:flex;gap:0.75rem;align-items:center;flex-wrap:wrap;font-size:0.75rem;color:var(--outline);">
        <?php
        $_legendLabels = ['Rzadko', '', 'Średnio', '', 'Często'];
        for ($b = 0; $b < 5; $b++):
            $clr = heatmap_bucket_color($b);
        ?>
        <span style="display:inline-flex;align-items:center;gap:0.25rem;">
            <span style="width:1.25rem;height:1.25rem;border-radius:var(--radius-full);background:<?= $clr['bg'] ?>;display:inline-block;"></span>
            <?= $_legendLabels[$b] ?>
        </span>
        <?php if ($b < 4): ?><span style="color:var(--outline-variant);">&rsaquo;</span><?php endif; ?>
        <?php endfor; ?>
    </div>
</div>

<!-- Decade Distribution Chart -->
<div class="card" style="flex:1;min-width:16rem;">
    <div class="mb-4">
        <h2 class="text-headline-md">Rozkład dziesiątek</h2>
        <p class="text-body-sm text-on-surface-variant">Suma trafień per przedział &mdash; ostatnie <?= AnalysisConfig::WINDOW_SIZE ?> losowań</p>
    </div>
    <div class="dist-bars">
        <?php for ($d = 1; $d <= $decadeCount; $d++):
            $lo   = ($d - 1) * 10 + 1;
            $hi   = min($d * 10, $poolSize);
            $freq = $decadeFreq[$d];
            $pct  = $maxDecadeFreq > 0 ? round($freq / $maxDecadeFreq * 100) : 0;
            $fillClass = $pct >= 80 ? 'dist-bar__fill--primary' : 'dist-bar__fill--secondary';
        ?>
        <div class="dist-bar">
            <span class="dist-bar__label"><?= $lo ?>–<?= $hi ?></span>
            <div class="dist-bar__track">
                <div class="dist-bar__fill <?= $fillClass ?>" style="width:<?= max(4, $pct) ?>%;"><?= $freq ?></div>
            </div>
        </div>
        <?php endfor; ?>
    </div>
</div>

</div><!-- /flex heatmap+decades row -->

<!-- Granular Data Matrix -->
<div class="card">
    <div class="flex justify-between items-center mb-6" style="flex-wrap:wrap;gap:1rem;">
        <div>
            <h2 class="text-headline-md">Szczegółowa macierz danych</h2>
            <p class="text-body-sm text-on-surface-variant">Analiza metryki wydajności każdej liczby</p>
        </div>
    </div>

    <div class="table-scroll">
    <table class="data-table">
        <thead>
            <tr>
                <th><a href="<?= h(stats_sort_url('num')) ?>">Liczba<?= sort_arrow('num') ?></a></th>
                <th><a href="<?= h(stats_sort_url('total_freq')) ?>"><?= $kit->texts()->renderTooltip('total_freq', $gameDef) ?><?= sort_arrow('total_freq') ?></a></th>
                <th><a href="<?= h(stats_sort_url('window_freq')) ?>"><?= $kit->texts()->renderTooltip('window_freq', $gameDef) ?><?= sort_arrow('window_freq') ?></a></th>
                <th>Częstość</th>
                <th>Trend</th>
                <th>Status</th>
                <th><a href="<?= h(stats_sort_url('current_gap')) ?>"><?= $kit->texts()->renderTooltip('current_gap', $gameDef) ?><?= sort_arrow('current_gap') ?></a></th>
                <th><a href="<?= h(stats_sort_url('overdue_score')) ?>"><?= $kit->texts()->renderTooltip('overdue_score', $gameDef) ?><?= sort_arrow('overdue_score') ?></a></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($stats as $row): ?>
            <?php
            // Temperature / status classification
            $isHot      = $row['window_freq'] > $avgWindowFreq + $zScore * $stdDevWindowFreq;
            $isCold     = $row['window_freq'] < $avgWindowFreq - $zScore * $stdDevWindowFreq;
            $isInactive = $row['window_freq'] == 0;

            if ($isInactive) {
                $statusBadge = render_badge('Nieaktywna', 'rare');
                $trendIcon   = '';
            } elseif ($isHot) {
                $statusBadge = render_badge('Gorąca', 'hot');
                $trendIcon   = '<span class="material-symbols-outlined text-primary" style="font-size:1.125rem;">trending_up</span>';
            } elseif ($isCold) {
                $statusBadge = render_badge('Zimna', 'cold');
                $trendIcon   = '<span class="material-symbols-outlined text-error" style="font-size:1.125rem;">trending_down</span>';
            } else {
                $statusBadge = render_badge('Stabilna', 'stable');
                $trendIcon   = '<span class="material-symbols-outlined text-primary" style="font-size:1.125rem;">trending_up</span>';
            }

            // Number ball style
            $ballMod = $isHot ? 'sm ball--hot' : 'sm';

            // Frequency bar width
            $freqPct = $maxFreq > 0 ? round($row['window_freq'] / $maxFreq * 100) : 0;
            $freqBarColor = $isHot ? 'var(--tertiary)' : 'var(--secondary-container)';
            ?>
            <tr>
                <td><?= render_ball($row['num'], $ballMod) ?></td>
                <td><strong><?= h((string)$row['total_freq']) ?></strong></td>
                <td><?= h((string)$row['window_freq']) ?></td>
                <td style="min-width:8rem;">
                    <div class="progress-bar" style="height:0.375rem;">
                        <div class="progress-bar__fill" style="width:<?= $freqPct ?>%;background:<?= $freqBarColor ?>;"></div>
                    </div>
                </td>
                <td><?= $trendIcon ?></td>
                <td><?= $statusBadge ?></td>
                <td><?= h((string)$row['current_gap']) ?></td>
                <td<?= $row['overdue_score'] > AnalysisConfig::OVERDUE_CRITICAL ? ' style="color:var(--outline);font-weight:600;"' : ($row['overdue_score'] > AnalysisConfig::OVERDUE_WARNING ? ' style="color:var(--outline);"' : '') ?> title="Stosunek przerwy do średniego interwału. Wysoka wartość NIE oznacza zwiększonego prawdopodobieństwa wylosowania."><?= h((string)$row['overdue_score']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <p class="text-body-sm text-on-surface-variant" style="margin-top:1rem;font-style:italic;">
        Wszystkie metryki mają charakter deskryptywny (opisują historię). Loteria nie ma pamięci &mdash; historyczna częstość ani przerwa nie wpływają na prawdopodobieństwo przyszłych losowań. Każda liczba ma takie samo szanse w każdym losowaniu.
    </p>
</div>
