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
}
