# Lotto Analysis App – Brief dla agenta AI

## 1. Cel projektu

Webowa aplikacja do analizy historycznych losowań polskiego Lotto i innych gier liczbowych Totalizatora Sportowego. Funkcje: statystyki, wizualizacje, rozkłady danych, systemy generowania kuponów (wheeling + filtry).

---

## 2. Stack technologiczny

| Warstwa | Technologia |
|---|---|
| Backend | PHP 8.x (shared hosting) |
| Baza danych | MySQL |
| Frontend | React + Tailwind CSS + HTML5 routing (`.htaccess` RewriteRule) |
| Wykresy | Chart.js + `chartjs-chart-matrix` (plugin do heatmap) |
| Import danych | Python lokalnie (pandas) → SQL dump → phpMyAdmin upload |
| API | PHP REST endpoints |
| Konfiguracja | `.env` + `vlucas/phpdotenv` (plik poza `public_html`, w `.gitignore`) |

Python NIE jest deployowany na serwerze – tylko lokalnie do jednorazowego importu inicjalnego. Bieżące aktualizacje przez PHP cron.

---

## 3. Obsługiwane gry

- **Lotto (6/49)** – priorytet #1
- **Lotto Plus**
- **Mini Lotto (5/42)**
- **Multi Multi (20/80)**
~~**Keno (10/70)**~~ – pominięta (niejasna wartość analityczna dla użytkownika).

~~Szybkie 600~~ – pominięta (niejasna struktura gry, wykluczona z zakresu projektu).

Każda gra = osobny zestaw tabel w DB.

---

## 4. Dane historyczne

### Źródło
- Baza dostępna od 1957 roku
- Totalizator Sportowy – lotto.pl (CSV/XML lub scraping)

### Podział na epoki (WAŻNE)

| Okres | Status |
|---|---|
| 1957–1975 | Inne zasady gry – tylko ciekawostka historyczna |
| 1975–2009 | Losowanie fizyczne (kule) – dane wartościowe |
| 2009–dziś | Losowanie elektroniczne RNG – **dane podstawowe** |
| Ostatnie 2–3 lata | Okno do hot/cold/gap – **default widok** |

Dane sprzed i po 2009 to dwa różne „światy" – nie mieszać w tej samej analizie statystycznej. Podział na epoki to **feature** aplikacji (porównanie kule vs RNG).

### Rekomendowany zakres
- **Default w aplikacji:** od 2009
- **Okno hot/cold:** ostatnie 500 losowań (~5 lat)
- **Minimum sensownej statystyki:** 200–300 losowań

---

## 5. Schemat bazy danych

**Decyzja architektoniczna:** Osobna tabela losowań dla każdej gry (MVP: Lotto, Lotto Plus, Mini Lotto). Multi Multi i Keno wykluczone z MVP.

### Tabela `lotto_draws` (Lotto 6/49)

```sql
CREATE TABLE lotto_draws (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  draw_date       DATE NOT NULL,
  draw_number     INT UNIQUE,               -- naturalny klucz z mbnet
  n1 TINYINT, n2 TINYINT, n3 TINYINT,
  n4 TINYINT, n5 TINYINT, n6 TINYINT,      -- liczby posortowane ASC

  -- Metryki pre-computed (liczyć raz przy imporcie)
  sum_total       SMALLINT,
  even_count      TINYINT,
  low_count       TINYINT,                   -- ile niskich (1–24)
  consecutive     TINYINT,
  decades_used    TINYINT,
  range_spread    TINYINT,
  last_digit_unique TINYINT,
  profile_hash    VARCHAR(30),               -- FK do lotto_draw_profiles

  INDEX idx_date (draw_date),
  INDEX idx_profile (profile_hash)
);
```

### Tabela `mini_lotto_draws` (Mini Lotto 5/42)

```sql
CREATE TABLE mini_lotto_draws (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  draw_date       DATE NOT NULL,
  draw_number     INT UNIQUE,
  n1 TINYINT, n2 TINYINT, n3 TINYINT,
  n4 TINYINT, n5 TINYINT,                   -- 5 liczb z 42

  sum_total       SMALLINT,
  even_count      TINYINT,
  low_count       TINYINT,                   -- ile niskich (1–21)
  consecutive     TINYINT,
  decades_used    TINYINT,
  range_spread    TINYINT,
  last_digit_unique TINYINT,
  profile_hash    VARCHAR(30),

  INDEX idx_date (draw_date),
  INDEX idx_profile (profile_hash)
);
```

### Tabela `multi_multi_draws`

```sql
-- Multi Multi 20/80 – bez profile_hash (zbyt wiele wymiarów)
CREATE TABLE multi_multi_draws (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  draw_date       DATE NOT NULL,
  draw_number     INT UNIQUE,
  n1  TINYINT, n2  TINYINT, n3  TINYINT, n4  TINYINT, n5  TINYINT,
  n6  TINYINT, n7  TINYINT, n8  TINYINT, n9  TINYINT, n10 TINYINT,
  n11 TINYINT, n12 TINYINT, n13 TINYINT, n14 TINYINT, n15 TINYINT,
  n16 TINYINT, n17 TINYINT, n18 TINYINT, n19 TINYINT, n20 TINYINT,

  sum_total       SMALLINT,
  even_count      TINYINT,
  low_count       TINYINT,                   -- ile niskich (1–40)
  consecutive     TINYINT,
  range_spread    TINYINT,

  INDEX idx_date (draw_date)
);
```

### Tabela `lotto_plus_draws` (Lotto Plus)

```sql
CREATE TABLE lotto_plus_draws (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  draw_date       DATE NOT NULL,
  draw_number     INT UNIQUE,
  n1 TINYINT, n2 TINYINT, n3 TINYINT,
  n4 TINYINT, n5 TINYINT, n6 TINYINT,
  plus_ball       TINYINT,                   -- dodatkowa kula Lotto Plus

  sum_total       SMALLINT,
  even_count      TINYINT,
  low_count       TINYINT,
  consecutive     TINYINT,
  range_spread    TINYINT,
  profile_hash    VARCHAR(30),

  INDEX idx_date (draw_date)
);
```

### Tabela `lotto_draw_profiles` (profile strukturalne – tylko Lotto i Mini Lotto)

**Decyzja:** Profile hash implementowane tylko dla Lotto (6/49) i Mini Lotto (5/42). Multi Multi i Keno wykluczone z MVP.

```sql
-- lotto_draw_profiles (analogiczna tabela: mini_lotto_draw_profiles)
CREATE TABLE lotto_draw_profiles (
  profile_hash    VARCHAR(30) PRIMARY KEY,  -- np. "3e3o_3l3h_sM_c0_rL"
  even_count      TINYINT,
  low_count       TINYINT,
  sum_bucket      CHAR(3),                  -- XS/S/M/L/XL
  consecutive     TINYINT,
  range_bucket    CHAR(3),
  total_draws     INT,                      -- aktualizowane przy imporcie
  pct_of_total    DECIMAL(5,2),
  last_seen       DATE,
  first_seen      DATE
);
```

### Tabela `games` (słownik gier)

**Decyzja:** `slug` jako primary key naturalny – bezpośrednio mapuje na nazwy tabel (`{slug}_draws`).

```sql
CREATE TABLE games (
  slug            VARCHAR(20) PRIMARY KEY,  -- np. "lotto", "mini_lotto"
  name            VARCHAR(50) NOT NULL,      -- np. "Lotto"
  pick_count      TINYINT NOT NULL,          -- ile liczb do wyboru (6)
  pool_size       TINYINT NOT NULL,          -- zakres puli (49)
  has_bonus       BOOLEAN DEFAULT FALSE,
  low_threshold   TINYINT NOT NULL,          -- granica niskie/wysokie (24 dla 6/49)
  supports_profiles BOOLEAN DEFAULT FALSE,  -- czy obsługuje profile_hash
  sync_url        VARCHAR(200),              -- URL do pobierania danych (mbnet)
  active          BOOLEAN DEFAULT TRUE
);

-- Dane inicjalne
INSERT INTO games VALUES
  ('lotto',       'Lotto',      6,  49, FALSE, 24, TRUE,  'https://mbnet.com.pl/dl.txt',      TRUE),
  ('lotto_plus',  'Lotto Plus', 6,  49, TRUE,  24, TRUE,  'https://mbnet.com.pl/dl_plus.txt', TRUE),
  ('mini_lotto',  'Mini Lotto', 5,  42, FALSE, 21, TRUE,  'https://mbnet.com.pl/ml.txt',      TRUE),
  -- ('multi_multi', 'Multi Multi', ...) – post-MVP
  -- ('keno', ...) – wykluczone z projektu
```

---

## 6. Metryki pre-computed (wszystkie liczyć przy imporcie)

| Kolumna | Opis | Wzór |
|---|---|---|
| `sum_total` | Suma liczb | n1+n2+...+n6 |
| `even_count` | Ile parzystych | count(n % 2 == 0) |
| `low_count` | Ile niskich | count(n <= pool/2) |
| `consecutive` | Ile par kolejnych | count(n[i+1] == n[i]+1) |
| `decades_used` | Ile różnych dziesiątek | count(distinct floor((n-1)/10)) |
| `range_spread` | Rozstęp | max(n) - min(n) |
| `last_digit_unique` | Unikalne ostatnie cyfry | count(distinct n%10) |

---

## 7. Profile Hash

### Idea
Każde losowanie sprowadzone do krótkiego „odcisku palca" struktury – nie konkretnych liczb.
Losowania `7,14,21,28,35,42` i `3,12,19,26,33,40` mają różne liczby ale **identyczny profil**.

### Format
```
{even}e{odd}o_{low}l{high}h_s{sum_bucket}_c{consecutive}_r{range_bucket}

Przykład: "3e3o_3l3h_sM_c1_rL"
```

### Buckety sumy (dla Lotto 6/49)
| Bucket | Zakres | % losowań |
|---|---|---|
| sXS | 21–79 | < 1% |
| sS | 80–109 | ~8% |
| sM | 110–170 | ~68% |
| sL | 171–200 | ~20% |
| sXL | 201–279 | < 3% |

### Buckety rozstępu
| Bucket | Zakres |
|---|---|
| rXS | 0–19 |
| rS | 20–29 |
| rM | 30–39 |
| rL | 40–44 |
| rXL | 45–48 |

### Zastosowania w aplikacji
1. **Rozkład profili** – `GROUP BY profile_hash` → top 20 najczęstszych wzorców z %
2. **Walidator kuponu** – użytkownik wpisuje 6 liczb → system zwraca jak często dany profil padał
3. **Generator UX** – użytkownik wybiera profil zamiast 8 osobnych suwaków
4. **Streak profilu** – ile razy dany profil padł w ostatnich 20 losowaniach
5. **Porównanie epok** – czy po 2009 rozkład profili się zmienił

### Uwaga implementacyjna
Zbyt wiele wymiarów → zbyt wiele unikalnych profili → brak wartości statystycznej.
Optimum: **4–5 wymiarów**, cel to 50–200 unikalnych hashów dla całej historii.

---

## 8. Moduły statystyczne

### 8.1 Analiza liczbowa (per liczba)

| Metryka | Opis |
|---|---|
| Częstość całkowita | Ile razy padła liczba X w historii |
| Częstość okienkowa | Częstość w ostatnich 50/100/500 losowaniach |
| Hot/Cold | Porównanie częstości okienkowej vs historycznej średniej |
| Temperatura | Trójstopniowa skala: hot / warm / cold |
| Gap (przerwa) | Ile losowań minęło od ostatniego wystąpienia |
| Overdue score | `aktualny_gap / średni_gap_historyczny` – wartość > 2.0 = mocno zalega |
| Rytm powrotów | Histogram przerw: po ilu losowaniach liczba wraca (min/max/mediana/σ) |
| Pozycyjna częstość | Jak często liczba X ląduje na pozycji 1, 2... 6 (po posortowaniu) |
| EMA 7/14/30 | Exponential Moving Average aktywności liczby | w MVP |
| RSI-14 | Relative Strength Index – "wykupienie/wyprzedanie" liczby | w MVP |

### 8.2 Metryki kuponowe (per losowanie / agregaty)

| Metryka | Opis |
|---|---|
| Suma | Rozkład sum historycznych → walidacja kuponu |
| Parzyste/nieparzyste | Histogram rozkładu 0:6 ... 6:0 |
| Niskie/wysokie | Histogram rozkładu |
| Pary kolejne | Ile par sekwencyjnych w losowaniu |
| Dekady | Ile liczb z każdej dziesiątki |
| Końcówki cyfr | Ostatnia cyfra każdej liczby – rozkład |
| Rozstęp | max – min w kuponie |

### 8.3 Analiza korelacji

| Metryka | Opis |
|---|---|
| Macierz par | NxN – jak często dwie liczby padają razem |
| Hot pairs | Top 20 najczęstszych par |
| Cold pairs | Pary które prawie nigdy nie padają razem |
| Triplets | Najczęstsze trójki liczb |

### 8.4 Statystyki zaawansowane (wszystkie w MVP – Faza 5)

| Metryka | Opis |
|---|---|
| Chi-kwadrat | Czy rozkład częstości istotnie odbiega od równomiernego |
| Shannon entropy | Miara losowości w oknie czasowym |
| Gini coefficient | Nierównomierność rozkładu |
| Autocorrelacja | Zależność między kolejnymi losowaniami |
| Markov chains | P(X w losowaniu N+1 \| Y w losowaniu N) |
| Benford's law | Anomalie w dystrybucji pierwszych cyfr |
| Prawo dużych liczb | Wykres zbieżności częstości do 1/pool_size w czasie |

### 8.5 Analiza trendów czasowych

- Aktywność każdej liczby w czasie (heatmapa rok × liczba)
- Zmiana temperatury liczby na przestrzeni lat
- Porównanie epok: kule (pre-2009) vs RNG (post-2009)
- Rozkład profili per rok

---

## 9. Systemy skreślania / generowania kuponów

### 9.1 Typy wheeli

**Full Wheel** – wszystkie kombinacje z N wybranych liczb. Gwarancja jackpotu jeśli masz wszystkie trafione w zestawie. Wadą jest eksplozja liczby kuponów (10 liczb = 210 kuponów dla 6/49).

**Abbreviated Wheel** – matematycznie minimalna liczba kuponów przy zachowaniu gwarancji `P-win if Q of N`. Przykład: `4if5of20` = gwarancja 4 trafień gdy 5 z twoich 20 liczb padnie.

**Key Wheel** – jak abbreviated, ale każdy kupon zawiera wskazaną liczbę kluczową.

### 9.2 Filtry kombinacji

| Filtr | Typ | Przykład |
|---|---|---|
| Zakres sumy | range | 110–170 |
| Parzyste | min/max | 2–4 |
| Niskie/wysokie | min/max | min 2 z każdej grupy |
| Pary kolejne | max | max 1 para |
| Z ostatniego losowania | max | max 2 powtórzenia |
| Dekady | max per dekada | max 3 z jednej |
| Hot numbers | min | min 2 z top-10 |
| Końcówki | max duplikatów | max 2 takie same |
| Profil hash | exact/lista | "3e3o_sM" |

Uwaga: filtry na Full Wheel niszczą matematyczną gwarancję wheela.

### 9.3 Generator statystyczny

Losowanie z wagami opartymi na historycznej częstości. Nie poprawia szans matematycznie, ale jest popularną funkcją dla użytkowników.

### 9.4 Źródła gotowych wheeli

- **Iliya Bluskov** – "Combinatorial Lottery Systems (Wheels) with Guaranteed Wins" – matematycznie minimalne wheele
- **lotterypost.com/wheels** – publiczny katalog z parametrami
- **lottoexpert.net/all-wheeling-systems** – baza wheeli
- **La Jolla Covering Repository** – akademicka baza minimalnych covering designs (bezpłatna)

Implementacja: załadować gotowe definicje jako szablony i podmienić numery teoretyczne na wybory użytkownika. NIE generować minimalnych wheeli od zera (NP-hard).

---

## 10. Synchronizacja danych (aktualizacja)

### Źródła danych

| Źródło | URL | Gra | Format |
|---|---|---|---|
| mbnet.com.pl (primary) | mbnet.com.pl/dl.txt | Lotto | TXT |
| mbnet.com.pl | mbnet.com.pl/dl_plus.txt | Lotto Plus | TXT |
| mbnet.com.pl | mbnet.com.pl/ml.txt | Mini Lotto | TXT |
| mbnet.com.pl | mbnet.com.pl/el.txt | Ekstra Lotek | TXT |
| wynikilotto.net.pl | /download/lotto.csv | Lotto | CSV (backup) |

### Format pliku mbnet

```
{numer}. {dd.mm.rrrr} {n1},{n2},{n3},{n4},{n5},{n6}

Przykład:
1. 27.01.1957 8,12,31,39,43,45
6841. 12.04.2026 3,17,22,31,38,45
```

Numer losowania = naturalny klucz synchronizacji.

### Rozmiar pliku

Lotto ~7 500 losowań od 1957 × ~30 znaków/linia = **ok. 250 KB**. Pobieranie całego pliku przy każdej synchronizacji jest akceptowalne.

### Import inicjalny (jednorazowy)

```
1. Python lokalnie: parsuj CSV/TXT ze wszystkich źródeł → oblicz metryki → dump SQL
2. Wgraj dump przez phpMyAdmin na shared hosting
3. Od tego momentu cron/PHP sync przejmuje aktualizacje
```

### Strategia synchronizacji (delta import – cron)

```
1. Pobierz cały plik (file_get_contents lub curl)
2. Sprawdź MAX(draw_number) w tabeli {game}_draws
3. Parsuj linie – SKIP wszystko gdzie numer <= MAX
4. INSERT tylko nowe rekordy
5. Oblicz metryki (sum, even, low, consecutive, range_spread, profile_hash) tylko dla nowych
6. Uaktualnij {game}_draw_profiles (total_draws, last_seen, pct_of_total)
7. Zapisz wynik do sync_log
```

### Szkielet PHP

```php
// Uwaga: nazwy tabel są budowane po whitelist-cie slugów – brak SQL injection
// Wszystkie zapytania używają prepared statements
const GAME_TABLES = [
    'lotto'       => 'lotto_draws',
    'lotto_plus'  => 'lotto_plus_draws',
    'mini_lotto'  => 'mini_lotto_draws',
    'multi_multi' => 'multi_multi_draws',
    // 'keno' – wykluczone z projektu
];

function syncGame(PDO $db, string $gameSlug, string $url): int {
    if (!isset(GAME_TABLES[$gameSlug])) throw new InvalidArgumentException('Unknown game');
    $table = GAME_TABLES[$gameSlug];  // bezpieczne – pochodzi z whitelist-y

    $stmt = $db->prepare("SELECT COALESCE(MAX(draw_number), 0) FROM {$table}");
    $stmt->execute();
    $lastNum = (int)$stmt->fetchColumn();

    $lines    = file($url, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $inserted = 0;

    foreach ($lines as $line) {
        if (!preg_match('/^(\d+)\.\s+(\d{2}\.\d{2}\.\d{4})\s+([\d,]+)$/', trim($line), $m)) continue;

        $num = (int)$m[1];
        if ($num <= $lastNum) continue;

        $date    = DateTime::createFromFormat('d.m.Y', $m[2])->format('Y-m-d');
        $numbers = array_map('intval', explode(',', $m[3]));
        sort($numbers);

        insertDrawWithMetrics($db, $table, $num, $date, $numbers);
        $inserted++;
    }

    return $inserted;
}
```

### Harmonogram (cron)

Lotto grane wt/czw/sob, wyniki publikowane ok. 22:00–22:30.

```bash
# cPanel / DirectAdmin cron
0 23 * * 2,4,6   php /home/user/public_html/lotto/cron/sync.php
```

Jeśli shared hosting nie daje crona → **cron-job.org** (bezpłatny) pinguje endpoint:
```
GET /api/sync?key=SECRET_TOKEN&game=lotto
```

### Tabela `sync_log`

```sql
CREATE TABLE sync_log (
  id               INT AUTO_INCREMENT PRIMARY KEY,
  game_id          TINYINT,
  synced_at        DATETIME DEFAULT NOW(),
  draws_added      INT,
  last_draw_number INT,
  source_url       VARCHAR(200),
  status           ENUM('ok', 'error', 'no_new'),
  error_msg        TEXT
);
```

### Obsługa błędów

- Źródło niedostępne → status `error` w sync_log, retry przy następnym cronie
- Zmiana formatu pliku → alert (regex nie pasuje do żadnej linii)
- Duplikat draw_number → INSERT IGNORE lub ON DUPLICATE KEY IGNORE
- Backup source (wynikilotto.net.pl) używany gdy primary zwróci błąd HTTP

---

## 11. API Endpoints (PHP)

```
GET  /api/games                          – lista gier
GET  /api/draws/{game}?limit=&from=&to=  – historia losowań
GET  /api/stats/frequency/{game}         – częstość wszystkich liczb
GET  /api/stats/gap/{game}               – aktualne przerwy
GET  /api/stats/overdue/{game}           – overdue score ranking
GET  /api/stats/pairs/{game}             – macierz par
GET  /api/stats/distributions/{game}     – rozkłady sum, parzystości itp.
GET  /api/stats/profiles/{game}          – rozkład profile_hash
POST /api/coupon/validate                – walidacja kuponu (profil + score)
POST /api/coupon/generate                – generator z filtrami
-- POST /api/coupon/wheel               -- wheeling odłożone na post-MVP
GET  /api/analysis/epochs/{game}        – porównanie epok
GET  /api/sync?key=&game=              – ręczny trigger synchronizacji
```

---

## 12. Frontend – widoki

| Widok | Opis |
|---|---|
| Dashboard | Ostatnie losowanie + quick stats + hot/cold top 10 |
| Historia losowań | Tabela z filtrem daty, podświetlenie hot/cold |
| Statystyki liczb | Tabela z all metrics + sortowanie + filtr zakresu dat |
| Heatmapa | Aktywność liczb w czasie (rok × liczba) |
| Rozkłady | Wykresy: suma, parzystość, niskie/wysokie, przerwy |
| Macierz par | Heatmapa NxN współwystępowania |
| Profile | Rozkład profili + walidator kuponu |
| Generator | UI filtrów → generuj kupony |
~~Wheeling~~ – odłożone na post-MVP |
| Porównanie epok | pre/post 2009 side-by-side |

---

## 13. Plan budowy (priorytety)

```
Faza 1 – Fundament
  - Schema DB (osobne tabele per gra: lotto, lotto_plus, mini_lotto)
  - Tabela games (slug PK), sync_log
  - Importer Python lokalnie: parsuj TXT z mbnet → SQL dump → phpMyAdmin upload
  - Metryki przy imporcie: sum, even, low, consecutive, range_spread, profile_hash

Faza 2 – Statystyki podstawowe
  - API: frequency, gap, overdue
  - Frontend: tabela liczb z hot/cold + sortowanie
  - URL query params jako globalny filtr (?game=lotto&from=&to=)

Faza 3 – Rozkłady i profile
  - API: distributions, profiles
  - Wykresy (Chart.js): suma, parzystość, dekady
  - Walidator kuponu (profil score)

Faza 4 – Generator kuponów
  - Filtr-based generator
  - UI filtrów (zakres sumy, parzystość, hot numbers itp.)

Faza 5 – Zaawansowane
  - EMA 7/14/30 + RSI-14 per liczba
  - Macierz par + heatmapa (Chart.js chartjs-chart-matrix)
  - Analiza epok (pre/post 2009) – osobna strona side-by-side
  - Chi-kwadrat, Shannon entropy, Gini, autocorrelacja, Markov, Benford
  - Trendy czasowe + heatmapa roczna

Faza 6 (post-MVP) – Wheeling
  - Import definicji wheeli z zewnętrznych baz
  - UI: wybór systemu + wprowadzanie liczb
```

---

## 14. Uwagi implementacyjne

- Wszystkie metryki per losowanie (sum, even, profile_hash itp.) liczyć **raz przy imporcie**, nie w locie
- `profile_hash` – optymalna liczba unikalnych hashów: 50–200 dla całej historii. Zbyt wiele wymiarów niszczy wartość statystyczną
- **Globalny filtr (gra + zakres dat):** przechowywany w URL query params (`?from=2009-01-01&to=2026-04-13&game=lotto`). Każda strona React czyta i zapisuje params przez `useSearchParams`. Umożliwia linkowanie i odświeżenie bez utraty stanu.
- **Deployment:** React build (`/public_html/lotto/app/`), `.htaccess` z `RewriteBase /lotto/app/` + `RewriteRule . index.html [L]`. API na tym samym hostingu pod `/lotto/api/`.
- **Cache:** brak. Direct DB query przy każdym requeście – statystyki zmieniają się max 3x/tydzień, ruch mały. Inwalidacja nie jest potrzebna.
- Indeksy DB: `(draw_date)`, `(profile_hash)`, `(n1)` ... `(n6)` per tabela
- Dla okna hot/cold: przechowywać `last_n` jako parametr (domyślnie 500 losowań), nie stały zakres dat
- Sync: zawsze INSERT IGNORE / ON DUPLICATE KEY – idempotentny import, bezpieczny przy ponownym uruchomieniu
- Po każdym sync przeliczać `{game}_draw_profiles.total_draws` i `pct_of_total` – prosty UPDATE z COUNT
- Nie ufać ciągłości numeracji mbnet – mogą być luki; używać `draw_number` jako zewnętrzny ID, nie jako sekwencję wewnętrzną
