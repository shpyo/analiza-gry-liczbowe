<?php
declare(strict_types=1);

final class MbnetLineParser implements LineParser
{
    public function __construct(
        private readonly int  $pickCount,
        private readonly bool $hasBonus,
    ) {
    }

    public function parse(string $line): ?array
    {
        $line = trim($line);
        // Format: {number}. {dd.mm.yyyy} {n1},{n2},...
        if (!preg_match('/^(\d+)\.\s+(\d{2})\.(\d{2})\.(\d{4})\s+([\d,]+)$/', $line, $m)) {
            return null;
        }

        $drawNumber = (int)$m[1];
        $drawDate   = $m[4] . '-' . $m[3] . '-' . $m[2]; // Y-m-d
        $rawNums    = array_map('intval', explode(',', $m[5]));

        $plusBall = null;

        if ($this->hasBonus && count($rawNums) === $this->pickCount + 1) {
            $plusBall = $rawNums[$this->pickCount];
            $rawNums  = array_slice($rawNums, 0, $this->pickCount);
        }

        if (count($rawNums) !== $this->pickCount) {
            return null;
        }

        sort($rawNums);

        return [
            'draw_number' => $drawNumber,
            'draw_date'   => $drawDate,
            'numbers'     => $rawNums,
            'plus_ball'   => $plusBall,
        ];
    }
}
