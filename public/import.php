<?php
declare(strict_types=1);

/**
 * import.php - One-time / admin import from mbnet.com.pl
 * Can be run from CLI:  php import.php [game_slug]
 * Or via browser:       import.php?game=lotto
 */

$isCli = PHP_SAPI === 'cli';

if (!$isCli) {
    // Ensure HTML wrapper not already started by index.php
    // This file is included inside index.php, so no need for headers here
}

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

// Determine which game(s) to import
$targetGame = null;
if ($isCli) {
    $targetGame = isset($argv[1]) ? trim($argv[1]) : null;
} else {
    $targetGame = isset($_GET['game']) ? trim($_GET['game']) : null;
}

if ($targetGame !== null && !in_array($targetGame, GAMES, true)) {
    $msg = 'Invalid game slug: ' . h($targetGame);
    if ($isCli) {
        echo $msg . "\n";
    } else {
        echo '<div class="alert alert-error">' . $msg . '</div>';
    }
    return;
}

$gamesToProcess = $targetGame !== null ? [$targetGame] : GAMES;

if (!$isCli) {
    echo '<h1>Import Draws</h1>';
}

foreach ($gamesToProcess as $slug) {
    $gameConfig = get_game_config($pdo, $slug);
    $url        = $gameConfig['sync_url'];
    $gameName   = $gameConfig['name'];

    if ($isCli) {
        echo "=== Importing {$gameName} from {$url} ===\n";
    } else {
        echo '<h2>' . h($gameName) . '</h2>';
        echo '<p>Fetching from <code>' . h($url) . '</code>...</p>';
    }

    $context = stream_context_create([
        'http' => [
            'header'  => "User-Agent: LottoAnalyzer/1.0 (PHP)\r\n",
            'timeout' => 30,
        ],
    ]);

    $content = @file_get_contents($url, false, $context);

    if ($content === false) {
        $errMsg = "Failed to fetch data from {$url}";
        if ($isCli) {
            echo "ERROR: {$errMsg}\n";
        } else {
            echo '<div class="alert alert-error">' . h($errMsg) . '</div>';
        }
        continue;
    }

    $lines    = explode("\n", $content);
    $inserted = 0;
    $skipped  = 0;
    $errors   = 0;

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '') {
            continue;
        }
        $parsed = parse_mbnet_line($line, $slug);
        if ($parsed === null) {
            $errors++;
            continue;
        }
        if (insert_draw($pdo, $slug, $parsed)) {
            $inserted++;
        } else {
            $skipped++;
        }
    }

    // Rebuild profiles after import
    rebuild_profiles($pdo, $slug);

    $summary = "Inserted: {$inserted}, Already existed: {$skipped}, Parse errors: {$errors}";
    if ($isCli) {
        echo $summary . "\n";
        echo "Profiles rebuilt.\n\n";
    } else {
        echo '<div class="alert alert-success">' . h($summary) . ' &mdash; Profiles rebuilt.</div>';
    }
}
