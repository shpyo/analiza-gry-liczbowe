<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/GameDefinitionFactory.php';

final class CoOccurrenceRepositoryTest extends TestCase
{
    private PDO $pdo;
    private CoOccurrenceRepository $repo;
    private GameDefinition $game;

    protected function setUp(): void
    {
        // In-memory SQLite database
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        // Create minimal draws table (20 number columns)
        $cols = implode(', ', array_map(fn($i) => "n{$i} INTEGER", range(1, 20)));
        $this->pdo->exec(
            "CREATE TABLE multi_multi_draws (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                draw_date TEXT NOT NULL,
                draw_number INTEGER UNIQUE,
                {$cols}
             )"
        );

        // Create pairs and triples tables
        $this->pdo->exec(
            "CREATE TABLE multi_multi_pairs (
                n1 INTEGER NOT NULL,
                n2 INTEGER NOT NULL,
                count INTEGER NOT NULL DEFAULT 0,
                last_seen TEXT,
                PRIMARY KEY (n1, n2)
             )"
        );
        $this->pdo->exec(
            "CREATE TABLE multi_multi_triples (
                n1 INTEGER NOT NULL,
                n2 INTEGER NOT NULL,
                n3 INTEGER NOT NULL,
                count INTEGER NOT NULL DEFAULT 0,
                last_seen TEXT,
                PRIMARY KEY (n1, n2, n3)
             )"
        );

        $this->repo = new CoOccurrenceRepository($this->pdo);
        $this->game = GameDefinitionFactory::multiMulti();
    }

    // -----------------------------------------------------------------------
    // Helper: insert a draw row into multi_multi_draws
    // -----------------------------------------------------------------------
    private function insertDraw(array $numbers, string $date, int $drawNumber): void
    {
        sort($numbers);
        $cols   = implode(', ', array_map(fn($i) => "n{$i}", range(1, 20)));
        $placeholders = implode(', ', array_fill(0, 20, '?'));
        $this->pdo->prepare(
            "INSERT INTO multi_multi_draws (draw_date, draw_number, {$cols}) VALUES (?, ?, {$placeholders})"
        )->execute(array_merge([$date, $drawNumber], $numbers));
    }

    // -----------------------------------------------------------------------
    // processNewDraw: basic pair and triple insertion
    // -----------------------------------------------------------------------
    public function testProcessNewDrawInsertsExpectedPairCount(): void
    {
        $numbers = range(1, 20);
        $this->repo->processNewDraw($this->game, $numbers, '2026-04-14');

        $pairCount = (int)$this->pdo->query("SELECT COUNT(*) FROM multi_multi_pairs")->fetchColumn();
        // C(20,2) = 190 pairs
        $this->assertSame(190, $pairCount);
    }

    public function testProcessNewDrawInsertsExpectedTripleCount(): void
    {
        $numbers = range(1, 20);
        $this->repo->processNewDraw($this->game, $numbers, '2026-04-14');

        $tripleCount = (int)$this->pdo->query("SELECT COUNT(*) FROM multi_multi_triples")->fetchColumn();
        // C(20,3) = 1140 triples
        $this->assertSame(1140, $tripleCount);
    }

    public function testProcessNewDrawPairOneTwo(): void
    {
        $numbers = range(1, 20);
        $this->repo->processNewDraw($this->game, $numbers, '2026-04-14');

        $row = $this->pdo->query(
            "SELECT count, last_seen FROM multi_multi_pairs WHERE n1 = 1 AND n2 = 2"
        )->fetch();

        $this->assertNotFalse($row);
        $this->assertSame(1, (int)$row['count']);
        $this->assertSame('2026-04-14', $row['last_seen']);
    }

    public function testProcessNewDrawIncrementsCount(): void
    {
        $numbers = range(1, 20);
        $this->repo->processNewDraw($this->game, $numbers, '2026-04-14');
        $this->repo->processNewDraw($this->game, $numbers, '2026-04-15');

        $count = (int)$this->pdo->query(
            "SELECT count FROM multi_multi_pairs WHERE n1 = 1 AND n2 = 2"
        )->fetchColumn();

        $this->assertSame(2, $count);
    }

    public function testProcessNewDrawUpdatesLastSeen(): void
    {
        $numbers = range(1, 20);
        $this->repo->processNewDraw($this->game, $numbers, '2026-01-01');
        $this->repo->processNewDraw($this->game, $numbers, '2026-04-14');

        $lastSeen = $this->pdo->query(
            "SELECT last_seen FROM multi_multi_pairs WHERE n1 = 1 AND n2 = 2"
        )->fetchColumn();

        $this->assertSame('2026-04-14', $lastSeen);
    }

    // -----------------------------------------------------------------------
    // rebuildFromDraws
    // -----------------------------------------------------------------------
    public function testRebuildFromDrawsProducesCorrectCounts(): void
    {
        $numbers = range(1, 20);
        $this->insertDraw($numbers, '2026-04-13', 1);
        $this->insertDraw($numbers, '2026-04-14', 2);

        $this->repo->rebuildFromDraws($this->game);

        $count = (int)$this->pdo->query(
            "SELECT count FROM multi_multi_pairs WHERE n1 = 1 AND n2 = 2"
        )->fetchColumn();

        $this->assertSame(2, $count);
    }

    public function testRebuildFromDrawsIsIdempotent(): void
    {
        $numbers = range(1, 20);
        $this->insertDraw($numbers, '2026-04-14', 1);

        $this->repo->rebuildFromDraws($this->game);
        $this->repo->rebuildFromDraws($this->game); // second call should reset and recount

        $count = (int)$this->pdo->query(
            "SELECT count FROM multi_multi_pairs WHERE n1 = 1 AND n2 = 2"
        )->fetchColumn();

        $this->assertSame(1, $count);
    }

    // -----------------------------------------------------------------------
    // getTopPairs
    // -----------------------------------------------------------------------
    public function testGetTopPairsReturnsSortedByLift(): void
    {
        $numbers = range(1, 20);
        $this->insertDraw($numbers, '2026-04-14', 1);
        $this->repo->rebuildFromDraws($this->game);

        $pairs = $this->repo->getTopPairs($this->game, 10, 1);

        $this->assertNotEmpty($pairs);
        $this->assertLessThanOrEqual(10, count($pairs));

        // Verify required keys are present
        $this->assertArrayHasKey('n1', $pairs[0]);
        $this->assertArrayHasKey('n2', $pairs[0]);
        $this->assertArrayHasKey('count', $pairs[0]);
        $this->assertArrayHasKey('expected', $pairs[0]);
        $this->assertArrayHasKey('lift', $pairs[0]);

        // Sorted descending by lift
        for ($i = 1; $i < count($pairs); $i++) {
            $this->assertGreaterThanOrEqual($pairs[$i]['lift'], $pairs[$i - 1]['lift']);
        }
    }

    public function testGetTopPairsRespectsMinCount(): void
    {
        $numbers = range(1, 20);
        $this->insertDraw($numbers, '2026-04-14', 1);
        $this->repo->rebuildFromDraws($this->game);

        // min_count = 2 should return nothing since we only have 1 draw
        $pairs = $this->repo->getTopPairs($this->game, 100, 2);

        $this->assertEmpty($pairs);
    }

    // -----------------------------------------------------------------------
    // getTopTriples
    // -----------------------------------------------------------------------
    public function testGetTopTriplesReturnsSortedByLift(): void
    {
        $numbers = range(1, 20);
        $this->insertDraw($numbers, '2026-04-14', 1);
        $this->repo->rebuildFromDraws($this->game);

        $triples = $this->repo->getTopTriples($this->game, 10, 1);

        $this->assertNotEmpty($triples);
        $this->assertLessThanOrEqual(10, count($triples));
        $this->assertArrayHasKey('n3', $triples[0]);

        // Sorted descending by lift
        for ($i = 1; $i < count($triples); $i++) {
            $this->assertGreaterThanOrEqual($triples[$i]['lift'], $triples[$i - 1]['lift']);
        }
    }

    public function testGetTopPairsEmptyWhenNoDraws(): void
    {
        $pairs = $this->repo->getTopPairs($this->game, 10, 1);
        $this->assertEmpty($pairs);
    }
}
