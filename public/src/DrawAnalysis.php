<?php
declare(strict_types=1);

final class DrawAnalysis
{
    public function __construct(
        public readonly int    $sumTotal,
        public readonly int    $evenCount,
        public readonly int    $lowCount,
        public readonly int    $consecutive,
        public readonly int    $decadesUsed,
        public readonly int    $rangeSpread,
        public readonly int    $lastDigitUnique,
        public readonly string $profileHash,
        public readonly string $sumBucket,
        public readonly string $rangeBucket,
        public readonly string $descriptionFull,
        public readonly string $descriptionShort,
    ) {
    }

    /** Metrics as associative array (compatible with DB column names). */
    public function metricsArray(): array
    {
        return [
            'sum_total'         => $this->sumTotal,
            'even_count'        => $this->evenCount,
            'low_count'         => $this->lowCount,
            'consecutive'       => $this->consecutive,
            'decades_used'      => $this->decadesUsed,
            'range_spread'      => $this->rangeSpread,
            'last_digit_unique' => $this->lastDigitUnique,
        ];
    }

    /** Full export including hash for DB insert compatibility. */
    public function toArray(): array
    {
        return array_merge($this->metricsArray(), [
            'profile_hash' => $this->profileHash,
        ]);
    }
}
