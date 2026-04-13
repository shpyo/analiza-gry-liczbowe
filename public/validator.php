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

    // Collect inputs n1..nX
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
        // Check for duplicates
        if (count($inputNums) !== count(array_unique($inputNums))) {
            $errors[] = "Liczby nie mogą się powtarzać.";
        }
    }

    if (empty($errors) && count($inputNums) === $pickCount) {
        sort($inputNums);
        $metrics = compute_metrics($inputNums, $game);
        $hash    = compute_profile_hash($metrics, $game);

        // Fetch profile info
        $profStmt = $pdo->prepare("SELECT * FROM `{$profileTable}` WHERE profile_hash = ?");
        $profStmt->execute([$hash]);
        $profileRow = $profStmt->fetch();

        // Check exact combination
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

// -----------------------------------------------------------------------
// Total draws for percentage context
// -----------------------------------------------------------------------
$totalDraws = (int)$pdo->query("SELECT COUNT(*) FROM `{$drawsTable}`")->fetchColumn();
?>
<h1><?= h($gameName) ?> &mdash; Weryfikator kombinacji</h1>

<p style="color:#555;">Wpisz kombinację <?= $pickCount ?> liczb z zakresu 1–<?= $poolSize ?>. System obliczy profil statystyczny, sprawdzi jak często taki wzorzec padał historycznie i czy ta dokładna kombinacja kiedykolwiek wypadła.</p>

<form method="post" action="">
    <input type="hidden" name="page" value="validator">
    <input type="hidden" name="game" value="<?= h($game) ?>">
    <p><small style="color:#555;">Wpisz liczby w dowolnej kolejności — zostaną automatycznie posortowane.</small></p>
    <p>
        <?php for ($i = 1; $i <= $pickCount; $i++): ?>
            <label>Liczba <?= $i ?>:
                <input type="number"
                       name="n<?= $i ?>"
                       min="1" max="<?= h((string)$poolSize) ?>"
                       value="<?= isset($inputNums[$i - 1]) ? h((string)$inputNums[$i - 1]) : '' ?>"
                       style="width:60px;"
                       required>
            </label>
        <?php endfor; ?>
    </p>
    <input type="submit" value="Analizuj">
</form>

<?php if (!empty($errors)): ?>
    <?php foreach ($errors as $err): ?>
        <div class="alert alert-error"><?= h($err) ?></div>
    <?php endforeach; ?>
<?php endif; ?>

<?php if ($submitted && empty($errors) && $metrics !== null): ?>

<h2>Wyniki analizy</h2>

<div class="coupon">
    <strong>Kombinacja:</strong>&nbsp;
    <?php foreach ($inputNums as $n): ?>
        <span class="ball"><?= h((string)$n) ?></span>
    <?php endforeach; ?>
</div>

<h3>Metryki</h3>
<table style="width:auto;">
    <tr><th>Metryka</th><th>Wartość</th></tr>
    <tr><td><?= render_tooltip('sum_total', $game) ?></td>                       <td><?= h((string)$metrics['sum_total']) ?></td></tr>
    <tr><td><?= render_tooltip('even_count', $game) ?></td>                     <td><?= h((string)$metrics['even_count']) ?></td></tr>
    <tr><td><?= render_tooltip('odd_count', $game) ?></td>                      <td><?= h((string)($pickCount - $metrics['even_count'])) ?></td></tr>
    <tr><td><?= render_tooltip('low_count', $game) ?> (≤ <?= (int)$gameConfig['low_threshold'] ?>)</td>
                                         <td><?= h((string)$metrics['low_count']) ?></td></tr>
    <tr><td><?= render_tooltip('high_count', $game) ?></td>                     <td><?= h((string)($pickCount - $metrics['low_count'])) ?></td></tr>
    <tr><td><?= render_tooltip('consecutive', $game) ?></td>                    <td><?= h((string)$metrics['consecutive']) ?></td></tr>
    <tr><td><?= render_tooltip('decades_used', $game) ?></td>                   <td><?= h((string)$metrics['decades_used']) ?></td></tr>
    <tr><td><?= render_tooltip('range_spread', $game) ?></td>                   <td><?= h((string)$metrics['range_spread']) ?></td></tr>
    <tr><td><?= render_tooltip('last_digit_unique', $game) ?></td>              <td><?= h((string)$metrics['last_digit_unique']) ?></td></tr>
    <tr><th><?= render_tooltip('profile_hash', $game) ?></th>                   <td><?= h(describe_profile($hash, $game)) ?></td></tr>
</table>

<h3>Profil w bazie</h3>
<?php if ($profileRow): ?>
    <div class="alert alert-success">
        Ten profil wystąpił <strong><?= h((string)$profileRow['total_draws']) ?></strong> razy
        (<?= h((string)$profileRow['pct_of_total']) ?>% wszystkich losowań).
        Po raz ostatni: <strong><?= h($profileRow['last_seen']) ?></strong>.
        Pierwszy raz: <?= h($profileRow['first_seen']) ?>.
    </div>
<?php elseif ($totalDraws > 0): ?>
    <div class="alert alert-error">
        Ten profil <strong>nigdy nie wystąpił</strong> w historii losowań.
    </div>
<?php else: ?>
    <div class="alert">Brak danych w bazie (import niewykonany).</div>
<?php endif; ?>

<h3>Co to znaczy?</h3>
<div style="background:#fff;border:1px solid #ddd;padding:12px 18px;border-radius:4px;margin-bottom:15px;">
    <p style="margin:0 0 8px 0;">
        <strong>Profil strukturalny:</strong> <?= h(describe_profile($hash, $game)) ?>
    </p>
    <?php if ($profileRow): ?>
        <?php $pct = (float)$profileRow['pct_of_total']; ?>
        <p style="margin:0 0 8px 0;">
            <?php if ($pct > 2.0): ?>
                <strong>Popularność wzorca: popularny</strong> — ten układ strukturalny padał w <?= h((string)$profileRow['pct_of_total']) ?>% wszystkich losowań. Jest to jeden z częstszych wzorców.
            <?php elseif ($pct >= 0.5): ?>
                <strong>Popularność wzorca: rzadki</strong> — ten układ strukturalny padał w <?= h((string)$profileRow['pct_of_total']) ?>% wszystkich losowań. Wzorzec niezbyt powszechny.
            <?php else: ?>
                <strong>Popularność wzorca: bardzo rzadki</strong> — ten układ strukturalny padał zaledwie w <?= h((string)$profileRow['pct_of_total']) ?>% wszystkich losowań.
            <?php endif; ?>
        </p>
    <?php elseif ($totalDraws > 0): ?>
        <p style="margin:0 0 8px 0;"><strong>Popularność wzorca:</strong> ten wzorzec strukturalny nigdy nie padł w historii <?= h($gameName) ?>.</p>
    <?php endif; ?>
    <p style="margin:0;">
        <?php if ($exactMatch): ?>
            Ta kombinacja padła już raz — dnia <strong><?= h($exactMatch['draw_date']) ?></strong> (losowanie #<?= h((string)$exactMatch['draw_number']) ?>).
        <?php else: ?>
            Ta kombinacja nigdy nie padła w historii <?= h($gameName) ?>.
        <?php endif; ?>
    </p>
</div>

<h3>Dokładna kombinacja</h3>
<?php if ($exactMatch): ?>
    <div class="alert alert-error">
        ⚠️ Ta kombinacja była już losowana
        w dniu <strong><?= h($exactMatch['draw_date']) ?></strong>
        (losowanie #<?= h((string)$exactMatch['draw_number']) ?>).
    </div>
<?php else: ?>
    <div class="alert alert-success">
        ✅ Ta dokładna kombinacja <strong>nigdy nie była losowana</strong>.
    </div>
<?php endif; ?>

<?php endif; ?>
