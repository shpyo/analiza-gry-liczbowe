<?php
declare(strict_types=1);

/**
 * CoOccurrenceRepository — manages pair and triple co-occurrence tables.
 *
 * Supports games that have `coOccurrence = true` (currently only multi_multi).
 * Tables: {slug}_pairs (n1, n2, count, last_seen)
 *         {slug}_triples (n1, n2, n3, count, last_seen)
 */
final class CoOccurrenceRepository
{
    private const BATCH_SIZE = 500;

    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * Full rebuild: delete all pairs/triples and recompute from all draws.
     * Call after a full import.
     */
    public function rebuildFromDraws(GameDefinition $game): void
    {
        $pairsTable   = "{$game->slug}_pairs";
        $triplesTable = "{$game->slug}_triples";
        $pickCount    = $game->pickCount;

        // Fetch all draws ordered by draw_number
        $cols    = $game->numberColumnsSql();
        $rows    = $this->pdo->query(
            "SELECT {$cols}, draw_date FROM `{$game->drawsTable}` ORDER BY draw_number ASC"
        )->fetchAll(PDO::FETCH_ASSOC);

        $pairsAcc   = [];  // 'n1:n2' => [count, last_seen]
        $triplesAcc = [];  // 'n1:n2:n3' => [count, last_seen]

        foreach ($rows as $row) {
            $numbers = [];
            for ($i = 1; $i <= $pickCount; $i++) {
                $numbers[] = (int)$row["n{$i}"];
            }
            sort($numbers);
            $date = (string)$row['draw_date'];

            // Accumulate pairs
            for ($i = 0; $i < $pickCount; $i++) {
                for ($j = $i + 1; $j < $pickCount; $j++) {
                    $key = $numbers[$i] . ':' . $numbers[$j];
                    if (!isset($pairsAcc[$key])) {
                        $pairsAcc[$key] = [0, $date, $numbers[$i], $numbers[$j]];
                    }
                    $pairsAcc[$key][0]++;
                    if ($date > $pairsAcc[$key][1]) {
                        $pairsAcc[$key][1] = $date;
                    }
                }
            }

            // Accumulate triples
            for ($i = 0; $i < $pickCount; $i++) {
                for ($j = $i + 1; $j < $pickCount; $j++) {
                    for ($k = $j + 1; $k < $pickCount; $k++) {
                        $key = $numbers[$i] . ':' . $numbers[$j] . ':' . $numbers[$k];
                        if (!isset($triplesAcc[$key])) {
                            $triplesAcc[$key] = [0, $date, $numbers[$i], $numbers[$j], $numbers[$k]];
                        }
                        $triplesAcc[$key][0]++;
                        if ($date > $triplesAcc[$key][1]) {
                            $triplesAcc[$key][1] = $date;
                        }
                    }
                }
            }
        }

        // Bulk write inside a transaction so readers never see partial data
        $this->pdo->beginTransaction();
        try {
            $this->pdo->exec("DELETE FROM `{$pairsTable}`");
            $pairsBatches = array_chunk(array_values($pairsAcc), self::BATCH_SIZE);
            foreach ($pairsBatches as $batch) {
                $placeholders = implode(', ', array_fill(0, count($batch), '(?, ?, ?, ?)'));
                $values = [];
                foreach ($batch as $entry) {
                    $values[] = $entry[2]; // n1
                    $values[] = $entry[3]; // n2
                    $values[] = $entry[0]; // count
                    $values[] = $entry[1]; // last_seen
                }
                $this->pdo->prepare(
                    "INSERT INTO `{$pairsTable}` (n1, n2, count, last_seen) VALUES {$placeholders}"
                )->execute($values);
            }

            $this->pdo->exec("DELETE FROM `{$triplesTable}`");
            $triplesBatches = array_chunk(array_values($triplesAcc), self::BATCH_SIZE);
            foreach ($triplesBatches as $batch) {
                $placeholders = implode(', ', array_fill(0, count($batch), '(?, ?, ?, ?, ?)'));
                $values = [];
                foreach ($batch as $entry) {
                    $values[] = $entry[2]; // n1
                    $values[] = $entry[3]; // n2
                    $values[] = $entry[4]; // n3
                    $values[] = $entry[0]; // count
                    $values[] = $entry[1]; // last_seen
                }
                $this->pdo->prepare(
                    "INSERT INTO `{$triplesTable}` (n1, n2, n3, count, last_seen) VALUES {$placeholders}"
                )->execute($values);
            }

            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Upsert pairs and triples for a single newly inserted draw.
     * Call from incremental sync after each successfully inserted draw.
     *
     * @param int[] $numbers Sorted draw numbers
     */
    public function processNewDraw(GameDefinition $game, array $numbers, string $drawDate): void
    {
        sort($numbers);
        $n            = count($numbers);
        $pairsTable   = "{$game->slug}_pairs";
        $triplesTable = "{$game->slug}_triples";
        $driver       = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        $insertIgnore = $driver === 'sqlite' ? 'INSERT OR IGNORE' : 'INSERT IGNORE';

        $pairInsert = $this->pdo->prepare(
            "{$insertIgnore} INTO `{$pairsTable}` (n1, n2, count, last_seen) VALUES (?, ?, 0, ?)"
        );
        $pairUpdate = $this->pdo->prepare(
            "UPDATE `{$pairsTable}` SET
               count     = count + 1,
               last_seen = CASE WHEN last_seen < ? THEN ? ELSE last_seen END
             WHERE n1 = ? AND n2 = ?"
        );

        $tripleInsert = $this->pdo->prepare(
            "{$insertIgnore} INTO `{$triplesTable}` (n1, n2, n3, count, last_seen) VALUES (?, ?, ?, 0, ?)"
        );
        $tripleUpdate = $this->pdo->prepare(
            "UPDATE `{$triplesTable}` SET
               count     = count + 1,
               last_seen = CASE WHEN last_seen < ? THEN ? ELSE last_seen END
             WHERE n1 = ? AND n2 = ? AND n3 = ?"
        );

        for ($i = 0; $i < $n; $i++) {
            for ($j = $i + 1; $j < $n; $j++) {
                $pairInsert->execute([$numbers[$i], $numbers[$j], $drawDate]);
                $pairUpdate->execute([$drawDate, $drawDate, $numbers[$i], $numbers[$j]]);
                for ($k = $j + 1; $k < $n; $k++) {
                    $tripleInsert->execute([$numbers[$i], $numbers[$j], $numbers[$k], $drawDate]);
                    $tripleUpdate->execute([$drawDate, $drawDate, $numbers[$i], $numbers[$j], $numbers[$k]]);
                }
            }
        }
    }

    /**
     * Return top pairs sorted by lift (observed / expected).
     *
     * Expected frequency of a pair = totalDraws × C(pickCount,2) / C(poolSize,2)
     *
     * @return array<array{n1:int, n2:int, count:int, expected:float, lift:float, last_seen:string}>
     */
    public function getTopPairs(GameDefinition $game, int $limit, int $minCount): array
    {
        $pairsTable = "{$game->slug}_pairs";
        $totalDraws = (int)$this->pdo->query(
            "SELECT COUNT(*) FROM `{$game->drawsTable}`"
        )->fetchColumn();

        if ($totalDraws === 0) {
            return [];
        }

        // C(pickCount, 2) / C(poolSize, 2)
        $pick    = $game->pickCount;
        $pool    = $game->poolSize;
        $cPick2  = $pick * ($pick - 1) / 2;
        $cPool2  = $pool * ($pool - 1) / 2;
        $pairExp = $cPool2 > 0 ? ($totalDraws * $cPick2 / $cPool2) : 0;

        // Fetch all pairs above minCount and rank by lift in SQL
        $stmt = $this->pdo->prepare(
            "SELECT n1, n2, count, last_seen
             FROM `{$pairsTable}`
             WHERE count >= ?
             ORDER BY (count / ?) DESC
             LIMIT ?"
        );
        $stmt->execute([$minCount, max($pairExp, 1e-9), $limit]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $result = [];
        foreach ($rows as $row) {
            $count    = (int)$row['count'];
            $expected = round($pairExp, 4);
            $lift     = $expected > 0 ? round($count / $pairExp, 3) : 0.0;
            $result[] = [
                'n1'        => (int)$row['n1'],
                'n2'        => (int)$row['n2'],
                'count'     => $count,
                'expected'  => $expected,
                'lift'      => $lift,
                'last_seen' => (string)$row['last_seen'],
            ];
        }
        return $result;
    }

    /**
     * Return top triples sorted by lift (observed / expected).
     *
     * Expected frequency of a triple = totalDraws × C(pickCount,3) / C(poolSize,3)
     *
     * @return array<array{n1:int, n2:int, n3:int, count:int, expected:float, lift:float, last_seen:string}>
     */
    public function getTopTriples(GameDefinition $game, int $limit, int $minCount): array
    {
        $triplesTable = "{$game->slug}_triples";
        $totalDraws   = (int)$this->pdo->query(
            "SELECT COUNT(*) FROM `{$game->drawsTable}`"
        )->fetchColumn();

        if ($totalDraws === 0) {
            return [];
        }

        $pick    = $game->pickCount;
        $pool    = $game->poolSize;
        $cPick3  = $pick * ($pick - 1) * ($pick - 2) / 6;
        $cPool3  = $pool * ($pool - 1) * ($pool - 2) / 6;
        $triExp  = $cPool3 > 0 ? ($totalDraws * $cPick3 / $cPool3) : 0;

        // Fetch all triples above minCount and rank by lift in SQL
        $stmt = $this->pdo->prepare(
            "SELECT n1, n2, n3, count, last_seen
             FROM `{$triplesTable}`
             WHERE count >= ?
             ORDER BY (count / ?) DESC
             LIMIT ?"
        );
        $stmt->execute([$minCount, max($triExp, 1e-9), $limit]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $result = [];
        foreach ($rows as $row) {
            $count    = (int)$row['count'];
            $expected = round($triExp, 4);
            $lift     = $expected > 0 ? round($count / $triExp, 3) : 0.0;
            $result[] = [
                'n1'        => (int)$row['n1'],
                'n2'        => (int)$row['n2'],
                'n3'        => (int)$row['n3'],
                'count'     => $count,
                'expected'  => $expected,
                'lift'      => $lift,
                'last_seen' => (string)$row['last_seen'],
            ];
        }
        return $result;
    }
}
