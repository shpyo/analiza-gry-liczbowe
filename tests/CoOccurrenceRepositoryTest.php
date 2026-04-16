<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class CoOccurrenceRepositoryTest extends TestCase
{
    private PDO $pdo;
    private CoOccurrenceRepository $repo;

    protected function setUp(): void
    {
        // In-memory SQLite for isolated tests
        $this->pdo = new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        $this->pdo->exec("
            CREATE TABLE multi_multi_pairs (
                n1 INTEGER NOT NULL,
                n2 INTEGER NOT NULL,
                count INTEGER NOT NULL DEFAULT 0,
                last_seen TEXT,
                PRIMARY KEY (n1, n2)
            )
        ");

        $this->pdo->exec("
            CREATE TABLE multi_multi_triples (
                n1 INTEGER NOT NULL,
                n2 INTEGER NOT NULL,
                n3 INTEGER NOT NULL,
                count INTEGER NOT NULL DEFAULT 0,
                last_seen TEXT,
                PRIMARY KEY (n1, n2, n3)
            )
        ");

        $this->pdo->exec("
            CREATE TABLE multi_multi_cooccurrence_log (
                draw_number INTEGER PRIMARY KEY
            )
        ");

        $this->repo = new CoOccurrenceRepository($this->pdo);
    }

    /** @return int[] Numbers 1..20 */
    private function nums(): array
    {
        return range(1, 20);
    }

    public function testUpsertCreatesPair(): void
    {
        $this->repo->upsertCoOccurrences('multi_multi', 1, $this->nums(), '2026-04-14');

        $count = (int)$this->pdo
            ->query("SELECT count FROM multi_multi_pairs WHERE n1=1 AND n2=2")
            ->fetchColumn();

        $this->assertSame(1, $count);
    }

    public function testUpsertCreatesAllPairs(): void
    {
        $this->repo->upsertCoOccurrences('multi_multi', 1, $this->nums(), '2026-04-14');

        $total = (int)$this->pdo
            ->query("SELECT COUNT(*) FROM multi_multi_pairs")
            ->fetchColumn();

        // C(20,2) = 190
        $this->assertSame(190, $total);
    }

    public function testUpsertCreatesAllTriples(): void
    {
        $this->repo->upsertCoOccurrences('multi_multi', 1, $this->nums(), '2026-04-14');

        $total = (int)$this->pdo
            ->query("SELECT COUNT(*) FROM multi_multi_triples")
            ->fetchColumn();

        // C(20,3) = 1140
        $this->assertSame(1140, $total);
    }

    public function testUpsertIdempotentOnSameDrawNumber(): void
    {
        $this->repo->upsertCoOccurrences('multi_multi', 1, $this->nums(), '2026-04-14');
        $this->repo->upsertCoOccurrences('multi_multi', 1, $this->nums(), '2026-04-14'); // second call, same draw

        $count = (int)$this->pdo
            ->query("SELECT count FROM multi_multi_pairs WHERE n1=1 AND n2=2")
            ->fetchColumn();

        $this->assertSame(1, $count); // count must not increase
    }

    public function testUpsertIncrementsOnNewDrawNumber(): void
    {
        $this->repo->upsertCoOccurrences('multi_multi', 1, $this->nums(), '2026-04-14');
        $this->repo->upsertCoOccurrences('multi_multi', 2, $this->nums(), '2026-04-21');

        $count = (int)$this->pdo
            ->query("SELECT count FROM multi_multi_pairs WHERE n1=1 AND n2=2")
            ->fetchColumn();

        $this->assertSame(2, $count);
    }

    public function testGetTopPairsReturnsSortedByLift(): void
    {
        $this->repo->upsertCoOccurrences('multi_multi', 1, $this->nums(), '2026-04-14');

        $pairs = $this->repo->getTopPairs('multi_multi', 1, 20, 80, 10, 1);

        $this->assertLessThanOrEqual(10, count($pairs));
        $this->assertNotEmpty($pairs);

        // Verify sorted descending by lift
        for ($i = 1; $i < count($pairs); $i++) {
            $this->assertGreaterThanOrEqual($pairs[$i]['lift'], $pairs[$i - 1]['lift']);
        }

        // Each pair has required fields
        $this->assertArrayHasKey('n1', $pairs[0]);
        $this->assertArrayHasKey('n2', $pairs[0]);
        $this->assertArrayHasKey('count', $pairs[0]);
        $this->assertArrayHasKey('expected', $pairs[0]);
        $this->assertArrayHasKey('lift', $pairs[0]);
    }

    public function testGetTopPairsMinCountFilter(): void
    {
        $this->repo->upsertCoOccurrences('multi_multi', 1, $this->nums(), '2026-04-14');

        // With minCount=2, no pairs should be returned after only 1 draw
        $pairs = $this->repo->getTopPairs('multi_multi', 1, 20, 80, 10, 2);

        $this->assertEmpty($pairs);
    }

    public function testGetTopTriplesReturnsSortedByLift(): void
    {
        $this->repo->upsertCoOccurrences('multi_multi', 1, $this->nums(), '2026-04-14');

        $triples = $this->repo->getTopTriples('multi_multi', 1, 20, 80, 10, 1);

        $this->assertLessThanOrEqual(10, count($triples));
        $this->assertNotEmpty($triples);

        // Each triple has required fields
        $this->assertArrayHasKey('n1', $triples[0]);
        $this->assertArrayHasKey('n2', $triples[0]);
        $this->assertArrayHasKey('n3', $triples[0]);
        $this->assertArrayHasKey('count', $triples[0]);
        $this->assertArrayHasKey('expected', $triples[0]);
        $this->assertArrayHasKey('lift', $triples[0]);
    }

    public function testLiftCalculationCorrect(): void
    {
        // After 1 draw with 20 numbers: every pair has count=1
        // Expected = 1 * C(20,2) / C(80,2) = 190 / 3160
        $this->repo->upsertCoOccurrences('multi_multi', 1, $this->nums(), '2026-04-14');

        $pairs       = $this->repo->getTopPairs('multi_multi', 1, 20, 80, 1, 1);
        $expectedRaw = 1 * 190 / 3160;
        $liftRaw     = 1 / $expectedRaw;

        $this->assertEqualsWithDelta($expectedRaw, $pairs[0]['expected'], 0.001);
        $this->assertEqualsWithDelta($liftRaw,     $pairs[0]['lift'],     0.01);
    }
}
