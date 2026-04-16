<?php
declare(strict_types=1);

/**
 * cooccurrence.php - Co-occurrence analysis (pairs and triples)
 * Included by index.php; $pdo, $game, $gameDef, $kit are available.
 * Only supported for multi_multi.
 */

if ($gameDef->slug !== 'multi_multi') {
    echo '<div class="alert alert-error">Analiza współwystępowania jest dostępna tylko dla gry Multi Multi.</div>';
    return;
}

$drawsTable = $gameDef->drawsTable;
$minCount   = max(1, (int)($_GET['min_count'] ?? 1));

$totalDraws = (int)$pdo->query("SELECT COUNT(*) FROM `{$drawsTable}`")->fetchColumn();

$coRepo = new CoOccurrenceRepository($pdo);

$pairs   = $coRepo->getTopPairs($gameDef->slug, $totalDraws, $gameDef->pickCount, $gameDef->poolSize, 50, $minCount);
$triples = $coRepo->getTopTriples($gameDef->slug, $totalDraws, $gameDef->pickCount, $gameDef->poolSize, 50, $minCount);
?>

<header class="page-header">
    <div class="page-header__row">
        <div>
            <span class="text-label-md text-primary mb-2" style="display:block;">ANALIZA WSPÓŁWYSTĘPOWANIA</span>
            <h1 class="page-header__title"><?= h($gameDef->name) ?> &mdash; Pary i trójki</h1>
            <p class="page-header__desc">
                Ranking par i trójek liczb według wskaźnika lift (stosunek obserwowanej do oczekiwanej częstości współwystępowania).
                Łączna liczba losowań: <strong><?= h((string)$totalDraws) ?></strong>.
            </p>
        </div>
    </div>
</header>

<!-- Filter -->
<form method="get" action="" class="filter-card">
    <input type="hidden" name="page" value="cooccurrence">
    <input type="hidden" name="game" value="<?= h($game) ?>">
    <div class="form-row">
        <div class="form-group">
            <label class="form-label">Min. liczba wystąpień</label>
            <input type="number" name="min_count" value="<?= h((string)$minCount) ?>" min="1" max="999" class="form-input" style="width:100px;">
        </div>
        <button type="submit" class="btn btn--primary btn--sm">
            <?= render_material_icon('filter_list') ?> Filtruj
        </button>
        <?php if ($minCount > 1): ?>
            <a href="?page=cooccurrence&game=<?= h($game) ?>" class="btn btn--ghost btn--sm">Wyczyść</a>
        <?php endif; ?>
    </div>
</form>

<p class="text-body-sm text-outline" style="margin-bottom:1.5rem;">
    <?= render_material_icon('info', 'icon-filled') ?>
    Czwórki i piątki są pomijane ze względu na liczbę kombinacji: C(20,4)=4845 i C(20,5)=15504 na losowanie —
    tabele byłyby zbyt duże i zbyt rzadko wypełnione, aby lift miał wartość analityczną.
</p>

<div class="bento-grid">

<!-- Pairs -->
<section class="card col-md-12">
    <h2 class="text-headline-md mb-4">Pary (top <?= count($pairs) ?>)</h2>
    <?php if (empty($pairs)): ?>
        <div class="alert">Brak par spełniających kryteria (min_count=<?= h((string)$minCount) ?>).</div>
    <?php else: ?>
    <div style="overflow-x:auto;">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Liczby</th>
                    <th>Wystąpienia</th>
                    <th>Oczekiwane</th>
                    <th>Lift</th>
                    <th>Ostatnio</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($pairs as $row): ?>
                <tr>
                    <td>
                        <div class="balls-row">
                            <?= render_ball($row['n1']) ?>
                            <?= render_ball($row['n2']) ?>
                        </div>
                    </td>
                    <td><?= h((string)$row['count']) ?></td>
                    <td><?= h(number_format($row['expected'], 2)) ?></td>
                    <td>
                        <?php $lift = $row['lift']; ?>
                        <strong style="color:<?= $lift >= 1.2 ? 'var(--tertiary)' : ($lift >= 1.0 ? 'var(--primary)' : 'var(--on-surface-variant)') ?>">
                            <?= h(number_format($lift, 3)) ?>
                        </strong>
                    </td>
                    <td style="white-space:nowrap;"><?= h((string)$row['last_seen']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</section>

<!-- Triples -->
<section class="card col-md-12">
    <h2 class="text-headline-md mb-4">Trójki (top <?= count($triples) ?>)</h2>
    <?php if (empty($triples)): ?>
        <div class="alert">Brak trójek spełniających kryteria (min_count=<?= h((string)$minCount) ?>).</div>
    <?php else: ?>
    <div style="overflow-x:auto;">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Liczby</th>
                    <th>Wystąpienia</th>
                    <th>Oczekiwane</th>
                    <th>Lift</th>
                    <th>Ostatnio</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($triples as $row): ?>
                <tr>
                    <td>
                        <div class="balls-row">
                            <?= render_ball($row['n1']) ?>
                            <?= render_ball($row['n2']) ?>
                            <?= render_ball($row['n3']) ?>
                        </div>
                    </td>
                    <td><?= h((string)$row['count']) ?></td>
                    <td><?= h(number_format($row['expected'], 2)) ?></td>
                    <td>
                        <?php $lift = $row['lift']; ?>
                        <strong style="color:<?= $lift >= 1.2 ? 'var(--tertiary)' : ($lift >= 1.0 ? 'var(--primary)' : 'var(--on-surface-variant)') ?>">
                            <?= h(number_format($lift, 3)) ?>
                        </strong>
                    </td>
                    <td style="white-space:nowrap;"><?= h((string)$row['last_seen']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</section>

</div>
