<?php
declare(strict_types=1);

final class GameDefinition
{
    public function __construct(
        public readonly string         $slug,
        public readonly string         $name,
        public readonly int            $pickCount,
        public readonly int            $poolSize,
        public readonly int            $lowThreshold,
        public readonly bool           $hasBonus,
        public readonly ?string        $syncUrl,
        public readonly string         $drawsTable,
        public readonly string         $profileTable,
        public readonly BucketStrategy $sumBuckets,
        public readonly BucketStrategy $rangeBuckets,
        public readonly LineParser     $lineParser,
        // Optional: min/max numbers in a player's bet (null = same as pickCount)
        public readonly ?int           $betPickMin = null,
        public readonly ?int           $betPickMax = null,
        // Whether this game has co-occurrence (pairs/triples) tables
        public readonly bool           $coOccurrence = false,
    ) {
    }

    /** Minimum numbers in a player's bet (equals pickCount for standard games). */
    public function betPickMin(): int
    {
        return $this->betPickMin ?? $this->pickCount;
    }

    /** Maximum numbers in a player's bet (equals pickCount for standard games). */
    public function betPickMax(): int
    {
        return $this->betPickMax ?? $this->pickCount;
    }

    /** @return string[] e.g. ['n1','n2','n3','n4','n5','n6'] */
    public function numberColumns(): array
    {
        $cols = [];
        for ($i = 1; $i <= $this->pickCount; $i++) {
            $cols[] = "n{$i}";
        }
        return $cols;
    }

    /** @return string e.g. '`n1`, `n2`, `n3`, `n4`, `n5`, `n6`' */
    public function numberColumnsSql(): string
    {
        return implode(', ', array_map(fn(string $c) => "`{$c}`", $this->numberColumns()));
    }

    /**
     * Returns UNION ALL SQL for unpivoting number columns from a CTE alias.
     * e.g. "SELECT `n1` AS num FROM cte UNION ALL SELECT `n2` AS num FROM cte ..."
     */
    public function unpivotNumbersSql(string $cteAlias = 'last500'): string
    {
        $parts = [];
        foreach ($this->numberColumns() as $col) {
            $parts[] = "SELECT `{$col}` AS num FROM {$cteAlias}";
        }
        return implode(' UNION ALL ', $parts);
    }
}
