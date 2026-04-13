<?php
declare(strict_types=1);

/**
 * sync.php - Token-protected incremental sync from mbnet.com.pl
 * Called via: sync.php?key=TOKEN[&game=lotto]
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

// Validate sync token
$syncToken  = $envVars['SYNC_TOKEN'] ?? '';
$givenToken = $_GET['key'] ?? '';

if ($syncToken === '' || $givenToken !== $syncToken) {
    http_response_code(403);
    echo '<div class="alert alert-error">403 Forbidden: invalid or missing sync token.</div>';
    return;
}

// Determine which game(s) to sync
$targetGame = isset($_POST['game']) ? trim($_POST['game'])
    : (isset($_GET['game']) ? trim($_GET['game']) : null);

if ($targetGame !== null && !in_array($targetGame, GAMES, true)) {
    echo '<div class="alert alert-error">Invalid game: ' . h($targetGame) . '</div>';
    return;
}

$gamesToSync = $targetGame !== null ? [$targetGame] : GAMES;

echo '<h1>Sync Draws</h1>';

foreach ($gamesToSync as $slug) {
    $gameConfig = get_game_config($pdo, $slug);
    $url        = $gameConfig['sync_url'];
    $gameName   = $gameConfig['name'];

    echo '<h2>' . h($gameName) . '</h2>';

    // Get the highest draw_number already in DB
    $drawsTable  = GAME_TABLES[$slug];
    $maxDrawNum  = (int)$pdo->query("SELECT COALESCE(MAX(draw_number),0) FROM `{$drawsTable}`")->fetchColumn();

    $context = stream_context_create([
        'http' => [
            'header'  => "User-Agent: LottoAnalyzer/1.0 (PHP)\r\n",
            'timeout' => 30,
        ],
    ]);

    $status   = 'ok';
    $errMsg   = null;
    $inserted = 0;
    $lastNum  = $maxDrawNum;

    $content = @file_get_contents($url, false, $context);

    if ($content === false) {
        $status = 'error';
        $errMsg = "Failed to fetch data from {$url}";
        echo '<div class="alert alert-error">' . h($errMsg) . '</div>';
    } else {
        $lines = explode("\n", $content);

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $parsed = parse_mbnet_line($line, $slug);
            if ($parsed === null) {
                continue;
            }
            // Only process draws newer than what we already have
            if ($parsed['draw_number'] <= $maxDrawNum) {
                continue;
            }
            if (insert_draw($pdo, $slug, $parsed)) {
                $inserted++;
                if ($parsed['draw_number'] > $lastNum) {
                    $lastNum = $parsed['draw_number'];
                }
            }
        }

        if ($inserted === 0) {
            $status = 'no_new';
        }

        // Rebuild profiles only if new draws were added
        if ($inserted > 0) {
            rebuild_profiles($pdo, $slug);
        }

        $summary = $inserted > 0
            ? "Added {$inserted} new draw(s). Last draw number: {$lastNum}."
            : "No new draws found (already up to date at draw #{$maxDrawNum}).";
        echo '<div class="alert ' . ($inserted > 0 ? 'alert-success' : '') . '">' . h($summary) . '</div>';
    }

    // Log to sync_log
    try {
        $logStmt = $pdo->prepare(
            "INSERT INTO sync_log (game_slug, draws_added, last_draw_number, source_url, status, error_msg)
             VALUES (?, ?, ?, ?, ?, ?)"
        );
        $logStmt->execute([$slug, $inserted, $lastNum, $url, $status, $errMsg]);
    } catch (PDOException $e) {
        // Non-fatal: log failure silently
    }
}
