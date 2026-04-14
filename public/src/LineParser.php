<?php
declare(strict_types=1);

interface LineParser
{
    /**
     * Parse a single line of draw data.
     *
     * @return null|array{draw_number: int, draw_date: string, numbers: int[], plus_ball: int|null}
     */
    public function parse(string $line): ?array;
}
