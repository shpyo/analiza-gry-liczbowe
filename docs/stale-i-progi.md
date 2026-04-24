# Stałe i progi obliczeniowe — LottoAnalytics

Kompletna lista wartości konfiguracyjnych używanych w obliczeniach,
z wyjaśnieniem **po co**, **dlaczego taka wartość** i **przykładami**.

---

## Spis treści

1. [Parametry gier](#1-parametry-gier)
2. [Okno analizy](#2-okno-analizy)
3. [Progi temperatury (hot/cold)](#3-progi-temperatury)
4. [Progi zaległości (overdue)](#4-progi-zaległości)
5. [Buckety sumy](#5-buckety-sumy)
6. [Buckety rozstępu](#6-buckety-rozstępu)
7. [Progi popularności profilu](#7-progi-popularności-profilu)
8. [Heatmapa — kwintyle](#8-heatmapa--kwintyle)
9. [Generator — wagi i limity](#9-generator--wagi-i-limity)
10. [Heatmapa generatora — progi kolorów](#10-heatmapa-generatora--progi-kolorów)
11. [Paginacja i UI](#11-paginacja-i-ui)
12. [Multi Multi — filtry dla zmiennego betPickCount](#12-multi-multi--filtry-dla-zakładów-o-zmiennej-liczbie-liczb)

---

## 1. Parametry gier

Źródło: tabela `games` w DB + `GameRegistry.php`

| Parametr | Lotto | Lotto Plus | Mini Lotto | Multi Multi | Po co? |
|---|---|---|---|---|---|
| `pickCount` | 6 | 6 | 5 | 20 | Ile liczb w jednym losowaniu |
| `poolSize` | 49 | 49 | 42 | 80 | Z jakiej puli się losuje (1–N) |
| `lowThreshold` | 24 | 24 | 21 | 40 | Próg podziału niskie/wysokie |
| `hasBonus` | nie | tak | nie | nie | Czy jest dodatkowa kula (plus_ball) |
| `betPickMin` | — | — | — | 1 | Min liczb w zakładzie gracza (MM: można grać 1–10 z 20) |
| `betPickMax` | — | — | — | 10 | Max liczb w zakładzie gracza |

### lowThreshold — dlaczego 24 i 21?

```
Lotto:      pula 1–49 → podział na pół ≈ 24.5
            niskie: 1–24 (24 liczby)
            wysokie: 25–49 (25 liczb)
            → prawie idealny podział 50/50

Mini Lotto:  pula 1–42 → podział na pół ≈ 21
            niskie: 1–21 (21 liczb)
            wysokie: 22–42 (21 liczb)
            → dokładny podział 50/50
```

**Po co:** Pozwala mierzyć, czy losowanie jest "zbalansowane" (3 niskie / 3 wysokie)
czy skrzywione (0 niskich / 6 wysokich = ekstremalnie rzadkie).

---

## 2. Okno analizy

| Stała | Wartość | Plik | Po co? |
|---|---|---|---|
| `WINDOW_SIZE` | **500** | `stats.php`, `generator.php`, `dashboard.php` | Ile ostatnich losowań brać pod uwagę w analizie częstości |

### Dlaczego 500?

```
Lotto — losowanie 3× tygodniowo:
  500 losowań ≈ 3.2 roku danych

Kompromis między:
  ├── za mało (np. 50)  → zbyt duże wahania, szum statystyczny dominuje
  ├── 500               → stabilne trendy, ale wciąż reaguje na zmiany
  └── za dużo (np. 5000) → uśrednia wszystko, żadnych trendów
```

**Przykład wpływu:**
```
Liczba 7 w różnych oknach:
  Ostatnie 50:   padła 9×  → freq = 9   (szum: ±4)
  Ostatnie 500:  padła 75× → freq = 75  (szum: ±7)
  Ostatnie 5000: padła 620× → freq = 620 (szum: ±23)

σ rośnie z √n, ale szum względny (σ/μ) maleje.
Okno 500 daje ~12% szum względny — wystarczająco stabilne do
rozróżnienia gorących od zimnych.
```

---

## 3. Progi temperatury

| Stała | Wartość | Plik | Linia |
|---|---|---|---|
| Z-score mnożnik | **1.5** | `stats.php` | ~424–425 |

### Jak to działa?

```
Model: rozkład dwumianowy

p = pickCount / poolSize        szansa wypadnięcia w jednym losowaniu
n = 500                         rozmiar okna

μ = n × p                       wartość oczekiwana
σ = √(n × p × (1 - p))         odchylenie standardowe

Gorąca:   window_freq > μ + 1.5σ
Zimna:    window_freq < μ - 1.5σ
Stabilna: reszta
```

### Wyliczone progi per gra

| Gra | p | μ | σ | Gorąca (>μ+1.5σ) | Zimna (<μ-1.5σ) | Stabilna |
|---|---|---|---|---|---|---|
| Lotto 6/49 | 6/49 = 0.122 | 61.2 | 7.33 | > 72 | < 50 | 50–72 |
| Mini Lotto 5/42 | 5/42 = 0.119 | 59.5 | 7.14 | > 70 | < 49 | 49–70 |

### Dlaczego 1.5σ?

```
±1.0σ → 31.7% poza zakresem → za dużo "gorących/zimnych" (~8 na stronę)
±1.5σ →  13.4% poza zakresem → ~3–4 gorące i 3–4 zimne z 49 ← wybrany
±2.0σ →   4.6% poza zakresem → tylko 1–2 gorące — za konserwatywne
```

**1.5σ** = najlepsza równowaga: wystarczająco selektywny, żeby "gorąca" coś znaczyło,
ale nie tak restrykcyjny, żeby tabela była pusta.

---

## 4. Progi zaległości

| Stała | Wartość | Plik | Kontekst |
|---|---|---|---|
| Próg ostrzeżenia | **1.0** | `stats.php:461` | Kolor pomarańczowy w tabeli |
| Próg krytyczny | **2.0** | `stats.php:461` | Kolor czerwony, bold w tabeli |

### Jak to działa?

```
overdue_score = current_gap / avg_interval

Przykład — liczba 13:
  avg_interval = 8.0 (pada średnio co 8 losowań)

  gap = 4  → score = 0.50 → szary       (krótsza przerwa niż zwykle)
  gap = 8  → score = 1.00 → POMARAŃCZ   (dokładnie na średniej)
  gap = 16 → score = 2.00 → CZERWONY    (2× dłuższa przerwa niż zwykle)
  gap = 32 → score = 4.00 → CZERWONY    (4× dłużej — ekstremalnie zaległa)
```

### Dlaczego 1.0 i 2.0?

```
score = 1.0 → przerwa = średnia historyczna
              To naturalny punkt odniesienia: "na czas" vs "spóźniona"

score = 2.0 → przerwa 2× dłuższa niż średnia
              Statystycznie: ~13.5% przerw jest dłuższych (rozkład geometryczny)
              Intuicyjnie: podwójna średnia = "coś jest nie tak"

Uwaga: overdue_score NIE jest predyktorem.
Loteria nie ma pamięci — wysoki score nie zwiększa szansy.
```

---

## 5. Buckety sumy

Źródło: `GameRegistry.php::registerDefaults()`

### Lotto / Lotto Plus (pickCount=6, poolSize=49)

```
suma:   21 ──── 79 ──── 109 ──── 170 ──── 200 ──── 279
bucket:    XS       S        M         L        XL
```

| Bucket | Zakres | Opis | % losowań* |
|---|---|---|---|
| XS | 21–79 | Bardzo mała | ~5% |
| S | 80–109 | Mała | ~20% |
| **M** | **110–170** | **Średnia** | **~50%** |
| L | 171–200 | Duża | ~20% |
| XL | 201–279 | Bardzo duża | ~5% |

### Mini Lotto (pickCount=5, poolSize=42)

```
suma:   15 ──── 49 ──── 79 ──── 120 ──── 159 ──── 200
bucket:    XS       S       M         L        XL
```

| Bucket | Zakres | Opis | % losowań* |
|---|---|---|---|
| XS | 15–49 | Bardzo mała | ~5% |
| S | 50–79 | Mała | ~20% |
| **M** | **80–120** | **Średnia** | **~50%** |
| L | 121–159 | Duża | ~20% |
| XL | 160–200 | Bardzo duża | ~5% |

### Skąd te progi?

```
Rozkład sumy K losowań z puli N zbliżony jest do rozkładu normalnego.

Lotto:  μ_sum = 6 × (1+49)/2 = 150
        σ_sum ≈ 32 (wyliczone z wariancji rozkładu hipergeometrycznego)

Progi przybliżają kwintyle:
  M (środek) ≈ μ ± 1σ     → 150 ± 32 ≈ 118–182 (ustawione: 110–170)
  S / L      ≈ μ ± 2σ     → 150 ± 64 ≈ 86–214  (ustawione: 80–200)
  XS / XL   = reszta

Progi zostały ręcznie zaokrąglone do "ładnych" wartości.
Nie są dokładnymi kwintylami — to przybliżenie empiryczne.
```

*\*Procenty orientacyjne, zależą od dokładnego rozkładu.*

---

## 6. Buckety rozstępu

Źródło: `GameRegistry.php::registerDefaults()`

### Lotto / Lotto Plus

```
rozstęp:  0 ──── 19 ──── 29 ──── 39 ──── 44 ──── 48
bucket:      XS      S       M       L       XL
```

| Bucket | Zakres | Opis |
|---|---|---|
| XS | 0–19 | Bardzo mały — liczby skupione blisko siebie |
| S | 20–29 | Mały |
| **M** | **30–39** | **Średni — typowy rozstęp** |
| L | 40–44 | Duży |
| XL | 45–48 | Bardzo duży — prawie pełny zakres puli |

### Mini Lotto

```
rozstęp:  0 ──── 12 ──── 22 ──── 31 ──── 37 ──── 41
bucket:      XS      S       M       L       XL
```

### Skąd te progi?

```
Max rozstęp:
  Lotto:  49 - 1 = 48
  Mini:   42 - 1 = 41

Rozstęp K liczb z puli N ma rozkład oparty na statystyce pozycyjnej.

Oczekiwany rozstęp:
  Lotto:  E[range] ≈ (N+1) × (K-1)/(K+1) = 50 × 5/7 ≈ 35.7
  Mini:   E[range] ≈ 43 × 4/6 ≈ 28.7

Progi wyznaczone empirycznie tak, aby M obejmowało ~50% losowań,
a XS/XL po ~5–10%.
```

---

## 7. Progi popularności profilu

Źródło: `validator.php`

| Stała | Wartość | Efekt w UI |
|---|---|---|
| Próg "Popularny" | **2.0%** | Badge zielony: "Popularny" |
| Próg "Rzadki" | **0.5%** | Badge żółty: "Rzadki" |
| Poniżej 0.5% | — | Badge czerwony: "Bardzo rzadki" |

### Dlaczego 2.0% i 0.5%?

```
Łączna liczba unikalnych profili w Lotto: ~200–400

Gdyby wszystkie były równie częste:
  1 / 300 ≈ 0.33% na profil

W rzeczywistości rozkład jest nierównomierny:
  Top profile: 3–5% (np. "3e3o_3l3h_sM_c0_rM")
  Typowe:      0.5–2%
  Rzadkie:     < 0.5%
  Unikalne:    0.01–0.1% (wylosowane raz lub dwa)

Progi:
  > 2.0% = górne ~10% profili → "popularny"
  0.5–2% = środek              → "rzadki"
  < 0.5% = ogon rozkładu       → "bardzo rzadki"
```

**Przykład:**
```
Profil "3e3o_3l3h_sM_c0_rL":
  Wystąpił 312 razy z 7000 losowań → 4.46%
  → Badge: "Popularny" ✓ (4.46 > 2.0)

Profil "6e0o_1l5h_sXL_c0_rXS":
  Wystąpił 2 razy z 7000 → 0.03%
  → Badge: "Bardzo rzadki" (0.03 < 0.5)
```

---

## 8. Heatmapa — kwintyle

Źródło: `stats.php` (funkcja `_heatmap_bucket`) + `helpers.php::heatmap_bucket_color()`

| Stała | Wartość | Po co? |
|---|---|---|
| Liczba bucketów | **5** | Kwintyle — dzielą dane na 5 równych grup po 20% |

### Jak to działa?

```
1. Zbierz window_freq dla każdej liczby 1..poolSize
2. Posortuj rosnąco
3. Wyznacz 4 progi kwintylowe:

   Q1 = wartość na pozycji ⌊count × 1/5⌋    (20. percentyl)
   Q2 = wartość na pozycji ⌊count × 2/5⌋    (40. percentyl)
   Q3 = wartość na pozycji ⌊count × 3/5⌋    (60. percentyl)
   Q4 = wartość na pozycji ⌊count × 4/5⌋    (80. percentyl)

4. Przypisz bucket:
   freq ≤ Q1 → bucket 0 (najrzadsze 20%)
   freq ≤ Q2 → bucket 1
   freq ≤ Q3 → bucket 2
   freq ≤ Q4 → bucket 3
   freq >  Q4 → bucket 4 (najczęstsze 20%)
```

### Paleta kolorów

| Bucket | Kolor tła | Kolor tekstu | Znaczenie |
|---|---|---|---|
| 0 | `#e7e8e9` (szary) | `#414754` | Rzadko (bottom 20%) |
| 1 | `#90bafe` (niebieski) | `#001a41` | Poniżej średniej |
| 2 | `#d8e2ff` (jasny) | `#004493` | Średnio |
| 3 | `#ffb870` (pomarańcz) | `#2c1600` | Powyżej średniej |
| 4 | `#8b5000` (brązowy) | `#ffffff` | Często (top 20%) |

### Dlaczego kwintyle a nie stałe progi?

```
Kwintyle są RELATYWNE — dopasowują się do danych.
  → Zawsze dokładnie 20% liczb w każdym bucketcie
  → Działa bez zmian dla Lotto (49 liczb) i Mini (42 liczby)
  → Automatycznie skaluje się do nowych gier

Stałe progi byłyby ABSOLUTNE:
  → Wymagałyby ręcznego doboru per gra
  → Mogłyby dać 0 lub 49 liczb w jednym bucketcie
```

---

## 9. Generator — wagi i limity

Źródło: `generator.php`

| Stała | Wartość | Po co? |
|---|---|---|
| Formuła wagi | `window_freq + 1` | Prawdopodobieństwo wylosowania proporcjonalne do częstości |
| Max prób | **100 000** | Limit iteracji generowania z filtrami |
| Max kuponów | **20** | Max liczba kuponów na jedno generowanie |
| Top-N gorących | **10** | Ile najczęstszych liczb tworzy "top gorących" |

### Filtry generatora — kompletna lista

| Filtr | Parametr | Zakres | Działanie |
|---|---|---|---|
| Suma min/max | `sum_min`, `sum_max` | 0 – poolSize×k | Odrzuć jeśli suma poza zakresem |
| Parzyste min/max | `even_min`, `even_max` | 0 – k | Odrzuć jeśli even_count poza zakresem |
| Niskie min/max | `low_min`, `low_max` | 0 – k | Odrzuć jeśli low_count poza zakresem |
| Max par sąsiadów | `consec_max` | 0 – k-1 | Odrzuć jeśli consecutive > limit |
| Max cyfr jedności | `last_digit_max` | 1 – k | Odrzuć jeśli last_digit_unique > limit |
| Min gorących | `hot_min` | 0 – k | Odrzuć jeśli mniej niż N z top-10 |
| Max z dziesiątki | `decades_max` | 1 – k | Odrzuć jeśli jakaś dziesiątka ma > N liczb z kuponu |
| Min różnych dziesiątek | `decades_min` | 1 – ceil(poolSize/10) | Odrzuć jeśli kupon kryje < N różnych dziesiątek |
| Profil strukturalny | `profile_hashes[]` | wybór z listy | Tylko dla gier o stałym pickCount |

**Uwaga dla Multi Multi:** Filtry `sum` i `profile_hash` są nieużyteczne
dla zakładów o zmiennym `betPickCount` — patrz sekcja 12.

### Formuła wagi: `freq + 1`

```
Dlaczego + 1?

  Liczba nigdy niewylosowana w oknie 500: freq = 0
    Bez +1: weight = 0 → NIGDY nie może być wybrana
    Z +1:   weight = 1 → ma szansę (minimalną, ale niezerową)

  Gorąca liczba (freq = 75): weight = 76
  Zimna liczba (freq = 45):  weight = 46
  Nieobecna (freq = 0):      weight = 1

  Proporcja gorąca:zimna:nieobecna = 76:46:1
  → Gorąca jest ~76× bardziej prawdopodobna niż nieobecna
  → Ale nieobecna wciąż może wypaść
```

### Max prób: 100 000

```
Dlaczego 100 000?

  Bez filtrów: generowanie 1 kuponu = 1 próba
  Z agresywnymi filtrami: może potrzeba 1000+ prób na 1 kupon

  Scenariusze:
    Luźne filtry:     ~1–5 prób/kupon → 20 kuponów w <100 prób
    Średnie filtry:   ~50–200 prób/kupon → 20 kuponów w ~4000 prób
    Agresywne filtry: ~5000+ prób/kupon → może nie zebrać 20

  100 000 = wystarczająco na rozsądne filtry
            przy max 20 kuponach → 5000 prób/kupon budżet
            czas wykonania: ~100–200ms na typowym serwerze
```

### Top-10 gorących

```
Dlaczego 10?

  Pula Lotto: 49 liczb → top 10 = ~20% puli
  Pula Mini:  42 liczby → top 10 = ~24% puli

  Używane w:
    1. Generatorze: filtr "min. gorących w kuponie" (hot_min)
    2. UI: podświetlanie gorących liczb pomarańczowym kolorem

  10 to kompromis:
    Za mało (np. 3)  → zbyt selektywne, za wiele filtrowanych kuponów
    10               → sensowna grupa "najczęstszych"
    Za dużo (np. 25) → połowa puli to "gorące" — traci sens
```

---

## 10. Heatmapa generatora — progi kolorów

Źródło: `generator.php` (inline heatmap w formularzu)

| Stała | Wartość | Kolor | Znaczenie |
|---|---|---|---|
| Próg "gorąca" | `ratio > 0.7` | `var(--tertiary)` pomarańcz | Freq > 70% max freq |
| Próg "ciepła" | `ratio > 0.4` | `var(--secondary-container)` | Freq > 40% max freq |
| Poniżej | — | `var(--surface-container-high)` szary | Freq ≤ 40% max freq |
| Top-10 | in `$top10` | `var(--tertiary)` pomarańcz | Nadpisuje powyższe — zawsze pomarańcz |

### Jak to działa?

```
ratio = freq(n) / max_freq_in_window

Przykład (max freq = 80):
  Liczba z freq 72 → ratio = 72/80 = 0.90 → gorąca (pomarańcz)
  Liczba z freq 40 → ratio = 40/80 = 0.50 → ciepła (niebieski)
  Liczba z freq 20 → ratio = 20/80 = 0.25 → zimna (szary)
```

**Uwaga:** Te progi (0.7, 0.4) to wartości wizualne — służą tylko do kolorowania
heatmapy w generatorze. Nie mają uzasadnienia statystycznego (w odróżnieniu
od progów temperatury w stats.php, które oparte są na σ).

---

## 11. Paginacja i UI

Źródło: `draws.php`, `stats.php`

| Stała | Wartość | Plik | Po co? |
|---|---|---|---|
| Wiersze/stronę | **50** | `draws.php` | Losowań na stronę w historii |
| Zakres paginacji | **±4** | `draws.php` | Linki do 4 stron przed i po bieżącej |
| Top gorące (dashboard) | **5** | `dashboard.php` | Liczba "gorących" w karcie bocznej |
| Top zimne (dashboard) | **3** | `dashboard.php` | Liczba "zimnych" w karcie bocznej |
| Top gorące (stats) | **4** | `stats.php` | Kulki gorących w sekcji bocznej |
| Top zimne (stats) | **3** | `stats.php` | Pozycje zimnych w sekcji bocznej |

---

## 12. Multi Multi — filtry dla zakładów o zmiennej liczbie liczb

Multi Multi umożliwia graczowi wybór **1–10 własnych liczb** z puli 80,
natomiast losowanie wyciąga zawsze **20 z 80**.

### Dlaczego suma i profile są nieużyteczne

Tabele `multi_multi_draw_profiles` zawierają profile pełnych losowań (20 liczb).
Zakład gracza (np. 6 liczb) ma inną przestrzeń sum i parzystych.
Porównanie hashów kuponów z hashami z bazy zawsze kończy się brakiem dopasowania.

Filtr profilu jest dlatego **ukryty w UI** dla Multi Multi.

### Metryki użyteczne — z proporcjonalnymi zakresami

Każda metryka poniżej działa dla dowolnego `k = betPickCount`,
o ile zakresy uwzględniają że losowanie 20/80 ma rozkład 50/50:

| Metryka | Proporcjonalny zakres dla k liczb |
|---|---|
| `even_count` | `[floor(k/2)-1, ceil(k/2)+1]` |
| `low_count` | `[floor(k/2)-1, ceil(k/2)+1]` |
| `consecutive` | `[0, floor(k/3)]` (unikanie skupień) |
| `decades_max` | `max(1, ceil(k/5))` — max liczb z jednej dziesiątki |
| `decades_min` | `max(1, ceil(k/2))` — min różnych dziesiątek |
| `hot_min` | zależnie od preferencji |

### Tabela proporcjonalnych wartości

```
k=2:   even 0–2 | low 0–2 | consec ≤1 | dec_max 1 | dec_min 1
k=4:   even 1–3 | low 1–3 | consec ≤1 | dec_max 1 | dec_min 2
k=6:   even 2–4 | low 2–4 | consec ≤2 | dec_max 2 | dec_min 3
k=8:   even 3–5 | low 3–5 | consec ≤2 | dec_max 2 | dec_min 4
k=10:  even 4–6 | low 4–6 | consec ≤3 | dec_max 2 | dec_min 5
```

### Dlaczego `decades_max ≤ 2` dla k=10?

```
8 dziesiątek (1–10, 11–20, ..., 71–80)
Wybierasz 10 liczb → oczekiwana gęstość: 10/8 = 1.25 na dziesiątkę

decades_max = 2  →  żadna dziesiątka nie ma > 2 liczb z kuponu
                →  dobre rozproszenie po puli 1–80

decades_max = 5  →  połowa kuponu (5 z 10) może być z 11–20
                →  skupienie = mało prawdopodobne wzorzec historycznie

Dla k=10: dec_max=2 to "zbalansowane", nie dec_max=k/2=5.
```

### Semantic: dwie metryki dziesiątek

```
decades_max  = max z jednej dziesiątki  →  bada SKUPIENIE
decades_min  = liczba różnych dziesiątek →  bada ROZPROSZENIE

Dla pełnego kuponu 10-liczbowego, idealny przypadek:
  10 liczb × 8 dziesiątek = brak możliwości pokrycia wszystkich 8
  realnie: 5–7 różnych dziesiątek

Kupon [1..10]:  decades_max=10, decades_min=1  → skupiony, odrzucony
Kupon [5,15,25,35,45,55,65,75,8,18]:
               decades_max=2,  decades_min=8  → rozproszony, OK
```

---

## Podsumowanie — co zmienić dodając nową grę

Przy dodawaniu nowej gry (np. Ekstra Pensja 5/35):

| Co | Gdzie | Automatyczne? |
|---|---|---|
| `pickCount`, `poolSize`, `lowThreshold` | DB: tabela `games` | Tak — INSERT |
| Sum bucket boundaries | `GameRegistry.php` | Trzeba dodać* |
| Range bucket boundaries | `GameRegistry.php` | Trzeba dodać* |
| `betPickMin`, `betPickMax` (jeśli variable) | `GameRegistry.php::get()` | Trzeba dodać |
| Progi temperatury (σ) | `stats.php` | **Automatyczne** (wyliczane z p) |
| Progi zaległości | `stats.php` | **Automatyczne** (1.0 / 2.0 to stałe) |
| Heatmapa kwintylowa | `stats.php` | **Automatyczne** (kwintyle) |
| Wagi generatora | `generator.php` | **Automatyczne** (freq+1) |
| Filtry form z betPickCount | `generator.php` | **Automatyczne** (clamping do k) |
| Selektor profilu | `generator.php` | Automatycznie ukryty dla variable-bet |

*\*Bucket boundaries trzeba wyliczyć raz na podstawie μ i σ rozkładu sumy/rozstępu nowej gry.*
