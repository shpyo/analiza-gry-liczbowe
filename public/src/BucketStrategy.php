<?php
declare(strict_types=1);

interface BucketStrategy
{
    /** Classify a numeric value into a bucket code (e.g. 'XS','S','M','L','XL'). */
    public function classify(int $value): string;

    /**
     * Return all bucket definitions as ordered array.
     * Each element: ['label' => string, 'max' => int|null]
     * Last tier has max=null (unbounded).
     */
    public function boundaries(): array;

    /** Human-readable label for a bucket code (e.g. 'bardzo mala (<=49)'). */
    public function describe(string $code): string;
}
