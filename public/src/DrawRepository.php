<?php
declare(strict_types=1);

final class DrawRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * Insert a draw. Dynamically builds column list from game definition.
     * Returns true if inserted (false if duplicate).
     */
    public function insertDraw(GameDefinition $game, array $parsed, MetricCalculator $calc, ProfileDescriber $describer): bool
    {
        $numbers = $parsed['numbers'];
        $metrics = $calc->computeMetrics($numbers, $game);
        $hash    = $describer->computeHash($metrics, $game);

        $numberCols = $game->numberColumns();
        $columns    = ['draw_date', 'draw_number'];
        $values     = [$parsed['draw_date'], $parsed['draw_number']];

        // Add number columns
        foreach ($numberCols as $i => $col) {
            $columns[] = $col;
            $values[]  = $numbers[$i];
        }

        // Add plus_ball if game has bonus
        if ($game->hasBonus) {
            $columns[] = 'plus_ball';
            $values[]  = $parsed['plus_ball'];
        }

        // Add metric columns
        $metricKeys = ['sum_total', 'even_count', 'low_count', 'consecutive',
                       'decades_used', 'range_spread', 'last_digit_unique'];
        foreach ($metricKeys as $key) {
            $columns[] = $key;
            $values[]  = $metrics[$key];
        }

        // Add profile hash
        $columns[] = 'profile_hash';
        $values[]  = $hash;

        $colsSql        = implode(', ', $columns);
        $placeholders   = implode(', ', array_fill(0, count($values), '?'));
        $table          = $game->drawsTable;

        $sql  = "INSERT IGNORE INTO `{$table}` ({$colsSql}) VALUES ({$placeholders})";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($values);

        return $stmt->rowCount() > 0;
    }

    /**
     * Rebuild the profiles aggregate table for a game.
     */
    public function rebuildProfiles(GameDefinition $game, ProfileDescriber $describer): void
    {
        $drawsTable   = $game->drawsTable;
        $profileTable = $game->profileTable;

        $this->pdo->exec("DELETE FROM `{$profileTable}`");

        $sql = "SELECT profile_hash, even_count, low_count, consecutive,
                       MIN(draw_date) AS first_seen,
                       MAX(draw_date) AS last_seen,
                       COUNT(*)       AS total_draws
                FROM `{$drawsTable}`
                WHERE profile_hash IS NOT NULL
                GROUP BY profile_hash, even_count, low_count, consecutive";

        $rows  = $this->pdo->query($sql)->fetchAll();
        $total = (int)$this->pdo->query("SELECT COUNT(*) FROM `{$drawsTable}`")->fetchColumn();

        if ($total === 0 || empty($rows)) {
            return;
        }

        $insert = $this->pdo->prepare(
            "INSERT INTO `{$profileTable}`
                 (profile_hash, even_count, low_count, sum_bucket, consecutive,
                  range_bucket, total_draws, pct_of_total, last_seen, first_seen)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );

        foreach ($rows as $row) {
            $parsed = $describer->parseHash((string)$row['profile_hash']);
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
}
