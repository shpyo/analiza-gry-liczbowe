<?php
declare(strict_types=1);

/**
 * generator.php - Weighted random coupon generator with profile filters
 * Included by index.php; $pdo, $game are available.
 */

$gameConfig   = get_game_config($pdo, $game);
$gameName     = $gameConfig['name'];
$drawsTable   = GAME_TABLES[$game];
$profileTable = PROFILE_TABLES[$game];
$pickCount    = (int)$gameConfig['pick_count'];
$poolSize     = (int)$gameConfig['pool_size'];

// -----------------------------------------------------------------------
// Build number weights from last 500 draws
// -----------------------------------------------------------------------
$numberCols  = [];
for ($i = 1; $i <= $pickCount; $i++) {
    $numberCols[] = "n{$i}";
}

$colList   = implode(', ', array_map(fn($c) => "`{$c}`", $numberCols));
$cteParts  = [];
foreach ($numberCols as $col) {
    $cteParts[] = "SELECT `{$col}` AS num FROM last500";
}
$unionSQL = implode(' UNION ALL ', $cteParts);

$freqRows = $pdo->query(
    "WITH last500 AS (SELECT {$colList} FROM `{$drawsTable}` ORDER BY draw_number DESC LIMIT 500)
     SELECT num, COUNT(*) AS freq FROM ({$unionSQL}) AS t WHERE num IS NOT NULL GROUP BY num"
)->fetchAll(PDO::FETCH_KEY_PAIR);

// weight = freq + 1 (min 1 so undrawn numbers are still eligible)
$weights = [];
for ($n = 1; $n <= $poolSize; $n++) {
    $weights[$n] = (int)($freqRows[$n] ?? 0) + 1;
}

// Top-10 hot numbers
arsort($freqRows);
$top10 = array_keys(array_slice($freqRows, 0, 10, true));

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
    $count       = max(1, min(20, (int)($_POST['count'] ?? 5)));

    // Sanitize wanted hashes against known hashes
    $knownHashes  = array_column($profileHashes, 'profile_hash');
    $wantedHashes = array_values(array_intersect($wantedHashes, $knownHashes));

    $maxAttempts = 50000;
    $attempts    = 0;

    while (count($results) < $count && $attempts < $maxAttempts) {
        $attempts++;

        // Weighted sampling without replacement
        $pool     = $weights; // [num => weight]
        $selected = [];

        for ($pick = 0; $pick < $pickCount; $pick++) {
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

        if (count($selected) !== $pickCount) {
            continue;
        }

        sort($selected);
        $metrics = compute_metrics($selected, $game);
        $hash    = compute_profile_hash($metrics, $game);

        // Apply filters
        if ($metrics['sum_total'] < $sumMin || $metrics['sum_total'] > $sumMax) {
            continue;
        }
        if ($metrics['even_count'] < $evenMin || $metrics['even_count'] > $evenMax) {
            continue;
        }
        if ($metrics['low_count'] < $lowMin || $metrics['low_count'] > $lowMax) {
            continue;
        }
        if ($metrics['consecutive'] > $consecMax) {
            continue;
        }
        if ($metrics['last_digit_unique'] > $lastDigMax) {
            continue;
        }

        // Hot numbers filter
        $hotInCoupon = count(array_intersect($selected, $top10));
        if ($hotInCoupon < $hotMin) {
            continue;
        }

        // Decades max filter: no single decade may have more than $decadesMax numbers
        if ($decadesMax < $pickCount) {
            $decadeGroups = [];
            foreach ($selected as $n) {
                $d = intdiv($n - 1, 10);
                $decadeGroups[$d] = ($decadeGroups[$d] ?? 0) + 1;
            }
            if (max($decadeGroups) > $decadesMax) {
                continue;
            }
        }

        // Profile hash filter
        if (!empty($wantedHashes) && !in_array($hash, $wantedHashes, true)) {
            continue;
        }

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

// -----------------------------------------------------------------------
// Default form values
// -----------------------------------------------------------------------
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
?>
<h1><?= h($gameName) ?> &mdash; Generator kuponów</h1>

<p style="color:#555;margin-bottom:15px;">Generator tworzy kupony spełniające wybrane kryteria statystyczne. Wagi losowania są oparte na historycznej częstości z ostatnich 500 losowań — liczby gorące są bardziej prawdopodobne do wylosowania. Nie zwiększa to szans na wygraną matematycznie, ale pozwala tworzyć kupony „zgodne z historycznym wzorcem".</p>

<form method="post" action="">
    <input type="hidden" name="page" value="generator">
    <input type="hidden" name="game" value="<?= h($game) ?>">

    <table style="width:auto; background:none; border:none;">
        <tr>
            <td><label title="Suma wszystkich <?= $pickCount ?> liczb w kuponie. Zmień zakres aby zawęzić generowane kupony.">Suma min:</label></td>
            <td><input type="number" name="sum_min" value="<?= h((string)$def['sum_min']) ?>" min="0" max="999" style="width:80px;"></td>
            <td><label title="Suma wszystkich <?= $pickCount ?> liczb w kuponie. Zmień zakres aby zawęzić generowane kupony.">Suma max:</label></td>
            <td><input type="number" name="sum_max" value="<?= h((string)$def['sum_max']) ?>" min="0" max="999" style="width:80px;"></td>
        </tr>
        <tr>
            <td><label title="Ile liczb parzystych (2,4,6...) w kuponie. Rozkład historyczny: najczęściej połowa lub blisko połowy.">Parzyste min:</label></td>
            <td>
                <select name="even_min">
                    <?php for ($i = 0; $i <= $pickCount; $i++): ?>
                        <option value="<?= $i ?>" <?= $def['even_min'] === $i ? 'selected' : '' ?>><?= $i ?></option>
                    <?php endfor; ?>
                </select>
            </td>
            <td><label title="Ile liczb parzystych (2,4,6...) w kuponie. Rozkład historyczny: najczęściej połowa lub blisko połowy.">Parzyste max:</label></td>
            <td>
                <select name="even_max">
                    <?php for ($i = 0; $i <= $pickCount; $i++): ?>
                        <option value="<?= $i ?>" <?= $def['even_max'] === $i ? 'selected' : '' ?>><?= $i ?></option>
                    <?php endfor; ?>
                </select>
            </td>
        </tr>
        <tr>
            <td><label title="Ile liczb z dolnej połowy puli (≤ <?= (int)$gameConfig['low_threshold'] ?>). Rozkład historyczny: najczęściej połowa lub blisko połowy.">Niskie min:</label></td>
            <td>
                <select name="low_min">
                    <?php for ($i = 0; $i <= $pickCount; $i++): ?>
                        <option value="<?= $i ?>" <?= $def['low_min'] === $i ? 'selected' : '' ?>><?= $i ?></option>
                    <?php endfor; ?>
                </select>
            </td>
            <td><label title="Ile liczb z dolnej połowy puli (≤ <?= (int)$gameConfig['low_threshold'] ?>). Rozkład historyczny: najczęściej połowa lub blisko połowy.">Niskie max:</label></td>
            <td>
                <select name="low_max">
                    <?php for ($i = 0; $i <= $pickCount; $i++): ?>
                        <option value="<?= $i ?>" <?= $def['low_max'] === $i ? 'selected' : '' ?>><?= $i ?></option>
                    <?php endfor; ?>
                </select>
            </td>
        </tr>
        <tr>
            <td><label title="Maksymalna liczba par kolejnych liczb (np. 7,8 lub 23,24). 0 = brak par kolejnych.">Maks. kolejnych par:</label></td>
            <td>
                <select name="consec_max">
                    <?php for ($i = 0; $i < $pickCount; $i++): ?>
                        <option value="<?= $i ?>" <?= $def['consec_max'] === $i ? 'selected' : '' ?>><?= $i ?></option>
                    <?php endfor; ?>
                </select>
            </td>
            <td><label title="Minimalna liczba gorących liczb (top-10 z ostatnich 500 losowań) w kuponie.">Min. gorących (top-10):</label></td>
            <td>
                <select name="hot_min">
                    <?php for ($i = 0; $i <= $pickCount; $i++): ?>
                        <option value="<?= $i ?>" <?= $def['hot_min'] === $i ? 'selected' : '' ?>><?= $i ?></option>
                    <?php endfor; ?>
                </select>
            </td>
        </tr>
        <tr>
            <td><label title="Ile liczb może kończyć się tą samą cyfrą (np. 3,13,23,33 – wszystkie kończą się 3). Brak ograniczenia: <?= $pickCount ?>.">Maks. tych samych ostatnich cyfr:</label></td>
            <td>
                <select name="last_digit_max">
                    <?php for ($i = 1; $i <= $pickCount; $i++): ?>
                        <option value="<?= $i ?>" <?= $def['last_digit_max'] === $i ? 'selected' : '' ?>><?= $i ?></option>
                    <?php endfor; ?>
                </select>
            </td>
            <td><label>Liczba kuponów:</label></td>
            <td>
                <select name="count">
                    <?php foreach ([1,2,3,5,10,15,20] as $c): ?>
                        <option value="<?= $c ?>" <?= $def['count'] === $c ? 'selected' : '' ?>><?= $c ?></option>
                    <?php endforeach; ?>
                </select>
            </td>
        </tr>
        <tr>
            <td><label title="Maksymalna liczba liczb z jednej dziesiątki (np. max 2 z zakresu 10–19). Zmniejsza skupienie liczb w jednym przedziale.">Maks. z jednej dziesiątki:</label></td>
            <td><input type="number" name="decades_max" value="<?= h((string)$def['decades_max']) ?>" min="1" max="<?= $pickCount ?>" style="width:60px;"></td>
            <td colspan="2"><small style="color:#555;">Ogranicza skupienie liczb w jednym przedziale (1–9, 10–19, 20–29 itd.)</small></td>
        </tr>
    </table>

    <?php if (!empty($profileHashes)): ?>
    <div style="margin-top:10px;">
        <label>Profile (opcjonalnie, Ctrl+klik dla wielu):</label><br>
        <select name="profile_hashes[]" multiple size="6" style="width:400px;">
            <?php foreach ($profileHashes as $ph): ?>
                <option value="<?= h($ph['profile_hash']) ?>"
                    <?= in_array($ph['profile_hash'], $postedHashes, true) ? 'selected' : '' ?>>
                    <?= h($ph['profile_hash']) ?>
                    (<?= h((string)$ph['total_draws']) ?> × / <?= h((string)$ph['pct_of_total']) ?>%)
                </option>
            <?php endforeach; ?>
        </select>
        <small style="display:block;color:#555;margin-top:4px;">Profil to strukturalny wzorzec losowania. Wybierz jeden lub więcej profili aby generować tylko kupony o danym charakterze.<br>Format: {parzyste}e{nieparzyste}o_{niskie}l{wysokie}h_s{zakres sumy}_c{kolejne}_r{rozstęp}</small>
    </div>
    <?php endif; ?>

    <br><input type="submit" value="Generuj kupony">
</form>

<?php if (!empty($warnings)): ?>
    <?php foreach ($warnings as $w): ?>
        <div class="alert alert-error"><?= h($w) ?></div>
    <?php endforeach; ?>
<?php endif; ?>

<?php if ($formPosted && !empty($results)): ?>
<h2>Wygenerowane kupony (<?= count($results) ?>)</h2>
<p>Top-10 gorących: <?php foreach ($top10 as $n): ?>
    <span class="ball hot"><?= h((string)$n) ?></span>
<?php endforeach; ?></p>

<?php foreach ($results as $idx => $r): ?>
<div class="coupon">
    <strong>Kupon <?= $idx + 1 ?></strong>&nbsp;&nbsp;
    <?php foreach ($r['numbers'] as $n): ?>
        <span class="ball <?= in_array($n, $top10, true) ? 'hot' : '' ?>"><?= h((string)$n) ?></span>
    <?php endforeach; ?>
    <br>
    <small>
        Suma: <?= h((string)$r['metrics']['sum_total']) ?> &nbsp;|&nbsp;
        Par.: <?= h((string)$r['metrics']['even_count']) ?> &nbsp;|&nbsp;
        Niskie: <?= h((string)$r['metrics']['low_count']) ?> &nbsp;|&nbsp;
        Kol.: <?= h((string)$r['metrics']['consecutive']) ?> &nbsp;|&nbsp;
        Cyfry: <?= h((string)$r['metrics']['last_digit_unique']) ?> &nbsp;|&nbsp;
        Profil: <code><?= h($r['hash']) ?></code>
    </small>
</div>
<?php endforeach; ?>

<?php elseif ($formPosted): ?>
<div class="alert alert-error">Nie udało się wygenerować żadnego kuponu spełniającego kryteria.</div>
<?php endif; ?>
