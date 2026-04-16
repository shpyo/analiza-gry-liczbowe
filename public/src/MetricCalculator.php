<?php
declare(strict_types=1);

final class MetricCalculator
{
    /**
     * Compute structural metrics for a set of drawn numbers.
     * Pure function: no DB, no side effects.
     *
     * @param int[] $numbers Raw drawn numbers (any order, will be sorted)
     * @return array{
     *   sum_total: int,
     *   even_count: int,
     *   low_count: int,
     *   consecutive: int,
     *   decades_used: int,
     *   range_spread: int,
     *   last_digit_unique: int
     * }
     */
    public function computeMetrics(array $numbers, GameDefinition $game): array
    {
        sort($numbers);

        $sumTotal    = array_sum($numbers);
        $evenCount   = 0;
        $lowCount    = 0;
        $consecutive = 0;
        $decades     = [];
        $lastDigits  = [];

        for ($i = 0, $count = count($numbers); $i < $count; $i++) {
            $n = $numbers[$i];
            if ($n % 2 === 0) {
                $evenCount++;
            }
            if ($n <= $game->lowThreshold) {
                $lowCount++;
            }
            $decades[intdiv($n - 1, 10)] = true;
            $lastDigits[$n % 10] = true;
            if ($i > 0 && $numbers[$i] === $numbers[$i - 1] + 1) {
                $consecutive++;
            }
        }

        return [
            'sum_total'         => $sumTotal,
            'even_count'        => $evenCount,
            'low_count'         => $lowCount,
            'consecutive'       => $consecutive,
            'decades_used'      => count($decades),
            'range_spread'      => $numbers[count($numbers) - 1] - $numbers[0],
            'last_digit_unique' => count($lastDigits),
        ];
    }

    /**
     * Full analysis: metrics + profile hash + buckets + descriptions in one call.
     */
    public function analyze(array $numbers, GameDefinition $game, ProfileDescriber $describer): DrawAnalysis
    {
        $metrics     = $this->computeMetrics($numbers, $game);
        $hash        = $describer->computeHash($metrics, $game);
        $sumBucket   = $game->sumBuckets->classify($metrics['sum_total']);
        $rangeBucket = $game->rangeBuckets->classify($metrics['range_spread']);

        return new DrawAnalysis(
            sumTotal:         $metrics['sum_total'],
            evenCount:        $metrics['even_count'],
            lowCount:         $metrics['low_count'],
            consecutive:      $metrics['consecutive'],
            decadesUsed:      $metrics['decades_used'],
            rangeSpread:      $metrics['range_spread'],
            lastDigitUnique:  $metrics['last_digit_unique'],
            profileHash:      $hash,
            sumBucket:        $sumBucket,
            rangeBucket:      $rangeBucket,
            descriptionFull:  $describer->describe($hash, $game),
            descriptionShort: $describer->describeShort($hash),
        );
    }
}
