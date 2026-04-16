<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/GameDefinitionFactory.php';

final class MetricCalculatorTest extends TestCase
{
    private MetricCalculator $calc;
    private GameDefinition $lotto;
    private GameDefinition $mini;

    protected function setUp(): void
    {
        $this->calc  = new MetricCalculator();
        $this->lotto = GameDefinitionFactory::lotto();
        $this->mini  = GameDefinitionFactory::miniLotto();
    }

    public function testComputeMetricsLottoStandard(): void
    {
        $metrics = $this->calc->computeMetrics([7, 14, 22, 35, 41, 48], $this->lotto);

        $this->assertSame(167, $metrics['sum_total']);
        $this->assertSame(3, $metrics['even_count']);       // 14, 22, 48
        $this->assertSame(3, $metrics['low_count']);         // 7, 14, 22 (<=24)
        $this->assertSame(0, $metrics['consecutive']);
        $this->assertSame(5, $metrics['decades_used']);      // 0s, 10s, 20s, 30s, 40s
        $this->assertSame(41, $metrics['range_spread']);     // 48 - 7
        $this->assertSame(6, $metrics['last_digit_unique']); // 7, 4, 2, 5, 1, 8
    }

    public function testComputeMetricsAllEven(): void
    {
        $metrics = $this->calc->computeMetrics([2, 4, 6, 8, 10, 12], $this->lotto);

        $this->assertSame(42, $metrics['sum_total']);
        $this->assertSame(6, $metrics['even_count']);
        $this->assertSame(6, $metrics['low_count']); // all <= 24
    }

    public function testComputeMetricsConsecutive(): void
    {
        $metrics = $this->calc->computeMetrics([10, 11, 12, 13, 14, 15], $this->lotto);

        $this->assertSame(5, $metrics['consecutive']); // 5 pairs: 10-11, 11-12, 12-13, 13-14, 14-15
    }

    public function testComputeMetricsMiniLotto(): void
    {
        $metrics = $this->calc->computeMetrics([5, 15, 25, 35, 40], $this->mini);

        $this->assertSame(120, $metrics['sum_total']);
        $this->assertSame(1, $metrics['even_count']);        // only 40 is even
        $this->assertSame(2, $metrics['low_count']);          // 5, 15 (<=21)
    }

    public function testComputeMetricsUnsortedInput(): void
    {
        // Numbers given in unsorted order — should produce same result
        $metrics = $this->calc->computeMetrics([48, 7, 35, 14, 41, 22], $this->lotto);

        $this->assertSame(167, $metrics['sum_total']);
        $this->assertSame(41, $metrics['range_spread']); // 48 - 7
    }

    public function testComputeMetricsSingleDecade(): void
    {
        $metrics = $this->calc->computeMetrics([1, 2, 3, 4, 5, 6], $this->lotto);

        $this->assertSame(1, $metrics['decades_used']); // all in 0s decade
        $this->assertSame(5, $metrics['consecutive']);   // 5 consecutive pairs
    }
}
