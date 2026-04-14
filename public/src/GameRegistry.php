<?php
declare(strict_types=1);

final class GameRegistry
{
    /** @var array<string, GameDefinition> */
    private array $cache = [];

    /** @var array<string, BucketStrategy> */
    private array $sumStrategies = [];

    /** @var array<string, BucketStrategy> */
    private array $rangeStrategies = [];

    /** @var array<string, LineParser> */
    private array $parsers = [];

    public function __construct(private readonly PDO $pdo)
    {
        $this->registerDefaults();
    }

    /** Get a fully hydrated GameDefinition (cached per slug). */
    public function get(string $slug): GameDefinition
    {
        if (isset($this->cache[$slug])) {
            return $this->cache[$slug];
        }

        $stmt = $this->pdo->prepare('SELECT * FROM games WHERE slug = ?');
        $stmt->execute([$slug]);
        $row = $stmt->fetch();
        if (!$row) {
            throw new RuntimeException("Unknown game slug: {$slug}");
        }

        $pickCount = (int)$row['pick_count'];
        $hasBonus  = (bool)($row['has_bonus'] ?? false);

        $sumBuckets   = $this->sumStrategies[$slug]   ?? $this->sumStrategies['default'];
        $rangeBuckets = $this->rangeStrategies[$slug]  ?? $this->rangeStrategies['default'];
        $lineParser   = $this->parsers[$slug]          ?? new MbnetLineParser($pickCount, $hasBonus);

        $def = new GameDefinition(
            slug:          $slug,
            name:          (string)$row['name'],
            pickCount:     $pickCount,
            poolSize:      (int)$row['pool_size'],
            lowThreshold:  (int)($row['low_threshold'] ?? ($pickCount === 5 ? 21 : 24)),
            hasBonus:      $hasBonus,
            syncUrl:       $row['sync_url'] ?? null,
            drawsTable:    "{$slug}_draws",
            profileTable:  "{$slug}_draw_profiles",
            sumBuckets:    $sumBuckets,
            rangeBuckets:  $rangeBuckets,
            lineParser:    $lineParser,
        );

        $this->cache[$slug] = $def;
        return $def;
    }

    /** Get all active game slugs. */
    public function allSlugs(): array
    {
        $rows = $this->pdo->query("SELECT slug FROM games ORDER BY id")->fetchAll(PDO::FETCH_COLUMN);
        return $rows ?: [];
    }

    public function registerSumStrategy(string $slug, BucketStrategy $strategy): void
    {
        $this->sumStrategies[$slug] = $strategy;
        unset($this->cache[$slug]);
    }

    public function registerRangeStrategy(string $slug, BucketStrategy $strategy): void
    {
        $this->rangeStrategies[$slug] = $strategy;
        unset($this->cache[$slug]);
    }

    public function registerParser(string $slug, LineParser $parser): void
    {
        $this->parsers[$slug] = $parser;
        unset($this->cache[$slug]);
    }

    private function registerDefaults(): void
    {
        // -- Sum bucket strategies --
        $this->sumStrategies['default'] = new ThresholdBucketStrategy([
            ['label' => 'XS', 'max' => 79,   'description' => 'bardzo mała (21–79)'],
            ['label' => 'S',  'max' => 109,  'description' => 'mała (80–109)'],
            ['label' => 'M',  'max' => 170,  'description' => 'średnia (110–170)'],
            ['label' => 'L',  'max' => 200,  'description' => 'duża (171–200)'],
            ['label' => 'XL', 'max' => null, 'description' => 'bardzo duża (201–279)'],
        ]);

        $this->sumStrategies['mini_lotto'] = new ThresholdBucketStrategy([
            ['label' => 'XS', 'max' => 49,   'description' => 'bardzo mała (≤49)'],
            ['label' => 'S',  'max' => 79,   'description' => 'mała (50–79)'],
            ['label' => 'M',  'max' => 120,  'description' => 'średnia (80–120)'],
            ['label' => 'L',  'max' => 159,  'description' => 'duża (121–159)'],
            ['label' => 'XL', 'max' => null, 'description' => 'bardzo duża (160+)'],
        ]);

        // -- Range bucket strategies --
        $this->rangeStrategies['default'] = new ThresholdBucketStrategy([
            ['label' => 'XS', 'max' => 19,   'description' => 'bardzo mały (0–19)'],
            ['label' => 'S',  'max' => 29,   'description' => 'mały (20–29)'],
            ['label' => 'M',  'max' => 39,   'description' => 'średni (30–39)'],
            ['label' => 'L',  'max' => 44,   'description' => 'duży (40–44)'],
            ['label' => 'XL', 'max' => null, 'description' => 'bardzo duży (45–48)'],
        ]);

        $this->rangeStrategies['mini_lotto'] = new ThresholdBucketStrategy([
            ['label' => 'XS', 'max' => 12,   'description' => 'bardzo mały (≤12)'],
            ['label' => 'S',  'max' => 22,   'description' => 'mały (13–22)'],
            ['label' => 'M',  'max' => 31,   'description' => 'średni (23–31)'],
            ['label' => 'L',  'max' => 37,   'description' => 'duży (32–37)'],
            ['label' => 'XL', 'max' => null, 'description' => 'bardzo duży (38+)'],
        ]);
    }
}
