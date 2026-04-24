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
        $slug = $game->slug;
        $isMM = ($slug === 'multi_multi');
        $isMini = ($slug === 'mini_lotto');

        return match ($key) {
            'sum_total' => $isMM
                ? "Suma wybranych przez Ciebie liczb z puli 1–80. "
                  . "Zależy od liczby liczb na kuponie (k): średnia = k × 40,5; "
                  . "typowy zakres = średnia ± 1,5σ (σ = √(k × 81 × (80−k) / 12)). "
                  . "Przykłady: k=6 → śr. 243, typowo 161–325; k=10 → śr. 405, typowo 302–508. "
                  . "Skrajne sumy (bliskie min lub max) są bardzo rzadkie. "
                  . "Patrz wskazówka 'Suma' poniżej pól — aktualizuje się przy zmianie liczby liczb."
                : ($isMini
                    ? "Suma wszystkich 5 wylosowanych liczb (pula 1–42). "
                      . "Rozkład zbliżony do normalnego: średnia ~107, odchylenie ~27. "
                      . "Typowy zakres: 80–140 (środkowe ~50% losowań). "
                      . "Sumy poniżej 50 lub powyżej 160 padają w <3% losowań. "
                      . "Przykład: 5+12+21+33+42 = 113 → bucket M (średnia)."
                    : "Suma wszystkich 6 wylosowanych liczb (pula 1–49). "
                      . "Rozkład zbliżony do normalnego: średnia teoretyczna = 150, odchylenie ~32. "
                      . "Typowy zakres: 110–170 (~40% losowań). "
                      . "Sumy poniżej 80 lub powyżej 220 padają w <3% losowań. "
                      . "Przykład: 7+14+22+35+41+48 = 167 → bucket M (średnia)."
                ),

            'even_count' => $isMM
                ? "Ile wybranych liczb jest parzystych (2, 4, 6, …, 80). "
                  . "Pula 1–80: dokładnie 40 parzystych + 40 nieparzystych (idealny podział 50/50). "
                  . "W pełnych losowaniach (20 z 80): średnio 10 parzystych. "
                  . "Przy kuponie k liczb: optymalny zakres ⌊k/2⌋−1 do ⌈k/2⌉+1. "
                  . "Przykład: k=10 → zalecane 4–6 parzystych; k=6 → zalecane 2–4. "
                  . "Skrajne (0 parzystych lub wszystkie parzyste) są rzadkie proporcjonalnie."
                : ($isMini
                    ? "Ile z 5 liczb jest parzystych. "
                      . "Pula 1–42: 21 parzystych + 21 nieparzystych (dokładny podział 50/50). "
                      . "Najczęstsze: 2 parzyste (~26%) i 3 parzyste (~26%). "
                      . "Skrajne (0 lub 5 parzystych): ~2% losowań każde. "
                      . "Przykład: klasyczny układ to 2 lub 3 parzyste."
                    : "Ile z 6 liczb jest parzystych (2, 4, 6, …, 48). "
                      . "Pula 1–49: 24 parzyste + 25 nieparzystych (prawie 50/50). "
                      . "Rozkład hipergeometryczny — najczęstsze: 3 parzyste (~31%). "
                      . "Skrajne (0 lub 6 parzystych): <3% łącznie. "
                      . "Zalecany zakres: 2–4 parzyste obejmuje ~77% historycznych losowań."
                ),

            'low_count' => $isMM
                ? "Ile wybranych liczb pochodzi z dolnej połowy puli (1–40). Liczby 41–80 to 'wysokie'. "
                  . "Pula 1–80: 40 niskich + 40 wysokich (idealny podział 50/50). "
                  . "W pełnych losowaniach (20 z 80): średnio 10 niskich. "
                  . "Przy kuponie k liczb: optymalny zakres ⌊k/2⌋−1 do ⌈k/2⌉+1. "
                  . "Przykład: k=10 → zalecane 4–6 niskich; k=6 → zalecane 2–4."
                : ($isMini
                    ? "Ile z 5 liczb pochodzi z dolnej połowy puli (1–21). Liczby 22–42 to 'wysokie'. "
                      . "Pula: 21 niskich + 21 wysokich (podział 50/50). "
                      . "Najczęstsze: 2 niskie (~26%) i 3 niskie (~26%). "
                      . "Skrajne (0 lub 5 niskich): ~2% każde. Zalecany zakres: 2–3 niskie."
                    : "Ile z 6 liczb pochodzi z dolnej połowy puli (1–{$low}). Liczby " . ($low + 1) . "–{$pool} to 'wysokie'. "
                      . "Pula: {$low} niskich + " . ($pool - $low) . " wysokich (prawie 50/50). "
                      . "Najczęstszy układ: 3 niskie / 3 wysokie (~31%). "
                      . "Skrajne (0 niskich lub 6 niskich): <2% łącznie. "
                      . "Zalecany zakres: 2–4 niskie obejmuje ~77% historycznych losowań."
                ),

            'consecutive' => $isMM
                ? "Ile par liczb sąsiadujących (np. 17 i 18) jest w Twoim kuponie. "
                  . "W pełnych losowaniach 20/80: ~4–6 par sąsiednich na losowanie (bo 20 liczb z 80 = gęste losowanie). "
                  . "Przy kuponie k liczb: naturalny poziom to ok. ⌊k/4⌋ par. "
                  . "Przykład: k=10 → max 2–3 pary. "
                  . "Kupon z wieloma parami z rzędu (np. 5,6,7,8,9) = wzorzec niemal nigdy niespotykany w zakładach."
                : ($isMini
                    ? "Ile par sąsiadujących liczb w losowaniu (np. 23 i 24). "
                      . "Historycznie (5/42): ~37% losowań bez par, ~39% z 1 parą, ~19% z 2 parami. "
                      . "Przykład: liczby 11, 12 → 1 para sąsiadów. Zalecane max: 2."
                    : "Ile par sąsiadujących liczb (np. 23 i 24) zawiera losowanie. "
                      . "Historycznie (6/49): ~35% bez par, ~38% z 1 parą, ~20% z 2 parami, <7% z 3+. "
                      . "Gracze psychologicznie unikają par, ale statystycznie są bardzo częste (~65% losowań ma co najmniej 1 parę). "
                      . "Przykład: liczby 17,18,19 → 2 pary (17–18 i 18–19). Zalecane max: 2."
                ),

            'decades_used' => $isMM
                ? "Z ilu różnych dziesiątek (1–10, 11–20, 21–30, …, 71–80) pochodzi Twój kupon. "
                  . "Multi Multi ma 8 grup dziesiętnych w puli 1–80. "
                  . "W pełnych losowaniach (20 liczb): wszystkie 8 dziesiątek zawsze reprezentowane. "
                  . "Przy kuponie k liczb: zalecane min ⌈k/2⌉ różnych dziesiątek (dobre rozproszenie). "
                  . "Filtr 'max z jednej dziesiątki': nie więcej niż ⌈k/5⌉ liczb z tej samej grupy. "
                  . "Przykład: k=10 → zalecane min 5 różnych dziesiątek, max 2 z jednej."
                : ($isMini
                    ? "Z ilu różnych dziesiątek pochodzi kombinacja. "
                      . "Pula 1–42: grupy 1–10, 11–20, 21–30, 31–40, 41–42 (5 grup). "
                      . "Typowo: 4–5 różnych dziesiątek w 5 liczbach. "
                      . "Mniej niż 3 = skupienie liczb w wąskim przedziale puli."
                    : "Z ilu różnych dziesiątek (1–10, 11–20, 21–30, 31–40, 41–49) pochodzi kombinacja. "
                      . "Pula Lotto ma 5 grup dziesiętnych. "
                      . "Typowo: 4–5 różnych dziesiątek w kuponie 6-liczbowym. "
                      . "Mniej niż 3 = skupienie w wąskim zakresie puli — rzadkie wzorce."
                ),

            'range_spread' => $isMM
                ? "Różnica max − min wybranych liczb. "
                  . "Przy k liczbach z puli 1–80: oczekiwany rozstęp ≈ 81×(k−1)/(k+1). "
                  . "Przykład: k=10 → oczekiwany rozstęp ~62; k=6 → ~52. "
                  . "Mały rozstęp = wszystkie liczby skupione blisko siebie = rzadkie."
                : ($isMini
                    ? "Różnica między największą a najmniejszą liczbą (max − min). "
                      . "Pula 1–42: oczekiwany rozstęp dla 5 liczb ≈ 43×4/6 ≈ 29. "
                      . "Typowy zakres: 23–37 (bucket M i L). "
                      . "Rozstęp <13 lub >39 = skupione lub rozciągnięte do granic — rzadkie."
                    : "Różnica między największą a najmniejszą liczbą (max − min). "
                      . "Pula 1–49: oczekiwany rozstęp dla 6 liczb ≈ 50×5/7 ≈ 36. "
                      . "Typowy zakres: 30–44 (bucket M i L) obejmuje ~65% losowań. "
                      . "Rozstęp <20 = liczby blisko siebie (~5% losowań); "
                      . "rozstęp >44 = prawie pełny zakres puli (~15% losowań)."
                ),

            'last_digit_unique' => $isMM
                ? "Ile różnych cyfr jedności (0–9) zawiera Twój kupon. "
                  . "Pula 1–80: każda cyfra jedności (0–9) pojawia się 8 razy. "
                  . "W pełnych losowaniach (20 liczb): typowo 8–10 unikalnych cyfr jedności. "
                  . "Przy k liczbach: im więcej unikalnych cyfr, tym lepiej rozproszony kupon. "
                  . "Przykład: k=10 → idealnie 8–10 różnych cyfr; mniej niż 5 = silne skupienie."
                : "Ile różnych cyfr jedności (0–9) zawiera kombinacja. "
                  . "Mała wartość = wiele liczb kończy się tą samą cyfrą (rzadkie). "
                  . "Typowo dla Lotto: 4–6 unikalnych cyfr jedności. "
                  . "Przykład: 3, 13, 23, 33 → wszystkie kończą się 3 → wynik = 1 (ekstremalnie rzadkie). "
                  . "Przykład normalny: 7, 14, 22, 35, 41, 48 → cyfry 7,4,2,5,1,8 → 6 unikalnych.",

            'profile_hash' =>
                "Strukturalny odcisk palca losowania — identyczny dla kombinacji "
                . "o tym samym układzie parzystości, niskich/wysokich, zakresu sumy i rozstępu. "
                . "Pomaga identyfikować powtarzające się wzorce niezależnie od konkretnych liczb. "
                . "Uwaga: profile w bazie dotyczą pełnych losowań ({$pick} liczb) — "
                . "niedostępne dla Multi Multi przy zmiennej liczbie liczb w zakładzie.",

            'overdue_score' =>
                "Wskaźnik zaległości = aktualna przerwa ÷ średni interwał tej liczby. "
                . "Wartość > 1,0 → liczba czeka dłużej niż jej historyczna średnia. "
                . "Wartość > 2,0 → mocno zalega. "
                . "Przykład: wartość 1,8 = ta liczba czeka 80% dłużej niż zwykle. "
                . "Ważne: wysoki wskaźnik NIE zwiększa prawdopodobieństwa wypadnięcia — loteria nie ma pamięci.",

            'current_gap' =>
                "Ile losowań minęło od ostatniego pojawienia się tej liczby. "
                . "Przykład: przerwa 45 = liczba nie padła przez ostatnie 45 losowań.",

            'avg_interval' =>
                "Średnio co ile losowań ta liczba pada (wszystkie losowania ÷ liczba wystąpień). "
                . "Wartość oczekiwana: {$pool}/{$pick} = " . round($pool / $pick, 1) . " losowań. "
                . "Przykład: wartość 8,2 = ta liczba pada średnio co 8 losowań.",

            'window_freq' =>
                'Ile razy liczba padła w ostatnich 500 losowaniach. '
                . 'Średnia oczekiwana: ' . round(500 * $pick / $pool, 0) . ' trafień '
                . '(= 500 × ' . $pick . '/' . $pool . '). '
                . 'Liczby powyżej tej wartości są "gorące", poniżej — "zimne". '
                . 'Odchylenie standardowe: ~' . round(sqrt(500 * $pick / $pool * (1 - $pick / $pool)), 1) . '.',

            'total_freq' =>
                "Całkowita liczba wystąpień tej liczby we wszystkich dostępnych losowaniach. "
                . "Oczekiwana wartość: łączna_liczba_losowań × {$pick}/{$pool} ≈ " . round($pick / $pool * 100, 1) . "% losowań.",

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
     * Compute theoretical sum statistics for each valid betPickCount of a variable-bet game.
     * Returns array keyed by k with [min, max, mean, typ_min, typ_max].
     *
     * Formula (k numbers without replacement from 1..N):
     *   min    = k*(k+1)/2
     *   max    = k*(2N−k+1)/2
     *   mean   = k*(N+1)/2
     *   sigma  = sqrt(k*(N+1)*(N−k)/12)
     *   typical range = mean ± 1.5*sigma (clamped to [min, max])
     *
     * @return array<int, array{min:int,max:int,mean:int,typ_min:int,typ_max:int}>
     */
    public function sumRangesForVarBet(GameDefinition $game): array
    {
        $n      = $game->poolSize;
        $ranges = [];
        for ($k = $game->betPickMin(); $k <= $game->betPickMax(); $k++) {
            $minS  = (int)($k * ($k + 1) / 2);
            $maxS  = (int)($k * (2 * $n - $k + 1) / 2);
            $mean  = $k * ($n + 1) / 2;
            $sig   = sqrt($k * ($n + 1) * ($n - $k) / 12);
            $ranges[$k] = [
                'min'     => $minS,
                'max'     => $maxS,
                'mean'    => (int)round($mean),
                'typ_min' => max($minS, (int)round($mean - 1.5 * $sig)),
                'typ_max' => min($maxS, (int)round($mean + 1.5 * $sig)),
            ];
        }
        return $ranges;
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
