<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ThresholdBucketStrategyTest extends TestCase
{
    private ThresholdBucketStrategy $lottoSum;
    private ThresholdBucketStrategy $miniSum;

    protected function setUp(): void
    {
        $this->lottoSum = new ThresholdBucketStrategy([
            ['label' => 'XS', 'max' => 79,   'description' => 'bardzo mała (21–79)'],
            ['label' => 'S',  'max' => 109,  'description' => 'mała (80–109)'],
            ['label' => 'M',  'max' => 170,  'description' => 'średnia (110–170)'],
            ['label' => 'L',  'max' => 200,  'description' => 'duża (171–200)'],
            ['label' => 'XL', 'max' => null, 'description' => 'bardzo duża (201–279)'],
        ]);

        $this->miniSum = new ThresholdBucketStrategy([
            ['label' => 'XS', 'max' => 49,   'description' => 'bardzo mała (≤49)'],
            ['label' => 'S',  'max' => 79,   'description' => 'mała (50–79)'],
            ['label' => 'M',  'max' => 120,  'description' => 'średnia (80–120)'],
            ['label' => 'L',  'max' => 159,  'description' => 'duża (121–159)'],
            ['label' => 'XL', 'max' => null, 'description' => 'bardzo duża (160+)'],
        ]);
    }

    public function testClassifyLottoSum(): void
    {
        $this->assertSame('XS', $this->lottoSum->classify(50));
        $this->assertSame('XS', $this->lottoSum->classify(79));  // boundary
        $this->assertSame('S', $this->lottoSum->classify(80));   // just above
        $this->assertSame('S', $this->lottoSum->classify(109));
        $this->assertSame('M', $this->lottoSum->classify(110));
        $this->assertSame('M', $this->lottoSum->classify(170));
        $this->assertSame('L', $this->lottoSum->classify(171));
        $this->assertSame('L', $this->lottoSum->classify(200));
        $this->assertSame('XL', $this->lottoSum->classify(201));
        $this->assertSame('XL', $this->lottoSum->classify(279));
    }

    public function testClassifyMiniLottoSum(): void
    {
        $this->assertSame('XS', $this->miniSum->classify(30));
        $this->assertSame('XS', $this->miniSum->classify(49));
        $this->assertSame('S', $this->miniSum->classify(50));
        $this->assertSame('M', $this->miniSum->classify(100));
        $this->assertSame('L', $this->miniSum->classify(150));
        $this->assertSame('XL', $this->miniSum->classify(160));
    }

    public function testDescribe(): void
    {
        $this->assertSame('bardzo mała (21–79)', $this->lottoSum->describe('XS'));
        $this->assertSame('średnia (110–170)', $this->lottoSum->describe('M'));
        $this->assertSame('bardzo duża (201–279)', $this->lottoSum->describe('XL'));
    }

    public function testDescribeUnknownCode(): void
    {
        $this->assertSame('ZZ', $this->lottoSum->describe('ZZ'));
    }

    public function testBoundaries(): void
    {
        $boundaries = $this->lottoSum->boundaries();
        $this->assertCount(5, $boundaries);
        $this->assertSame('XS', $boundaries[0]['label']);
        $this->assertNull($boundaries[4]['max']);
    }
}
