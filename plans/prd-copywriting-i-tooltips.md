# PRD: Czytelne opisy, etykiety i wyjaśnienia w całej aplikacji

---

## Problem Statement

Użytkownik korzystający z aplikacji do analizy wyników lotto — entuzjasta z podstawową wiedzą statystyczną — napotyka w każdym miejscu interfejsu technicznie brzmiące, angielskie lub skrótowe nazwy metryk: `profile_hash`, `even_count`, `overdue_score`, `last_digit_unique`, `consecutive`. Nagłówki kolumn w tabelach nic mu nie mówią. Generator nie wyjaśnia, dlaczego wygenerował właśnie te liczby, a nie inne. Weryfikator po przeanalizowaniu kombinacji pokazuje dane liczbowe bez słowa komentarza — czy to typowy wzorzec, czy anomalia? Użytkownik nie wie, co z tymi wynikami zrobić.

Aplikacja jest napisana po polsku, ale jej dane i etykiety są w dużej mierze zrozumiałe tylko dla programisty, który ją stworzył.

---

## Solution

Wprowadzenie centralnego słownika tekstów (`texts.php`) jako pojedynczego źródła prawdy dla wszystkich etykiet, opisów i przykładów metryk. Na jego podstawie:

- każda tabela w aplikacji dostaje czytelne, polskie nagłówki kolumn z tooltipem wyjaśniającym metrykę i podającym konkretny przykład;
- strona Generatora po wygenerowaniu kuponu pokazuje sekcję „Dlaczego te liczby?" — krótkie wyjaśnienie w języku naturalnym, które filtry spełnia wygenerowana kombinacja i co mówi jej profil strukturalny;
- strona Weryfikatora po analizie kombinacji pokazuje sekcję „Co to znaczy?" — interpretację profilu w kontekście historycznym (czy to częsty wzorzec, czy rzadki, jak daleko odbiega od średniej);
- strona Statystyk dostaje opisowe nagłówki kolumn z ikonami sortowania i krótkim objaśnieniem przy każdej metryce.

---

## User Stories

1. Jako użytkownik oglądający tabelę losowań, chcę zobaczyć czytelny nagłówek „Suma liczb" zamiast `sum_total`, żeby od razu wiedzieć, co dana kolumna oznacza.
2. Jako użytkownik, chcę przy każdej kolumnie w tabeli móc najeść myszką na ikonkę „?" i zobaczyć krótki opis metryki wraz z przykładem, żeby rozumieć dane bez zaglądania do dokumentacji.
3. Jako użytkownik przeglądający statystyki częstości, chcę zobaczyć nagłówek „Zaległość (losowania)" zamiast `current_gap`, żeby intuicyjnie rozumieć, że chodzi o to, ile losowań minęło od ostatniego pojawienia się liczby.
4. Jako użytkownik korzystający z Generatora, chcę po wygenerowaniu kuponów zobaczyć sekcję „Dlaczego te liczby?", która w jednym zdaniu opisze każdy wygenerowany kupon: ile zawiera parzystych, niskich, jaką ma sumę i jaki profil — żebym rozumiał, że to nie jest czysto losowe.
5. Jako użytkownik korzystający z Generatora, chcę zobaczyć, jak ustawione przeze mnie filtry przełożyły się na wygenerowaną kombinację, żeby rozumieć związek między parametrami a wynikiem.
6. Jako użytkownik korzystający z Generatora, chcę przy każdym polu filtra (np. „Minimum parzystych") zobaczyć krótkie objaśnienie, co to oznacza i jaka wartość jest typowa historycznie, żebym mógł świadomie ustawiać filtry.
7. Jako użytkownik korzystający z Weryfikatora, chcę po wpisaniu kombinacji i kliknięciu „Analizuj" zobaczyć sekcję „Co to znaczy?", która powie mi w języku naturalnym, czy ten wzorzec jest typowy czy rzadki — np. „Ten profil padał w 4,7% losowań Lotto. To jeden z popularniejszych wzorców."
8. Jako użytkownik korzystający z Weryfikatora, chcę zobaczyć interpretację każdej wyliczonej metryki osobno (np. „Suma 147 — mieści się w najczęstszym przedziale środkowym"), żeby wiedzieć, które cechy kombinacji są standardowe, a które odbiegają od normy.
9. Jako użytkownik, chcę w Weryfikatorze zobaczyć, czy moja kombinacja kiedykolwiek padła, opisane naturalnym zdaniem — „Ta dokładna kombinacja nigdy nie padła w historii Lotto" lub „Ta kombinacja padła raz — 14 marca 2003 r.", zamiast suchego rekordu z bazy.
10. Jako użytkownik przeglądający tabelę statystyk per-liczba, chcę zobaczyć czytelny nagłówek „Wskaźnik zaległości" zamiast `overdue_score` z podpowiedzią, że wyższy wynik = liczba jest bardziej „spóźniona" względem swojej historycznej średniej.
11. Jako użytkownik, chcę nagłówki sortowania w tabeli statystyk były opisane tak, żebym wiedział, co oznacza kliknięcie — np. „Sortuj od najgorętszych" przy kolumnie częstości.
12. Jako użytkownik Dashboardu, chcę przy informacji o profilu ostatniego losowania (`3e3o_3l3h_sM_c1_rL`) zobaczyć rozkodowanie w czytelnym formacie — np. „3 parzyste, 3 niskie, suma średnia, 1 para kolejna, rozstęp duży" — żeby profil coś mi mówił.
13. Jako użytkownik, chcę w interfejsie nigdzie nie widzieć surowej wartości `profile_hash` bez jej tłumaczenia na ludzki język.
14. Jako użytkownik przeglądającym Draws (listę losowań), chcę kolumny metryczne (`even_count`, `low_count`, `consecutive` itp.) miały polskie nagłówki z ikonkami `?`, żebym rozumiał, co każda kolumna opisuje.
15. Jako użytkownik, chcę w formularzu Generatora pole „Ile kupeli wygenerować" miało etykietę „Liczba kuponów do wygenerowania (1–20)" zamiast technicznej nazwy, żebym nie musiał zgadywać zakresu.
16. Jako użytkownik, chcę etykiety „hot" i „cold" na Dashboardzie brzmiały po polsku — np. „Gorące liczby" i „Zimne liczby" — z krótkim wyjaśnieniem pod nagłówkiem co to w praktyce oznacza.
17. Jako użytkownik, chcę napis „Sync" w nawigacji był zastąpiony przez „Aktualizuj dane" lub podobny czytelny polskojęzyczny komunika,t żeby rozumieć co ta zakładka robi.
18. Jako użytkownik, chcę sekcja „Import" w nawigacji była opisana jako „Wczytaj dane historyczne" — dla odróżnienia od bieżącej synchronizacji.
19. Jako użytkownik korzystający z Generatora, chcę widzieć przy opcji „profil strukturalny" (profile_hash) listę hashów opisanych po ludzku — np. „3 parzyste / 3 niskie / suma M / bez par kolejnych / rozstęp duży" — a nie surowe kody.
20. Jako użytkownik, chcę przy metryce `consecutive` w każdym miejscu zobaczyć jasne objaśnienie i przykład: „Para kolejna to np. liczby 17 i 18 w jednym losowaniu. 0 par = żadne dwie liczby nie sąsiadują, 1 para = jedna taka kombinacja."
21. Jako użytkownik, chcę przy metryce `decades_used` zobaczyć przykład: „Dziesiątki: liczby 1–10 to pierwsza dziesiątka, 11–20 to druga itd. Wynik '4' oznacza, że losowanie zawierało liczby z 4 różnych przedziałów."
22. Jako użytkownik, chcę przy metryce `last_digit_unique` zobaczyć przykład: „Unikalne cyfry jedności: jeśli padły 3, 13, 23, to wszystkie kończą się na '3' — wynik to 1. Im wyżej, tym mniej powtórzeń na ostatniej cyfrze."
23. Jako użytkownik, chcę zamiast etykiet `sXS`, `sM`, `rL` zobaczyć słowne odpowiedniki na wszelkich stronach: „bardzo mała", „średnia", „duża" lub pełne rozkodowanie zakresu w tooltipie.
24. Jako mobilny użytkownik, chcę tooltips działały też na dotyk (tap zamiast hover), żebym miał dostęp do objaśnień na smartfonie.

---

## Implementation Decisions

### Centralne repozytorium tekstów — `texts.php`

Dedykowany plik `texts.php` zawierający tablice asocjacyjne z etykietami, krótkimi opisami i przykładami dla każdej metryki — oddzielnie dla każdej gry, gdzie definicja różni się (np. „niskie" = ≤24 dla Lotto vs ≤21 dla Mini Lotto). Publiczne interfejsy:

- `metric_label(string $metric): string` — krótka etykieta do nagłówka lub formularza (np. „Suma liczb")
- `metric_description(string $metric, string $game): string` — pełny opis metryki dostosowany do gry
- `metric_example(string $metric, string $game): string` — konkretny przykład w jednym zdaniu

Wszystkie pozostałe pliki importują opisy wyłącznie z tego modułu — żadnych literałów opisowych w `stats.php`, `draws.php` itp.

### Tooltip renderer — funkcja `render_tooltip()`

Globalna funkcja pomocnicza w `helpers.php` (lub osobnym `ui_helpers.php`) o sygnaturze:

```
render_tooltip(string $metric, string $game): string
```

Zwraca fragment HTML: ikonkę `?` z atrybutem `title` zawierającym opis i przykład z `texts.php`. Tooltip CSS-owy działa na hover i tap (dostępność mobilna). Używany wszędzie tam, gdzie nagłówek tabeli lub etykieta formularza potrzebuje objaśnienia.

### Odczyt profilu — funkcja `describe_profile()`

Nowa funkcja w `helpers.php` przyjmująca `profile_hash` jako string i zwracająca czytelne rozkodowanie po polsku, np.:

```
"3 parzyste · 3 niskie · suma średnia (sM: 110–170) · 1 para kolejna · rozstęp duży (rL: 40–44)"
```

Używana wszędzie tam, gdzie wyświetlany jest `profile_hash`: w tabelach, Dashboardzie, Generatorze i Weryfikatorze.

### Generator — sekcja „Dlaczego te liczby?"

Po wygenerowaniu kuponów, każdy kupon otrzymuje sekcję tekstową renderowaną w PHP z tablicy metryk. Format: jedno akapitowe zdanie opisujące kluczowe cechy kuponu oraz krótkie zdanie o jego profilu i jego historycznej częstości (jeśli profil istnieje w `{game}_draw_profiles`). Dane do opisu są już obliczone (`compute_metrics`) — wystarczy je przekształcić na tekst.

### Validator — sekcja „Co to znaczy?"

Po analizie kombinacji, pod tabelą metryk wyświetlana jest sekcja interpretacyjna. Składa się z:

1. Zdania o profilu i jego historycznej częstości: czy jest typowy (> 2% losowań), rzadki (0,5–2%) lub bardzo rzadki (< 0,5%).
2. Per-metrykowego komentarza — dla każdej metryki jedno zdanie oceniające, czy wartość mieści się w typowym zakresie historycznym (np. czy suma jest w przedziale sM, który padał najczęściej).
3. Zdania o dokładnym dopasowaniu: czy kombinacja kiedykolwiek padła i kiedy.

Komentarze generowane są na podstawie porównania z bucketami i zakresami zdefiniowanymi w `texts.php`.

### Stats page — nagłówki sortowalne z tooltipami

Nagłówki kolumn w tabeli statystyk zastąpione przez linki sortujące opatrzone tooltipem. Etykiety nagłówków pobierane z `metric_label()`. Przy kliknięciu strzałka kierunkowa (▲/▼) wskazuje kierunek sortowania.

### Nawigacja i etykiety systemowe

Zakładka „Sync" przemianowana na „Aktualizuj dane", „Import" na „Wczytaj historię". Etykiety w kodzie aktualizowane w jednym miejscu (`$allowedPages` mapping w `index.php` lub osobna tablica `$navLabels`).

### Formularz Generatora

Etykiety pól formularza przepisane w oparciu o `texts.php`. Przy każdym polu zakresu (min/max) dodana informacja o wartości typowej historycznie — np. przy polu sumy wyświetlana notka „Typowy zakres: 110–170 (środkowe pasmo, ~40% losowań)". Lista profili w multi-select zastąpiona przez czytelne opisy zamiast surowych hashów.

### Schemat bazy — bez zmian

PRD nie wprowadza żadnych zmian schematu bazy danych. Wszystkie metryki są już obliczone i dostępne — praca dotyczy wyłącznie warstwy prezentacji.

---

## Testing Decisions

Właściciel podjął decyzję, że w ramach tego PRD **nie są pisane testy automatyczne**. Uzasadnienie: zmiany dotyczą wyłącznie warstwy prezentacji i treści copywriterskich — nie logiki obliczeniowej. Nie ma kodu, którego zachowanie zewnętrzne można by przetestować niezależnie od UI.

Dobry test (gdyby był pisany) testowałby wyłącznie zewnętrzne zachowanie: czy funkcja `describe_profile('3e3o_3l3h_sM_c1_rL', 'lotto')` zwraca string zawierający słowa „parzyste", „niskie", „średnia" — bez zaglądania w szczegóły implementacji.

Walidacja dokonywana jest ręcznie: przejrzenie każdej strony po wdrożeniu i sprawdzenie, czy żaden surowy klucz techniczny (`profile_hash`, `sum_total` itp.) nie jest widoczny bez tłumaczenia.

---

## Out of Scope

- Zmiana logiki obliczeń metryk (jak jest liczone `overdue_score`, `profile_hash` itp.).
- Dodawanie nowych metryk do bazy danych.
- Migracja frontendu do React/Tailwind.
- Lokalizacja do innych języków niż polski.
- Eksport raportów PDF / drukowane kupony.
- Zmiany po stronie synchronizacji i importu danych (poza samymi etykietami w nawigacji).
- Obsługa gier Multi Multi i Keno — poza zakresem MVP.
- Edukacyjne artykuły lub strony pomocy — wyjaśnienia mają być inline, nie na osobnych stronach.

---

## Further Notes

- Słownik metryk w `texts.php` powinien być napisany w stylu popularyzatorskim — jak artykuł w dziale „Wiedza" portalu lotto, nie jak dokumentacja techniczna. Zdania mają być krótkie, konkretne i zawierać liczby (np. „W ostatnich 5 latach suma najczęściej mieściła się między 110 a 170").
- Przy opisie profilu strukturalnego warto pokazać nie tylko rozkodowanie składowych, ale też percentyl historyczny: „Ten profil padał w 4,7% losowań (ok. raz na 21 razy)".
- `describe_profile()` powinna gracefully degradować: jeśli profil nie istnieje w tabeli `{game}_draw_profiles`, zwraca tylko rozkodowanie składowych bez danych historycznych.
- Jeśli w przyszłości zostanie dodana gra Multi Multi (20 liczb), framework `texts.php` musi obsługiwać `game_slug` jako parametr kontekstowy, bo progi metryk różnią się między grami.
