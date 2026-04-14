<?php
declare(strict_types=1);

/**
 * texts.php - Centralne źródło etykiet i opisów metryk.
 * Jedyne miejsce, gdzie definiujemy polskie nazwy dla kluczy bazy danych.
 */

// ---------------------------------------------------------------------------
// Krótkie etykiety metryk (do nagłówków tabel, labelek formularzy)
// ---------------------------------------------------------------------------
const METRIC_LABELS = [
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

// ---------------------------------------------------------------------------
// Opisy metryk z przykładami — zależne od gry (low_count i last_digit_unique
// mają różne progi dla lotto vs mini_lotto)
// ---------------------------------------------------------------------------

function metric_label(string $key): string
{
    return METRIC_LABELS[$key] ?? $key;
}

/** @deprecated Use MetricTextProvider::tooltip() */
function metric_tooltip(string $key, string $game = 'lotto'): string
{
    $lowThreshold = ($game === 'mini_lotto') ? 21 : 24;
    $pickCount    = ($game === 'mini_lotto') ? 5 : 6;
    $poolSize     = ($game === 'mini_lotto') ? 42 : 49;

    switch ($key) {
        case 'sum_total':
            return "Suma wszystkich {$pickCount} wylosowanych liczb w kuponie. "
                 . "Dla typowego losowania mieści się między 110 a 170 — "
                 . "to środkowe pasmo, które padało w ok. 40% losowań. "
                 . "Przykład: liczby 7, 14, 22, 35, 41, 48 dają sumę 167.";

        case 'even_count':
            return "Ile liczb w losowaniu jest parzystych (2, 4, 6, 8…). "
                 . "Historycznie najczęściej pada 2–4 parzystych — rozkład bliski "
                 . "połowie jest najbardziej typowy. "
                 . "Przykład: 3 parzyste + 3 nieparzyste = klasyczny układ.";

        case 'low_count':
            return "Ile liczb pochodzi z dolnej połowy puli (liczby 1–{$lowThreshold}). "
                 . "Liczby {$lowThreshold}+ są „wysokie”. "
                 . "Historycznie dominują układy z 2–4 niskimi. "
                 . "Przykład: liczby 8, 15, 22 to niskie; 30, 44, 49 to wysokie.";

        case 'consecutive':
            return "Ile par liczb leżących obok siebie jest w losowaniu (np. 17 i 18). "
                 . "Liczymy pary: 17, 18, 19 to dwie pary (17–18 i 18–19). "
                 . "Większość losowań ma 0–2 takie pary. "
                 . "Przykład: kombinacja z parą 23, 24 ma wynik = 1.";

        case 'decades_used':
            return "Z ilu różnych przedziałów dziesiętnych (1–9, 10–19, 20–29, 30–39, 40–{$poolSize}) "
                 . "pochodzi kombinacja. Większa wartość = lepsze rozproszenie liczb. "
                 . "Przykład: liczby z 4 różnych dziesiątek = dobre rozproszenie.";

        case 'range_spread':
            return "Różnica między największą a najmniejszą liczbą w losowaniu (max − min). "
                 . "Duży rozstęp = liczby rozłożone szeroko po całej puli. "
                 . "Przykład: liczby 3 i 47 → rozstęp = 44.";

        case 'last_digit_unique':
            return "Ile różnych cyfr jedności (0–9) zawiera kombinacja. "
                 . "Mała wartość = wiele liczb kończy się tą samą cyfrą. "
                 . "Przykład: liczby 3, 13, 23, 33 → wszystkie kończą się 3 → wynik = 1 (rzadkie).";

        case 'profile_hash':
            return "Strukturalny odcisk palca losowania — identyczny dla kombinacji "
                 . "o tym samym układzie parzystości, niskich/wysokich, zakresu sumy i rozstępu. "
                 . "Pomaga identyfikować powtarzające się wzorce niezależnie od konkretnych liczb.";

        case 'overdue_score':
            return "Wskaźnik zaległości = aktualna przerwa ÷ średni interwał tej liczby. "
                 . "Wartość >1,0 oznacza, że liczba czeka dłużej niż jej historyczna średnia. "
                 . "Wartość >2,0 = mocno zalega. "
                 . "Przykład: wartość 1,8 = ta liczba czeka 80% dłużej niż zwykle.";

        case 'current_gap':
            return "Ile losowań minęło od ostatniego pojawienia się tej liczby. "
                 . "Przykład: przerwa 45 = liczba nie padła przez ostatnie 45 losowań.";

        case 'avg_interval':
            return "Średnio co ile losowań ta liczba pada (wszystkie losowania ÷ liczba wystąpień). "
                 . "Przykład: wartość 8,2 = ta liczba pada średnio co 8 losowań.";

        case 'window_freq':
            return "Ile razy liczba padła w ostatnich 500 losowaniach. "
                 . "Średnia wynosi ok. " . round(500 * $pickCount / $poolSize, 0) . " — "
                 . "liczby powyżej tej wartości są „gorące”, poniżej — „zimne”.";

        case 'total_freq':
            return "Całkowita liczba wystąpień tej liczby w całej dostępnej historii losowań.";

        default:
            return $key;
    }
}

// ---------------------------------------------------------------------------
// Buckety sumy — polskie nazwy z zakresami
// ---------------------------------------------------------------------------

/** @deprecated Use MetricTextProvider::sumBucketLabel() */
function sum_bucket_label(string $bucket, string $game = 'lotto'): string
{
    if ($game === 'mini_lotto') {
        $map = [
            'XS' => 'bardzo mała (≤49)',
            'S'  => 'mała (50–79)',
            'M'  => 'średnia (80–120)',
            'L'  => 'duża (121–159)',
            'XL' => 'bardzo duża (160+)',
        ];
    } else {
        $map = [
            'XS' => 'bardzo mała (21–79)',
            'S'  => 'mała (80–109)',
            'M'  => 'średnia (110–170)',
            'L'  => 'duża (171–200)',
            'XL' => 'bardzo duża (201–279)',
        ];
    }
    return $map[$bucket] ?? $bucket;
}

// ---------------------------------------------------------------------------
// Buckety rozstępu — polskie nazwy z zakresami
// ---------------------------------------------------------------------------

/** @deprecated Use MetricTextProvider::rangeBucketLabel() */
function range_bucket_label(string $bucket, string $game = 'lotto'): string
{
    if ($game === 'mini_lotto') {
        $map = [
            'XS' => 'bardzo mały (≤12)',
            'S'  => 'mały (13–22)',
            'M'  => 'średni (23–31)',
            'L'  => 'duży (32–37)',
            'XL' => 'bardzo duży (38+)',
        ];
    } else {
        $map = [
            'XS' => 'bardzo mały (0–19)',
            'S'  => 'mały (20–29)',
            'M'  => 'średni (30–39)',
            'L'  => 'duży (40–44)',
            'XL' => 'bardzo duży (45–48)',
        ];
    }
    return $map[$bucket] ?? $bucket;
}

// ---------------------------------------------------------------------------
// Etykiety nawigacji
// ---------------------------------------------------------------------------

const NAV_LABELS = [
    'dashboard' => 'Dashboard',
    'draws'     => 'Losowania',
    'stats'     => 'Statystyki',
    'generator' => 'Generator',
    'validator' => 'Weryfikator',
    'sync'      => 'Synchronizuj',
    'import'    => 'Wczytaj historię',
];

const NAV_ICONS = [
    'dashboard' => 'dashboard',
    'draws'     => 'event_note',
    'stats'     => 'analytics',
    'generator' => 'casino',
    'validator' => 'task_alt',
    'sync'      => 'sync',
    'import'    => 'download',
];
