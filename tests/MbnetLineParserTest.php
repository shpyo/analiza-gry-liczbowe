<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class MbnetLineParserTest extends TestCase
{
    public function testParseLottoLine(): void
    {
        $parser = new MbnetLineParser(6, false);

        $result = $parser->parse('1234. 15.03.2024 3,7,14,22,35,48');

        $this->assertNotNull($result);
        $this->assertSame(1234, $result['draw_number']);
        $this->assertSame('2024-03-15', $result['draw_date']);
        $this->assertSame([3, 7, 14, 22, 35, 48], $result['numbers']);
        $this->assertNull($result['plus_ball']);
    }

    public function testParseLottoPlusWithBonus(): void
    {
        $parser = new MbnetLineParser(6, true);

        $result = $parser->parse('5678. 20.12.2023 1,5,12,23,34,45,7');

        $this->assertNotNull($result);
        $this->assertSame(5678, $result['draw_number']);
        $this->assertSame('2023-12-20', $result['draw_date']);
        $this->assertSame([1, 5, 12, 23, 34, 45], $result['numbers']);
        $this->assertSame(7, $result['plus_ball']);
    }

    public function testParseMiniLottoLine(): void
    {
        $parser = new MbnetLineParser(5, false);

        $result = $parser->parse('100. 01.01.2024 5,10,20,30,42');

        $this->assertNotNull($result);
        $this->assertSame(100, $result['draw_number']);
        $this->assertSame([5, 10, 20, 30, 42], $result['numbers']);
    }

    public function testParseInvalidLine(): void
    {
        $parser = new MbnetLineParser(6, false);

        $this->assertNull($parser->parse(''));
        $this->assertNull($parser->parse('not a valid line'));
        $this->assertNull($parser->parse('abc. 01.01.2024 1,2,3,4,5,6'));
    }

    public function testParseWrongNumberCount(): void
    {
        $parser = new MbnetLineParser(6, false);

        // Only 5 numbers for a 6-pick game
        $this->assertNull($parser->parse('1. 01.01.2024 1,2,3,4,5'));
    }

    public function testParseMultiMultiLine(): void
    {
        $parser = new MbnetLineParser(20, false);

        $line   = '1. 14.04.2026 1,5,8,12,15,19,23,27,31,35,40,44,48,52,56,60,64,68,72,78';
        $result = $parser->parse($line);

        $this->assertNotNull($result);
        $this->assertSame(1, $result['draw_number']);
        $this->assertSame('2026-04-14', $result['draw_date']);
        $this->assertCount(20, $result['numbers']);
        $this->assertSame([1, 5, 8, 12, 15, 19, 23, 27, 31, 35, 40, 44, 48, 52, 56, 60, 64, 68, 72, 78], $result['numbers']);
        $this->assertNull($result['plus_ball']);
    }

    public function testParseMultiMultiLineUnsorted(): void
    {
        $parser = new MbnetLineParser(20, false);

        // Numbers given unsorted — parser should sort them
        $line   = '42. 01.01.2025 78,1,64,5,72,8,68,12,60,15,56,19,52,23,48,27,44,31,40,35';
        $result = $parser->parse($line);

        $this->assertNotNull($result);
        $this->assertSame(42, $result['draw_number']);
        $this->assertSame([1, 5, 8, 12, 15, 19, 23, 27, 31, 35, 40, 44, 48, 52, 56, 60, 64, 68, 72, 78], $result['numbers']);
    }

    public function testParseMultiMultiWrongCount(): void
    {
        $parser = new MbnetLineParser(20, false);

        // Only 19 numbers — should return null
        $this->assertNull($parser->parse('1. 01.01.2024 1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19'));
    }

    public function testParseNumbersSorted(): void
    {
        $parser = new MbnetLineParser(6, false);

        $result = $parser->parse('1. 01.01.2024 48,3,22,7,35,14');

        $this->assertNotNull($result);
        $this->assertSame([3, 7, 14, 22, 35, 48], $result['numbers']); // sorted
    }
}
