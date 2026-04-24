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
$betPickMin   = $gameDef->betPickMin();
$betPickMax   = $gameDef->betPickMax();
$hasVarBet    = $betPickMin !== $betPickMax;

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
    // For variable-bet games (Multi Multi), pick count comes from the form
    $betPickCount = $hasVarBet
        ? max($betPickMin, min($betPickMax, (int)($_POST['bet_pick_count'] ?? $betPickMax)))
        : $pickCount;

    $sumAbsMin   = (int)($betPickCount * ($betPickCount + 1) / 2);
    $sumAbsMax   = (int)($betPickCount * (2 * $poolSize - $betPickCount + 1) / 2);
    $sumMin      = isset($_POST['sum_min'])       ? min($sumAbsMax, max($sumAbsMin, (int)$_POST['sum_min']))    : $sumAbsMin;
    $sumMax      = isset($_POST['sum_max'])       ? min($sumAbsMax, max($sumAbsMin, (int)$_POST['sum_max']))    : $sumAbsMax;
    $evenMin     = isset($_POST['even_min'])      ? min($betPickCount, max(0, (int)$_POST['even_min']))     : 0;
    $evenMax     = isset($_POST['even_max'])      ? min($betPickCount, max(0, (int)$_POST['even_max']))     : $betPickCount;
    $lowMin      = isset($_POST['low_min'])       ? min($betPickCount, max(0, (int)$_POST['low_min']))      : 0;
    $lowMax      = isset($_POST['low_max'])       ? min($betPickCount, max(0, (int)$_POST['low_max']))      : $betPickCount;
    $consecMax   = isset($_POST['consec_max'])    ? min($betPickCount - 1, max(0, (int)$_POST['consec_max'])): $betPickCount - 1;
    $hotMin      = isset($_POST['hot_min'])       ? min($betPickCount, max(0, (int)$_POST['hot_min']))      : 0;
    $lastDigMax  = isset($_POST['last_digit_max'])? min($betPickCount, max(1, (int)$_POST['last_digit_max'])): $betPickCount;
    $decadesMax  = isset($_POST['decades_max'])   ? max(1, min($betPickCount, (int)$_POST['decades_max'])) : $betPickCount;
    // Max dziesiątek to 8 dla MM (80 liczb / 10), min sens = 1
    $decadesMaxPool = (int)ceil($poolSize / 10);
    $decadesMin  = isset($_POST['decades_min'])   ? max(1, min($decadesMaxPool, (int)$_POST['decades_min'])) : 1;
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

        for ($pick = 0; $pick < $betPickCount; $pick++) {
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

        if (count($selected) !== $betPickCount) {
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

        // Decades: compute groups for both min and max checks
        $decadeGroups = [];
        foreach ($selected as $n) {
            $d = intdiv($n - 1, 10);
            $decadeGroups[$d] = ($decadeGroups[$d] ?? 0) + 1;
        }
        if ($metrics['decades_used'] < $decadesMin) continue;
        if (max($decadeGroups) > $decadesMax) continue;

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

// Default form values (for non-POST render; betPickCount not yet set outside POST block)
$defBetPickCount = $hasVarBet
    ? max($betPickMin, min($betPickMax, (int)($_POST['bet_pick_count'] ?? $betPickMax)))
    : $pickCount;

// Sum range data for variable-bet games (e.g. Multi Multi)
$sumRangeData = [];
if ($hasVarBet) {
    $sumRangeData = $kit->texts()->sumRangesForVarBet($gameDef);
}

// Smart defaults: use typical ranges (mean ± 1.5σ) instead of full 0–max
$k  = $defBetPickCount;
$N  = $poolSize;
$sumMean  = $k * ($N + 1) / 2;
$sumSigma = sqrt($k * ($N + 1) * ($N - $k) / 12);
$defSumMin = max((int)($k * ($k + 1) / 2), (int)round($sumMean - 1.5 * $sumSigma));
$defSumMax = min((int)($k * (2 * $N - $k + 1) / 2), (int)round($sumMean + 1.5 * $sumSigma));

// Even/low: binomial mean ± 1 (for k numbers from pool, ~half are even/low)
$evenMean = $k / 2;
$defEvenMin = max(0, (int)floor($evenMean - 1));
$defEvenMax = min($k, (int)ceil($evenMean + 1));
$lowRatio   = $gameDef->lowThreshold / $N;
$lowMean    = $k * $lowRatio;
$defLowMin  = max(0, (int)floor($lowMean - 1));
$defLowMax  = min($k, (int)ceil($lowMean + 1));

// Decades: typical spread
$defDecadesMin = max(1, (int)round($k * 0.5));

$defSumAbsMax = (int)($k * (2 * $N - $k + 1) / 2);
$def = [
    'sum_min'        => min($defSumAbsMax, max(0, (int)($_POST['sum_min']        ?? $defSumMin))),
    'sum_max'        => min($defSumAbsMax, max(0, (int)($_POST['sum_max']        ?? $defSumMax))),
    'even_min'       => min($k, max(0, (int)($_POST['even_min']    ?? $defEvenMin))),
    'even_max'       => min($k, max(0, (int)($_POST['even_max']    ?? $defEvenMax))),
    'low_min'        => min($k, max(0, (int)($_POST['low_min']     ?? $defLowMin))),
    'low_max'        => min($k, max(0, (int)($_POST['low_max']     ?? $defLowMax))),
    'consec_max'     => min($k - 1, max(0, (int)($_POST['consec_max'] ?? 2))),
    'hot_min'        => min($k, max(0, (int)($_POST['hot_min']     ?? 0))),
    'last_digit_max' => min($k, max(1, (int)($_POST['last_digit_max'] ?? $k))),
    'decades_max'    => min($k, max(1, (int)($_POST['decades_max']    ?? $k))),
    'decades_min'    => max(1, (int)($_POST['decades_min']    ?? $defDecadesMin)),
    'count'          => (int)($_POST['count']          ?? 5),
    'bet_pick_count' => $defBetPickCount,
];
$postedHashes = isset($_POST['profile_hashes']) && is_array($_POST['profile_hashes'])
    ? $_POST['profile_hashes'] : [];
?>

<!-- Page Header -->
<header class="page-header">
    <div class="page-header__row">
        <div>
            <h1 class="page-header__title">Generator kuponów</h1>
            <p class="page-header__desc">Losowanie ważone częstością historyczną z ostatnich <?= AnalysisConfig::WINDOW_SIZE ?> losowań <?= h($gameDef->name) ?> z filtrami strukturalnymi.</p>
        </div>
    </div>
</header>

    <!-- Generate Card (main) -->
    <section class="card">
        <form method="post" action="<?= h($router->url('generator', $game)) ?>">

            <div class="flex justify-between items-center mb-6" style="flex-wrap:wrap;gap:0.5rem;">
                <div>
                    <span class="badge badge--info mb-2"><?= render_material_icon('bolt') ?> LOSOWANIE WAŻONE + FILTRY</span>
                    <h2 class="text-headline-lg" style="margin-top:0.5rem;">Generuj kupony</h2>
                </div>
                <div class="flex gap-3" style="flex-wrap:wrap;align-items:flex-end;">
                    <?php if ($hasVarBet): ?>
                    <div class="form-group" style="min-width:120px;">
                        <label class="form-label">Liczby w kuponie</label>
                        <select name="bet_pick_count" class="form-select" style="width:auto;">
                            <?php for ($bpc = $betPickMin; $bpc <= $betPickMax; $bpc++): ?>
                                <option value="<?= $bpc ?>" <?= $def['bet_pick_count'] === $bpc ? 'selected' : '' ?>><?= $bpc ?> liczb</option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <?php endif; ?>
                    <div class="form-group" style="min-width:100px;">
                        <label class="form-label">Zestawy</label>
                        <select name="count" class="form-select" style="width:auto;">
                            <?php foreach (AnalysisConfig::GENERATOR_COUNT_OPTIONS as $c): ?>
                                <option value="<?= $c ?>" <?= $def['count'] === $c ? 'selected' : '' ?>><?= $c ?> zest.</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Generated balls (only when results available) -->
            <?php if ($formPosted && !empty($results)): ?>
            <div class="balls-row balls-row--lg mb-6" style="justify-content:center;">
                <?php foreach ($results[0]['numbers'] as $n): ?>
                    <?= render_ball($n, 'xl' . (in_array($n, $top10, true) ? ' ball--hot' : '')) ?>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- Advanced Filters (always visible) -->
            <div class="advanced-filters" style="margin-top:1.5rem;">
                <h3 class="text-title-md mb-4">Zaawansowane filtry</h3>

                <!-- SUMA -->
                <?php
                    $sumAbsMax = $poolSize * $defBetPickCount;
                    $curSR = $sumRangeData[$defBetPickCount] ?? null;
                ?>
                <div class="filter-section">
                    <div class="filter-section__title"><?= render_material_icon('functions') ?> Suma liczb</div>
                    <div class="filter-grid-range">
                        <div class="form-group">
                            <label class="form-label">od</label>
                            <input type="number" name="sum_min" value="<?= h((string)$def['sum_min']) ?>" min="<?= $curSR ? $curSR['min'] : 0 ?>" max="<?= $sumAbsMax ?>" class="form-input">
                        </div>
                        <span class="range-label">&ndash;</span>
                        <div class="form-group">
                            <label class="form-label">do</label>
                            <input type="number" name="sum_max" value="<?= h((string)$def['sum_max']) ?>" min="<?= $curSR ? $curSR['min'] : 0 ?>" max="<?= $sumAbsMax ?>" class="form-input">
                        </div>
                    </div>
                    <?php if ($hasVarBet && $curSR): ?>
                    <p class="form-hint" style="margin-top:0.5rem;">
                        Dla <strong id="sum-hint-k"><?= $defBetPickCount ?></strong> liczb: typowa <strong id="sum-hint-typ"><?= $curSR['typ_min'] ?>&ndash;<?= $curSR['typ_max'] ?></strong>
                        &middot; zakres <span id="sum-hint-range"><?= $curSR['min'] ?>&ndash;<?= $curSR['max'] ?></span>
                        &middot; ~<span id="sum-hint-mean"><?= $curSR['mean'] ?></span>
                    </p>
                    <?php endif; ?>
                    <details class="filter-info">
                        <summary><?= render_material_icon('help_outline') ?> Co to jest?</summary>
                        <div class="filter-info__body">
                            <strong>Suma liczb</strong> to łączna wartość wszystkich liczb na kuponie. Np. kupon 7+14+22+35+41+48 ma sumę 167. Bardzo niska lub bardzo wysoka suma zdarza się rzadko — typowe kupony mieszczą się w środkowym przedziale.
                        </div>
                    </details>
                </div>
                <?php if ($hasVarBet && $curSR): ?>
                <script>
                (function() {
                    const ranges = <?= json_encode($sumRangeData, JSON_THROW_ON_ERROR) ?>;
                    const sel = document.querySelector('[name="bet_pick_count"]');
                    if (!sel) return;
                    const posted = <?= $formPosted ? 'true' : 'false' ?>;
                    function updateHint(k, updateValues) {
                        const r = ranges[k];
                        if (!r) return;
                        document.getElementById('sum-hint-k').textContent = k;
                        document.getElementById('sum-hint-typ').textContent = r.typ_min + '–' + r.typ_max;
                        document.getElementById('sum-hint-range').textContent = r.min + '–' + r.max;
                        document.getElementById('sum-hint-mean').textContent = r.mean;
                        const sumMinEl = document.querySelector('[name="sum_min"]');
                        const sumMaxEl = document.querySelector('[name="sum_max"]');
                        if (sumMinEl) { sumMinEl.min = r.min; sumMinEl.max = r.max; }
                        if (sumMaxEl) { sumMaxEl.min = r.min; sumMaxEl.max = r.max; }
                        if (updateValues) {
                            if (sumMinEl) sumMinEl.value = r.typ_min;
                            if (sumMaxEl) sumMaxEl.value = r.typ_max;
                        }
                    }
                    sel.addEventListener('change', function() { updateHint(parseInt(this.value), true); });
                })();
                </script>
                <?php endif; ?>

                <!-- PARZYSTE / NISKIE -->
                <div class="filter-section">
                    <div class="filter-section__title"><?= render_material_icon('balance') ?> Parzyste / Niskie</div>
                    <div class="filter-grid-range" style="margin-bottom:0.75rem;">
                        <div class="form-group">
                            <label class="form-label">Parzyste od</label>
                            <select name="even_min" class="form-select">
                                <?php for ($i = 0; $i <= $defBetPickCount; $i++): ?>
                                    <option value="<?= $i ?>" <?= $def['even_min'] === $i ? 'selected' : '' ?>><?= $i ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <span class="range-label">&ndash;</span>
                        <div class="form-group">
                            <label class="form-label">do</label>
                            <select name="even_max" class="form-select">
                                <?php for ($i = 0; $i <= $defBetPickCount; $i++): ?>
                                    <option value="<?= $i ?>" <?= $def['even_max'] === $i ? 'selected' : '' ?>><?= $i ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                    </div>
                    <div class="filter-grid-range">
                        <div class="form-group">
                            <label class="form-label">Niskie (1–<?= $gameDef->lowThreshold ?>) od</label>
                            <select name="low_min" class="form-select">
                                <?php for ($i = 0; $i <= $defBetPickCount; $i++): ?>
                                    <option value="<?= $i ?>" <?= $def['low_min'] === $i ? 'selected' : '' ?>><?= $i ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <span class="range-label">&ndash;</span>
                        <div class="form-group">
                            <label class="form-label">do</label>
                            <select name="low_max" class="form-select">
                                <?php for ($i = 0; $i <= $defBetPickCount; $i++): ?>
                                    <option value="<?= $i ?>" <?= $def['low_max'] === $i ? 'selected' : '' ?>><?= $i ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                    </div>
                    <details class="filter-info">
                        <summary><?= render_material_icon('help_outline') ?> Co to jest?</summary>
                        <div class="filter-info__body">
                            <strong>Parzyste</strong> — ile liczb w kuponie jest parzystych (2, 4, 6…). W typowym losowaniu najczęściej wychodzą 3 parzyste i 3 nieparzyste.
                            <strong>Niskie</strong> — ile liczb pochodzi z dolnej połowy puli (1–<?= $gameDef->lowThreshold ?>).
                        </div>
                    </details>
                </div>

                <!-- PARY SĄSIADÓW + GORĄCE + CYFRY JEDNOŚCI -->
                <div class="filter-section">
                    <div class="filter-section__title"><?= render_material_icon('local_fire_department') ?> Sąsiedzi, gorące i cyfry</div>
                    <div class="filter-grid-3">
                        <div class="form-group">
                            <label class="form-label">Maks. par sąsiednich</label>
                            <select name="consec_max" class="form-select">
                                <?php for ($i = 0; $i < $defBetPickCount; $i++): ?>
                                    <option value="<?= $i ?>" <?= $def['consec_max'] === $i ? 'selected' : '' ?>><?= $i ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Min. gorących (top-10)</label>
                            <select name="hot_min" class="form-select">
                                <?php for ($i = 0; $i <= $defBetPickCount; $i++): ?>
                                    <option value="<?= $i ?>" <?= $def['hot_min'] === $i ? 'selected' : '' ?>><?= $i ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Maks. unikalnych cyfr jedności</label>
                            <select name="last_digit_max" class="form-select">
                                <?php for ($i = 1; $i <= $defBetPickCount; $i++): ?>
                                    <option value="<?= $i ?>" <?= $def['last_digit_max'] === $i ? 'selected' : '' ?>><?= $i ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                    </div>
                    <p class="form-hint" style="margin-top:0.375rem;">
                        Gorące: <?= implode(', ', $top10) ?>
                    </p>
                    <details class="filter-info">
                        <summary><?= render_material_icon('help_outline') ?> Co to jest?</summary>
                        <div class="filter-info__body">
                            <strong>Para sąsiednich</strong> — dwie liczby obok siebie, np. 17 i 18. W ok. 65% losowań pojawia się co najmniej jedna para.
                            <strong>Gorące liczby</strong> — top-10 najczęściej losowanych w ostatnich <?= AnalysisConfig::WINDOW_SIZE ?> losowaniach.
                            <strong>Cyfry jedności</strong> — ostatnia cyfra każdej liczby (np. w 21, 31, 41 jedność to 1). Im więcej różnych, tym bardziej rozrzucony kupon.
                        </div>
                    </details>
                </div>

                <!-- DZIESIĄTKI -->
                <?php $defDecadesMaxPool = (int)ceil($poolSize / 10); ?>
                <div class="filter-section">
                    <div class="filter-section__title"><?= render_material_icon('grid_view') ?> Dziesiątki</div>
                    <div class="filter-grid-2">
                        <div class="form-group">
                            <label class="form-label">Min. różnych dziesiątek</label>
                            <input type="number" name="decades_min" value="<?= h((string)$def['decades_min']) ?>" min="1" max="<?= $defDecadesMaxPool ?>" class="form-input">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Maks. z jednej dziesiątki</label>
                            <input type="number" name="decades_max" value="<?= h((string)$def['decades_max']) ?>" min="1" max="<?= $defBetPickCount ?>" class="form-input">
                        </div>
                    </div>
                    <details class="filter-info">
                        <summary><?= render_material_icon('help_outline') ?> Co to jest?</summary>
                        <div class="filter-info__body">
                            <strong>Dziesiątki</strong> to grupy: 1–10, 11–20, 21–30 itd.
                            <strong>Min. różnych</strong> — z ilu grup pochodzi kupon.
                            <strong>Maks. z jednej</strong> — ile liczb może być z tej samej grupy.
                        </div>
                    </details>
                </div>

                <?php if (!empty($profileHashes) && !$hasVarBet): ?>
                <!-- PROFIL -->
                <div class="filter-section">
                    <div class="filter-section__title"><?= render_material_icon('fingerprint') ?> Profil strukturalny</div>
                    <p class="form-hint mb-3">Wybierz profile, aby ograniczyć generowanie do określonych wzorców. Zostaw puste = bez ograniczeń.</p>
                    <div class="profile-checkboxes">
                        <?php
                        $topProfiles = array_slice($profileHashes, 0, 10);
                        foreach ($topProfiles as $ph): ?>
                            <label class="profile-check">
                                <input type="checkbox" name="profile_hashes[]" value="<?= h($ph['profile_hash']) ?>"
                                    <?= in_array($ph['profile_hash'], $postedHashes, true) ? 'checked' : '' ?>>
                                <span><?= h($kit->describer()->describeShort($ph['profile_hash'])) ?></span>
                                <span class="profile-check__pct"><?= h((string)$ph['total_draws']) ?>x (<?= h((string)$ph['pct_of_total']) ?>%)</span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <?php if (count($profileHashes) > 10): ?>
                    <details class="filter-info" style="margin-top:0.5rem;">
                        <summary><?= render_material_icon('expand_more') ?> Pokaż wszystkie (<?= count($profileHashes) ?>)</summary>
                        <div class="filter-info__body" style="padding:0;">
                            <div class="profile-checkboxes" style="max-height:15rem;">
                                <?php foreach (array_slice($profileHashes, 10) as $ph): ?>
                                    <label class="profile-check">
                                        <input type="checkbox" name="profile_hashes[]" value="<?= h($ph['profile_hash']) ?>"
                                            <?= in_array($ph['profile_hash'], $postedHashes, true) ? 'checked' : '' ?>>
                                        <span><?= h($kit->describer()->describeShort($ph['profile_hash'])) ?></span>
                                        <span class="profile-check__pct"><?= h((string)$ph['total_draws']) ?>x (<?= h((string)$ph['pct_of_total']) ?>%)</span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </details>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- Generate button (sticky at bottom) -->
            <div class="form-actions-sticky">
                <button type="submit" class="btn btn--primary btn--lg flex-1 w-full">
                    <?= render_material_icon('bolt') ?> GENERUJ LICZBY
                </button>
            </div>
        </form>

    </section>



<!-- Analiza ukończona / PRO TIP (below form) -->
<?php if ($formPosted && !empty($results)): ?>
<section class="analysis-card" style="margin-top:1.5rem;">
    <h3 class="text-headline-md mb-3" style="font-family:var(--font-headline);">Generowanie zakończone</h3>
    <p style="color:rgba(255,255,255,0.8);font-size:0.875rem;margin-bottom:0.75rem;line-height:1.6;">
        Wygenerowano <?= count($results) ?> kuponów z wagami opartymi na historycznej częstości z ostatnich <?= AnalysisConfig::WINDOW_SIZE ?> losowań.
        Top-10 najczęstszych: <?= implode(', ', $top10) ?>.
    </p>
    <p style="color:rgba(255,255,255,0.55);font-size:0.75rem;line-height:1.5;">
        Uwaga: historyczna częstość nie gwarantuje przyszłych wyników. Każda kombinacja ma identyczne prawdopodobieństwo wylosowania.
    </p>
</section>
<?php else: ?>
<section class="card card--tonal" style="margin-top:1.5rem;">
    <h3 class="text-headline-md mb-3">PRO TIP</h3>
    <p class="text-body-sm text-on-surface-variant leading-relaxed">
        Wygenerowane liczby oparte są na częstości występowania z ostatnich <?= AnalysisConfig::WINDOW_SIZE ?> losowań. Częściej losowane liczby mają wyższe wagi, ale każda liczba ma szansę. Użyj filtrów, aby doprecyzować kryteria strukturalne kuponu.
        Pamiętaj: w loterii każda kombinacja ma identyczne prawdopodobieństwo — filtry pomagają jedynie unikać nietypowych wzorców.
    </p>
</section>
<?php endif; ?>

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
