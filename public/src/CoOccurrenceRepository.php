<?php
declare(strict_types=1);

final class CoOccurrenceRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * Upsert pairs and triples for a draw. Idempotent: calling with the same
     * draw_number a second time does not change any counts.
     *
     * @param string $slug       Game slug, e.g. 'multi_multi'
     * @param int    $drawNumber Unique draw identifier
     * @param int[]  $numbers    Sorted drawn numbers
     */
    public function upsertCoOccurrences(string $slug, int $drawNumber, array $numbers, string $drawDate): void
    {
        $logTable = "{$slug}_cooccurrence_log";
        $driver   = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        // Guard against double-processing (works on both MySQL and SQLite)
        $existing = $this->pdo->prepare("SELECT COUNT(*) FROM `{$logTable}` WHERE draw_number = ?");
        $existing->execute([$drawNumber]);
        if ((int)$existing->fetchColumn() > 0) {
            return; // already processed
        }
        $this->pdo->prepare("INSERT INTO `{$logTable}` (draw_number) VALUES (?)")->execute([$drawNumber]);

        sort($numbers);
        $count = count($numbers);

        // Upsert pairs
        $pairsTable = "{$slug}_pairs";
        if ($driver === 'sqlite') {
            $pairInsert = "INSERT OR IGNORE INTO `{$pairsTable}` (n1, n2, count, last_seen) VALUES (?, ?, 0, ?)";
            $pairUpdate = "UPDATE `{$pairsTable}` SET count = count + 1, last_seen = ? WHERE n1 = ? AND n2 = ?";
        } else {
            $pairInsert = "INSERT IGNORE INTO `{$pairsTable}` (n1, n2, count, last_seen) VALUES (?, ?, 0, ?)";
            $pairUpdate = "UPDATE `{$pairsTable}` SET count = count + 1, last_seen = ? WHERE n1 = ? AND n2 = ?";
        }
        $pairInsertStmt = $this->pdo->prepare($pairInsert);
        $pairUpdateStmt = $this->pdo->prepare($pairUpdate);

        for ($i = 0; $i < $count - 1; $i++) {
            for ($j = $i + 1; $j < $count; $j++) {
                $pairInsertStmt->execute([$numbers[$i], $numbers[$j], $drawDate]);
                $pairUpdateStmt->execute([$drawDate, $numbers[$i], $numbers[$j]]);
            }
        }

        // Upsert triples
        $triplesTable = "{$slug}_triples";
        if ($driver === 'sqlite') {
            $tripleInsert = "INSERT OR IGNORE INTO `{$triplesTable}` (n1, n2, n3, count, last_seen) VALUES (?, ?, ?, 0, ?)";
            $tripleUpdate = "UPDATE `{$triplesTable}` SET count = count + 1, last_seen = ? WHERE n1 = ? AND n2 = ? AND n3 = ?";
        } else {
            $tripleInsert = "INSERT IGNORE INTO `{$triplesTable}` (n1, n2, n3, count, last_seen) VALUES (?, ?, ?, 0, ?)";
            $tripleUpdate = "UPDATE `{$triplesTable}` SET count = count + 1, last_seen = ? WHERE n1 = ? AND n2 = ? AND n3 = ?";
        }
        $tripleInsertStmt = $this->pdo->prepare($tripleInsert);
        $tripleUpdateStmt = $this->pdo->prepare($tripleUpdate);

        for ($i = 0; $i < $count - 2; $i++) {
            for ($j = $i + 1; $j < $count - 1; $j++) {
                for ($k = $j + 1; $k < $count; $k++) {
                    $tripleInsertStmt->execute([$numbers[$i], $numbers[$j], $numbers[$k], $drawDate]);
                    $tripleUpdateStmt->execute([$drawDate, $numbers[$i], $numbers[$j], $numbers[$k]]);
                }
            }
        }
    }

    /**
     * Get top pairs sorted by lift (count / expected).
     * Expected frequency of a pair: totalDraws * C(pickCount,2) / C(poolSize,2)
     *
     * @return array<array{n1:int, n2:int, count:int, last_seen:string, expected:float, lift:float}>
     */
    public function getTopPairs(string $slug, int $totalDraws, int $pickCount, int $poolSize, int $limit = 50, int $minCount = 1): array
    {
        $table    = "{$slug}_pairs";
        $stmt     = $this->pdo->prepare(
            "SELECT n1, n2, count, last_seen FROM `{$table}` WHERE count >= ? ORDER BY count DESC LIMIT ?"
        );
        $stmt->execute([$minCount, $limit * 10]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $combPick = $this->combinations($pickCount, 2);
        $combPool = $this->combinations($poolSize, 2);

        $results = [];
        foreach ($rows as $row) {
            $expected = $totalDraws > 0 ? ($totalDraws * $combPick / $combPool) : 0.0;
            $lift     = $expected > 0 ? (float)$row['count'] / $expected : 0.0;
            $results[] = [
                'n1'        => (int)$row['n1'],
                'n2'        => (int)$row['n2'],
                'count'     => (int)$row['count'],
                'last_seen' => $row['last_seen'],
                'expected'  => round($expected, 4),
                'lift'      => round($lift, 4),
            ];
        }

        usort($results, fn($a, $b) => $b['lift'] <=> $a['lift']);

        return array_slice($results, 0, $limit);
    }

    /**
     * Get top triples sorted by lift.
     * Expected frequency of a triple: totalDraws * C(pickCount,3) / C(poolSize,3)
     *
     * @return array<array{n1:int, n2:int, n3:int, count:int, last_seen:string, expected:float, lift:float}>
     */
    public function getTopTriples(string $slug, int $totalDraws, int $pickCount, int $poolSize, int $limit = 50, int $minCount = 1): array
    {
        $table = "{$slug}_triples";
        $stmt  = $this->pdo->prepare(
            "SELECT n1, n2, n3, count, last_seen FROM `{$table}` WHERE count >= ? ORDER BY count DESC LIMIT ?"
        );
        $stmt->execute([$minCount, $limit * 10]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $combPick = $this->combinations($pickCount, 3);
        $combPool = $this->combinations($poolSize, 3);

        $results = [];
        foreach ($rows as $row) {
            $expected = $totalDraws > 0 ? ($totalDraws * $combPick / $combPool) : 0.0;
            $lift     = $expected > 0 ? (float)$row['count'] / $expected : 0.0;
            $results[] = [
                'n1'        => (int)$row['n1'],
                'n2'        => (int)$row['n2'],
                'n3'        => (int)$row['n3'],
                'count'     => (int)$row['count'],
                'last_seen' => $row['last_seen'],
                'expected'  => round($expected, 4),
                'lift'      => round($lift, 4),
            ];
        }

        usort($results, fn($a, $b) => $b['lift'] <=> $a['lift']);

        return array_slice($results, 0, $limit);
    }

    /** Binomial coefficient C(n, k). */
    private function combinations(int $n, int $k): int
    {
        if ($k > $n) {
            return 0;
        }
        if ($k === 0 || $k === $n) {
            return 1;
        }
        $result = 1;
        for ($i = 0; $i < $k; $i++) {
            $result = intdiv($result * ($n - $i), $i + 1);
        }
        return $result;
    }
}
