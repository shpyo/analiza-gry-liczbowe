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

    public function testParseNumbersSorted(): void
    {
        $parser = new MbnetLineParser(6, false);

        $result = $parser->parse('1. 01.01.2024 48,3,22,7,35,14');

        $this->assertNotNull($result);
        $this->assertSame([3, 7, 14, 22, 35, 48], $result['numbers']); // sorted
    }

    public function testParseMultiMultiLine(): void
    {
        $parser = new MbnetLineParser(20, false);
        $numbers = '1,5,8,12,15,20,23,28,31,35,40,44,47,52,55,60,63,68,71,78';

        $result = $parser->parse('9999. 14.04.2026 ' . $numbers);

        $this->assertNotNull($result);
        $this->assertSame(9999, $result['draw_number']);
        $this->assertSame('2026-04-14', $result['draw_date']);
        $this->assertCount(20, $result['numbers']);
        $this->assertSame(1, $result['numbers'][0]);   // sorted, first
        $this->assertSame(78, $result['numbers'][19]); // sorted, last
        $this->assertNull($result['plus_ball']);
    }

    public function testParseMultiMultiWrongCount(): void
    {
        $parser = new MbnetLineParser(20, false);

        // Only 19 numbers for a 20-pick game
        $this->assertNull($parser->parse('1. 01.01.2026 1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19'));
    }
}
