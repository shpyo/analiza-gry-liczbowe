<?php
declare(strict_types=1);

/**
 * dashboard.php - Overview for the current game
 * Included by index.php; $pdo, $game, $gameDef, $kit, $gameNames are available.
 */

$pickCount  = $gameDef->pickCount;
$poolSize   = $gameDef->poolSize;
$drawsTable = $gameDef->drawsTable;

// -----------------------------------------------------------------------
// Last draw
// -----------------------------------------------------------------------
$lastDraw = $pdo->query(
    "SELECT * FROM `{$drawsTable}` ORDER BY draw_number DESC LIMIT 1"
)->fetch();

// -----------------------------------------------------------------------
// Hot / Cold numbers (last N draws)
// -----------------------------------------------------------------------
$windowLimit = AnalysisConfig::WINDOW_SIZE;
$colList  = $gameDef->numberColumnsSql();
$unionSql = $gameDef->unpivotNumbersSql('last_window');

$freqRows = $pdo->query(
    "WITH last_window AS (SELECT {$colList} FROM `{$drawsTable}` ORDER BY draw_number DESC LIMIT {$windowLimit})
     SELECT num, COUNT(*) AS freq
     FROM ({$unionSql}) AS t
     WHERE num IS NOT NULL
     GROUP BY num
     ORDER BY freq DESC"
)->fetchAll();

$hotNumbers  = array_slice($freqRows, 0, AnalysisConfig::DISPLAY_DASHBOARD_HOT);
$coldNumbers = array_slice(array_reverse($freqRows), 0, AnalysisConfig::DISPLAY_DASHBOARD_COLD);

// -----------------------------------------------------------------------
// Total draws count
// -----------------------------------------------------------------------
$totalDraws = (int)$pdo->query("SELECT COUNT(*) FROM `{$drawsTable}`")->fetchColumn();

// -----------------------------------------------------------------------
// Compute distribution for last draw
// -----------------------------------------------------------------------
$evenPct = 0;
$oddPct  = 0;
$lowPct  = 0;
$highPct = 0;
if ($lastDraw) {
    $evenPct = round((int)$lastDraw['even_count'] / $pickCount * 100);
    $oddPct  = 100 - $evenPct;
    $lowPct  = round((int)$lastDraw['low_count'] / $pickCount * 100);
    $highPct = 100 - $lowPct;
}

// -----------------------------------------------------------------------
// Render
// -----------------------------------------------------------------------
?>

<!-- Page Header -->
<header class="page-header">
    <div class="page-header__row">
        <div>
            <span class="text-label-md text-primary mb-2" style="display:block;"><?= h(strtoupper($gameDef->name)) ?> DASHBOARD</span>
            <h1 class="page-header__title">Najnowsze wyniki losowania</h1>
            <p class="page-header__desc">Kompleksowe zestawienie statystyczne najnowszego losowania <?= h($gameDef->name) ?>. Analiza częstości, trendów i wzorców.</p>
        </div>
        <div>
            <a href="<?= h($router->url('validator', $game)) ?>" class="btn btn--secondary btn--lg">
                <?= render_material_icon('confirmation_number', 'icon-filled') ?>
                Weryfikuj kupon
            </a>
        </div>
    </div>
</header>

<?php if (!$lastDraw): ?>
    <div class="alert alert-error">Brak losowań w bazie. Użyj <a href="<?= h($router->url('import', $game)) ?>">Import</a> aby pobrać dane.</div>
<?php else: ?>

<!-- Bento Grid -->
<div class="bento-grid">

    <!-- Latest Draw Card (col-span-8) -->
    <section class="card col-md-8 col-lg-8">
        <div class="flex justify-between items-center mb-6" style="flex-wrap:wrap;gap:0.5rem;">
            <div>
                <p class="text-label-lg text-outline mb-2">Losowanie #<?= h((string)$lastDraw['draw_number']) ?> &mdash; <?= h($lastDraw['draw_date']) ?></p>
                <h2 class="text-headline-lg">Wylosowane liczby</h2>
            </div>
            <span class="badge badge--info">
                <?= render_material_icon('verified') ?>
                Oficjalne wyniki
            </span>
        </div>

        <div class="balls-row balls-row--lg mb-8">
            <?php for ($i = 1; $i <= $pickCount; $i++): ?>
                <?= render_ball((int)$lastDraw["n{$i}"], 'xl') ?>
            <?php endfor; ?>
            <?php if ($gameDef->hasBonus && $lastDraw['plus_ball'] !== null): ?>
                <?= render_ball((int)$lastDraw['plus_ball'], 'xl ball--plus') ?>
            <?php endif; ?>
        </div>

        <div class="section-divider" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:1.5rem;">
            <div class="stat-item">
                <span class="stat-item__label">Suma liczb</span>
                <span class="stat-item__value"><?= h((string)$lastDraw['sum_total']) ?></span>
            </div>
            <div class="stat-item">
                <span class="stat-item__label">Parzyste / Nieparzyste</span>
                <span class="stat-item__value"><?= h((string)$lastDraw['even_count']) ?> / <?= h((string)($pickCount - (int)$lastDraw['even_count'])) ?></span>
            </div>
            <div class="stat-item">
                <span class="stat-item__label">Łącznie losowań</span>
                <span class="stat-item__value stat-item__value--primary"><?= h((string)$totalDraws) ?></span>
            </div>
        </div>
    </section>

    <!-- Hot Numbers Card (col-span-4) -->
    <section class="card card--tonal col-md-4 col-lg-4" style="display:flex;flex-direction:column;">
        <div class="flex items-center gap-3 mb-4">
            <?= render_material_icon('local_fire_department', 'icon-filled') ?>
            <h2 class="text-headline-md" style="color:var(--tertiary);">Gorące liczby</h2>
        </div>
        <p class="text-body-sm text-on-surface-variant mb-4 leading-relaxed">Najczęściej losowane w ostatnich 500 losowaniach.</p>

        <div style="display:flex;flex-direction:column;gap:0.75rem;flex:1;">
            <?php foreach ($hotNumbers as $row): ?>
            <div class="hot-item">
                <div class="hot-item__left">
                    <span class="hot-item__ball"><?= str_pad((string)$row['num'], 2, '0', STR_PAD_LEFT) ?></span>
                    <span class="hot-item__name">Numer <?= h((string)$row['num']) ?></span>
                </div>
                <span class="hot-item__count"><?= h((string)$row['freq']) ?> los.</span>
            </div>
            <?php endforeach; ?>
        </div>

        <a href="<?= h($router->url('stats', $game)) ?>" class="btn btn--ghost btn--full" style="margin-top:1rem;">
            Zobacz statystyki <?= render_material_icon('arrow_forward') ?>
        </a>
    </section>

    <!-- Odd/Even Distribution (col-span-7) -->
    <section class="card col-md-7 col-lg-7">
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-headline-md">Rozkład parzystych / niskich</h2>
            <span class="text-label-lg text-outline">Obecny trend</span>
        </div>
        <div style="display:flex;align-items:flex-end;gap:1.5rem;height:12rem;padding:0 0.5rem;">
            <div style="flex:1;display:flex;flex-direction:column;align-items:center;gap:0.5rem;height:100%;justify-content:flex-end;">
                <div style="width:100%;background:var(--primary-container);border-radius:var(--radius-xl) var(--radius-xl) 0 0;height:<?= $oddPct ?>%;min-height:2px;"></div>
                <span class="text-label-lg text-outline">NIEP. <?= $oddPct ?>%</span>
            </div>
            <div style="flex:1;display:flex;flex-direction:column;align-items:center;gap:0.5rem;height:100%;justify-content:flex-end;">
                <div style="width:100%;background:var(--secondary-container);border-radius:var(--radius-xl) var(--radius-xl) 0 0;height:<?= $evenPct ?>%;min-height:2px;"></div>
                <span class="text-label-lg text-outline">PARZ. <?= $evenPct ?>%</span>
            </div>
            <div style="flex:1;display:flex;flex-direction:column;align-items:center;gap:0.5rem;height:100%;justify-content:flex-end;">
                <div style="width:100%;background:var(--surface-container-high);border-radius:var(--radius-xl) var(--radius-xl) 0 0;height:<?= $lowPct ?>%;min-height:2px;"></div>
                <span class="text-label-lg text-outline">NISKIE <?= $lowPct ?>%</span>
            </div>
            <div style="flex:1;display:flex;flex-direction:column;align-items:center;gap:0.5rem;height:100%;justify-content:flex-end;">
                <div style="width:100%;background:var(--surface-container-high);border-radius:var(--radius-xl) var(--radius-xl) 0 0;height:<?= $highPct ?>%;min-height:2px;"></div>
                <span class="text-label-lg text-outline">WYSOKIE <?= $highPct ?>%</span>
            </div>
        </div>
    </section>

    <!-- Generate CTA Card (col-span-5) -->
    <section class="card card--cta col-md-5 col-lg-5">
        <div class="card-deco-1"></div>
        <div class="card-deco-2"></div>
        <div class="relative z-10">
            <h2 class="text-headline-lg mb-3" style="font-family:var(--font-headline);">Generuj moje liczby</h2>
            <p style="color:rgba(255,255,255,0.8);font-size:0.875rem;margin-bottom:1.5rem;line-height:1.6;">Nasz algorytm dobierze liczby na podstawie historycznych trendów prawdopodobieństwa.</p>
            <a href="<?= h($router->url('generator', $game)) ?>" class="btn btn--white">Szybki generator</a>
        </div>
    </section>

</div>

<?php endif; ?>
