<?php
declare(strict_types=1);

// ---------------------------------------------------------------------------
// Game configuration helpers
// ---------------------------------------------------------------------------

function get_game_config(PDO $pdo, string $game_slug): array
{
    $stmt = $pdo->prepare('SELECT * FROM games WHERE slug = ?');
    $stmt->execute([$game_slug]);
    $row = $stmt->fetch();
    if (!$row) {
        throw new RuntimeException("Unknown game slug: {$game_slug}");
    }
    return $row;
}

// ---------------------------------------------------------------------------
// @deprecated - Use MetricCalculator::computeMetrics() instead
// ---------------------------------------------------------------------------

/** @deprecated Use MetricCalculator::computeMetrics() */
function compute_metrics(array $numbers, string $game_slug): array
{
    sort($numbers);
    $pickCount = ($game_slug === 'mini_lotto') ? 5 : 6;
    $lowThreshold = ($game_slug === 'mini_lotto') ? 21 : 24;

    $sumTotal  = array_sum($numbers);
    $evenCount = 0;
    $lowCount  = 0;
    $consecutive = 0;
    $decades   = [];
    $lastDigits = [];

    for ($i = 0; $i < count($numbers); $i++) {
        $n = $numbers[$i];
        if ($n % 2 === 0) {
            $evenCount++;
        }
        if ($n <= $lowThreshold) {
            $lowCount++;
        }
        $decades[intdiv($n - 1, 10)] = true;
        $lastDigits[$n % 10] = true;
        if ($i > 0 && $numbers[$i] === $numbers[$i - 1] + 1) {
            $consecutive++;
        }
    }

    $rangeSpread   = $numbers[count($numbers) - 1] - $numbers[0];
    $decadesUsed   = count($decades);
    $lastDigitUnique = count($lastDigits);

    return [
        'sum_total'        => $sumTotal,
        'even_count'       => $evenCount,
        'low_count'        => $lowCount,
        'consecutive'      => $consecutive,
        'decades_used'     => $decadesUsed,
        'range_spread'     => $rangeSpread,
        'last_digit_unique'=> $lastDigitUnique,
    ];
}

// ---------------------------------------------------------------------------
// @deprecated - Use GameDefinition->sumBuckets->classify() instead
// ---------------------------------------------------------------------------

/** @deprecated Use GameDefinition->sumBuckets->classify() */
function sum_bucket(int $sum, string $game_slug): string
{
    if ($game_slug === 'mini_lotto') {
        if ($sum <= 49)  return 'XS';
        if ($sum <= 79)  return 'S';
        if ($sum <= 120) return 'M';
        if ($sum <= 159) return 'L';
        return 'XL';
    }
    if ($sum <= 79)  return 'XS';
    if ($sum <= 109) return 'S';
    if ($sum <= 170) return 'M';
    if ($sum <= 200) return 'L';
    return 'XL';
}

/** @deprecated Use GameDefinition->rangeBuckets->classify() */
function range_bucket(int $range, string $game_slug): string
{
    if ($game_slug === 'mini_lotto') {
        if ($range <= 12) return 'XS';
        if ($range <= 22) return 'S';
        if ($range <= 31) return 'M';
        if ($range <= 37) return 'L';
        return 'XL';
    }
    if ($range <= 19) return 'XS';
    if ($range <= 29) return 'S';
    if ($range <= 39) return 'M';
    if ($range <= 44) return 'L';
    return 'XL';
}

// ---------------------------------------------------------------------------
// @deprecated - Use ProfileDescriber::computeHash() instead
// ---------------------------------------------------------------------------

/** @deprecated Use ProfileDescriber::computeHash() */
function compute_profile_hash(array $metrics, string $game_slug): string
{
    $pickCount = ($game_slug === 'mini_lotto') ? 5 : 6;
    $even = (int)$metrics['even_count'];
    $odd  = $pickCount - $even;
    $low  = (int)$metrics['low_count'];
    $high = $pickCount - $low;
    $sB   = sum_bucket((int)$metrics['sum_total'], $game_slug);
    $rB   = range_bucket((int)$metrics['range_spread'], $game_slug);
    $c    = (int)$metrics['consecutive'];

    return "{$even}e{$odd}o_{$low}l{$high}h_s{$sB}_c{$c}_r{$rB}";
}

/** @deprecated Use ProfileDescriber::parseHash() */
function parse_profile_hash(string $hash): array
{
    $parts = explode('_', $hash);
    $sumBucket   = isset($parts[2]) ? substr($parts[2], 1) : 'M';
    $rangeBucket = isset($parts[4]) ? substr($parts[4], 1) : 'M';
    return [
        'sum_bucket'   => $sumBucket,
        'range_bucket' => $rangeBucket,
    ];
}

// ---------------------------------------------------------------------------
// @deprecated - Use MbnetLineParser::parse() instead
// ---------------------------------------------------------------------------

/** @deprecated Use GameDefinition->lineParser->parse() */
function parse_mbnet_line(string $line, string $game_slug): ?array
{
    $line = trim($line);
    if (!preg_match('/^(\d+)\.\s+(\d{2})\.(\d{2})\.(\d{4})\s+([\d,]+)$/', $line, $m)) {
        return null;
    }

    $drawNumber = (int)$m[1];
    $drawDate   = $m[4] . '-' . $m[3] . '-' . $m[2];
    $rawNums    = array_map('intval', explode(',', $m[5]));

    $pickCount = ($game_slug === 'mini_lotto') ? 5 : 6;
    $plusBall  = null;

    if ($game_slug === 'lotto_plus' && count($rawNums) === 7) {
        $plusBall = $rawNums[6];
        $rawNums  = array_slice($rawNums, 0, 6);
    }

    if (count($rawNums) !== $pickCount) {
        return null;
    }

    sort($rawNums);

    return [
        'draw_number' => $drawNumber,
        'draw_date'   => $drawDate,
        'numbers'     => $rawNums,
        'plus_ball'   => $plusBall,
    ];
}

// ---------------------------------------------------------------------------
// @deprecated - Use DrawRepository::insertDraw() instead
// ---------------------------------------------------------------------------

/** @deprecated Use DrawRepository::insertDraw() */
function insert_draw(PDO $pdo, string $game_slug, array $parsed): bool
{
    $table   = GAME_TABLES[$game_slug];
    $numbers = $parsed['numbers'];
    $metrics = compute_metrics($numbers, $game_slug);
    $hash    = compute_profile_hash($metrics, $game_slug);

    if ($game_slug === 'lotto_plus') {
        $sql = "INSERT IGNORE INTO `{$table}`
                    (draw_date, draw_number, n1, n2, n3, n4, n5, n6, plus_ball,
                     sum_total, even_count, low_count, consecutive,
                     decades_used, range_spread, last_digit_unique, profile_hash)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?,
                        ?, ?, ?, ?, ?, ?, ?, ?)";
        $params = [
            $parsed['draw_date'],
            $parsed['draw_number'],
            $numbers[0], $numbers[1], $numbers[2],
            $numbers[3], $numbers[4], $numbers[5],
            $parsed['plus_ball'],
            $metrics['sum_total'],
            $metrics['even_count'],
            $metrics['low_count'],
            $metrics['consecutive'],
            $metrics['decades_used'],
            $metrics['range_spread'],
            $metrics['last_digit_unique'],
            $hash,
        ];
    } elseif ($game_slug === 'mini_lotto') {
        $sql = "INSERT IGNORE INTO `{$table}`
                    (draw_date, draw_number, n1, n2, n3, n4, n5,
                     sum_total, even_count, low_count, consecutive,
                     decades_used, range_spread, last_digit_unique, profile_hash)
                VALUES (?, ?, ?, ?, ?, ?, ?,
                        ?, ?, ?, ?, ?, ?, ?, ?)";
        $params = [
            $parsed['draw_date'],
            $parsed['draw_number'],
            $numbers[0], $numbers[1], $numbers[2],
            $numbers[3], $numbers[4],
            $metrics['sum_total'],
            $metrics['even_count'],
            $metrics['low_count'],
            $metrics['consecutive'],
            $metrics['decades_used'],
            $metrics['range_spread'],
            $metrics['last_digit_unique'],
            $hash,
        ];
    } else {
        $sql = "INSERT IGNORE INTO `{$table}`
                    (draw_date, draw_number, n1, n2, n3, n4, n5, n6,
                     sum_total, even_count, low_count, consecutive,
                     decades_used, range_spread, last_digit_unique, profile_hash)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?,
                        ?, ?, ?, ?, ?, ?, ?, ?)";
        $params = [
            $parsed['draw_date'],
            $parsed['draw_number'],
            $numbers[0], $numbers[1], $numbers[2],
            $numbers[3], $numbers[4], $numbers[5],
            $metrics['sum_total'],
            $metrics['even_count'],
            $metrics['low_count'],
            $metrics['consecutive'],
            $metrics['decades_used'],
            $metrics['range_spread'],
            $metrics['last_digit_unique'],
            $hash,
        ];
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->rowCount() > 0;
}

// ---------------------------------------------------------------------------
// @deprecated - Use DrawRepository::rebuildProfiles() instead
// ---------------------------------------------------------------------------

/** @deprecated Use DrawRepository::rebuildProfiles() */
function rebuild_profiles(PDO $pdo, string $game_slug): void
{
    $drawsTable   = GAME_TABLES[$game_slug];
    $profileTable = PROFILE_TABLES[$game_slug];

    $pdo->exec("DELETE FROM `{$profileTable}`");

    $sql = "SELECT profile_hash, even_count, low_count, consecutive,
                   MIN(draw_date) AS first_seen,
                   MAX(draw_date) AS last_seen,
                   COUNT(*)       AS total_draws
            FROM `{$drawsTable}`
            WHERE profile_hash IS NOT NULL
            GROUP BY profile_hash, even_count, low_count, consecutive";

    $rows  = $pdo->query($sql)->fetchAll();
    $total = (int)$pdo->query("SELECT COUNT(*) FROM `{$drawsTable}`")->fetchColumn();

    if ($total === 0 || empty($rows)) {
        return;
    }

    $insert = $pdo->prepare(
        "INSERT INTO `{$profileTable}`
             (profile_hash, even_count, low_count, sum_bucket, consecutive,
              range_bucket, total_draws, pct_of_total, last_seen, first_seen)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );

    foreach ($rows as $row) {
        $parsed = parse_profile_hash((string)$row['profile_hash']);
        $pct    = round((float)$row['total_draws'] / $total * 100, 2);
        $insert->execute([
            $row['profile_hash'],
            $row['even_count'],
            $row['low_count'],
            $parsed['sum_bucket'],
            $row['consecutive'],
            $parsed['range_bucket'],
            $row['total_draws'],
            $pct,
            $row['last_seen'],
            $row['first_seen'],
        ]);
    }
}

// ---------------------------------------------------------------------------
// HTML escaping helper (still used everywhere)
// ---------------------------------------------------------------------------

function h(string $val): string
{
    return htmlspecialchars($val, ENT_QUOTES, 'UTF-8');
}

// ---------------------------------------------------------------------------
// Heatmap colour helper (still used in stats.php)
// ---------------------------------------------------------------------------

function heatmap_bucket_color(int $bucket): array
{
    $palette = [
        0 => ['bg' => '#e7e8e9', 'text' => '#414754'],
        1 => ['bg' => '#90bafe', 'text' => '#001a41'],
        2 => ['bg' => '#d8e2ff', 'text' => '#004493'],
        3 => ['bg' => '#ffb870', 'text' => '#2c1600'],
        4 => ['bg' => '#8b5000', 'text' => '#ffffff'],
    ];
    return $palette[max(0, min(4, $bucket))];
}

// ---------------------------------------------------------------------------
// @deprecated tooltip rendering - Use MetricTextProvider::renderTooltip()
// ---------------------------------------------------------------------------

/** @deprecated Use MetricTextProvider::renderTooltip() */
function render_tooltip(string $metric, string $game = 'lotto'): string
{
    $label   = metric_label($metric);
    $tooltip = metric_tooltip($metric, $game);
    return '<span class="tooltip-trigger" title="' . htmlspecialchars($tooltip, ENT_QUOTES, 'UTF-8') . '">'
         . htmlspecialchars($label, ENT_QUOTES, 'UTF-8')
         . ' <span class="tooltip-icon">?</span></span>';
}

// ---------------------------------------------------------------------------
// Render helpers for the design system (still used everywhere)
// ---------------------------------------------------------------------------

function render_ball(int $number, string $modifier = ''): string
{
    $class = 'ball' . ($modifier ? ' ball--' . $modifier : '');
    return '<span class="' . $class . '">' . $number . '</span>';
}

function render_badge(string $label, string $type = 'info'): string
{
    return '<span class="badge badge--' . h($type) . '">' . h($label) . '</span>';
}

function render_material_icon(string $name, string $extraClass = ''): string
{
    $cls = 'material-symbols-outlined' . ($extraClass ? ' ' . $extraClass : '');
    return '<span class="' . $cls . '">' . h($name) . '</span>';
}

// ---------------------------------------------------------------------------
// @deprecated profile description helpers
// ---------------------------------------------------------------------------

/** @deprecated Use ProfileDescriber::describe() */
function describe_profile(string $hash, string $game = 'lotto'): string
{
    $parts = explode('_', $hash);
    if (count($parts) < 5) {
        return $hash;
    }

    if (!preg_match('/^(\d+)e(\d+)o$/', $parts[0], $m0)) {
        return $hash;
    }
    $even = (int)$m0[1];

    if (!preg_match('/^(\d+)l(\d+)h$/', $parts[1], $m1)) {
        return $hash;
    }
    $low = (int)$m1[1];

    $sumBucket = substr($parts[2] ?? 's?', 1);

    if (!preg_match('/^c(\d+)$/', $parts[3] ?? '', $m3)) {
        return $hash;
    }
    $consecutive = (int)$m3[1];

    $rangeBucket = substr($parts[4] ?? 'r?', 1);

    $evenLabel = $even === 1 ? '1 parzysta' : "{$even} parzyste";
    $lowLabel  = $low  === 1 ? '1 niska'    : "{$low} niskie";

    if ($consecutive === 0) {
        $consLabel = 'brak par sąsiadów';
    } elseif ($consecutive === 1) {
        $consLabel = '1 para sąsiadów';
    } else {
        $consLabel = "{$consecutive} pary sąsiadów";
    }

    $sumLabel   = 'suma ' . sum_bucket_label($sumBucket, $game);
    $rangeLabel = 'rozstęp ' . range_bucket_label($rangeBucket, $game);

    return implode(' · ', [$evenLabel, $lowLabel, $sumLabel, $consLabel, $rangeLabel]);
}

/** @deprecated Use ProfileDescriber::describeShort() */
function describe_profile_short(string $hash): string
{
    $parts = explode('_', $hash);
    if (count($parts) < 5) {
        return $hash;
    }

    if (!preg_match('/^(\d+)e(\d+)o$/', $parts[0], $m0)) {
        return $hash;
    }

    $even     = $m0[1];
    $odd      = $m0[2];
    $consec   = isset($parts[3]) ? $parts[3] : 'c?';
    $sumCode  = $parts[2] ?? 's?';
    $rangeCode = $parts[4] ?? 'r?';

    return "{$even}p/{$odd}n · {$sumCode} · {$consec} · {$rangeCode}";
}
