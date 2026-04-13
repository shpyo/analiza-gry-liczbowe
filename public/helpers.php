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
// Metric computation
// ---------------------------------------------------------------------------

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
// Bucket helpers
// ---------------------------------------------------------------------------

function sum_bucket(int $sum, string $game_slug): string
{
    if ($game_slug === 'mini_lotto') {
        if ($sum <= 49)  return 'XS';
        if ($sum <= 79)  return 'S';
        if ($sum <= 120) return 'M';
        if ($sum <= 159) return 'L';
        return 'XL';
    }
    // lotto / lotto_plus
    if ($sum <= 79)  return 'XS';
    if ($sum <= 109) return 'S';
    if ($sum <= 170) return 'M';
    if ($sum <= 200) return 'L';
    return 'XL';
}

function range_bucket(int $range, string $game_slug): string
{
    if ($game_slug === 'mini_lotto') {
        if ($range <= 12) return 'XS';
        if ($range <= 22) return 'S';
        if ($range <= 31) return 'M';
        if ($range <= 37) return 'L';
        return 'XL';
    }
    // lotto / lotto_plus
    if ($range <= 19) return 'XS';
    if ($range <= 29) return 'S';
    if ($range <= 39) return 'M';
    if ($range <= 44) return 'L';
    return 'XL';
}

// ---------------------------------------------------------------------------
// Profile hash
// ---------------------------------------------------------------------------

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

function parse_profile_hash(string $hash): array
{
    $parts = explode('_', $hash);
    // parts[2] = 'sM', parts[4] = 'rL'
    $sumBucket   = isset($parts[2]) ? substr($parts[2], 1) : 'M';
    $rangeBucket = isset($parts[4]) ? substr($parts[4], 1) : 'M';
    return [
        'sum_bucket'   => $sumBucket,
        'range_bucket' => $rangeBucket,
    ];
}

// ---------------------------------------------------------------------------
// mbnet line parser
// ---------------------------------------------------------------------------

function parse_mbnet_line(string $line, string $game_slug): ?array
{
    $line = trim($line);
    // Format: {number}. {dd.mm.yyyy} {n1},{n2},...
    if (!preg_match('/^(\d+)\.\s+(\d{2})\.(\d{2})\.(\d{4})\s+([\d,]+)$/', $line, $m)) {
        return null;
    }

    $drawNumber = (int)$m[1];
    $drawDate   = $m[4] . '-' . $m[3] . '-' . $m[2]; // Y-m-d
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
// Insert draw
// ---------------------------------------------------------------------------

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
        // lotto
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
// Rebuild profiles
// ---------------------------------------------------------------------------

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
// HTML escaping helper
// ---------------------------------------------------------------------------

function h(string $val): string
{
    return htmlspecialchars($val, ENT_QUOTES, 'UTF-8');
}
