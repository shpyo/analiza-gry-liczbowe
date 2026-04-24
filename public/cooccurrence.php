<?php
declare(strict_types=1);

/**
 * cooccurrence.php - Co-occurrence analysis (pairs & triples) for Multi Multi.
 * Included by index.php; $pdo, $game, $gameDef, $kit are available.
 */

if (!$gameDef->coOccurrence) {
    echo '<div class="alert alert-error">Analiza współwystępowania jest dostępna tylko dla gry Multi Multi.</div>';
    return;
}

// -----------------------------------------------------------------------
// Tab + independent filter params
// -----------------------------------------------------------------------
$activeTab = in_array($_GET['tab'] ?? '', ['pairs', 'triples'], true) ? $_GET['tab'] : 'pairs';

$pairsMinCount   = max(1, (int)($_GET['pairs_min']   ?? 5));
$triplesMinCount = max(1, (int)($_GET['triples_min'] ?? 3));
$pairsLimit      = 200;
$triplesLimit    = 200;

// -----------------------------------------------------------------------
// Data (only fetch active tab to avoid unnecessary queries)
// -----------------------------------------------------------------------
$totalDraws = (int)$pdo->query("SELECT COUNT(*) FROM `{$gameDef->drawsTable}`")->fetchColumn();

$topPairs   = $activeTab === 'pairs'
    ? $kit->coOccurrence()->getTopPairs($gameDef, $pairsLimit, $pairsMinCount)
    : [];
$topTriples = $activeTab === 'triples'
    ? $kit->coOccurrence()->getTopTriples($gameDef, $triplesLimit, $triplesMinCount)
    : [];

// Helper – base URL preserving the other tab's filter values
function cooc_url(string $game, string $tab, array $extra = []): string {
    $params = array_merge(['page' => 'cooccurrence', 'game' => $game, 'tab' => $tab], $extra);
    return '?' . http_build_query($params);
}

$pairsBaseUrl   = cooc_url($game, 'pairs',   ['pairs_min'   => $pairsMinCount,   'triples_min' => $triplesMinCount]);
$triplesBaseUrl = cooc_url($game, 'triples', ['pairs_min'   => $pairsMinCount,   'triples_min' => $triplesMinCount]);
?>
<style>
.co-tabs{display:flex;gap:0;border-bottom:2px solid var(--md-sys-color-outline-variant,#cac4d0);margin-bottom:1.5rem;}
.co-tab{padding:.625rem 1.25rem;font-weight:600;font-size:.875rem;color:var(--md-sys-color-on-surface-variant,#49454f);text-decoration:none;border-bottom:2px solid transparent;margin-bottom:-2px;transition:color .15s,border-color .15s;}
.co-tab:hover{color:var(--md-sys-color-primary,#6750a4);}
.co-tab.active{color:var(--md-sys-color-primary,#6750a4);border-bottom-color:var(--md-sys-color-primary,#6750a4);}
</style>

<!-- Page Header -->
<header class="page-header">
    <div class="page-header__row">
        <div>
            <span class="text-label-md text-primary mb-2" style="display:block;">ANALIZA KOMBINACJI</span>
            <h1 class="page-header__title">Współwystępowanie — <?= h($gameDef->name) ?></h1>
            <p class="page-header__desc">
                Pary i trójki liczb pojawiające się razem częściej lub rzadziej niż przewiduje model losowy.
                Lift &gt; 1 = nadreprezentacja, lift &lt; 1 = niedobór.
                Analiza oparta na <?= h((string)$totalDraws) ?> losowaniach.
                Lift bliski 1,0 (np. 0,9&ndash;1,1) jest prawdopodobnie wynikiem naturalnego szumu losowego, nie realnej zależności.
            </p>
        </div>
    </div>
</header>

<!-- Tabs -->
<nav class="co-tabs">
    <a href="<?= h($pairsBaseUrl) ?>"
       class="co-tab<?= $activeTab === 'pairs' ? ' active' : '' ?>">
        <?= render_material_icon('join_inner') ?> Pary
    </a>
    <a href="<?= h($triplesBaseUrl) ?>"
       class="co-tab<?= $activeTab === 'triples' ? ' active' : '' ?>">
        <?= render_material_icon('join_full') ?> Trójki
    </a>
</nav>

<div class="bento-grid">

<?php if ($activeTab === 'pairs'): ?>
<!-- ================================================================ PAIRS TAB -->

<!-- Filter: Pairs -->
<div class="card" style="grid-column: 1 / -1;">
<form method="get" action="" class="filter-card" style="margin:0;border:0;padding:0;background:none;">
    <input type="hidden" name="page"        value="cooccurrence">
    <input type="hidden" name="game"        value="<?= h($game) ?>">
    <input type="hidden" name="tab"         value="pairs">
    <input type="hidden" name="triples_min" value="<?= h((string)$triplesMinCount) ?>">
    <div class="form-row">
        <div class="form-group">
            <label class="form-label">Min. wystąpień pary</label>
            <input type="number" name="pairs_min" value="<?= h((string)$pairsMinCount) ?>"
                   min="1" max="<?= h((string)$totalDraws) ?>" class="form-input" style="width:90px;">
        </div>
        <button type="submit" class="btn btn--primary btn--sm">
            <?= render_material_icon('filter_list') ?> Filtruj
        </button>
        <?php if ($pairsMinCount !== 5): ?>
            <a href="<?= h(cooc_url($game, 'pairs', ['pairs_min' => 5, 'triples_min' => $triplesMinCount])) ?>"
               class="btn btn--ghost btn--sm">Resetuj</a>
        <?php endif; ?>
    </div>
</form>
</div>

<!-- Results: Pairs -->
<section class="card" style="grid-column: 1 / -1;">
    <div class="flex justify-between items-center mb-4">
        <div>
            <span class="text-label-md text-primary mb-1" style="display:block;">PARY</span>
            <h2 class="text-headline-md" style="margin:0;">
                Najczęstsze pary — C(20,2) = 190 par/losowanie
            </h2>
        </div>
        <span class="badge badge--info"><?= count($topPairs) ?> wyników</span>
    </div>

    <?php if (empty($topPairs)): ?>
        <div class="alert">Brak par z min_count ≥ <?= h((string)$pairsMinCount) ?>.
            Uruchom import/sync aby wypełnić tabele.</div>
    <?php else: ?>
    <div style="overflow-x:auto;">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Liczba 1</th>
                    <th>Liczba 2</th>
                    <th>Razem</th>
                    <th>Oczekiwane</th>
                    <th>Lift</th>
                    <th>Ostatnio</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($topPairs as $row): ?>
                <tr>
                    <td><?= render_ball($row['n1']) ?></td>
                    <td><?= render_ball($row['n2']) ?></td>
                    <td><strong><?= h((string)$row['count']) ?></strong></td>
                    <td class="text-muted"><?= h(number_format($row['expected'], 1)) ?></td>
                    <td>
                        <?php $liftClass = $row['lift'] >= 1.2 ? 'badge--success' : ($row['lift'] <= 0.8 ? 'badge--error' : ''); ?>
                        <span class="badge <?= $liftClass ?>"><?= h(number_format($row['lift'], 3)) ?></span>
                    </td>
                    <td style="white-space:nowrap;"><?= h($row['last_seen']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</section>

<?php else: ?>
<!-- ============================================================== TRIPLES TAB -->

<!-- Filter: Triples -->
<div class="card" style="grid-column: 1 / -1;">
<form method="get" action="" class="filter-card" style="margin:0;border:0;padding:0;background:none;">
    <input type="hidden" name="page"      value="cooccurrence">
    <input type="hidden" name="game"      value="<?= h($game) ?>">
    <input type="hidden" name="tab"       value="triples">
    <input type="hidden" name="pairs_min" value="<?= h((string)$pairsMinCount) ?>">
    <div class="form-row">
        <div class="form-group">
            <label class="form-label">Min. wystąpień trójki</label>
            <input type="number" name="triples_min" value="<?= h((string)$triplesMinCount) ?>"
                   min="1" max="<?= h((string)$totalDraws) ?>" class="form-input" style="width:90px;">
        </div>
        <button type="submit" class="btn btn--primary btn--sm">
            <?= render_material_icon('filter_list') ?> Filtruj
        </button>
        <?php if ($triplesMinCount !== 3): ?>
            <a href="<?= h(cooc_url($game, 'triples', ['pairs_min' => $pairsMinCount, 'triples_min' => 3])) ?>"
               class="btn btn--ghost btn--sm">Resetuj</a>
        <?php endif; ?>
    </div>
</form>
</div>

<!-- Results: Triples -->
<section class="card" style="grid-column: 1 / -1;">
    <div class="flex justify-between items-center mb-4">
        <div>
            <span class="text-label-md text-primary mb-1" style="display:block;">TRÓJKI</span>
            <h2 class="text-headline-md" style="margin:0;">
                Najczęstsze trójki — C(20,3) = 1 140 trójek/losowanie
            </h2>
        </div>
        <span class="badge badge--info"><?= count($topTriples) ?> wyników</span>
    </div>

    <?php if (empty($topTriples)): ?>
        <div class="alert">Brak trójek z min_count ≥ <?= h((string)$triplesMinCount) ?>.
            Uruchom import/sync aby wypełnić tabele.</div>
    <?php else: ?>
    <div style="overflow-x:auto;">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Liczba 1</th>
                    <th>Liczba 2</th>
                    <th>Liczba 3</th>
                    <th>Razem</th>
                    <th>Oczekiwane</th>
                    <th>Lift</th>
                    <th>Ostatnio</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($topTriples as $row): ?>
                <tr>
                    <td><?= render_ball($row['n1']) ?></td>
                    <td><?= render_ball($row['n2']) ?></td>
                    <td><?= render_ball($row['n3']) ?></td>
                    <td><strong><?= h((string)$row['count']) ?></strong></td>
                    <td class="text-muted"><?= h(number_format($row['expected'], 1)) ?></td>
                    <td>
                        <?php $liftClass = $row['lift'] >= 1.2 ? 'badge--success' : ($row['lift'] <= 0.8 ? 'badge--error' : ''); ?>
                        <span class="badge <?= $liftClass ?>"><?= h(number_format($row['lift'], 3)) ?></span>
                    </td>
                    <td style="white-space:nowrap;"><?= h($row['last_seen']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</section>

<?php endif; ?>

<!-- INFO CARD: statistical significance -->
<section class="card card--tonal" style="grid-column: 1 / -1;">
    <h3 class="text-headline-sm mb-2">Jak interpretować lift?</h3>
    <p class="text-body-sm text-on-surface-variant" style="line-height:1.7;">
        Lift mierzy stosunek obserwowanej częstości do oczekiwanej przy założeniu losowości.
        Wartości <strong>0,9&ndash;1,1</strong> mieszczą się w zakresie normalnych wahań losowych i nie powinny być interpretowane jako wzorzec.
        Dopiero lift <strong>&gt; 1,2</strong> lub <strong>&lt; 0,8</strong> może wskazywać na ciekawą zależność, choć nawet te odchylenia mogą być dziełem przypadku.
        Przy <?= h((string)$totalDraws) ?> losowaniach i tysiącach par, <strong>niektóre</strong> odchylenia wystąpią czysto losowo (problem wielokrotnych porównań).
        Ta analiza ma charakter eksploracyjny &mdash; nie stanowi dowodu na nielosowość gry.
    </p>
</section>

<!-- INFO CARD: why no 4-tuples -->
<section class="card" style="grid-column: 1 / -1;">
    <h3 class="text-headline-sm mb-2">Dlaczego nie analizujemy czwórek ani piątek?</h3>
    <p class="text-body-sm text-on-surface-variant">
        Liczba możliwych czwórek to C(80,4) = 1 581 580. Przy ~10 000 losowań każda konkretna czwórka
        ma oczekiwaną częstość poniżej 30 wystąpień — za mała próba do wiarygodnej oceny statystycznej.
        Trójki (C(80,3) = 82 160) przy tej samej liczbie losowań mają oczekiwane ~138 wystąpień każda —
        wystarczające do sensownego rankingu lift.
    </p>
</section>

</div>
