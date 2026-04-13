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
            $errors[] = "Pole n{$i} musi być liczbą całkowitą.";
            continue;
        }
        $val = (int)$raw;
        if ($val < 1 || $val > $poolSize) {
            $errors[] = "Liczba n{$i} ({$val}) musi być w zakresie 1–{$poolSize}.";
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

<form method="post" action="">
    <input type="hidden" name="page" value="validator">
    <input type="hidden" name="game" value="<?= h($game) ?>">
    <p>
        <?php for ($i = 1; $i <= $pickCount; $i++): ?>
            <label>n<?= $i ?>:
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
    <tr><td>Suma</td>                    <td><?= h((string)$metrics['sum_total']) ?></td></tr>
    <tr><td>Parzyste</td>                <td><?= h((string)$metrics['even_count']) ?></td></tr>
    <tr><td>Nieparzyste</td>             <td><?= h((string)($pickCount - $metrics['even_count'])) ?></td></tr>
    <tr><td>Niskie (≤ <?= (int)$gameConfig['low_threshold'] ?>)</td>
                                         <td><?= h((string)$metrics['low_count']) ?></td></tr>
    <tr><td>Wysokie</td>                 <td><?= h((string)($pickCount - $metrics['low_count'])) ?></td></tr>
    <tr><td>Pary kolejnych</td>          <td><?= h((string)$metrics['consecutive']) ?></td></tr>
    <tr><td>Użyte dziesiątki</td>        <td><?= h((string)$metrics['decades_used']) ?></td></tr>
    <tr><td>Rozpiętość</td>              <td><?= h((string)$metrics['range_spread']) ?></td></tr>
    <tr><td>Unikalne ostatnie cyfry</td> <td><?= h((string)$metrics['last_digit_unique']) ?></td></tr>
    <tr><th>Profil (hash)</th>           <td><code><?= h($hash) ?></code></td></tr>
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
        Ten profil (<code><?= h($hash) ?></code>) <strong>nigdy nie wystąpił</strong> w historii losowań.
    </div>
<?php else: ?>
    <div class="alert">Brak danych w bazie (import niewykonany).</div>
<?php endif; ?>

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
