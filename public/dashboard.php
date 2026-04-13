<?php
declare(strict_types=1);

/**
 * dashboard.php - Overview for the current game
 * Included by index.php; $pdo, $game, $gameNames are available.
 */

$gameConfig = get_game_config($pdo, $game);
$gameName   = $gameConfig['name'];
$drawsTable = GAME_TABLES[$game];
$pickCount  = (int)$gameConfig['pick_count'];

// -----------------------------------------------------------------------
// Last draw
// -----------------------------------------------------------------------
$lastDraw = $pdo->query(
    "SELECT * FROM `{$drawsTable}` ORDER BY draw_number DESC LIMIT 1"
)->fetch();

// -----------------------------------------------------------------------
// Hot / Cold numbers (last 500 draws)
// -----------------------------------------------------------------------
$numberCols = [];
for ($i = 1; $i <= $pickCount; $i++) {
    $numberCols[] = "n{$i}";
}

// Build a CTE-based UNION query; last 500 draws fetched once
$colList   = implode(', ', array_map(fn($c) => "`{$c}`", $numberCols));
$cteParts  = [];
foreach ($numberCols as $col) {
    $cteParts[] = "SELECT `{$col}` AS num FROM last500";
}
$unionSql = implode(' UNION ALL ', $cteParts);

$freqRows = $pdo->query(
    "WITH last500 AS (SELECT {$colList} FROM `{$drawsTable}` ORDER BY draw_number DESC LIMIT 500)
     SELECT num, COUNT(*) AS freq
     FROM ({$unionSql}) AS t
     WHERE num IS NOT NULL
     GROUP BY num
     ORDER BY freq DESC"
)->fetchAll();

$hotNumbers  = array_slice($freqRows, 0, 5);
$coldNumbers = array_slice(array_reverse($freqRows), 0, 5);

// -----------------------------------------------------------------------
// Last sync info
// -----------------------------------------------------------------------
$syncLog = $pdo->prepare(
    "SELECT * FROM sync_log WHERE game_slug = ? ORDER BY synced_at DESC LIMIT 1"
);
$syncLog->execute([$game]);
$lastSync = $syncLog->fetch();

// -----------------------------------------------------------------------
// Total draws count
// -----------------------------------------------------------------------
$totalDraws = (int)$pdo->query("SELECT COUNT(*) FROM `{$drawsTable}`")->fetchColumn();

// -----------------------------------------------------------------------
// Render
// -----------------------------------------------------------------------
?>
<h1><?= h($gameName) ?> &mdash; Dashboard</h1>

<p><strong>Łącznie losowań w bazie:</strong> <?= h((string)$totalDraws) ?></p>

<?php if ($lastDraw): ?>
<h2>Ostatnie losowanie</h2>
<div class="coupon">
    <strong>Losowanie #<?= h((string)$lastDraw['draw_number']) ?></strong>
    &mdash;
    <?= h($lastDraw['draw_date']) ?>
    &nbsp;&nbsp;
    <?php for ($i = 1; $i <= $pickCount; $i++): ?>
        <span class="ball"><?= h((string)$lastDraw["n{$i}"]) ?></span>
    <?php endfor; ?>
    <?php if ($game === 'lotto_plus' && $lastDraw['plus_ball'] !== null): ?>
        <span class="ball plus"><?= h((string)$lastDraw['plus_ball']) ?></span>
    <?php endif; ?>
    <br><small>
        Suma: <?= h((string)$lastDraw['sum_total']) ?> &nbsp;|&nbsp;
        Parzyste: <?= h((string)$lastDraw['even_count']) ?> &nbsp;|&nbsp;
        Niskie: <?= h((string)$lastDraw['low_count']) ?> &nbsp;|&nbsp;
        Profil: <code><?= h((string)$lastDraw['profile_hash']) ?></code><span style="font-size:0.8em;color:#555;"> (strukturalny wzorzec)</span>
    </small>
</div>
<?php else: ?>
<div class="alert alert-error">Brak losowań w bazie. Użyj Import aby pobrać dane.</div>
<?php endif; ?>

<h2>Gorące liczby (ostatnie 500 losowań)</h2>
<p style="font-size:0.85em;color:#555;margin-top:-8px;">Liczby które padały najczęściej w ostatnich 500 losowaniach. Kliknij <a href="?page=stats&game=<?= h($game) ?>">Statystyki</a> aby zobaczyć pełną tabelę z zaległościami.</p>
<?php if (!empty($hotNumbers)): ?>
    <?php foreach ($hotNumbers as $row): ?>
        <span class="ball hot" title="Freq: <?= h((string)$row['freq']) ?>">
            <?= h((string)$row['num']) ?>
        </span>
    <?php endforeach; ?>
    <br><small>Liczba pojawień: <?= implode(', ', array_map(fn($r) => h((string)$r['num']) . '×' . h((string)$r['freq']), $hotNumbers)) ?></small>
<?php else: ?>
    <p>Brak danych.</p>
<?php endif; ?>

<h2>Zimne liczby (ostatnie 500 losowań)</h2>
<p style="font-size:0.85em;color:#555;margin-top:-8px;">Liczby które padały najrzadziej w ostatnich 500 losowaniach.</p>
<?php if (!empty($coldNumbers)): ?>
    <?php foreach ($coldNumbers as $row): ?>
        <span class="ball cold" title="Freq: <?= h((string)$row['freq']) ?>">
            <?= h((string)$row['num']) ?>
        </span>
    <?php endforeach; ?>
    <br><small>Liczba pojawień: <?= implode(', ', array_map(fn($r) => h((string)$r['num']) . '×' . h((string)$r['freq']), $coldNumbers)) ?></small>
<?php else: ?>
    <p>Brak danych.</p>
<?php endif; ?>

<h2>Ostatnia synchronizacja</h2>
<?php if ($lastSync): ?>
<table style="width:auto;">
    <tr><th>Data</th><td><?= h($lastSync['synced_at']) ?></td></tr>
    <tr><th>Dodane</th><td><?= h((string)$lastSync['draws_added']) ?></td></tr>
    <tr><th>Status</th><td><?= h($lastSync['status']) ?></td></tr>
    <tr><th>Ostatni numer</th><td><?= h((string)$lastSync['last_draw_number']) ?></td></tr>
    <?php if ($lastSync['error_msg']): ?>
    <tr><th>Błąd</th><td class="hot"><?= h($lastSync['error_msg']) ?></td></tr>
    <?php endif; ?>
</table>
<?php else: ?>
<p>Brak wpisów synchronizacji.</p>
<?php endif; ?>

<h2>Szybkie linki</h2>
<p>
    <a href="?page=draws&game=<?= h($game) ?>">📋 Lista losowań</a> &nbsp;|&nbsp;
    <a href="?page=stats&game=<?= h($game) ?>">📊 Statystyki liczb</a> &nbsp;|&nbsp;
    <a href="?page=generator&game=<?= h($game) ?>">🎲 Generator kuponów</a> &nbsp;|&nbsp;
    <a href="?page=validator&game=<?= h($game) ?>">✅ Weryfikator</a> &nbsp;|&nbsp;
    <a href="?page=import&game=<?= h($game) ?>">⬇ Import danych</a>
</p>
