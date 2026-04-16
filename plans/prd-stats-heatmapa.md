# PRD: Heatmapa częstości na stronie statystyk

## Problem Statement

Użytkownik przeglądający stronę statystyk widzi jedynie tabelę z liczbami posortowaną według wybranej kolumny. Aby zauważyć wzorce rozkładu „gorących" i „zimnych" liczb na całej puli, musi mentalnie skanować wiele wierszy tabeli. Brakuje szybkiego, wizualnego przeglądu — jednego rzutu oka na wszystkie liczby jednocześnie.

## Solution

Na stronie statystyk, nad istniejącą tabelą, dodać heatmapę numbers grid. Każda komórka reprezentuje jedną liczbę (1 do pool_size). Komórki są ułożone w rzędach po 10 liczb (1–10, 11–20, 21–30 itd.). Kolor tła komórki odpowiada częstości wystąpień liczby w ostatnich 500 losowaniach — od jasnoniebieskiego (rzadko) do głębokiego granatowego (często), w spójnym odcieniu bazowym #1a3a6b. Pod heatmapą widnieje legenda z paskiem gradientu i etykietami wartości min/max. Po najechaniu kursorem na komórkę pojawia się tooltip z dokładną liczbą wystąpień.

## User Stories

1. Jako analityk gier liczbowych chcę zobaczyć heatmapę wszystkich liczb na jednym ekranie, żeby szybko ocenić rozkład częstości bez konieczności skanowania tabeli wiersz po wierszu.
2. Jako użytkownik chcę, żeby liczby były ułożone w rzędach po 10 (1–10, 11–20 itd.), żebym mógł łatwo odczytać każdą wartość i porównać sąsiadujące dziesiątki.
3. Jako użytkownik chcę, żeby intensywność koloru była proporcjonalna do częstości w ostatnich 500 losowaniach, żeby od razu widzieć, które liczby były ostatnio „gorące".
4. Jako użytkownik chcę, żeby kolory były subtelne w odcieniu niebieskim spójnym z resztą interfejsu, żeby heatmapa nie dominowała wizualnie nad innymi elementami strony.
5. Jako użytkownik chcę, żeby najsłabiej losowane liczby miały jasne (prawie białe) tło, a najczęściej losowane — głęboki granat (#1a3a6b lub podobny), żebym natychmiast odróżniał wartości krańcowe.
6. Jako użytkownik chcę najechać kursorem na dowolną komórkę heatmapy i zobaczyć tooltip z dokładną wartością window_freq dla tej liczby, żebym mógł odczytać precyzyjną liczbę bez sięgania do tabeli.
7. Jako użytkownik chcę widzieć numer wewnątrz każdej komórki heatmapy, żebym wiedział, której liczby dotyczy dana komórka.
8. Jako użytkownik chcę, żeby heatmapa znajdowała się nad istniejącą tabelą statystyk, żeby stanowiła skrótowe wprowadzenie do pełnych danych.
9. Jako użytkownik chcę, żeby pod heatmapą znajdował się pasek legendy (gradientu) z etykietami wartości minimalnej i maksymalnej, żebym wiedział, do czego odnosi się skala kolorów.
10. Jako użytkownik przeglądający dane Mini Lotto (42 liczby) chcę, żeby ostania niekompletna dziesiątka (41–42) wyrenderowała się poprawnie bez pustych komórek zakłócających układ.
11. Jako użytkownik chcę, żeby heatmapa reagowała na filtr gry (Lotto / Lotto Plus / Mini Lotto), żeby zawsze wyświetlała dane dotyczące aktualnie wybranej gry.
12. Jako użytkownik chcę, żeby heatmapa była czytelna na ekranach mobilnych (responsywna siatka), żebym mógł korzystać z niej na telefonie.
13. Jako użytkownik chcę, żeby tekst numeru w komórce był czytelny zarówno na jasnym, jak i ciemnym tle, żebym bez problemu odczytał numer bez względu na intensywność koloru.
14. Jako użytkownik chcę, żeby heatmapa używała tej samej puli danych (window_freq) co kolumna „Ost. 500" w tabeli poniżej, żebym miał spójny obraz w obu widokach.
15. Jako użytkownik chcę, żeby heatmapa nie wymagała żadnych dodatkowych zapytań do bazy danych — korzystała z danych już pobranych na tej samej stronie, żeby nie spowalniać ładowania.

## Implementation Decisions

### Moduły do zbudowania / zmodyfikowania

1. **Funkcja normalizacji koloru (`heatmap_color`)** — czysta funkcja przyjmująca wartość, minimum i maksimum puli; zwraca ciąg CSS `rgb(...)` interpolowany liniowo w przestrzeni RGB między kolorem najjaśniejszym a bazowym (#1a3a6b). Umieszczona w `helpers.php` lub inline w `stats.php`.

2. **Renderer heatmapy HTML** — funkcja generująca siatkę `<div>` (lub tabelę) z komórkami ułożonymi w rzędach po 10. Dla każdej liczby 1..pool_size ustawia `background-color` z `heatmap_color` oraz atrybut `title` z dokładną wartością window_freq.

3. **Renderer legendy** — gradient CSS od koloru minimalnego do maksymalnego z etykietami „min: X" i „max: Y" pod paskiem.

4. **Modyfikacja `stats.php`** — sekcja heatmapy wstawiona za formularzem filtrów, przed istniejącą tabelą; używa już obliczonego `$windowFreqMap` i `$poolSize`.

### Decyzje techniczne

- **Dane źródłowe**: `$windowFreqMap` — już obliczone na stronie, zero dodatkowych zapytań SQL.
- **Kolory**: interpolacja liniowa RGB między `#e8edf5` (jasny niebieski, freq = 0 lub min) a `#1a3a6b` (ciemny granat, freq = max). Dla liczby o `window_freq = 0` stosowany jest najjaśniejszy odcień (#e8edf5) — bez wizualnego wyróżnienia braku danych, spójne ze skalą.
- **Edge case min = max**: gdy wszystkie liczby mają identyczną window_freq, każda komórka dostaje kolor środkowy skali (50% interpolacji).
- **Układ siatki**: CSS Grid `grid-template-columns: repeat(10, 40px)` z `gap: 4px`. Komórki 40×40 px, kształt koło (`border-radius: 50%`), spójne z istniejącym `.ball`.
- **Responsywność**: brak specjalnej obsługi mobile — nie priorytet w tej iteracji.
- **Tooltip**: natywny atrybut HTML `title="7: 34/500"` — brak zależności od JS.
- **Kolor tekstu w komórce**: czarny (`#222`) gdy freq < 40% max; biały (`#fff`) gdy freq ≥ 40% max.
- **Format numeru**: bez dopełnienia zerami (np. „7", nie „07") — spójne z `.ball`.
- **Tytuł sekcji**: `<h3>` „Heatmapa częstości – ostatnie 500 losowań".
- **Pozycja w stats.php**: po `<p>Łącznie losowań...</p>`, przed blokiem objaśnienia kolumn.
- **CSS**: inline `<style>` na górze `stats.php` — izolowane, bez modyfikacji `index.php`.
- **Legenda**: pasek gradientu CSS tej samej szerokości co siatka (~436 px = 10×40px + 9×4px gap), etykiety „min: X" i „max: Y" po bokach.
- **`heatmap_color()` placement**: funkcja dodana do `helpers.php` — możliwe wielokrotne użycie na innych stronach.
- **Brak filtracji dat**: window_freq jest zawsze liczone dla ostatnich 500 losowań niezależnie od filtra dat — heatmapa odzwierciedla tę samą logikę.

### Zmiany schematu / API

Brak — funkcja czysto frontendowa, korzysta z istniejących danych w pamięci.

## Testing Decisions

**Co stanowi dobry test:**
Testuj wyłącznie zachowanie zewnętrzne modułu (jego publiczny interfejs/wyjście), nie szczegóły implementacji.

**Moduły do przetestowania:**

1. **`heatmap_color(int $value, int $min, int $max): string`**
   - Wartość = min → kolor najjaśniejszy (`#e8edf5` lub odpowiednik rgb).
   - Wartość = max → kolor najciemniejszy (`#1a3a6b`).
   - Wartość pośrednia → kolor między granicami (weryfikacja interpolacji liniowej).
   - min = max (wszystkie liczby jednakowo częste) → bez dzielenia przez zero, zwraca stały kolor.
   - value < min i value > max → clamp do granic (defensywne przypadki).

2. **Renderer heatmapy (integracyjny/unit)**
   - Pool size 10 → generuje dokładnie 1 wiersz z 10 komórkami.
   - Pool size 12 → generuje 2 wiersze (10 + 2 komórki w ostatnim).
   - Każda komórka zawiera prawidłowy numer (1..pool_size).
   - Atrybut `title` komórki zawiera wartość window_freq dla danej liczby.

**Istniejące wzorce testów:** Brak zidentyfikowanych plików testowych w repozytorium — testy będą pierwszymi w projekcie. Zalecane PHPUnit dla testów jednostkowych funkcji pomocniczych.

## Out of Scope

- Animacje lub efekty przejścia przy zmianie gry.
- Możliwość przełączania metryki heatmapy (total_freq, overdue_score) — tylko window_freq.
- Klikalna komórka heatmapy scrollująca do wiersza w tabeli.
- Eksport heatmapy do obrazka / PDF.
- Konfiguracja okna czasowego (inne niż ostatnie 500 losowań).
- Kolorowanie według wielu wymiarów jednocześnie.

## Further Notes

- Pula liczb zależy od gry: Lotto = 1–49, Lotto Plus = 1–49, Mini Lotto = 1–42. Renderer musi pobierać `$poolSize` dynamicznie.
- Z uwagi na subtelność kolorów (jasny–ciemny niebieski), heatmapa nie zastępuje tabeli — jest jej dopełnieniem.
- Interpolacja koloru powinna być liniowa w przestrzeni sRGB (wystarczająca dla tej skali danych); przestrzeń Lab / OKLCH wychodzi poza zakres.
