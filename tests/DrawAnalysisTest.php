<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/GameDefinitionFactory.php';

final class DrawAnalysisTest extends TestCase
{
    public function testAnalyzeReturnsDrawAnalysis(): void
    {
        $calc      = new MetricCalculator();
        $describer = new ProfileDescriber();
        $lotto     = GameDefinitionFactory::lotto();

        $analysis = $calc->analyze([7, 14, 22, 35, 41, 48], $lotto, $describer);

        $this->assertInstanceOf(DrawAnalysis::class, $analysis);
        $this->assertSame(167, $analysis->sumTotal);
        $this->assertSame(3, $analysis->evenCount);
        $this->assertSame(3, $analysis->lowCount);
        $this->assertSame(0, $analysis->consecutive);
        $this->assertSame(5, $analysis->decadesUsed);
        $this->assertSame(41, $analysis->rangeSpread);
        $this->assertSame(6, $analysis->lastDigitUnique);
        $this->assertSame('M', $analysis->sumBucket);   // 167 -> M
        $this->assertSame('L', $analysis->rangeBucket);  // 41 -> L
        $this->assertSame('3e3o_3l3h_sM_c0_rL', $analysis->profileHash);
    }

    public function testMetricsArray(): void
    {
        $analysis = new DrawAnalysis(
            sumTotal: 100,
            evenCount: 3,
            lowCount: 2,
            consecutive: 1,
            decadesUsed: 4,
            rangeSpread: 30,
            lastDigitUnique: 5,
            profileHash: '3e3o_2l4h_sS_c1_rM',
            sumBucket: 'S',
            rangeBucket: 'M',
            descriptionFull: 'test full',
            descriptionShort: 'test short',
        );

        $arr = $analysis->metricsArray();
        $this->assertSame(100, $arr['sum_total']);
        $this->assertSame(3, $arr['even_count']);
        $this->assertArrayNotHasKey('profile_hash', $arr);

        $full = $analysis->toArray();
        $this->assertArrayHasKey('profile_hash', $full);
        $this->assertSame('3e3o_2l4h_sS_c1_rM', $full['profile_hash']);
    }
}
