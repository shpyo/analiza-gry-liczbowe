# Plan: Multi Multi

> Source PRD: [plans/prd-multi-multi.md](./prd-multi-multi.md)

## Architectural decisions

- **Routes**: wszystkie istniejące strony używają `?game=multi_multi` — brak nowych URL poza `cooccurrence.php?game=multi_multi`
- **Schema**: 4 nowe tabele — `multi_multi_draws` (n1–n20), `multi_multi_draw_profiles`, `multi_multi_pairs` (n1, n2, count, last_seen), `multi_multi_triples` (n1, n2, n3, count, last_seen); aktualizacja `games` INSERT oraz sync_url Mini Lotto
- **Key models**: `GameDefinition` bez zmian (parametryczny; pick_count=20, pool_size=80, low_threshold=40); nowy `CoOccurrenceRepository`; `MbnetLineParser` bez zmian (pick_count steruje parsowaniem)
- **Import pattern**: bez zmian — `import.php` i `sync.php` iterują po `allSlugs()`, logują do `sync_log`; rejestracja gry przez `games` table + `GameRegistry::registerDefaults()`
- **Bucket strategies**: nowe progi zarejestrowane w `GameRegistry::registerDefaults()` dla slug `multi_multi` — nie wpływają na inne gry
- **Co-occurrence**: upsert par i trójek wywoływany inline w pętli importu po każdym poprawnie zapisanym losowaniu; tabele budowane przyrostowo

---

## Phase 1: Rejestracja gry i import historii

**User stories**: 1, 2, 3, 4, 5, 6, 7, 8

### What to build

Zarejestrować grę Multi Multi w całym stosie: schema SQL, wiersz w tabeli `games`, dwie tabele wynikowe, rejestracja kubełków w `GameRegistry`. Naprawić URL Mini Lotto. Po tej fazie `import.php multi_multi` pobiera plik ml.txt (20 liczb na losowanie), parsuje go, zapisuje do `multi_multi_draws` z obliczonymi metrykami i loguje wynik do `sync_log`. Gra pojawia się w nawigacji aplikacji i można ją wybrać przez `?game=multi_multi`.

### Acceptance criteria

- [ ] `php import.php multi_multi` kończy się sukcesem i dodaje ≥1 losowanie do `multi_multi_draws`
- [ ] każde losowanie ma poprawnie obliczone: sum_total, even_count, low_count, consecutive, decades_used, range_spread, last_digit_unique
- [ ] `sync_log` zawiera wpis ze statusem `ok` dla `multi_multi`
- [ ] `sync.php?game=multi_multi` (z ważnym tokenem) dodaje tylko nowe losowania, nie duplikuje istniejących
- [ ] Mini Lotto synchronizuje dane z `http://www.mbnet.com.pl/el.txt`
- [ ] gra Multi Multi pojawia się w nawigacji aplikacji (top-navbar games) i jest wybieralna
- [ ] test: `MbnetLineParser(20, false)->parse('1. 14.04.2026 1,5,8,...,78')` zwraca tablicę z 20 posortowanymi liczbami i poprawnymi draw_number / draw_date
- [ ] test: `MetricCalculatorTest` obejmuje wejście z 20 liczbami przy GameDefinition(poolSize=80, lowThreshold=40)

---

## Phase 2: Statystyki i heatmapa dla puli 80 liczb

**User stories**: 9, 10, 11, 12, 13, 14, 15, 16, 23, 24, 25

### What to build

Upewnić się, że strona `stats.php` działa poprawnie dla Multi Multi bez modyfikacji architektonicznych. Kluczowe walidacje: heatmapa renderuje 8 rzędów po 10 komórek (liczby 1–80), kubełki sum i rozstępów używają dedykowanych progów Multi Multi (zarejestrowanych w fazie 1), wszystkie kolumny sortowania (total_freq, window_freq, last_seen_draw, current_gap, avg_interval, overdue_score) działają, filtr dat działa. Widok losowań (`draws.php`) wyświetla 20 kolumn liczb.

Nowe progi do zarejestrowania:
- **sumBuckets** `multi_multi`: XS ≤ 675 / S 676–745 / M 746–875 / L 876–945 / XL ≥ 946
- **rangeBuckets** `multi_multi`: XS ≤ 62 / S 63–70 / M 71–75 / L 76–78 / XL ≥ 79

### Acceptance criteria

- [ ] `stats.php?game=multi_multi` ładuje się bez błędów PHP
- [ ] heatmapa wyświetla 80 komórek ułożonych w 8 rzędów po 10
- [ ] dla losowania z 20 liczbami bucket sumy jest poprawny (np. suma 810 → M)
- [ ] bucket rozstępu jest poprawny (np. rozstęp 73 → M)
- [ ] sortowanie po każdej kolumnie (asc/desc) zwraca posortowane wyniki
- [ ] filtr dat zawęża zarówno tabelę losowań jak i dane window_freq
- [ ] `draws.php?game=multi_multi` wyświetla tabelę z kolumnami n1–n20
- [ ] test: `ThresholdBucketStrategy` dla progów Multi Multi — graniczne wartości każdego kubełka sumy i rozstępu

---

## Phase 3: Profile losowań

**User stories**: 26

### What to build

Przy zapisie każdego losowania (import i sync) obliczać i upsertować profil do `multi_multi_draw_profiles`. Widok profili działa dla `multi_multi`. Dostosować opisy profili w `ProfileDescriber` do typowych rozkładów 20 liczb z 80: `decades_used` norma 7–8 (nie 4–5 jak w Lotto), `last_digit_unique` niemal zawsze = 10, `consecutive` średnio ~7 par.

### Acceptance criteria

- [ ] po imporcie tabela `multi_multi_draw_profiles` zawiera ≥1 profil
- [ ] strona profili (jeśli istnieje jako widok) wyświetla dane Multi Multi bez błędów
- [ ] `profile_hash` w `multi_multi_draws` jest wypełniony dla każdego zaimportowanego losowania
- [ ] `ProfileDescriber` nie zwraca ogólnikowych/błędnych opisów dla wektorów typowych dla Multi Multi (np. decades_used=7 nie jest opisywane jako „mało dekad")
- [ ] test: `ProfileDescriberTest` obejmuje przypadki testowe z 20 liczbami (decades_used=8, consecutive=7, last_digit_unique=10)

---

## Phase 4: Analiza współwystępowania (pary i trójki)

**User stories**: 17, 18, 19, 20, 21, 22

### What to build

Stworzyć `CoOccurrenceRepository` obsługujący upsert par (C(20,2)=190 par na losowanie) i trójek (C(20,3)=1140 trójek na losowanie) do dedykowanych tabel. Import i sync wywołują upsert dla każdego nowego losowania. Nowa strona `cooccurrence.php?game=multi_multi` wyświetla dwa rankingi (pary i trójki) z kolumnami: liczby, count, expected, lift. Filtr minimalnej liczby wystąpień ogranicza wyniki. Uzasadnienie pominięcia czwórek i piątek wyjaśnione inline w UI.

- **Oczekiwana częstość pary**: `totalDraws × 190 / 3160`
- **Oczekiwana częstość trójki**: `totalDraws × 1140 / 82160`
- **Lift**: `count / expected`

### Acceptance criteria

- [ ] po imporcie N losowań tabela `multi_multi_pairs` zawiera wpisy dla par z najwyższym lift
- [ ] po imporcie N losowań tabela `multi_multi_triples` zawiera wpisy dla trójek
- [ ] ponowny import tego samego losowania nie zmienia wartości count (idempotentność lub deduplikacja przez draw_number)
- [ ] `cooccurrence.php?game=multi_multi` ładuje się bez błędów, wyświetla tabele par i trójek
- [ ] filtr `min_count=5` ukrywa pary z count < 5
- [ ] lift jest obliczony poprawnie (para z count=observed, expected=N×190/3160)
- [ ] test: `CoOccurrenceRepository` — upsert [1..20]: tabela zawiera parę (1,2) z count=1; drugi upsert tych samych liczb z tym samym draw_number nie zwiększa count; `getTopPairs(10, 1)` zwraca ≤10 rekordów posortowanych malejąco po lift

---

## Phase 5: Generator zakładów

**User stories**: 27, 28

### What to build

`generator.php` obsługuje Multi Multi: użytkownik wybiera, ile liczb chce wygenerować (1–10), generator proponuje zestaw liczb z puli 1–80 z wagami opartymi na overdue_score lub window_freq pobranych ze statystyk Multi Multi (takie samo podejście jak dla pozostałych gier).

### Acceptance criteria

- [ ] `generator.php?game=multi_multi` ładuje się bez błędów PHP
- [ ] formularz pozwala wybrać liczbę typów 1–10 (a nie 6 jak dla Lotto)
- [ ] wygenerowany zestaw zawiera dokładnie tyle liczb, ile wybrał użytkownik, z zakresu 1–80
- [ ] wygenerowane liczby różnią się między wywołaniami (losowość)
- [ ] strona nie ładuje danych z tabel innej gry (np. Lotto)
