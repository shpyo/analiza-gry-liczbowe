<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/GameDefinitionFactory.php';

final class ProfileDescriberTest extends TestCase
{
    private ProfileDescriber $describer;
    private GameDefinition $lotto;

    protected function setUp(): void
    {
        $this->describer = new ProfileDescriber();
        $this->lotto     = GameDefinitionFactory::lotto();
    }

    public function testComputeHashRoundTrip(): void
    {
        $metrics = [
            'sum_total'         => 167,
            'even_count'        => 3,
            'low_count'         => 3,
            'consecutive'       => 0,
            'decades_used'      => 5,
            'range_spread'      => 41,
            'last_digit_unique' => 6,
        ];

        $hash = $this->describer->computeHash($metrics, $this->lotto);

        // 3 even, 3 odd, 3 low, 3 high, sum=167→M, consecutive=0, range=41→L
        $this->assertSame('3e3o_3l3h_sM_c0_rL', $hash);

        // Parse back
        $parsed = $this->describer->parseHash($hash);
        $this->assertSame('M', $parsed['sum_bucket']);
        $this->assertSame('L', $parsed['range_bucket']);
    }

    public function testDescribeFull(): void
    {
        $desc = $this->describer->describe('3e3o_3l3h_sM_c1_rL', $this->lotto);

        $this->assertStringContainsString('3 parzyste', $desc);
        $this->assertStringContainsString('3 niskie', $desc);
        $this->assertStringContainsString('suma', $desc);
        $this->assertStringContainsString('1 para sąsiadów', $desc);
        $this->assertStringContainsString('rozstęp', $desc);
    }

    public function testDescribeShort(): void
    {
        $desc = $this->describer->describeShort('3e3o_3l3h_sM_c1_rL');

        $this->assertSame('3p/3n · sM · c1 · rL', $desc);
    }

    public function testDescribeGracefulDegradation(): void
    {
        // Malformed hash returns as-is
        $this->assertSame('garbage', $this->describer->describeShort('garbage'));
        $this->assertSame('garbage', $this->describer->describe('garbage', $this->lotto));
    }

    public function testDescribeZeroConsecutive(): void
    {
        $desc = $this->describer->describe('2e4o_2l4h_sS_c0_rM', $this->lotto);

        $this->assertStringContainsString('2 parzyste', $desc);
        $this->assertStringContainsString('brak par sąsiadów', $desc);
    }

    public function testHashAndDescribeMultiMulti(): void
    {
        $multiMulti = GameDefinitionFactory::multiMulti();
        // Typical multi_multi metrics: 10 even, 10 low, sum=810 (M), 7 consecutive, range=75 (M)
        $metrics = [
            'sum_total'         => 810,
            'even_count'        => 10,
            'low_count'         => 10,
            'consecutive'       => 7,
            'decades_used'      => 8,
            'range_spread'      => 75,
            'last_digit_unique' => 10,
        ];

        $hash = $this->describer->computeHash($metrics, $multiMulti);

        // 10e10o_10l10h_sM_c7_rM
        $this->assertSame('10e10o_10l10h_sM_c7_rM', $hash);

        $desc = $this->describer->describe($hash, $multiMulti);
        $this->assertStringContainsString('10 parzyste', $desc);
        $this->assertStringContainsString('10 niskie', $desc);
        $this->assertStringContainsString('suma', $desc);
        $this->assertStringContainsString('7 pary sąsiadów', $desc);
    }
}
