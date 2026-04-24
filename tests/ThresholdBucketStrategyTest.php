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

    public function testClassifyMultiMultiSum(): void
    {
        $mmSum = new ThresholdBucketStrategy([
            ['label' => 'XS', 'max' => 675,  'description' => 'bardzo mała (≤675)'],
            ['label' => 'S',  'max' => 745,  'description' => 'mała (676–745)'],
            ['label' => 'M',  'max' => 875,  'description' => 'średnia (746–875)'],
            ['label' => 'L',  'max' => 945,  'description' => 'duża (876–945)'],
            ['label' => 'XL', 'max' => null, 'description' => 'bardzo duża (946+)'],
        ]);

        $this->assertSame('XS', $mmSum->classify(500));
        $this->assertSame('XS', $mmSum->classify(675));   // boundary
        $this->assertSame('S',  $mmSum->classify(676));   // just above
        $this->assertSame('S',  $mmSum->classify(745));
        $this->assertSame('M',  $mmSum->classify(746));
        $this->assertSame('M',  $mmSum->classify(810));   // typical sum
        $this->assertSame('M',  $mmSum->classify(875));
        $this->assertSame('L',  $mmSum->classify(876));
        $this->assertSame('L',  $mmSum->classify(945));
        $this->assertSame('XL', $mmSum->classify(946));
        $this->assertSame('XL', $mmSum->classify(1590));
    }

    public function testClassifyMultiMultiRange(): void
    {
        $mmRange = new ThresholdBucketStrategy([
            ['label' => 'XS', 'max' => 62,   'description' => 'bardzo mały (≤62)'],
            ['label' => 'S',  'max' => 70,   'description' => 'mały (63–70)'],
            ['label' => 'M',  'max' => 75,   'description' => 'średni (71–75)'],
            ['label' => 'L',  'max' => 78,   'description' => 'duży (76–78)'],
            ['label' => 'XL', 'max' => null, 'description' => 'bardzo duży (79+)'],
        ]);

        $this->assertSame('XS', $mmRange->classify(50));
        $this->assertSame('XS', $mmRange->classify(62));
        $this->assertSame('S',  $mmRange->classify(63));
        $this->assertSame('S',  $mmRange->classify(70));
        $this->assertSame('M',  $mmRange->classify(71));
        $this->assertSame('M',  $mmRange->classify(75));
        $this->assertSame('L',  $mmRange->classify(76));
        $this->assertSame('L',  $mmRange->classify(78));
        $this->assertSame('XL', $mmRange->classify(79));
        $this->assertSame('XL', $mmRange->classify(79));  // max possible range in 1-80
    }
}
