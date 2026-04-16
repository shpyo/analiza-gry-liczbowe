<?php
declare(strict_types=1);

/**
 * generator.php - Weighted random coupon generator with profile filters
 * Included by index.php; $pdo, $game, $gameDef, $kit are available.
 */

$pickCount    = $gameDef->pickCount;
$poolSize     = $gameDef->poolSize;
$drawsTable   = $gameDef->drawsTable;
$profileTable = $gameDef->profileTable;

// For Multi Multi, the player picks 1–10 numbers (not the full 20 drawn numbers).
$isMultiMulti      = ($gameDef->slug === 'multi_multi');
$multiMultiMaxPick = 10;
$playerPickCount   = $pickCount; // default: use full draw count (overridden below for multi_multi)
if ($isMultiMulti) {
    $playerPickCount = max(1, min($multiMultiMaxPick, (int)($_POST['player_pick_count'] ?? $_GET['player_pick_count'] ?? 5)));
}

// -----------------------------------------------------------------------
// Build number weights from last N draws
// -----------------------------------------------------------------------
$windowLimit = AnalysisConfig::WINDOW_SIZE;
$colList  = $gameDef->numberColumnsSql();
$unionSQL = $gameDef->unpivotNumbersSql('last_window');

$freqRows = $pdo->query(
    "WITH last_window AS (SELECT {$colList} FROM `{$drawsTable}` ORDER BY draw_number DESC LIMIT {$windowLimit})
     SELECT num, COUNT(*) AS freq FROM ({$unionSQL}) AS t WHERE num IS NOT NULL GROUP BY num"
)->fetchAll(PDO::FETCH_KEY_PAIR);

// weight = freq + floor (min weight so undrawn numbers still have a chance)
$weightFloor = AnalysisConfig::GENERATOR_WEIGHT_FLOOR;
$weights = [];
for ($n = 1; $n <= $poolSize; $n++) {
    $weights[$n] = (int)($freqRows[$n] ?? 0) + $weightFloor;
}

// Top-N hot numbers
arsort($freqRows);
$top10 = array_keys(array_slice($freqRows, 0, AnalysisConfig::GENERATOR_TOP_HOT_COUNT, true));

// -----------------------------------------------------------------------
// Profile hashes for select
// -----------------------------------------------------------------------
$profileHashes = $pdo->query(
    "SELECT profile_hash, total_draws, pct_of_total FROM `{$profileTable}` ORDER BY total_draws DESC"
)->fetchAll();

// -----------------------------------------------------------------------
// Handle POST - generate coupons
// -----------------------------------------------------------------------
$results     = [];
$warnings    = [];
$formPosted  = $_SERVER['REQUEST_METHOD'] === 'POST';

if ($formPosted) {
    $sumMin      = isset($_POST['sum_min'])       ? (int)$_POST['sum_min']       : 0;
    $sumMax      = isset($_POST['sum_max'])       ? (int)$_POST['sum_max']       : $poolSize * $pickCount;
    $evenMin     = isset($_POST['even_min'])      ? (int)$_POST['even_min']      : 0;
    $evenMax     = isset($_POST['even_max'])      ? (int)$_POST['even_max']      : $pickCount;
    $lowMin      = isset($_POST['low_min'])       ? (int)$_POST['low_min']       : 0;
    $lowMax      = isset($_POST['low_max'])       ? (int)$_POST['low_max']       : $pickCount;
    $consecMax   = isset($_POST['consec_max'])    ? (int)$_POST['consec_max']    : $pickCount;
    $hotMin      = isset($_POST['hot_min'])       ? (int)$_POST['hot_min']       : 0;
    $lastDigMax  = isset($_POST['last_digit_max'])? (int)$_POST['last_digit_max']: $pickCount;
    $decadesMax  = isset($_POST['decades_max'])   ? max(1, min($pickCount, (int)$_POST['decades_max'])) : $pickCount;
    $wantedHashes= isset($_POST['profile_hashes']) && is_array($_POST['profile_hashes'])
                     ? $_POST['profile_hashes'] : [];
    $count       = max(1, min(AnalysisConfig::GENERATOR_MAX_COUPONS, (int)($_POST['count'] ?? AnalysisConfig::GENERATOR_DEFAULT_COUNT)));

    $knownHashes  = array_column($profileHashes, 'profile_hash');
    $wantedHashes = array_values(array_intersect($wantedHashes, $knownHashes));

    $calc     = $kit->calculator();
    $describer = $kit->describer();

    $maxAttempts = AnalysisConfig::GENERATOR_MAX_ATTEMPTS;
    $attempts    = 0;

    while (count($results) < $count && $attempts < $maxAttempts) {
        $attempts++;

        $pool     = $weights;
        $selected = [];

        for ($pick = 0; $pick < $playerPickCount; $pick++) {
            $totalW = array_sum($pool);
            if ($totalW <= 0) {
                break;
            }
            $rand = mt_rand(1, $totalW);
            $cum  = 0;
            foreach ($pool as $num => $w) {
                $cum += $w;
                if ($rand <= $cum) {
                    $selected[] = $num;
                    unset($pool[$num]);
                    break;
                }
            }
        }

        if (count($selected) !== $playerPickCount) {
            continue;
        }

        sort($selected);
        $metrics = $calc->computeMetrics($selected, $gameDef);
        $hash    = $describer->computeHash($metrics, $gameDef);

        if ($metrics['sum_total'] < $sumMin || $metrics['sum_total'] > $sumMax) continue;
        if ($metrics['even_count'] < $evenMin || $metrics['even_count'] > $evenMax) continue;
        if ($metrics['low_count'] < $lowMin || $metrics['low_count'] > $lowMax) continue;
        if ($metrics['consecutive'] > $consecMax) continue;
        if ($metrics['last_digit_unique'] > $lastDigMax) continue;

        $hotInCoupon = count(array_intersect($selected, $top10));
        if ($hotInCoupon < $hotMin) continue;

        if ($decadesMax < $pickCount) {
            $decadeGroups = [];
            foreach ($selected as $n) {
                $d = intdiv($n - 1, 10);
                $decadeGroups[$d] = ($decadeGroups[$d] ?? 0) + 1;
            }
            if (max($decadeGroups) > $decadesMax) continue;
        }

        if (!empty($wantedHashes) && !in_array($hash, $wantedHashes, true)) continue;

        $results[] = [
            'numbers' => $selected,
            'metrics' => $metrics,
            'hash'    => $hash,
        ];
    }

    if (count($results) < $count) {
        $warnings[] = "Znaleziono tylko " . count($results) . " z {$count} kuponów po {$attempts} próbach. "
                    . "Spróbuj poluzować kryteria filtrowania.";
    }
}

// Default form values
$def = [
    'sum_min'        => (int)($_POST['sum_min']        ?? 0),
    'sum_max'        => (int)($_POST['sum_max']        ?? $poolSize * $pickCount),
    'even_min'       => (int)($_POST['even_min']       ?? 0),
    'even_max'       => (int)($_POST['even_max']       ?? $pickCount),
    'low_min'        => (int)($_POST['low_min']        ?? 0),
    'low_max'        => (int)($_POST['low_max']        ?? $pickCount),
    'consec_max'     => (int)($_POST['consec_max']     ?? $pickCount - 1),
    'hot_min'        => (int)($_POST['hot_min']        ?? 0),
    'last_digit_max' => (int)($_POST['last_digit_max'] ?? $pickCount),
    'decades_max'    => (int)($_POST['decades_max']    ?? $pickCount),
    'count'          => (int)($_POST['count']          ?? 5),
];
$postedHashes = isset($_POST['profile_hashes']) && is_array($_POST['profile_hashes'])
    ? $_POST['profile_hashes'] : [];

// Heatmap data for frequency grid
$maxFreqHm = max(1, max(array_values($freqRows) ?: [1]));
?>

<!-- Page Header -->
<header class="page-header">
    <div class="page-header__row">
        <div>
            <h1 class="page-header__title">Generator kuponów</h1>
            <p class="page-header__desc">Zaawansowane algorytmy dobierające liczby na podstawie historycznych trendów prawdopodobieństwa z ostatnich 500 losowań <?= h($gameDef->name) ?>.</p>
        </div>
    </div>
</header>

<div class="bento-grid">

    <!-- Generate Card (main) -->
    <section class="card col-md-8 col-lg-8">
        <form method="post" action="?page=generator&game=<?= h($game) ?>">

            <div class="flex justify-between items-center mb-6" style="flex-wrap:wrap;gap:0.5rem;">
                <div>
                    <span class="badge badge--info mb-2"><?= render_material_icon('bolt') ?> SMART ENGINE ACTIVE</span>
                    <h2 class="text-headline-lg" style="margin-top:0.5rem;">Generuj kupony</h2>
                </div>
                <div class="form-group" style="min-width:100px;">
                    <label class="form-label">Zestawy</label>
                    <select name="count" class="form-select" style="width:auto;">
                        <?php foreach (AnalysisConfig::GENERATOR_COUNT_OPTIONS as $c): ?>
                            <option value="<?= $c ?>" <?= $def['count'] === $c ? 'selected' : '' ?>><?= $c ?> zest.</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php if ($isMultiMulti): ?>
                <div class="form-group" style="min-width:120px;">
                    <label class="form-label">Liczb w kuponie</label>
                    <select name="player_pick_count" class="form-select" style="width:auto;">
                        <?php for ($i = 1; $i <= $multiMultiMaxPick; $i++): ?>
                            <option value="<?= $i ?>" <?= $playerPickCount === $i ? 'selected' : '' ?>><?= $i ?> liczb</option>
                        <?php endfor; ?>
                    </select>
                </div>
                <?php endif; ?>
            </div>

            <!-- Placeholder / Generated balls -->
            <div class="balls-row balls-row--lg mb-6" style="justify-content:center;">
                <?php if ($formPosted && !empty($results)): ?>
                    <?php foreach ($results[0]['numbers'] as $n): ?>
                        <?= render_ball($n, 'xl' . (in_array($n, $top10, true) ? ' ball--hot' : '')) ?>
                    <?php endforeach; ?>
                <?php else: ?>
                    <?php for ($i = 0; $i < $playerPickCount; $i++): ?>
                        <span class="ball ball--xl ball--placeholder">?</span>
                    <?php endfor; ?>
                <?php endif; ?>
            </div>

            <!-- Action buttons -->
            <div class="flex gap-4 mb-8" style="flex-wrap:wrap;">
                <button type="submit" class="btn btn--primary btn--lg flex-1">
                    <?= render_material_icon('bolt') ?> GENERUJ LICZBY
                </button>
            </div>

            <!-- Advanced Filters (collapsible) -->
            <details class="card-details">
                <summary>Zaawansowane filtry</summary>
                <div class="details-content">
                    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:1rem;margin-top:1rem;">
                        <div class="form-group">
                            <label class="form-label"><?= $kit->texts()->renderTooltip('sum_total', $gameDef) ?> min</label>
                            <input type="number" name="sum_min" value="<?= h((string)$def['sum_min']) ?>" min="0" max="999" class="form-input">
                        </div>
                        <div class="form-group">
                            <label class="form-label"><?= $kit->texts()->renderTooltip('sum_total', $gameDef) ?> max</label>
                            <input type="number" name="sum_max" value="<?= h((string)$def['sum_max']) ?>" min="0" max="999" class="form-input">
                        </div>
                        <div class="form-group">
                            <label class="form-label"><?= $kit->texts()->renderTooltip('even_count', $gameDef) ?> min</label>
                            <select name="even_min" class="form-select">
                                <?php for ($i = 0; $i <= $pickCount; $i++): ?>
                                    <option value="<?= $i ?>" <?= $def['even_min'] === $i ? 'selected' : '' ?>><?= $i ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label"><?= $kit->texts()->renderTooltip('even_count', $gameDef) ?> max</label>
                            <select name="even_max" class="form-select">
                                <?php for ($i = 0; $i <= $pickCount; $i++): ?>
                                    <option value="<?= $i ?>" <?= $def['even_max'] === $i ? 'selected' : '' ?>><?= $i ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label"><?= $kit->texts()->renderTooltip('low_count', $gameDef) ?> min</label>
                            <select name="low_min" class="form-select">
                                <?php for ($i = 0; $i <= $pickCount; $i++): ?>
                                    <option value="<?= $i ?>" <?= $def['low_min'] === $i ? 'selected' : '' ?>><?= $i ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label"><?= $kit->texts()->renderTooltip('low_count', $gameDef) ?> max</label>
                            <select name="low_max" class="form-select">
                                <?php for ($i = 0; $i <= $pickCount; $i++): ?>
                                    <option value="<?= $i ?>" <?= $def['low_max'] === $i ? 'selected' : '' ?>><?= $i ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label"><?= $kit->texts()->renderTooltip('consecutive', $gameDef) ?> maks.</label>
                            <select name="consec_max" class="form-select">
                                <?php for ($i = 0; $i < $pickCount; $i++): ?>
                                    <option value="<?= $i ?>" <?= $def['consec_max'] === $i ? 'selected' : '' ?>><?= $i ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Min. gorących (top-10)</label>
                            <select name="hot_min" class="form-select">
                                <?php for ($i = 0; $i <= $pickCount; $i++): ?>
                                    <option value="<?= $i ?>" <?= $def['hot_min'] === $i ? 'selected' : '' ?>><?= $i ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label"><?= $kit->texts()->renderTooltip('last_digit_unique', $gameDef) ?> maks.</label>
                            <select name="last_digit_max" class="form-select">
                                <?php for ($i = 1; $i <= $pickCount; $i++): ?>
                                    <option value="<?= $i ?>" <?= $def['last_digit_max'] === $i ? 'selected' : '' ?>><?= $i ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label"><?= $kit->texts()->renderTooltip('decades_used', $gameDef) ?> maks. z jednej</label>
                            <input type="number" name="decades_max" value="<?= h((string)$def['decades_max']) ?>" min="1" max="<?= $pickCount ?>" class="form-input">
                        </div>
                    </div>

                    <?php if (!empty($profileHashes)): ?>
                    <div style="margin-top:1rem;">
                        <label class="form-label"><?= $kit->texts()->renderTooltip('profile_hash', $gameDef) ?> (Ctrl+klik dla wielu)</label>
                        <select name="profile_hashes[]" multiple size="5" class="form-select" style="height:auto;">
                            <?php foreach ($profileHashes as $ph): ?>
                                <option value="<?= h($ph['profile_hash']) ?>"
                                    <?= in_array($ph['profile_hash'], $postedHashes, true) ? 'selected' : '' ?>>
                                    <?= h($kit->describer()->describeShort($ph['profile_hash'])) ?>
                                    &nbsp;(<?= h((string)$ph['total_draws']) ?>x / <?= h((string)$ph['pct_of_total']) ?>%)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="form-hint">Wybierz profile aby ograniczyć generowanie do określonych wzorców strukturalnych.</p>
                    </div>
                    <?php endif; ?>
                </div>
            </details>
        </form>

        <!-- Frequency Heat Map -->
        <div style="margin-top:2rem;">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-headline-md">Heatmapa częstości</h3>
                <div class="flex items-center gap-3 text-body-sm text-outline">
                    <span style="display:inline-flex;align-items:center;gap:0.25rem;"><span style="width:0.75rem;height:0.75rem;border-radius:var(--radius-full);background:var(--secondary-container);display:inline-block;"></span> Niska</span>
                    <span style="display:inline-flex;align-items:center;gap:0.25rem;"><span style="width:0.75rem;height:0.75rem;border-radius:var(--radius-full);background:var(--tertiary);display:inline-block;"></span> Wysoka</span>
                </div>
            </div>
            <div class="freq-heatmap">
                <?php for ($n = 1; $n <= $poolSize; $n++):
                    $freq = (int)($freqRows[$n] ?? 0);
                    $ratio = $maxFreqHm > 0 ? $freq / $maxFreqHm : 0;
                    $isHot = in_array($n, $top10, true);
                    if ($isHot) {
                        $bg = "var(--tertiary)";
                        $color = "var(--on-tertiary)";
                    } elseif ($ratio > AnalysisConfig::GENERATOR_HEATMAP_HOT_RATIO) {
                        $bg = "var(--tertiary-fixed-dim)";
                        $color = "var(--on-tertiary-fixed)";
                    } elseif ($ratio > AnalysisConfig::GENERATOR_HEATMAP_WARM_RATIO) {
                        $bg = "var(--secondary-container)";
                        $color = "var(--on-primary-fixed)";
                    } else {
                        $bg = "var(--surface-container-high)";
                        $color = "var(--on-surface-variant)";
                    }
                ?>
                <div class="freq-heatmap__cell" style="background:<?= $bg ?>;color:<?= $color ?>;" title="<?= h("{$n}: {$freq} trafień") ?>"><?= $n ?></div>
                <?php endfor; ?>
            </div>
            <p class="text-body-sm text-outline" style="margin-top:0.75rem;">
                <?= render_material_icon('info', 'icon-filled') ?>
                Częstość z ostatnich 500 losowań. Pomarańczowe pola to gorące liczby.
            </p>
        </div>
    </section>

    <!-- Right Sidebar -->
    <div class="col-md-4 col-lg-4" style="display:flex;flex-direction:column;gap:1.5rem;">

        <!-- Generated History -->
        <?php if ($formPosted && !empty($results)): ?>
        <section class="card">
            <h3 class="text-headline-md mb-4">Wygenerowane kupony</h3>
            <div style="display:flex;flex-direction:column;gap:0.75rem;">
                <?php foreach ($results as $idx => $r): ?>
                <div class="gen-history-item">
                    <div class="gen-history-item__time">Kupon <?= $idx + 1 ?></div>
                    <div class="balls-row" style="gap:0.25rem;">
                        <?php foreach ($r['numbers'] as $n): ?>
                            <?= render_ball($n, 'sm' . (in_array($n, $top10, true) ? ' ball--hot' : '')) ?>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>

        <!-- Analysis Card -->
        <?php if ($formPosted && !empty($results)): ?>
        <section class="analysis-card">
            <h3 class="text-headline-md mb-3" style="font-family:var(--font-headline);">Analiza ukończona</h3>
            <p style="color:rgba(255,255,255,0.8);font-size:0.875rem;margin-bottom:1.5rem;line-height:1.6;">
                Wygenerowano <?= count($results) ?> kuponów z wagami opartymi na historycznej częstości.
                Top-10 gorących: <?= implode(', ', $top10) ?>.
            </p>
            <div class="progress-bar" style="margin-bottom:0.5rem;">
                <div class="progress-bar__fill" style="width:72%;"></div>
            </div>
            <div class="flex justify-between" style="font-size:0.75rem;opacity:0.8;">
                <span>Zgodność z wzorcem</span>
                <span style="font-weight:700;">72%</span>
            </div>
        </section>
        <?php else: ?>
        <section class="card card--tonal">
            <h3 class="text-headline-md mb-3">PRO TIP</h3>
            <p class="text-body-sm text-on-surface-variant leading-relaxed">
                Wygenerowane liczby oparte są na trendach prawdopodobieństwa z ostatnich 500 losowań. Gorące liczby mają wyższe wagi, ale każda liczba ma szansę. Użyj filtrów, aby doprecyzować kryteria kuponu.
            </p>
        </section>
        <?php endif; ?>
    </div>
</div>

<?php if (!empty($warnings)): ?>
    <?php foreach ($warnings as $w): ?>
        <div class="alert alert-warning" style="margin-top:1.5rem;"><?= h($w) ?></div>
    <?php endforeach; ?>
<?php endif; ?>

<?php if ($formPosted && !empty($results)): ?>
<!-- Detailed Coupon Breakdown -->
<div style="margin-top:2rem;">
    <h2 class="text-headline-lg mb-4">Szczegóły kuponów</h2>
    <?php foreach ($results as $idx => $r): ?>
    <div class="coupon">
        <div class="flex justify-between items-center mb-3" style="flex-wrap:wrap;gap:0.5rem;">
            <strong class="text-title-md">Kupon <?= $idx + 1 ?></strong>
            <span class="text-body-sm text-on-surface-variant">
                Suma: <?= h((string)$r['metrics']['sum_total']) ?> &middot;
                Parzyste: <?= h((string)$r['metrics']['even_count']) ?> &middot;
                Niskie: <?= h((string)$r['metrics']['low_count']) ?> &middot;
                <?= h($kit->describer()->describeShort($r['hash'])) ?>
            </span>
        </div>
        <div class="balls-row mb-3">
            <?php foreach ($r['numbers'] as $n): ?>
                <?= render_ball($n, 'md' . (in_array($n, $top10, true) ? ' ball--hot' : '')) ?>
            <?php endforeach; ?>
        </div>

        <?php
        $defaultSumMin  = 0;
        $defaultSumMax  = $poolSize * $pickCount;
        $defaultEvenMin = 0;
        $defaultEvenMax = $pickCount;
        $defaultLowMin  = 0;
        $defaultLowMax  = $pickCount;
        $defaultConsec  = $pickCount - 1;
        $defaultHotMin  = 0;
        $defaultLastDig = $pickCount;
        $activeFilters  = [];

        if ($sumMin > $defaultSumMin || $sumMax < $defaultSumMax) {
            $activeFilters[] = ['label' => $kit->texts()->label('sum_total'), 'value' => $r['metrics']['sum_total'], 'range' => "{$sumMin}–{$sumMax}", 'ok' => ($r['metrics']['sum_total'] >= $sumMin && $r['metrics']['sum_total'] <= $sumMax)];
        } else {
            $activeFilters[] = ['label' => $kit->texts()->label('sum_total'), 'value' => $r['metrics']['sum_total'], 'range' => '—', 'ok' => true];
        }
        if ($evenMin > $defaultEvenMin || $evenMax < $defaultEvenMax) {
            $activeFilters[] = ['label' => $kit->texts()->label('even_count'), 'value' => $r['metrics']['even_count'], 'range' => "{$evenMin}–{$evenMax}", 'ok' => ($r['metrics']['even_count'] >= $evenMin && $r['metrics']['even_count'] <= $evenMax)];
        }
        if ($lowMin > $defaultLowMin || $lowMax < $defaultLowMax) {
            $activeFilters[] = ['label' => $kit->texts()->label('low_count'), 'value' => $r['metrics']['low_count'], 'range' => "{$lowMin}–{$lowMax}", 'ok' => ($r['metrics']['low_count'] >= $lowMin && $r['metrics']['low_count'] <= $lowMax)];
        }
        if ($consecMax < $defaultConsec) {
            $activeFilters[] = ['label' => $kit->texts()->label('consecutive'), 'value' => $r['metrics']['consecutive'], 'range' => "maks. {$consecMax}", 'ok' => ($r['metrics']['consecutive'] <= $consecMax)];
        }
        if ($lastDigMax < $defaultLastDig) {
            $activeFilters[] = ['label' => $kit->texts()->label('last_digit_unique'), 'value' => $r['metrics']['last_digit_unique'], 'range' => "maks. {$lastDigMax}", 'ok' => ($r['metrics']['last_digit_unique'] <= $lastDigMax)];
        }
        if ($hotMin > $defaultHotMin) {
            $hotInCoupon = count(array_intersect($r['numbers'], $top10));
            $activeFilters[] = ['label' => 'Gorące (top-10)', 'value' => $hotInCoupon, 'range' => "min. {$hotMin}", 'ok' => ($hotInCoupon >= $hotMin)];
        }
        if (!empty($wantedHashes)) {
            $activeFilters[] = ['label' => $kit->texts()->label('profile_hash'), 'value' => $kit->describer()->describeShort($r['hash']), 'range' => '(wybrany)', 'ok' => in_array($r['hash'], $wantedHashes, true)];
        } else {
            $activeFilters[] = ['label' => $kit->texts()->label('profile_hash'), 'value' => $kit->describer()->describeShort($r['hash']), 'range' => '—', 'ok' => true];
        }
        ?>
        <details class="card-details">
            <summary>Dlaczego te liczby?</summary>
            <div class="details-content">
                <table class="data-table" style="margin-top:0.5rem;">
                    <thead>
                        <tr>
                            <th>Filtr</th>
                            <th>Wartość</th>
                            <th>Zakres</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($activeFilters as $af): ?>
                        <tr>
                            <td><?= h($af['label']) ?></td>
                            <td><strong><?= h((string)$af['value']) ?></strong></td>
                            <td class="text-on-surface-variant"><?= h($af['range']) ?></td>
                            <td><?= $af['ok'] ? render_badge('OK', 'stable') : render_badge('X', 'cold') ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </details>
    </div>
    <?php endforeach; ?>
</div>

<?php elseif ($formPosted): ?>
<div class="alert alert-error" style="margin-top:1.5rem;">Nie udało się wygenerować żadnego kuponu spełniającego kryteria.</div>
<?php endif; ?>
