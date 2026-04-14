<?php
declare(strict_types=1);

/**
 * validator.php - Validate / analyse a user-provided combination
 * Included by index.php; $pdo, $game are available.
 */

$gameConfig   = get_game_config($pdo, $game);
$gameName     = $gameConfig['name'];
$drawsTable   = GAME_TABLES[$game];
$profileTable = PROFILE_TABLES[$game];
$pickCount    = (int)$gameConfig['pick_count'];
$poolSize     = (int)$gameConfig['pool_size'];

$errors    = [];
$inputNums = [];
$metrics   = null;
$hash      = null;
$profileRow = null;
$exactMatch = null;
$submitted  = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submitted = true;

    for ($i = 1; $i <= $pickCount; $i++) {
        $raw = isset($_POST["n{$i}"]) ? trim($_POST["n{$i}"]) : '';
        if ($raw === '' || !ctype_digit($raw)) {
            $errors[] = "Liczba {$i} musi być liczbą całkowitą.";
            continue;
        }
        $val = (int)$raw;
        if ($val < 1 || $val > $poolSize) {
            $errors[] = "Liczba {$i} ({$val}) musi być w zakresie 1–{$poolSize}.";
            continue;
        }
        $inputNums[] = $val;
    }

    if (empty($errors)) {
        if (count($inputNums) !== count(array_unique($inputNums))) {
            $errors[] = "Liczby nie mogą się powtarzać.";
        }
    }

    if (empty($errors) && count($inputNums) === $pickCount) {
        sort($inputNums);
        $metrics = compute_metrics($inputNums, $game);
        $hash    = compute_profile_hash($metrics, $game);

        $profStmt = $pdo->prepare("SELECT * FROM `{$profileTable}` WHERE profile_hash = ?");
        $profStmt->execute([$hash]);
        $profileRow = $profStmt->fetch();

        $conditions = [];
        $params     = [];
        for ($i = 0; $i < $pickCount; $i++) {
            $col          = 'n' . ($i + 1);
            $conditions[] = "`{$col}` = ?";
            $params[]     = $inputNums[$i];
        }
        $exactSql  = "SELECT draw_number, draw_date FROM `{$drawsTable}` WHERE "
                   . implode(' AND ', $conditions)
                   . " LIMIT 1";
        $exactStmt = $pdo->prepare($exactSql);
        $exactStmt->execute($params);
        $exactMatch = $exactStmt->fetch();
    }
}

$totalDraws = (int)$pdo->query("SELECT COUNT(*) FROM `{$drawsTable}`")->fetchColumn();
?>

<!-- Page Header -->
<header class="page-header">
    <div class="page-header__row">
        <div>
            <span class="text-label-md text-primary mb-2" style="display:block;">WERYFIKACJA</span>
            <h1 class="page-header__title"><?= h($gameName) ?> &mdash; Weryfikator</h1>
            <p class="page-header__desc">Wpisz kombinację <?= $pickCount ?> liczb z zakresu 1&ndash;<?= $poolSize ?>. System obliczy profil statystyczny, sprawdzi historię wzorca i unikatowość kombinacji.</p>
        </div>
    </div>
</header>

<!-- Input Form -->
<div class="card mb-6">
    <form method="post" action="?page=validator&game=<?= h($game) ?>">
        <p class="text-body-sm text-on-surface-variant mb-4">Wpisz liczby w dowolnej kolejności — zostaną automatycznie posortowane.</p>

        <div class="flex gap-3 mb-6" style="flex-wrap:wrap;justify-content:center;">
            <?php for ($i = 1; $i <= $pickCount; $i++): ?>
                <div style="display:flex;flex-direction:column;align-items:center;gap:0.25rem;">
                    <input type="number"
                           name="n<?= $i ?>"
                           min="1" max="<?= h((string)$poolSize) ?>"
                           value="<?= isset($inputNums[$i - 1]) ? h((string)$inputNums[$i - 1]) : '' ?>"
                           class="num-input-circle"
                           placeholder="?"
                           required>
                    <span class="text-label-lg text-outline"><?= $i ?></span>
                </div>
            <?php endfor; ?>
        </div>

        <div style="text-align:center;">
            <button type="submit" class="btn btn--primary btn--lg">
                <?= render_material_icon('task_alt') ?> Analizuj kombinację
            </button>
        </div>
    </form>
</div>

<?php if (!empty($errors)): ?>
    <?php foreach ($errors as $err): ?>
        <div class="alert alert-error"><?= h($err) ?></div>
    <?php endforeach; ?>
<?php endif; ?>

<?php if ($submitted && empty($errors) && $metrics !== null): ?>

<!-- Results -->
<div class="bento-grid">

    <!-- Combination Display + Metrics -->
    <section class="card col-md-8 col-lg-8">
        <h2 class="text-headline-lg mb-6">Wyniki analizy</h2>

        <!-- Ball display -->
        <div class="balls-row balls-row--lg mb-8" style="justify-content:center;">
            <?php foreach ($inputNums as $n): ?>
                <?= render_ball($n, 'xl') ?>
            <?php endforeach; ?>
        </div>

        <!-- Metrics Table -->
        <table class="data-table">
            <thead>
                <tr>
                    <th>Metryka</th>
                    <th>Wartość</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><?= render_tooltip('sum_total', $game) ?></td>
                    <td><strong><?= h((string)$metrics['sum_total']) ?></strong></td>
                </tr>
                <tr>
                    <td><?= render_tooltip('even_count', $game) ?> / <?= metric_label('odd_count') ?></td>
                    <td><strong><?= h((string)$metrics['even_count']) ?></strong> / <?= h((string)($pickCount - $metrics['even_count'])) ?></td>
                </tr>
                <tr>
                    <td><?= render_tooltip('low_count', $game) ?> (&le; <?= (int)$gameConfig['low_threshold'] ?>) / <?= metric_label('high_count') ?></td>
                    <td><strong><?= h((string)$metrics['low_count']) ?></strong> / <?= h((string)($pickCount - $metrics['low_count'])) ?></td>
                </tr>
                <tr>
                    <td><?= render_tooltip('consecutive', $game) ?></td>
                    <td><strong><?= h((string)$metrics['consecutive']) ?></strong></td>
                </tr>
                <tr>
                    <td><?= render_tooltip('decades_used', $game) ?></td>
                    <td><strong><?= h((string)$metrics['decades_used']) ?></strong></td>
                </tr>
                <tr>
                    <td><?= render_tooltip('range_spread', $game) ?></td>
                    <td><strong><?= h((string)$metrics['range_spread']) ?></strong></td>
                </tr>
                <tr>
                    <td><?= render_tooltip('last_digit_unique', $game) ?></td>
                    <td><strong><?= h((string)$metrics['last_digit_unique']) ?></strong></td>
                </tr>
                <tr>
                    <td><?= render_tooltip('profile_hash', $game) ?></td>
                    <td><strong><?= h(describe_profile_short($hash)) ?></strong></td>
                </tr>
            </tbody>
        </table>
    </section>

    <!-- Profile & Match Info -->
    <div class="col-md-4 col-lg-4" style="display:flex;flex-direction:column;gap:1.5rem;">

        <!-- Profile Status -->
        <section class="card card--tonal">
            <h3 class="text-headline-md mb-3">Profil w bazie</h3>
            <?php if ($profileRow): ?>
                <div class="alert alert-success">
                    Profil wystąpił <strong><?= h((string)$profileRow['total_draws']) ?></strong> razy
                    (<?= h((string)$profileRow['pct_of_total']) ?>% losowań).
                </div>
                <div style="display:flex;flex-direction:column;gap:0.5rem;">
                    <div class="stat-item">
                        <span class="stat-item__label">Ostatnio</span>
                        <span class="stat-item__value stat-item__value--sm"><?= h($profileRow['last_seen']) ?></span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-item__label">Pierwszy raz</span>
                        <span class="stat-item__value stat-item__value--sm"><?= h($profileRow['first_seen']) ?></span>
                    </div>
                </div>
            <?php elseif ($totalDraws > 0): ?>
                <div class="alert alert-error">
                    Ten profil <strong>nigdy nie wystąpił</strong> w historii losowań.
                </div>
            <?php else: ?>
                <div class="alert alert-warning">Brak danych w bazie (import niewykonany).</div>
            <?php endif; ?>
        </section>

        <!-- Exact Match -->
        <section class="card">
            <h3 class="text-headline-md mb-3">Dokładna kombinacja</h3>
            <?php if ($exactMatch): ?>
                <div class="alert alert-error">
                    <?= render_material_icon('warning', 'icon-filled') ?>
                    Kombinacja była losowana <?= h($exactMatch['draw_date']) ?>
                    (#<?= h((string)$exactMatch['draw_number']) ?>).
                </div>
            <?php else: ?>
                <div class="alert alert-success">
                    <?= render_material_icon('check_circle', 'icon-filled') ?>
                    Ta kombinacja <strong>nigdy nie była losowana</strong>.
                </div>
            <?php endif; ?>
        </section>

        <!-- Popularity -->
        <?php if ($profileRow): ?>
        <section class="card">
            <h3 class="text-headline-md mb-3">Popularność wzorca</h3>
            <?php $pct = (float)$profileRow['pct_of_total']; ?>
            <?php if ($pct > 2.0): ?>
                <?= render_badge('Popularny', 'stable') ?>
                <p class="text-body-sm text-on-surface-variant" style="margin-top:0.5rem;">Ten układ padał w <?= h((string)$profileRow['pct_of_total']) ?>% losowań — jeden z częstszych wzorców.</p>
            <?php elseif ($pct >= 0.5): ?>
                <?= render_badge('Rzadki', 'rare') ?>
                <p class="text-body-sm text-on-surface-variant" style="margin-top:0.5rem;">Ten układ padał w <?= h((string)$profileRow['pct_of_total']) ?>% losowań — niezbyt powszechny.</p>
            <?php else: ?>
                <?= render_badge('Bardzo rzadki', 'cold') ?>
                <p class="text-body-sm text-on-surface-variant" style="margin-top:0.5rem;">Ten układ padał zaledwie w <?= h((string)$profileRow['pct_of_total']) ?>% losowań.</p>
            <?php endif; ?>
            <div class="progress-bar" style="margin-top:0.75rem;">
                <div class="progress-bar__fill" style="width:<?= min(100, $pct * 10) ?>%;"></div>
            </div>
        </section>
        <?php endif; ?>
    </div>

</div>

<!-- Profile Description -->
<div class="card" style="margin-top:1.5rem;">
    <h3 class="text-headline-md mb-3">Opis profilu strukturalnego</h3>
    <p class="text-body-lg" style="line-height:1.8;">
        <?= h(describe_profile($hash, $game)) ?>
    </p>
</div>

<?php endif; ?>
