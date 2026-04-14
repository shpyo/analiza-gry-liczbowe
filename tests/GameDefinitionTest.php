<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/GameDefinitionFactory.php';

final class GameDefinitionTest extends TestCase
{
    public function testNumberColumnsLotto(): void
    {
        $game = GameDefinitionFactory::lotto();

        $this->assertSame(['n1', 'n2', 'n3', 'n4', 'n5', 'n6'], $game->numberColumns());
    }

    public function testNumberColumnsMiniLotto(): void
    {
        $game = GameDefinitionFactory::miniLotto();

        $this->assertSame(['n1', 'n2', 'n3', 'n4', 'n5'], $game->numberColumns());
    }

    public function testNumberColumnsSql(): void
    {
        $game = GameDefinitionFactory::miniLotto();

        $this->assertSame('`n1`, `n2`, `n3`, `n4`, `n5`', $game->numberColumnsSql());
    }

    public function testUnpivotNumbersSql(): void
    {
        $game = GameDefinitionFactory::miniLotto();

        $sql = $game->unpivotNumbersSql('cte');

        $this->assertStringContainsString('SELECT `n1` AS num FROM cte', $sql);
        $this->assertStringContainsString('UNION ALL', $sql);
        $this->assertStringContainsString('SELECT `n5` AS num FROM cte', $sql);
        $this->assertStringNotContainsString('n6', $sql);
    }

    public function testReadonlyProperties(): void
    {
        $game = GameDefinitionFactory::lotto();

        $this->assertSame('lotto', $game->slug);
        $this->assertSame('Lotto', $game->name);
        $this->assertSame(6, $game->pickCount);
        $this->assertSame(49, $game->poolSize);
        $this->assertSame(24, $game->lowThreshold);
        $this->assertFalse($game->hasBonus);
        $this->assertSame('lotto_draws', $game->drawsTable);
        $this->assertSame('lotto_draw_profiles', $game->profileTable);
    }

    public function testLottoPlusHasBonus(): void
    {
        $game = GameDefinitionFactory::lottoPlus();

        $this->assertTrue($game->hasBonus);
    }
}
