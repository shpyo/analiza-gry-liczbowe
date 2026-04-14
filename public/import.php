<?php
declare(strict_types=1);

/**
 * import.php - One-time / admin import from mbnet.com.pl
 * Can be run from CLI:  php import.php [game_slug]
 * Or via browser:       import.php?game=lotto
 */

$isCli = PHP_SAPI === 'cli';

if (!$isCli) {
    // Included inside index.php, no headers needed
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
    echo '<header class="page-header"><div><span class="text-label-md text-primary mb-2" style="display:block;">ADMINISTRACJA</span>';
    echo '<h1 class="page-header__title">Import losowań</h1>';
    echo '<p class="page-header__desc">Jednorazowy import pełnej historii losowań z mbnet.com.pl.</p></div></header>';
}

foreach ($gamesToProcess as $slug) {
    $gameConfig = get_game_config($pdo, $slug);
    $url        = $gameConfig['sync_url'];
    $gameName   = $gameConfig['name'];

    if ($isCli) {
        echo "=== Importing {$gameName} from {$url} ===\n";
    } else {
        echo '<div class="card mb-4">';
        echo '<h2 class="text-headline-md mb-3">' . h($gameName) . '</h2>';
        echo '<p class="text-body-sm text-on-surface-variant mb-4">Pobieranie z <code>' . h($url) . '</code>...</p>';
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
            echo '<div class="alert alert-error">' . h($errMsg) . '</div></div>';
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

    rebuild_profiles($pdo, $slug);

    $summary = "Wstawiono: {$inserted}, Już istniało: {$skipped}, Błędy parsowania: {$errors}";
    if ($isCli) {
        echo $summary . "\n";
        echo "Profiles rebuilt.\n\n";
    } else {
        echo '<div class="alert alert-success">' . h($summary) . ' &mdash; Profile przebudowane.</div>';
        echo '</div>';
    }
}
