<?php
declare(strict_types=1);

/**
 * sync.php - Token-protected incremental sync from mbnet.com.pl
 * Called via: sync.php?key=TOKEN[&game=lotto]
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/src/autoload.php';

$kit = new GameKit($pdo);

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

$allSlugs = $kit->registry()->allSlugs();
if ($targetGame !== null && !in_array($targetGame, $allSlugs, true)) {
    echo '<div class="alert alert-error">Invalid game: ' . h($targetGame) . '</div>';
    return;
}

$gamesToSync = $targetGame !== null ? [$targetGame] : $allSlugs;

echo '<header class="page-header"><div><span class="text-label-md text-primary mb-2" style="display:block;">ADMINISTRACJA</span>';
echo '<h1 class="page-header__title">Synchronizacja losowań</h1></div></header>';

foreach ($gamesToSync as $slug) {
    $gameDef    = $kit->game($slug);
    $drawsTable = $gameDef->drawsTable;

    echo '<div class="card mb-4">';
    echo '<h2 class="text-headline-md mb-3">' . h($gameDef->name) . '</h2>';

    $maxDrawNum = (int)$pdo->query("SELECT COALESCE(MAX(draw_number),0) FROM `{$drawsTable}`")->fetchColumn();

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

    $content = @file_get_contents($gameDef->syncUrl, false, $context);

    if ($content === false) {
        $status = 'error';
        $errMsg = "Failed to fetch data from {$gameDef->syncUrl}";
        echo '<div class="alert alert-error">' . h($errMsg) . '</div>';
    } else {
        $lines = explode("\n", $content);

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $parsed = $gameDef->lineParser->parse($line);
            if ($parsed === null) {
                continue;
            }
            if ($parsed['draw_number'] <= $maxDrawNum) {
                continue;
            }
            if ($kit->repository()->insertDraw($gameDef, $parsed, $kit->calculator(), $kit->describer())) {
                $inserted++;
                if ($parsed['draw_number'] > $lastNum) {
                    $lastNum = $parsed['draw_number'];
                }
                if ($gameDef->coOccurrence) {
                    $kit->coOccurrence()->processNewDraw($gameDef, $parsed['numbers'], $parsed['draw_date']);
                }
            }
        }

        if ($inserted === 0) {
            $status = 'no_new';
        }

        if ($inserted > 0) {
            $kit->repository()->rebuildProfiles($gameDef, $kit->describer());
        }

        $summary = $inserted > 0
            ? "Dodano {$inserted} nowych losowań. Ostatni numer: {$lastNum}."
            : "Brak nowych losowań (aktualne przy losowaniu #{$maxDrawNum}).";
        echo '<div class="alert ' . ($inserted > 0 ? 'alert-success' : '') . '">' . h($summary) . '</div>';
    }

    echo '</div>';

    // Log to sync_log
    try {
        $logStmt = $pdo->prepare(
            "INSERT INTO sync_log (game_slug, draws_added, last_draw_number, source_url, status, error_msg)
             VALUES (?, ?, ?, ?, ?, ?)"
        );
        $logStmt->execute([$slug, $inserted, $lastNum, $gameDef->syncUrl, $status, $errMsg]);
    } catch (PDOException $e) {
        // Non-fatal: log failure silently
    }
}
