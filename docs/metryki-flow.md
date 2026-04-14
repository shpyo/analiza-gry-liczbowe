# Analiza metryk LottoAnalytics — Flow & Interpretacja

## Spis treści

1. [Przegląd przepływu danych](#1-przegląd-przepływu-danych)
2. [Metryki per-losowanie (compute_metrics)](#2-metryki-per-losowanie)
   - [sum_total](#21-sum_total--suma-liczb)
   - [even_count](#22-even_count--parzyste)
   - [low_count](#23-low_count--niskie)
   - [consecutive](#24-consecutive--pary-sąsiadów)
   - [decades_used](#25-decades_used--użyte-dziesiątki)
   - [range_spread](#26-range_spread--rozstęp)
   - [last_digit_unique](#27-last_digit_unique--unikalne-cyfry-jedności)
3. [Buckety (klasyfikacja przedziałowa)](#3-buckety)
   - [sum_bucket](#31-sum_bucket)
   - [range_bucket](#32-range_bucket)
4. [Profil strukturalny (profile_hash)](#4-profil-strukturalny)
5. [Metryki per-liczba (stats.php)](#5-metryki-per-liczba)
   - [total_freq](#51-total_freq)
   - [window_freq](#52-window_freq)
   - [current_gap](#53-current_gap)
   - [avg_interval](#54-avg_interval)
   - [overdue_score](#55-overdue_score)
   - [Klasyfikacja temperatury](#56-klasyfikacja-temperatury)
6. [Heatmapa kwintylowa](#6-heatmapa-kwintylowa)
7. [Generator — wagi i filtry](#7-generator--wagi-i-filtry)

---

## 1. Przegląd przepływu danych

```
                    ┌──────────────────┐
                    │   mbnet.com.pl   │  zewnętrzne źródło danych
                    └────────┬─────────┘
                             │ tekst: "1234. 15.03.2024 3,7,14,22,35,48"
                             ▼
                    ┌──────────────────┐
                    │  MbnetLineParser │  parsowanie linii → draw_number,
                    │     ::parse()    │  draw_date, numbers[], plus_ball
                    └────────┬─────────┘
                             │ [3, 7, 14, 22, 35, 48]
                             ▼
                    ┌──────────────────┐
                    │ MetricCalculator │  obliczenie 7 metryk per losowanie
                    │::computeMetrics()│  (funkcja czysta, bez DB)
                    └────────┬─────────┘
                             │ {sum_total, even_count, low_count, ...}
                             ▼
               ┌─────────────┴─────────────┐
               │                           │
               ▼                           ▼
    ┌─────────────────┐         ┌─────────────────┐
    │ BucketStrategy   │         │ ProfileDescriber │
    │  ::classify()    │         │  ::computeHash() │
    │ sum → XS/S/M/L/XL│        │ → "3e3o_3l3h_    │
    │ range → XS/S/M/L/XL│      │    sM_c0_rL"     │
    └────────┬─────────┘         └────────┬─────────┘
             │                            │
             └─────────────┬──────────────┘
                           ▼
                  ┌──────────────────┐
                  │  DrawRepository  │  INSERT do {game}_draws
                  │  ::insertDraw()  │  (metryki + hash zapisane w DB)
                  └────────┬─────────┘
                           │
                           ▼
        ┌──────────────────────────────────────┐
        │           BAZA DANYCH                │
        │  {game}_draws: n1..n6, sum_total,    │
        │  even_count, ..., profile_hash       │
        │                                      │
        │  {game}_draw_profiles: hash → count  │
        └──────────────────┬───────────────────┘
                           │
              ┌────────────┼────────────┐
              ▼            ▼            ▼
        ┌──────────┐ ┌──────────┐ ┌──────────┐
        │dashboard │ │  stats   │ │generator │  strony UI
        │  .php    │ │  .php    │ │  .php    │  (odczyt + prezentacja)
        └──────────┘ └──────────┘ └──────────┘
```

**Kluczowa zasada:** Metryki obliczane są RAZ przy imporcie/synchronizacji i zapisywane w DB. Strony UI tylko je odczytują — nie przeliczają.

---

## 2. Metryki per-losowanie

Plik źródłowy: `public/src/MetricCalculator.php::computeMetrics()`

Wejście: tablica liczb (np. `[7, 14, 22, 35, 41, 48]`) + `GameDefinition`

```
sort($numbers)  ──►  [3, 7, 14, 22, 35, 48]  (zawsze rosnąco)
                            │
            ┌───────────────┼───────────────┐
            ▼               ▼               ▼
     iteracja po         porównanie      obliczenia
     każdej liczbie      par sąsiadów    globalne
     (even, low,        (consecutive)   (sum, range,
      decades, digits)                   decades_used,
                                         last_digit_unique)
```

---

### 2.1 sum_total — Suma liczb

```
Wzór:   sum_total = n1 + n2 + n3 + ... + nK
        (K = pickCount: 6 dla Lotto, 5 dla Mini Lotto)
```

**Przykład krok po kroku:**

```
Liczby: [7, 14, 22, 35, 41, 48]

sum_total = 7 + 14 + 22 + 35 + 41 + 48 = 167
```

**Rozkład teoretyczny:**

```
Lotto 6/49:
  Min możliwa:  1+2+3+4+5+6     =  21
  Max możliwa:  44+45+46+47+48+49 = 279
  Średnia:      (1+49) × 6 / 2   = 150

Mini Lotto 5/42:
  Min możliwa:  1+2+3+4+5        =  15
  Max możliwa:  38+39+40+41+42   = 200
  Średnia:      (1+42) × 5 / 2   = 107.5
```

**Interpretacja:**

| Suma (Lotto) | Bucket | Znaczenie |
|---|---|---|
| 21–79 | XS | Ekstremalnie niska — bardzo rzadka |
| 80–109 | S | Niska — poniżej średniej |
| **110–170** | **M** | **Typowa — ~40% losowań mieści się tutaj** |
| 171–200 | L | Wysoka — powyżej średniej |
| 201–279 | XL | Ekstremalnie wysoka — bardzo rzadka |

**Dlaczego to ważne:** Rozkład sum zbliżony jest do krzywej normalnej (Gaussa). Skrajne wartości (XS, XL) są statystycznie rzadkie. Generator używa sum jako filtra — kupon z sumą = 30 jest podejrzanie niski.

**Powiązania:** `sum_total` → `sum_bucket()` → `profile_hash` (segment `sM`)

---

### 2.2 even_count — Parzyste

```
Wzór:   even_count = |{n ∈ numbers : n mod 2 = 0}|

Pochodna: odd_count = pickCount - even_count
```

**Przykład krok po kroku:**

```
Liczby: [7, 14, 22, 35, 41, 48]

 7  mod 2 = 1  → nieparzysta
14  mod 2 = 0  → PARZYSTA  ✓
22  mod 2 = 0  → PARZYSTA  ✓
35  mod 2 = 1  → nieparzysta
41  mod 2 = 1  → nieparzysta
48  mod 2 = 0  → PARZYSTA  ✓

even_count = 3      odd_count = 6 - 3 = 3
```

**Rozkład (Lotto 6/49):**

```
Parzyste w puli: 24 (2,4,6,...,48)
Nieparzyste:     25 (1,3,5,...,49)

Rozkład hipergeometryczny:
  0 parzystych:  ~1.5%     ← rzadkie
  1 parzysta:    ~9.5%
  2 parzyste:    ~24%
  3 parzyste:    ~31%      ← najczęstsze
  4 parzyste:    ~22%
  5 parzystych:  ~10%
  6 parzystych:  ~2%       ← rzadkie
```

**Interpretacja:**
- `even_count = 3` → klasyczny układ 3/3 — najczęstszy
- `even_count = 0 lub 6` → skrajne, statystycznie rzadkie (<3% łącznie)
- Dla generatora: filtry `even_min` / `even_max` ograniczają dopuszczalne zakresy

**Powiązania:** `even_count` → `profile_hash` (segment `3e3o`)

---

### 2.3 low_count — Niskie

```
Wzór:   low_count = |{n ∈ numbers : n ≤ lowThreshold}|

        lowThreshold:
          Lotto / Lotto Plus:  24  (pula 49, podział ~50/50)
          Mini Lotto:          21  (pula 42, podział ~50/50)

Pochodna: high_count = pickCount - low_count
```

**Przykład krok po kroku (Lotto, próg = 24):**

```
Liczby: [7, 14, 22, 35, 41, 48]

 7  ≤ 24?  TAK → NISKA  ✓
14  ≤ 24?  TAK → NISKA  ✓
22  ≤ 24?  TAK → NISKA  ✓
35  ≤ 24?  NIE → wysoka
41  ≤ 24?  NIE → wysoka
48  ≤ 24?  NIE → wysoka

low_count = 3      high_count = 3
```

**Interpretacja:**
- Analogiczna do even/odd — rozkład hipergeometryczny
- `low_count = 3` → równomierny podział — najczęstszy
- Skrajne wartości (0 lub 6) bardzo rzadkie
- Próg 24 dla Lotto dzieli pulę na: niskie [1–24] (24 liczby) / wysokie [25–49] (25 liczb)

**Powiązania:** `low_count` → `profile_hash` (segment `3l3h`)

---

### 2.4 consecutive — Pary sąsiadów

```
Wzór:   consecutive = |{i : numbers[i] = numbers[i-1] + 1, i = 1..K-1}|
        (po posortowaniu numbers rosnąco)
```

**Przykład krok po kroku:**

```
Liczby (posortowane): [7, 14, 22, 35, 41, 48]

14 = 7  + 1?  NIE (14 ≠ 8)
22 = 14 + 1?  NIE (22 ≠ 15)
35 = 22 + 1?  NIE (35 ≠ 23)
41 = 35 + 1?  NIE (41 ≠ 36)
48 = 41 + 1?  NIE (48 ≠ 42)

consecutive = 0
```

**Przykład z parami:**

```
Liczby: [5, 17, 18, 19, 33, 44]

18 = 17 + 1?  TAK  ✓  (para 17–18)
19 = 18 + 1?  TAK  ✓  (para 18–19)
33 = 19 + 1?  NIE
44 = 33 + 1?  NIE

consecutive = 2
```

**Flow obliczenia:**

```
sort(numbers)
    │
    ▼
┌─── for i = 1 to K-1 ──────────────┐
│                                     │
│   numbers[i] == numbers[i-1] + 1?  │
│        │                            │
│     TAK│        NIE                 │
│        ▼         │                  │
│   consecutive++  │                  │
│        │         │                  │
│        └────┬────┘                  │
│             │                       │
└─────────────┘                       │
                                      │
return consecutive ◄──────────────────┘
```

**Interpretacja:**
- `0` → brak par sąsiadujących (najczęstszy wynik: ~35% losowań)
- `1` → jedna para (np. 23, 24) — dość częste (~38%)
- `2` → dwie pary LUB trójka kolejnych — mniej częste (~20%)
- `3+` → rzadkie (<7%)
- Gracze psychologicznie unikają par, ale statystycznie są częste

**Powiązania:** `consecutive` → `profile_hash` (segment `c0`, `c1`, `c2`)

---

### 2.5 decades_used — Użyte dziesiątki

```
Wzór:   decades_used = |{⌊(n-1) / 10⌋ : n ∈ numbers}|

Grupy dziesiątkowe:
  Grupa 0:  1–10
  Grupa 1: 11–20
  Grupa 2: 21–30
  Grupa 3: 31–40
  Grupa 4: 41–49 (lub 41–42 dla Mini)
```

**Przykład krok po kroku:**

```
Liczby: [7, 14, 22, 35, 41, 48]

 7  → ⌊(7-1)/10⌋   = ⌊0.6⌋  = 0  (grupa 1–10)
14  → ⌊(14-1)/10⌋  = ⌊1.3⌋  = 1  (grupa 11–20)
22  → ⌊(22-1)/10⌋  = ⌊2.1⌋  = 2  (grupa 21–30)
35  → ⌊(35-1)/10⌋  = ⌊3.4⌋  = 3  (grupa 31–40)
41  → ⌊(41-1)/10⌋  = ⌊4.0⌋  = 4  (grupa 41–49)
48  → ⌊(48-1)/10⌋  = ⌊4.7⌋  = 4  (grupa 41–49)  ← powtórzenie

Unikalne grupy: {0, 1, 2, 3, 4} → decades_used = 5
```

**Interpretacja:**
- `5` (Lotto) → liczby z każdej dziesiątki — dobre rozproszenie
- `3` lub mniej → klasteryzacja, liczby skupione w kilku grupach
- W generatorze `decades_max` ogranicza max. liczbę z jednej dziesiątki

---

### 2.6 range_spread — Rozstęp

```
Wzór:   range_spread = max(numbers) - min(numbers)
        (po posortowaniu: numbers[K-1] - numbers[0])
```

**Przykład:**

```
Liczby (posortowane): [7, 14, 22, 35, 41, 48]

range_spread = 48 - 7 = 41
```

**Zakres możliwych wartości:**

```
Lotto 6/49:
  Min:  1,2,3,4,5,6     → range = 5
  Max:  1,_,_,_,_,49    → range = 48

Mini Lotto 5/42:
  Min:  1,2,3,4,5       → range = 4
  Max:  1,_,_,_,42      → range = 41
```

**Interpretacja:**

| Rozstęp (Lotto) | Bucket | Znaczenie |
|---|---|---|
| 0–19 | XS | Wszystkie liczby blisko siebie — rzadkie |
| 20–29 | S | Wąski zakres |
| **30–39** | **M** | **Typowy rozstęp** |
| 40–44 | L | Szerokie rozłożenie |
| 45–48 | XL | Prawie pełny zakres puli |

**Powiązania:** `range_spread` → `range_bucket()` → `profile_hash` (segment `rL`)

---

### 2.7 last_digit_unique — Unikalne cyfry jedności

```
Wzór:   last_digit_unique = |{n mod 10 : n ∈ numbers}|
```

**Przykład krok po kroku:**

```
Liczby: [7, 14, 22, 35, 41, 48]

 7  mod 10 = 7
14  mod 10 = 4
22  mod 10 = 2
35  mod 10 = 5
41  mod 10 = 1
48  mod 10 = 8

Unikalne cyfry: {7, 4, 2, 5, 1, 8} → last_digit_unique = 6
```

**Kontrprzykład (niska unikalność):**

```
Liczby: [3, 13, 23, 33, 43, 6]

 3 mod 10 = 3
13 mod 10 = 3  ← powtórzenie
23 mod 10 = 3  ← powtórzenie
33 mod 10 = 3  ← powtórzenie
43 mod 10 = 3  ← powtórzenie
 6 mod 10 = 6

Unikalne: {3, 6} → last_digit_unique = 2   (bardzo rzadkie!)
```

**Interpretacja:**
- `6` (max dla Lotto) → wszystkie różne cyfry jedności — dobre rozproszenie
- `1` → wszystkie kończą się tą samą cyfrą — ekstremalnie rzadkie
- Typowo: 4–6 unikalnych cyfr
- Niska unikalność → dużo powtórzeń końcówek → nietypowy kupon

---

## 3. Buckety

Buckety klasyfikują ciągłe wartości (sumy, rozstępy) w dyskretne kategorie XS/S/M/L/XL.

Plik źródłowy: `public/src/ThresholdBucketStrategy.php`

```
Wejście: wartość liczbowa (np. sum = 167)
                    │
                    ▼
    ┌─── iteracja po progach (rosnąco) ──┐
    │                                     │
    │   wartość ≤ tier.max?               │
    │        │                            │
    │     TAK│        NIE                 │
    │        ▼         │                  │
    │   return tier    │ → następny tier  │
    │     .label       │                  │
    │                  │                  │
    └──────────────────┘                  │
                                          │
    (ostatni tier ma max=null → fallback) │
    return 'XL' ◄─────────────────────────┘
```

### 3.1 sum_bucket

**Progi — Lotto / Lotto Plus:**

```
wartość:  21 ──── 79 ──── 109 ──── 170 ──── 200 ──── 279
bucket:     XS      S        M         L        XL
          bardzo   mała    średnia    duża    bardzo
          mała                                duża
```

**Progi — Mini Lotto:**

```
wartość:  15 ──── 49 ──── 79 ──── 120 ──── 159 ──── 200
bucket:     XS      S       M         L        XL
```

### 3.2 range_bucket

**Progi — Lotto / Lotto Plus:**

```
wartość:   0 ──── 19 ──── 29 ──── 39 ──── 44 ──── 48
bucket:      XS      S       M       L       XL
```

**Progi — Mini Lotto:**

```
wartość:   0 ──── 12 ──── 22 ──── 31 ──── 37 ──── 41
bucket:      XS      S       M       L       XL
```

---

## 4. Profil strukturalny

Plik źródłowy: `public/src/ProfileDescriber.php::computeHash()`

Profil to "odcisk palca" losowania — dwie zupełnie różne kombinacje mogą mieć identyczny profil.

```
Wzór:   "{even}e{odd}o_{low}l{high}h_s{sumBucket}_c{consecutive}_r{rangeBucket}"
```

**Flow budowania profilu:**

```
numbers = [7, 14, 22, 35, 41, 48]
                │
    ┌───────────┴───────────┐
    ▼                       ▼
computeMetrics()        sumBucket()  rangeBucket()
    │                       │             │
    │  even_count = 3       │  sum=167    │  range=41
    │  low_count  = 3       │    → M      │    → L
    │  consecutive = 0      │             │
    └───────────┬───────────┘             │
                ▼                         │
    odd  = 6 - 3 = 3                     │
    high = 6 - 3 = 3                     │
                │                         │
                ▼                         │
    hash = "3e3o" + "_" +                │
           "3l3h" + "_" +                │
           "sM"   + "_" +                │
           "c0"   + "_" +                │
           "rL"           ◄──────────────┘
                │
                ▼
    profile_hash = "3e3o_3l3h_sM_c0_rL"
```

**Dekodowanie profilu:**

```
    "3e3o_3l3h_sM_c0_rL"
     │ │  │ │  ││ ││ ││
     │ │  │ │  ││ ││ │└─ bucket rozstępu: L (duży, 40–44)
     │ │  │ │  ││ ││ └── prefix: r = range
     │ │  │ │  ││ │└──── pary sąsiadów: 0
     │ │  │ │  ││ └───── prefix: c = consecutive
     │ │  │ │  │└─────── bucket sumy: M (średnia, 110–170)
     │ │  │ │  └──────── prefix: s = sum
     │ │  │ └─────────── wysokie: 3
     │ │  └───────────── niskie: 3
     │ └──────────────── nieparzyste: 3
     └────────────────── parzyste: 3

Opis pełny: "3 parzyste · 3 niskie · suma średnia (110–170) · brak par sąsiadów · rozstęp duży (40–44)"
Opis krótki: "3p/3n · sM · c0 · rL"
```

**Do czego służy profil:**
1. **Analiza wzorców** — które struktury padają najczęściej?
2. **Filtr generatora** — generuj tylko kupony o wybranym profilu
3. **Walidator** — "ten profil padał 4.2% losowań" vs "nigdy nie wystąpił"

---

## 5. Metryki per-liczba

Obliczane dynamicznie w `stats.php` (nie przechowywane w DB).

Dotyczą każdej liczby z puli (1–49 dla Lotto, 1–42 dla Mini).

### 5.1 total_freq

```
Wzór:   total_freq(n) = ile razy liczba n pojawiła się we WSZYSTKICH losowaniach

SQL:    SELECT n, COUNT(*) FROM (
          SELECT n1 AS n FROM draws UNION ALL
          SELECT n2 AS n FROM draws UNION ALL
          ...
        ) GROUP BY n
```

**Interpretacja:**
- Oczekiwana wartość (Lotto): `total_draws × pickCount / poolSize = total_draws × 6/49`
  - Każda liczba ma szansę `6/49` (≈12.2%) na wypadnięcie w jednym losowaniu
  - Po 7000 losowań: `7000 × 6/49 ≈ 857` — tyle razy powinna statystycznie wypaść każda liczba
- Odchylenia od oczekiwanej → "szczęśliwe" lub "pechowe" liczby (statystycznie: szum)

### 5.2 window_freq

```
Wzór:   window_freq(n) = ile razy liczba n pojawiła się w OSTATNICH 500 losowaniach

        Identyczne jak total_freq, ale na oknie 500 najnowszych losowań.
```

**Interpretacja:**
- Oczekiwana (Lotto): `500 × 6 / 49 ≈ 61`
- `window_freq > 61` → liczba "gorąca" w ostatnim okresie
- `window_freq < 61` → liczba "zimna"
- Okno 500 to kompromis: dość duże dla stabilności, dość małe dla trendów

### 5.3 current_gap

```
Wzór:   current_gap(n) = max_draw_number - last_seen_draw(n)

        last_seen_draw(n) = MAX(draw_number) z losowań zawierających n
```

**Przykład:**

```
Ostatni numer losowania:  7654
Liczba 13 ostatnio padła: 7640

current_gap(13) = 7654 - 7640 = 14 losowań
```

**Interpretacja:**
- `0` → liczba padła w ostatnim losowaniu
- `gap = avg_interval` → liczba jest "na czas"
- `gap >> avg_interval` → liczba jest zaległa (overdue)

### 5.4 avg_interval

```
Wzór:   avg_interval(n) = total_draws / total_freq(n)

        Interpretacja: "co ile losowań średnio pada ta liczba"
```

**Przykład:**

```
total_draws = 7654
total_freq(13) = 950

avg_interval(13) = 7654 / 950 ≈ 8.06

→ Liczba 13 pada średnio co ~8 losowań
```

**Oczekiwane wartości:**
- Lotto: `49 / 6 ≈ 8.17` (każda liczba powinna padać co ~8 losowań)
- Mini Lotto: `42 / 5 = 8.4`

### 5.5 overdue_score

```
Wzór:   overdue_score(n) = current_gap(n) / avg_interval(n)
```

**Flow obliczenia:**

```
current_gap = 14     avg_interval = 8.06
      │                    │
      └────────┬───────────┘
               ▼
    overdue_score = 14 / 8.06 = 1.74
```

**Interpretacja:**

```
overdue_score:

    0.0 ─── 0.5 ─── 1.0 ─── 1.5 ─── 2.0 ─── 3.0+
     │              │                 │
     ▼              ▼                 ▼
   świeża       "na czas"        mocno zaległa
   (niedawno     (typowa           (czeka dłużej
    padła)       przerwa)           niż zwykle)
```

| Wartość | Znaczenie |
|---|---|
| `< 0.5` | Niedawno padła, przerwa krótsza niż zwykle |
| `≈ 1.0` | Na czas — przerwa zbliżona do średniej |
| `1.0 – 2.0` | Lekko zaległa |
| `> 2.0` | **Mocno zaległa** — czeka znacznie dłużej niż średnia |
| `> 3.0` | Ekstremalnie zaległa — ale NIE oznacza, że "musi paść" |

**Ważne:** overdue_score NIE jest predyktorem. Loteria nie ma pamięci. Wysoki overdue_score oznacza jedynie, że przerwa jest dłuższa niż historyczna średnia — nie zwiększa to prawdopodobieństwa wypadnięcia.

### 5.6 Klasyfikacja temperatury

Obliczana w `stats.php` na podstawie `window_freq` i średniej:

```
avg_window_freq = SUM(window_freq) / count(numbers_in_pool)

┌─────────────────────────────────────────────┐
│              window_freq(n)                 │
│                    │                        │
│      > avg × 1.2? ├──── TAK ──► GORĄCA     │
│           │        │     (badge: 'hot')     │
│          NIE       │                        │
│           │        │                        │
│      < avg × 0.8? ├──── TAK ──► ZIMNA      │
│           │        │     (badge: 'cold')    │
│          NIE       │                        │
│           │        │                        │
│      == 0?    ─────┼──── TAK ──► NIEAKTYWNA │
│           │        │     (badge: 'rare')    │
│          NIE       │                        │
│           │        │                        │
│           └────────┴───────► STABILNA       │
│                          (badge: 'stable')  │
└─────────────────────────────────────────────┘
```

**Model statystyczny: rozkład dwumianowy**

Każda liczba w oknie 500 losowań zachowuje się jak zmienna losowa
z rozkładu zbliżonego do dwumianowego (Bernoulli × n prób):

```
p = pickCount / poolSize          prawdop. wypadnięcia w jednym losowaniu
n = 500                           rozmiar okna

μ = n × p                         wartość oczekiwana (średnia)
σ = √(n × p × (1 - p))           odchylenie standardowe
```

**Wartości dla każdej gry:**

```
Lotto 6/49:
  p = 6/49 ≈ 0.1224
  μ = 500 × 0.1224 ≈ 61.2
  σ = √(500 × 0.1224 × 0.8776) ≈ 7.33

Mini Lotto 5/42:
  p = 5/42 ≈ 0.1190
  μ = 500 × 0.1190 ≈ 59.5
  σ = √(500 × 0.1190 × 0.8810) ≈ 7.14
```

**Progi temperatury: μ ± 1.5σ**

Progi oparte na z-score z modelu dwumianowego (`stats.php`):

```
Gorąca:   window_freq > μ + 1.5σ     (top ~6.7% rozkładu)
Zimna:    window_freq < μ - 1.5σ     (bottom ~6.7%)
Stabilna: reszta                      (~86.6% liczb)
```

| Gra | μ | σ | Gorąca (>μ+1.5σ) | Zimna (<μ-1.5σ) |
|---|---|---|---|---|
| Lotto 6/49 | 61.2 | 7.33 | > 72 | < 50 |
| Mini Lotto 5/42 | 59.5 | 7.14 | > 70 | < 49 |

Progi automatycznie dopasowują się do każdej gry (różne p → różne σ).

---

## 6. Heatmapa kwintylowa

Plik: `stats.php` (funkcja `_heatmap_bucket`) + `helpers.php::heatmap_bucket_color()`

Heatmapa dzieli wszystkie liczby na 5 równych grup (kwintyle) wg `window_freq`:

```
┌─────────────────────────────────────────────────────────────┐
│ 1. Zbierz window_freq dla każdej liczby 1..poolSize         │
│ 2. Posortuj rosnąco                                         │
│ 3. Wyznacz 4 progi kwintylowe (Q1, Q2, Q3, Q4)              │
│    Q_i = wartość na pozycji ⌊count × i/5⌋                   │
│ 4. Przypisz bucket 0–4 każdej liczbie                       │
└─────────────────────────────────────────────────────────────┘

freq ≤ Q1 → bucket 0 (najrzadsze 20%)
freq ≤ Q2 → bucket 1
freq ≤ Q3 → bucket 2
freq ≤ Q4 → bucket 3
freq >  Q4 → bucket 4 (najczęstsze 20%)
```

**Paleta kolorów:**

```
Bucket 0: #e7e8e9 (szary)    ── Rzadko
Bucket 1: #90bafe (niebieski) ──
Bucket 2: #d8e2ff (jasny)    ── Średnio
Bucket 3: #ffb870 (pomarańcz) ──
Bucket 4: #8b5000 (brązowy)  ── Często
```

---

## 7. Generator — wagi i filtry

Plik: `public/generator.php`

### Flow generowania kuponu

```
┌──────────────────────────────────────────────────────────────┐
│ 1. BUDOWANIE WAG                                             │
│                                                              │
│    Dla każdej liczby 1..poolSize:                            │
│      weight[n] = window_freq[n] + 1                          │
│                                                              │
│    (+1 gwarantuje, że nawet niewylosowane mają szansę)       │
│    Gorące liczby mają wyższe wagi → wyższe p-stwo wyboru     │
└──────────────────────────────────┬───────────────────────────┘
                                   │
                                   ▼
┌──────────────────────────────────────────────────────────────┐
│ 2. LOSOWANIE WAŻONE (powtarzane pickCount razy)              │
│                                                              │
│    totalWeight = SUM(weight[pool])                           │
│    rand = mt_rand(1, totalWeight)                            │
│                                                              │
│    Kumuluj wagi, wybierz liczbę gdzie cum ≥ rand             │
│    Usuń wybraną z puli (bez zwracania)                       │
│                                                              │
│    Prawdop. wyboru: P(n) = weight[n] / totalWeight           │
└──────────────────────────────────┬───────────────────────────┘
                                   │
                                   ▼
┌──────────────────────────────────────────────────────────────┐
│ 3. WALIDACJA FILTRÓW                                         │
│                                                              │
│    sort(selected)                                            │
│    metrics = computeMetrics(selected, game)                  │
│    hash    = computeHash(metrics, game)                      │
│                                                              │
│    Sprawdź KAŻDY filtr:                                      │
│    ├─ sum_total    w zakresie [sumMin, sumMax]?              │
│    ├─ even_count   w zakresie [evenMin, evenMax]?            │
│    ├─ low_count    w zakresie [lowMin, lowMax]?              │
│    ├─ consecutive  ≤ consecMax?                              │
│    ├─ last_digit   ≤ lastDigMax?                             │
│    ├─ hot_count    ≥ hotMin? (ile z top-10 gorących)         │
│    ├─ decades_max  max z jednej dziesiątki ≤ limit?          │
│    └─ profile_hash ∈ wantedHashes? (jeśli wybrano)           │
│                                                              │
│    Którykolwiek NIE → odrzuć, wróć do kroku 2                │
│    Wszystkie TAK → dodaj do wyników                          │
└──────────────────────────────────┬───────────────────────────┘
                                   │
                                   ▼
┌──────────────────────────────────────────────────────────────┐
│ 4. POWTARZANIE                                               │
│                                                              │
│    Powtórz do momentu aż:                                    │
│    - zebrano wymaganą liczbę kuponów (count), LUB            │
│    - przekroczono 50 000 prób (maxAttempts)                  │
│                                                              │
│    Jeśli nie udało się zebrać count kuponów:                 │
│    → ostrzeżenie "poluzuj kryteria"                          │
└──────────────────────────────────────────────────────────────┘
```

### Prawdopodobieństwo wyboru liczby

```
Przykład (Lotto, okno 500):

Liczba 7:   window_freq = 75   → weight = 76
Liczba 13:  window_freq = 45   → weight = 46
Liczba 49:  window_freq = 0    → weight = 1

P(7)  = 76 / Σ(weights) ≈ 76/3000 ≈ 2.5%
P(13) = 46 / Σ(weights) ≈ 46/3000 ≈ 1.5%
P(49) =  1 / Σ(weights) ≈  1/3000 ≈ 0.03%

→ Gorąca "7" jest ~76× bardziej prawdopodobna niż zimna "49"
```

---

## Podsumowanie zależności

```
┌──────────────────────────────────────────────────────────┐
│                    IMPORT / SYNC                         │
│  numbers[] ──► computeMetrics() ──► 7 metryk             │
│                      │                                   │
│                      ├──► sumBucket()  ──┐               │
│                      ├──► rangeBucket() ─┤               │
│                      │                   │               │
│                      └───────────────────┴──► hash       │
│                                                          │
│  Zapisane w DB: n1..n6 + 7 metryk + hash                 │
└──────────────────────────────────────────────────────────┘

┌──────────────────────────────────────────────────────────┐
│                    STATS (dynamiczne)                    │
│  DB → UNION ALL n1..n6 ──► total_freq, window_freq       │
│                                │                         │
│  DB → MAX(draw_number) ──► current_gap                   │
│                                │                         │
│  total_freq ──► avg_interval ──┤                         │
│                                ▼                         │
│  current_gap / avg_interval = overdue_score              │
│                                                          │
│  window_freq vs avg ──► temperatura (hot/cold/stable)    │
│  window_freq kwintyle ──► heatmapa (bucket 0–4)          │
└──────────────────────────────────────────────────────────┘

┌──────────────────────────────────────────────────────────┐
│                    GENERATOR                             │
│  window_freq + 1 ──► wagi ──► losowanie ważone           │
│                                    │                     │
│  computeMetrics() ◄────────────────┘                     │
│       │                                                  │
│       └──► filtry (sum, even, low, consec, hash, ...)    │
│                 │                                        │
│              OK?──► kupon zaakceptowany                  │
└──────────────────────────────────────────────────────────┘
```
