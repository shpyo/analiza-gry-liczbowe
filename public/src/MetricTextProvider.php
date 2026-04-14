<?php
declare(strict_types=1);

final class MetricTextProvider
{
    private const LABELS = [
        'sum_total'          => 'Suma liczb',
        'even_count'         => 'Parzyste',
        'odd_count'          => 'Nieparzyste',
        'low_count'          => 'Niskie',
        'high_count'         => 'Wysokie',
        'consecutive'        => 'Pary sąsiadów',
        'decades_used'       => 'Użyte dziesiątki',
        'range_spread'       => 'Rozstęp',
        'last_digit_unique'  => 'Unikalne cyfry jedności',
        'profile_hash'       => 'Profil strukturalny',
        'overdue_score'      => 'Wskaźnik zaległości',
        'current_gap'        => 'Przerwa',
        'avg_interval'       => 'Śr. co ile losowań',
        'window_freq'        => 'Ost. 500 losowań',
        'total_freq'         => 'Łącznie',
    ];

    public function label(string $key): string
    {
        return self::LABELS[$key] ?? $key;
    }

    public function tooltip(string $key, GameDefinition $game): string
    {
        $low  = $game->lowThreshold;
        $pick = $game->pickCount;
        $pool = $game->poolSize;

        return match ($key) {
            'sum_total' =>
                "Suma wszystkich {$pick} wylosowanych liczb w kuponie. "
                . "Dla typowego losowania mieści się między 110 a 170 — "
                . "to środkowe pasmo, które padało w ok. 40% losowań. "
                . "Przykład: liczby 7, 14, 22, 35, 41, 48 dają sumę 167.",

            'even_count' =>
                "Ile liczb w losowaniu jest parzystych (2, 4, 6, 8…). "
                . "Historycznie najczęściej pada 2–4 parzystych — rozkład bliski "
                . "połowie jest najbardziej typowy. "
                . "Przykład: 3 parzyste + 3 nieparzyste = klasyczny układ.",

            'low_count' =>
                'Ile liczb pochodzi z dolnej połowy puli (liczby 1–' . $low . '). '
                . 'Liczby ' . ($low + 1) . '+ są „wysokie". '
                . 'Historycznie dominują układy z 2–4 niskimi. '
                . 'Przykład: liczby 8, 15, 22 to niskie; 30, 44, 49 to wysokie.',

            'consecutive' =>
                "Ile par liczb leżących obok siebie jest w losowaniu (np. 17 i 18). "
                . "Liczymy pary: 17, 18, 19 to dwie pary (17–18 i 18–19). "
                . "Większość losowań ma 0–2 takie pary. "
                . "Przykład: kombinacja z parą 23, 24 ma wynik = 1.",

            'decades_used' =>
                "Z ilu różnych przedziałów dziesiętnych (1–9, 10–19, 20–29, 30–39, 40–{$pool}) "
                . "pochodzi kombinacja. Większa wartość = lepsze rozproszenie liczb. "
                . "Przykład: liczby z 4 różnych dziesiątek = dobre rozproszenie.",

            'range_spread' =>
                "Różnica między największą a najmniejszą liczbą w losowaniu (max − min). "
                . "Duży rozstęp = liczby rozłożone szeroko po całej puli. "
                . "Przykład: liczby 3 i 47 → rozstęp = 44.",

            'last_digit_unique' =>
                "Ile różnych cyfr jedności (0–9) zawiera kombinacja. "
                . "Mała wartość = wiele liczb kończy się tą samą cyfrą. "
                . "Przykład: liczby 3, 13, 23, 33 → wszystkie kończą się 3 → wynik = 1 (rzadkie).",

            'profile_hash' =>
                "Strukturalny odcisk palca losowania — identyczny dla kombinacji "
                . "o tym samym układzie parzystości, niskich/wysokich, zakresu sumy i rozstępu. "
                . "Pomaga identyfikować powtarzające się wzorce niezależnie od konkretnych liczb.",

            'overdue_score' =>
                "Wskaźnik zaległości = aktualna przerwa ÷ średni interwał tej liczby. "
                . "Wartość >1,0 oznacza, że liczba czeka dłużej niż jej historyczna średnia. "
                . "Wartość >2,0 = mocno zalega. "
                . "Przykład: wartość 1,8 = ta liczba czeka 80% dłużej niż zwykle.",

            'current_gap' =>
                "Ile losowań minęło od ostatniego pojawienia się tej liczby. "
                . "Przykład: przerwa 45 = liczba nie padła przez ostatnie 45 losowań.",

            'avg_interval' =>
                "Średnio co ile losowań ta liczba pada (wszystkie losowania ÷ liczba wystąpień). "
                . "Przykład: wartość 8,2 = ta liczba pada średnio co 8 losowań.",

            'window_freq' =>
                'Ile razy liczba padła w ostatnich 500 losowaniach. '
                . 'Średnia wynosi ok. ' . round(500 * $pick / $pool, 0) . ' -- '
                . 'liczby powyżej tej wartości są gorące, poniżej -- zimne.',

            'total_freq' =>
                "Całkowita liczba wystąpień tej liczby w całej dostępnej historii losowań.",

            default => $key,
        };
    }

    public function sumBucketLabel(string $code, GameDefinition $game): string
    {
        return $game->sumBuckets->describe($code);
    }

    public function rangeBucketLabel(string $code, GameDefinition $game): string
    {
        return $game->rangeBuckets->describe($code);
    }

    /**
     * Render an HTML tooltip span for a metric.
     */
    public function renderTooltip(string $metric, GameDefinition $game): string
    {
        $label   = $this->label($metric);
        $tip     = $this->tooltip($metric, $game);
        return '<span class="tooltip-trigger" title="' . htmlspecialchars($tip, ENT_QUOTES, 'UTF-8') . '">'
             . htmlspecialchars($label, ENT_QUOTES, 'UTF-8')
             . ' <span class="tooltip-icon">?</span></span>';
    }
}
