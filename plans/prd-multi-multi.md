# PRD: Nowa gra — Multi Multi

## Problem Statement

Aplikacja obsługuje gry Lotto, Lotto Plus i Mini Lotto. Brakuje obsługi gry **Multi Multi** — popularnej polskiej loterii kenotycznej, w której losowane jest 20 liczb z puli 1–80, dwa razy dziennie (rano i wieczorem). Użytkownik nie może przeglądać historii losowań Multi Multi ani analizować statystyk tej gry w aplikacji. Ponadto adres URL importu danych dla Mini Lotto jest nieprawidłowy i wymaga korekty.

## Solution

Dodać Multi Multi jako pełnoprawną grę w aplikacji: import danych historycznych i synchronizacja, obliczanie wszystkich standardowych metryk (suma, parzyste, niskie, kolejne, dekady, rozstęp, unikalne ostatnie cyfry), strona statystyk z heatmapą (identyczna jak dla innych gier), dedykowane widoki do analizy współwystępowania par i trójek liczb oraz analizy przerw (ile losowań temu pojawiła się każda liczba). Jednocześnie naprawić błędny URL synchronizacji Mini Lotto.

## User Stories

### Import i synchronizacja

1. Jako administrator chcę zaimportować pełną historię losowań Multi Multi z http://mbnet.com.pl/ml.txt, żeby mieć w bazie wszystkie historyczne wyniki.
2. Jako administrator chcę uruchomić synchronizację Multi Multi z linii poleceń (`php import.php multi_multi`), żeby regularnie aktualizować bazę.
3. Jako administrator chcę uruchomić synchronizację Multi Multi przez panel webowy (import.php?game=multi_multi), żeby odświeżać dane bez dostępu do CLI.
4. Jako administrator chcę, żeby import logował wynik do tabeli `sync_log`, żebym wiedział, ile losowań zostało dodanych i kiedy.
5. Jako administrator chcę, żeby adres URL Mini Lotto był zaktualizowany do http://www.mbnet.com.pl/el.txt, żeby synchronizacja Mini Lotto pobierała prawidłowe dane.
6. Jako administrator chcę, żeby parser poprawnie rozpoznał format pliku ml.txt (numer. DD.MM.YYYY n1,n2,...,n20), żeby żadne losowanie nie zostało pominięte.
7. Jako administrator chcę, żeby zduplikowane losowania (ten sam draw_number) były ignorowane przy kolejnym imporcie, żeby baza nie zawierała duplikatów.

### Przeglądanie losowań

8. Jako użytkownik chcę wybrać grę Multi Multi w nawigacji aplikacji, żeby zobaczyć losowania i statystyki tej gry.
9. Jako użytkownik chcę przeglądać tabelę historycznych losowań Multi Multi z datą, numerem losowania i 20 wylosowanymi liczbami, żeby śledzić wyniki.
10. Jako użytkownik chcę widzieć metryki (sumę, parzyste itd.) przy każdym losowaniu Multi Multi, żeby porównywać strukturę wyników.
11. Jako użytkownik chcę filtrować historię losowań według zakresu dat, żeby skupić się na wybranym okresie.

### Statystyki liczbowe (hot/cold/gap)

12. Jako analityk chcę zobaczyć heatmapę wszystkich 80 liczb Multi Multi z częstością wystąpień w ostatnich N losowaniach, żeby jednym rzutem oka ocenić rozkład „gorących" i „zimnych" liczb.
13. Jako analityk chcę posortować tabelę statystyk według częstości globalnej, częstości okienkowej, ostatniego wystąpienia, przerwy bieżącej i wyniku przetrzymania (overdue_score), żeby znaleźć liczby o interesujących właściwościach.
14. Jako analityk chcę zobaczyć dla każdej liczby 1–80: łączną liczbę wystąpień, częstość w ostatnich 500 losowaniach, numer losowania ostatniego wystąpienia, bieżącą przerwę i średni interwał między wystąpieniami, żeby ocenić regularność pojawiania się liczby.
15. Jako analityk chcę zobaczyć wykres lub wskaźnik „przeoczenia" (overdue_score) dla każdej liczby, żeby szybko znaleźć liczby rzadko niepojawiane się mimo statystycznej oczekiwaności.
16. Jako analityk chcę filtrować tabelę statystyk Multi Multi według zakresu dat, żeby analizować podzbiory historii.

### Analiza współwystępowania (pary i trójki)

17. Jako analityk chcę zobaczyć ranking najczęściej i najrzadziej współwystępujących par liczb w historii Multi Multi, żeby odkryć kombinacje pojawiające się częściej lub rzadziej niż losowość wskazuje.
18. Jako analityk chcę zobaczyć dla wybranej pary liczb (np. 7 i 35): obserwowaną częstość wspólnych wystąpień, oczekiwaną częstość przy losowości, stosunek tych wartości (lift) oraz p-value z testu statystycznego, żeby ocenić, czy para jest faktycznie nadreprezentowana.
19. Jako analityk chcę filtrować ranking par według minimalnej liczby wspólnych wystąpień, żeby odfiltrować rzadkie przypadki bez znaczenia statystycznego.
20. Jako analityk chcę zobaczyć ranking najczęstszych trójek liczb w historii Multi Multi, żeby wyszukiwać szersze wzorce kombinacji.
21. Jako analityk chcę widzieć przy każdej trójce jej obserwowaną częstość oraz lift względem oczekiwań losowych, żeby ocenić siłę wzorca.
22. Jako analityk chcę odpowiedź na pytanie: czy czwórki i piątki są warte analizowania? — w UI zawarto informację statystyczną (przy 10 000 losowań oczekiwana liczba wystąpień każdej konkretnej czwórki wynosi ~30 — granicznie użyteczne; czwórki są dostępne jako rozszerzenie opcjonalne, brak w wersji podstawowej).

### Analizy metryk strukturalnych

23. Jako analityk chcę zobaczyć rozkład sum 20 wylosowanych liczb w bucketsach (XS/S/M/L/XL), żeby ocenić, czy ostatnie losowania odchylają się od historycznej normy.
24. Jako analityk chcę zobaczyć rozkład rozstępów (max–min 20 liczb) w bucketsach, żeby ocenić, jak szeroko rozkładają się wyniki.
25. Jako analityk chcę zobaczyć rozkłady metryk (parzyste, niskie ≤40, kolejne pary, ile dekad użytych, unikalne końcówki) dla Multi Multi identycznie jak dla innych gier, żeby korzystać ze znanych już widoków.
26. Jako analityk chcę zobaczyć profile losowań Multi Multi (profil = kombinacja wartości metryk) z informacją, jak często dany profil się powtarzał i kiedy ostatnio wystąpił.

### Generowanie zakładów

27. Jako użytkownik chcę wybrać Multi Multi w generatorze zakładów, żeby wygenerować propozycję 1–10 liczb z puli 1–80.
28. Jako użytkownik chcę, żeby generator zakładów Multi Multi korzystał z tych samych statystyk (overdue_score, hot/cold) co pozostałe gry, żeby mieć spójne podpowiedzi.

## Implementation Decisions

### Nowe i zmodyfikowane moduły

1. **`games` table (schema.sql)** — Nowa encja `multi_multi`: slug `multi_multi`, name `Multi Multi`, pick_count `20`, pool_size `80`, has_bonus `FALSE`, low_threshold `40`, sync_url `http://mbnet.com.pl/ml.txt`. Aktualizacja rekordu `mini_lotto` — sync_url zmieniony z `https://mbnet.com.pl/ml.txt` na `http://www.mbnet.com.pl/el.txt`.

2. **`multi_multi_draws` table (schema.sql)** — Tabela wyników identyczna strukturą jak pozostałe `*_draws`, ale z kolumnami n1–n20 (TINYINT). Kolumny metryk i indeksy takie same (sum_total, even_count, low_count, consecutive, decades_used, range_spread, last_digit_unique, profile_hash).

3. **`multi_multi_draw_profiles` table (schema.sql)** — Tabela profili zidentyczna z `lotto_draw_profiles` (hash, metadane, total_draws, pct_of_total, last_seen, first_seen).

4. **`multi_multi_pairs` table (schema.sql)** — Tabela współwystępowania par: PRIMARY KEY (n1, n2), kolumny `count INT`, `last_seen DATE`. C(80,2) = 3 160 możliwych par — rozmiar zarządzalny.

5. **`multi_multi_triples` table (schema.sql)** — Tabela trójek: PRIMARY KEY (n1, n2, n3), kolumny `count INT`, `last_seen DATE`. C(80,3) = 82 160 możliwych trójek — rozmiar akceptowalny, indeksujemy przyrostowo przy każdym imporcie.

6. **`GameRegistry::registerDefaults()` (GameRegistry.php)** — Rejestracja strategii kubełków dla Multi Multi:
   - **sumBuckets** (`multi_multi`): XS ≤ 675, S 676–745, M 746–875, L 876–945, XL ≥ 946. Progi obliczone na podstawie rozkładu normalnego sumy z μ = 810, σ ≈ 90 (próbka bez zwracania n=20 z N=80).
   - **rangeBuckets** (`multi_multi`): XS ≤ 62, S 63–70, M 71–75, L 76–78, XL ≥ 79. Progi oparte na oczekiwanym rozstępie ≈ 72 przy 20 liczbach z 80.

7. **`MbnetLineParser`** — Brak zmian. Parametryzowany przez `pickCount=20` obsługuje format ml.txt automatycznie.

8. **`MetricCalculator`** — Brak zmian. Funkcja `computeMetrics()` jest już ogólna: `low_threshold = 40`, `decades` grupuje po 10 w zakresie 1–80 (dekady 0–9 przez `intdiv($n-1, 10)`), `consecutive` liczy kolejne pary. Dla 20 liczb z 80 wszystkie metryki mają znaczący rozkład.

9. **`CoOccurrenceRepository` (nowy plik w src/)** — Moduł do zapisu i odczytu par/trójek:
   - `upsertPairsForDraw(array $numbers, string $date): void` — aktualizuje `multi_multi_pairs` dla wszystkich C(20,2)=190 par z danego losowania.
   - `upsertTriplesForDraw(array $numbers, string $date): void` — aktualizuje `multi_multi_triples` dla wszystkich C(20,3)=1140 trójek.
   - `getTopPairs(int $limit, int $minCount): array` — zwraca pary z największym lift = observed/expected.
   - `getTopTriples(int $limit, int $minCount): array` — analogicznie dla trójek.
   - Wywołanie: `upsert` wywoływane przez `import.php` po każdym pomyślnym zapisie losowania.

10. **`stats.php`** — Brak zmian architektonicznych. Istniejąca logika (heatmapa, gap analysis, window_freq, overdue_score) jest w pełni parametryczna przez `$gameDef` i obsługuje pool_size=80 automatycznie. Heatmapa wyrenderuje siatce 8 dziesiątek po 10 liczb.

11. **Nowa strona `cooccurrence.php`** — Dedykowana podstrona dostępna z nawigacji gry: wyświetla ranking par i trójek z kolumnami (liczba 1, liczba 2[, liczba 3], count, expected, lift). Filtr: minimalna liczba wystąpień. Tabele obsługują sortowanie. Dane pobierane z `CoOccurrenceRepository`.

### Decyzje architektoniczne

- **Czwórki i piątki**: poza zakresem v1. Przy ~10 000 losowań każda konkretna czwórka z C(80,4)=1 581 580 pojawia się średnio ~30 razy — granicznie diagnostyczne. Liczba wierszy (1,58 M) czyni pełne przechowywanie kosztownym. Dodać w v2 jeśli będzie potrzeba.
- **Analiza rano/wieczór**: poza zakresem v1. Format ml.txt nie zwiera informacji o porze dnia.
- **Przeliczenie par/trójek dla historii**: import buduje tabele `pairs` i `triples` od zera przy pełnym imporcie; przyrostowa synchronizacja (`sync.php`) dokłada wpisy dla nowych losowań.
- **Oczekiwana częstość pary**: expected = totalDraws × C(20,2)/C(80,2) = totalDraws × 190/3160 ≈ totalDraws × 0.0601.
- **Oczekiwana częstość trójki**: expected = totalDraws × C(20,3)/C(80,3) = totalDraws × 1140/82160 ≈ totalDraws × 0.01388.

### Schema changes (podsumowanie)

- INSERT do `games`: slug=`multi_multi`, pick_count=20, pool_size=80, low_threshold=40.
- UPDATE `games` SET sync_url=`http://www.mbnet.com.pl/el.txt` WHERE slug=`mini_lotto`.
- CREATE TABLE `multi_multi_draws` (n1–n20 + metryki).
- CREATE TABLE `multi_multi_draw_profiles`.
- CREATE TABLE `multi_multi_pairs` (n1, n2, count, last_seen).
- CREATE TABLE `multi_multi_triples` (n1, n2, n3, count, last_seen).

## Testing Decisions

### Co to jest dobry test?

Dobry test weryfikuje **zewnętrzne zachowanie modułu** (jego publiczne API, wynik zwrócony lub stan zapisany), a nie szczegóły implementacji. Nie testujemy prywatnych metod ani konkretnej kolejności instrukcji SQL — testujemy, że po wywołaniu metody dane wyjściowe są poprawne.

### Moduły do przetestowania

1. **`MetricCalculator` — testy dla 20 liczb z puli 1–80**
   - Wejście: 20 posortowanych liczb, `GameDefinition` z pool_size=80, low_threshold=40, pickCount=20.
   - Weryfikacja: sum_total = suma ręczna, even_count / low_count / consecutive / decades_used / range_spread / last_digit_unique z wartościami oczekiwanymi.
   - Prior art: `MetricCalculatorTest.php` — takie same typy asercji, ten sam schemat fabryki.

2. **`MbnetLineParser` — 20 liczb**
   - Wejście: linia w formacie `123. 14.04.2026 1,5,8,12,...,78` (20 liczb).
   - Weryfikacja: draw_number, draw_date, numbers (20 elementów posortowanych).
   - Prior art: `MbnetLineParserTest.php`.

3. **`CoOccurrenceRepository` — pary i trójki**
   - Test bazy w pamięci (SQLite lub mock): po `upsertPairsForDraw([1,2,3,...,20])` tabela ma ≥1 wpis dla pary (1,2) z count=1.
   - Po dwukrotnym imporcie tego samego draw_number z wynikiem zawierającym (1,2) — count pozostaje 1 (idempotentność lub deduplikacja przez draw_number).
   - `getTopPairs(limit:10, minCount:1)` zwraca posortowane malejąco po lift, max 10 rekordów, każdy z polami n1, n2, count, expected, lift.
   - Prior art: `DrawAnalysisTest.php` lub `ThresholdBucketStrategyTest.php` (wstrzykiwanie PDO z in-memory SQLite).

4. **`GameDefinitionFactory` (testy) — multi_multi**
   - Fabryka testowa zwraca `GameDefinition` z pickCount=20, poolSize=80, lowThreshold=40.
   - Prior art: `GameDefinitionFactory.php` w tests/ — dodać metodę `makeMultiMulti()`.

5. **`ThresholdBucketStrategy` — nowe progi Multi Multi**
   - Weryfikacja kubełków: 675 → XS, 676 → S, 746 → M, 876 → L, 946 → XL; null (brak wartości) → obsłużone bez wyjątku.
   - Prior art: `ThresholdBucketStrategyTest.php`.

## Out of Scope

- Analiza porannych vs. wieczornych losowań (plik źródłowy nie zawiera informacji o porze).
- Czwórki i piątki (patrz uzasadnienie w Implementation Decisions, planowane jako v2).
- Wariant Multi Multi Plus.
- Predykcja wyników (żadna analiza statystyczna nie zwiększa szans w grze losowej).
- Powiadomienia push / alerty o „przetrzymanych" liczbach.
- API REST do zewnętrznego dostępu do danych.

## Further Notes

- **Dwa losowania dziennie** powoduje, że historia przybywa dwukrotnie szybciej niż w Lotto. Przy imporcie pełnej historii (Multimulti działa od 2004, ~15 000+ losowań) przeliczenie wszystkich trójek jest jednorazową operacją kosztowną czasowo — rozważyć dodanie `--rebuild-cooccurrence` jako opcjonalnego przełącznika CLI.
- **Lift vs. p-value**: dla prostoty v1 wyświetlamy lift (observed/expected). Test chi-kwadrat lub test dwumianowy można dodać w v2 dla oceny istotności statystycznej.
- **Rozkład metryk Multi Multi** jest inny niż w Lotto: `decades_used` w zakresie 6–8 z 8 możliwych, `last_digit_unique` niemal zawsze = 10 (bo 20 liczb pokrywa wszystkie końcówki), `consecutive` średnio ~7 par z 19 możliwych. Opisy profili w `ProfileDescriber` mogą wymagać nowego zestawu progów komentarzy dla tej gry.
