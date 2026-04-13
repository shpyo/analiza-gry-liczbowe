<?php
declare(strict_types=1);

/**
 * stats.php - Per-number frequency statistics
 * Included by index.php; $pdo, $game are available.
 */

$gameConfig = get_game_config($pdo, $game);
$gameName   = $gameConfig['name'];
$drawsTable = GAME_TABLES[$game];
$pickCount  = (int)$gameConfig['pick_count'];
$poolSize   = (int)$gameConfig['pool_size'];

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
$numberCols = [];
for ($i = 1; $i <= $pickCount; $i++) {
    $numberCols[] = "n{$i}";
}

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

// Total frequency per number (with date filter)
$totalFreqMap = [];
$windowFreqMap = [];
$lastSeenMap   = [];

// Build union for total freq
$unionTotalParts = [];
foreach ($numberCols as $col) {
    if ($dateWhereSQL) {
        $unionTotalParts[] = "SELECT `{$col}` AS num FROM `{$drawsTable}` {$dateWhereSQL}";
    } else {
        $unionTotalParts[] = "SELECT `{$col}` AS num FROM `{$drawsTable}`";
    }
}
$unionTotalSQL = implode(' UNION ALL ', $unionTotalParts);

// Execute total freq
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

// Window frequency (last 500 draws, no date filter - always last 500); CTE fetches once
$winColList  = implode(', ', array_map(fn($c) => "`{$c}`", $numberCols));
$winCteParts = [];
foreach ($numberCols as $col) {
    $winCteParts[] = "SELECT `{$col}` AS num FROM last500";
}
$unionWinSQL = implode(' UNION ALL ', $winCteParts);
$winFreqRows = $pdo->query(
    "WITH last500 AS (SELECT {$winColList} FROM `{$drawsTable}` ORDER BY draw_number DESC LIMIT 500)
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
    return $dir === 'asc' ? ' ▲' : ' ▼';
}
?>
<h1><?= h($gameName) ?> &mdash; Statystyki liczb</h1>

<form method="get" action="">
    <input type="hidden" name="page"  value="stats">
    <input type="hidden" name="game"  value="<?= h($game) ?>">
    <input type="hidden" name="sort"  value="<?= h($sort) ?>">
    <input type="hidden" name="dir"   value="<?= h($dir) ?>">
    <label>Od: <input type="date" name="date_from" value="<?= h($dateFrom) ?>"></label>
    <label>Do: <input type="date" name="date_to"   value="<?= h($dateTo) ?>"></label>
    <input type="submit" value="Filtruj">
    <?php if ($dateFrom !== '' || $dateTo !== ''): ?>
        <a href="?page=stats&game=<?= h($game) ?>">Wyczyść</a>
    <?php endif; ?>
</form>

<p>Łącznie losowań: <strong><?= h((string)$totalDraws) ?></strong> &mdash;
   Ostatni numer: <strong><?= h((string)$maxDrawNum) ?></strong></p>

<div style="background:#fff;border:1px solid #ddd;padding:12px 18px;margin-bottom:15px;border-radius:4px;font-size:0.9em;">
  <strong>📖 Objaśnienie kolumn:</strong>
  <ul style="margin:6px 0 0 0;padding-left:18px;">
    <li><strong>Łącznie</strong> – całkowita liczba wystąpień tej liczby w historii</li>
    <li><strong>Ost. 500</strong> – wystąpienia w ostatnich 500 losowaniach (domyślne okno hot/cold)</li>
    <li><strong>Temp.</strong> – temperatura: 🔥 Gorąca (często), ~ Letnia (przeciętnie), ❄ Zimna (rzadko)</li>
    <li><strong>Przerwa</strong> – ile losowań minęło od ostatniego wystąpienia (aktualny gap)</li>
    <li><strong>Śr. interw.</strong> – średnio co ile losowań ta liczba pada (historia / częstość)</li>
    <li><strong>Zaległość</strong> – przerwa ÷ średni interwał; &gt;1.0 = czeka dłużej niż zwykle; &gt;2.0 = mocno zalega</li>
  </ul>
</div>

<table>
    <thead>
        <tr>
            <th><a href="<?= h(stats_sort_url('num')) ?>" style="color:#fff;"><abbr title="Kliknij nagłówek kolumny aby posortować tabelę">Liczba↕</abbr><?= sort_arrow('num') ?></a></th>
            <th><a href="<?= h(stats_sort_url('total_freq')) ?>" style="color:#fff;"><abbr title="Ile razy ta liczba padła w całej historii losowań (w wybranym przedziale dat)">Łącznie↕</abbr><?= sort_arrow('total_freq') ?></a></th>
            <th><a href="<?= h(stats_sort_url('window_freq')) ?>" style="color:#fff;"><abbr title="Ile razy ta liczba padła w ostatnich 500 losowaniach (okno czasowe dla hot/cold)">Ost. 500↕</abbr><?= sort_arrow('window_freq') ?></a></th>
            <th>Temp.</th>
            <th><a href="<?= h(stats_sort_url('last_seen_draw')) ?>" style="color:#fff;"><abbr title="Numer ostatniego losowania, w którym ta liczba padła">Ostatnio↕</abbr><?= sort_arrow('last_seen_draw') ?></a></th>
            <th><a href="<?= h(stats_sort_url('current_gap')) ?>" style="color:#fff;"><abbr title="Ile losowań minęło od ostatniego wystąpienia tej liczby (aktualny gap)">Przerwa↕</abbr><?= sort_arrow('current_gap') ?></a></th>
            <th><a href="<?= h(stats_sort_url('avg_interval')) ?>" style="color:#fff;"><abbr title="Średnia liczba losowań między kolejnymi wystąpieniami tej liczby (całkowita historia ÷ częstość)">Śr. interw.↕</abbr><?= sort_arrow('avg_interval') ?></a></th>
            <th><a href="<?= h(stats_sort_url('overdue_score')) ?>" style="color:#fff;"><abbr title="Wskaźnik zaległości = aktualna przerwa ÷ średni interwał. Wartość &gt; 1.0 = liczba czeka dłużej niż zwykle. Wartość &gt; 2.0 = mocno zalega.">Zaległość↕</abbr><?= sort_arrow('overdue_score') ?></a></th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($stats as $row): ?>
        <?php
        $isHot  = $row['window_freq'] > $avgWindowFreq;
        $isCold = $row['window_freq'] < $avgWindowFreq && $row['window_freq'] > 0;
        $class  = $isHot ? 'hot' : ($isCold ? 'cold' : '');

        if ($row['window_freq'] == 0) {
            $tempLabel = '<span style="color:#95a5a6;" title="Nie padła w ostatnich 500 losowaniach">— Nieaktywna</span>';
        } elseif ($row['window_freq'] > $avgWindowFreq * 1.2) {
            $tempLabel = '<span style="color:#c0392b;font-weight:bold;" title="Gorąca — pojawia się częściej niż średnia w ostatnich 500 losowaniach">🔥 Gorąca</span>';
        } elseif ($row['window_freq'] >= $avgWindowFreq * 0.8) {
            $tempLabel = '<span style="color:#e67e22;font-weight:bold;" title="Umiarkowana — pojawia się zbliżoną do średniej częstością">~ Letnia</span>';
        } else {
            $tempLabel = '<span style="color:#2980b9;font-weight:bold;" title="Zimna — pojawia się rzadziej niż średnia w ostatnich 500 losowaniach">❄ Zimna</span>';
        }

        if ($row['overdue_score'] > 2.0) {
            $overdueStyle = ' style="color:#c0392b;font-weight:bold;"';
        } elseif ($row['overdue_score'] > 1.0) {
            $overdueStyle = ' style="color:#e67e22;"';
        } else {
            $overdueStyle = '';
        }
        ?>
        <tr>
            <td><span class="ball <?= $class ?>"><?= h((string)$row['num']) ?></span></td>
            <td class="<?= $class ?>"><?= h((string)$row['total_freq']) ?></td>
            <td class="<?= $class ?>"><?= h((string)$row['window_freq']) ?></td>
            <td><?= $tempLabel ?></td>
            <td><?= $row['last_seen_draw'] > 0 ? h((string)$row['last_seen_draw']) : '—' ?></td>
            <td><?= h((string)$row['current_gap']) ?></td>
            <td><?= h((string)$row['avg_interval']) ?></td>
            <td<?= $overdueStyle ?>><?= h((string)$row['overdue_score']) ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
