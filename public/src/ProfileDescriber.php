<?php
declare(strict_types=1);

final class ProfileDescriber
{
    /**
     * Compute the structural profile hash from metrics.
     * Format: "{even}e{odd}o_{low}l{high}h_s{sumBucket}_c{consecutive}_r{rangeBucket}"
     */
    public function computeHash(array $metrics, GameDefinition $game): string
    {
        $even = (int)$metrics['even_count'];
        $odd  = $game->pickCount - $even;
        $low  = (int)$metrics['low_count'];
        $high = $game->pickCount - $low;
        $sB   = $game->sumBuckets->classify((int)$metrics['sum_total']);
        $rB   = $game->rangeBuckets->classify((int)$metrics['range_spread']);
        $c    = (int)$metrics['consecutive'];

        return "{$even}e{$odd}o_{$low}l{$high}h_s{$sB}_c{$c}_r{$rB}";
    }

    /**
     * Parse a profile hash into its components.
     *
     * @return array{sum_bucket: string, range_bucket: string}
     */
    public function parseHash(string $hash): array
    {
        $parts = explode('_', $hash);
        $sumBucket   = isset($parts[2]) ? substr($parts[2], 1) : 'M';
        $rangeBucket = isset($parts[4]) ? substr($parts[4], 1) : 'M';
        return [
            'sum_bucket'   => $sumBucket,
            'range_bucket' => $rangeBucket,
        ];
    }

    /**
     * Full human-readable description of a profile hash.
     * Example: "3 parzyste . 3 niskie . suma srednia (110-170) . 1 para sasiadow . rozstep duzy (40-44)"
     */
    public function describe(string $hash, GameDefinition $game): string
    {
        $parts = explode('_', $hash);
        if (count($parts) < 5) {
            return $hash;
        }

        if (!preg_match('/^(\d+)e(\d+)o$/', $parts[0], $m0)) {
            return $hash;
        }
        $even = (int)$m0[1];

        if (!preg_match('/^(\d+)l(\d+)h$/', $parts[1], $m1)) {
            return $hash;
        }
        $low = (int)$m1[1];

        $sumBucket = substr($parts[2] ?? 's?', 1);

        if (!preg_match('/^c(\d+)$/', $parts[3] ?? '', $m3)) {
            return $hash;
        }
        $consecutive = (int)$m3[1];

        $rangeBucket = substr($parts[4] ?? 'r?', 1);

        $evenLabel = $even === 1 ? '1 parzysta' : "{$even} parzyste";
        $lowLabel  = $low  === 1 ? '1 niska'    : "{$low} niskie";

        if ($consecutive === 0) {
            $consLabel = 'brak par sąsiadów';
        } elseif ($consecutive === 1) {
            $consLabel = '1 para sąsiadów';
        } else {
            $consLabel = "{$consecutive} pary sąsiadów";
        }

        $sumLabel   = 'suma ' . $game->sumBuckets->describe($sumBucket);
        $rangeLabel = 'rozstęp ' . $game->rangeBuckets->describe($rangeBucket);

        return implode(' · ', [$evenLabel, $lowLabel, $sumLabel, $consLabel, $rangeLabel]);
    }

    /**
     * Short description for compact UI (list selects, table cells).
     * Example: "3p/3n . sM . c1 . rL"
     */
    public function describeShort(string $hash): string
    {
        $parts = explode('_', $hash);
        if (count($parts) < 5) {
            return $hash;
        }

        if (!preg_match('/^(\d+)e(\d+)o$/', $parts[0], $m0)) {
            return $hash;
        }

        $even     = $m0[1];
        $odd      = $m0[2];
        $consec   = $parts[3] ?? 'c?';
        $sumCode  = $parts[2] ?? 's?';
        $rangeCode = $parts[4] ?? 'r?';

        return "{$even}p/{$odd}n · {$sumCode} · {$consec} · {$rangeCode}";
    }
}
