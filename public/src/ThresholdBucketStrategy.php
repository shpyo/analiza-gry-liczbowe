<?php
declare(strict_types=1);

final class ThresholdBucketStrategy implements BucketStrategy
{
    /**
     * @param array<array{label: string, max: int|null, description: string}> $tiers
     *   Ordered ascending by max. Last tier has max=null (unbounded).
     *   Example: [
     *     ['label' => 'XS', 'max' => 49,   'description' => 'bardzo mala (<=49)'],
     *     ['label' => 'S',  'max' => 79,   'description' => 'mala (50-79)'],
     *     ['label' => 'XL', 'max' => null, 'description' => 'bardzo duza (160+)'],
     *   ]
     */
    public function __construct(private readonly array $tiers)
    {
    }

    public function classify(int $value): string
    {
        foreach ($this->tiers as $tier) {
            if ($tier['max'] === null || $value <= $tier['max']) {
                return $tier['label'];
            }
        }
        // Fallback to last tier (should not happen if tiers are well-formed)
        return $this->tiers[array_key_last($this->tiers)]['label'];
    }

    public function boundaries(): array
    {
        return $this->tiers;
    }

    public function describe(string $code): string
    {
        foreach ($this->tiers as $tier) {
            if ($tier['label'] === $code) {
                return $tier['description'];
            }
        }
        return $code;
    }
}
