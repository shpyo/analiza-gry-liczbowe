# Lotto Analysis App — Plan Minimum (PHP + MySQL)

> Wersja: totalne minimum — baza danych + wyświetlanie w czystym PHP  
> Bez: React, Tailwind, Chart.js, API REST, Pythona  
> Stack: PHP 8.x + MySQL + surowy HTML  
> Dev lokalnie: Laravel Herd (macOS) + MySQL

---

## 1. Stack

| Warstwa | Technologia |
|---|---|
| Backend | PHP 8.x |
| Baza danych | MySQL (Herd lokalnie, shared hosting docelowo) |
| Frontend | Surowy HTML — tabele, formularze, zero CSS frameworka |
| Import danych | PHP skrypt importujący mbnet.com.pl |
| Synchronizacja | Ręczny przycisk „Synchronizuj" w UI |
| Routing | Query params: `index.php?page=draws&game=lotto` |

---

## 2. Gry w zakresie

- **Lotto (6/49)** — priorytet #1
- **Lotto Plus** — 6/49 + bonus ball
- **Mini Lotto (5/42)**
- ~~Multi Multi~~ — poza zakresem minimum
- ~~Keno~~ — wykluczone z projektu

---

## 3. Schemat bazy danych

### Tabela `games`

```sql
CREATE TABLE games (
  slug              VARCHAR(20) PRIMARY KEY,
  name              VARCHAR(50)  NOT NULL,
  pick_count        TINYINT      NOT NULL,
  pool_size         TINYINT      NOT NULL,
  has_bonus         BOOLEAN      DEFAULT FALSE,
  low_threshold     TINYINT      NOT NULL,
  supports_profiles BOOLEAN      DEFAULT FALSE,
  sync_url          VARCHAR(200),
  active            BOOLEAN      DEFAULT TRUE
);

INSERT INTO games VALUES
  ('lotto',      'Lotto',      6, 49, FALSE, 24, TRUE,  'https://mbnet.com.pl/dl.txt',      TRUE),
  ('lotto_plus', 'Lotto Plus', 6, 49, TRUE,  24, TRUE,  'https://mbnet.com.pl/dl_plus.txt', TRUE),
  ('mini_lotto', 'Mini Lotto', 5, 42, FALSE, 21, TRUE,  'https://mbnet.com.pl/ml.txt',      TRUE);
```

### Tabela `lotto_draws`

```sql
CREATE TABLE lotto_draws (
  id                INT AUTO_INCREMENT PRIMARY KEY,
  draw_date         DATE    NOT NULL,
  draw_number       INT     UNIQUE,
  n1 TINYINT, n2 TINYINT, n3 TINYINT,
  n4 TINYINT, n5 TINYINT, n6 TINYINT,
  sum_total         SMALLINT,
  even_count        TINYINT,
  low_count         TINYINT,
  consecutive       TINYINT,
  decades_used      TINYINT,
  range_spread      TINYINT,
  last_digit_unique TINYINT,
  profile_hash      VARCHAR(30),
  INDEX idx_date    (draw_date),
  INDEX idx_profile (profile_hash)
);
```

### Tabela `lotto_plus_draws`

```sql
CREATE TABLE lotto_plus_draws (
  id                INT AUTO_INCREMENT PRIMARY KEY,
  draw_date         DATE    NOT NULL,
  draw_number       INT     UNIQUE,
  n1 TINYINT, n2 TINYINT, n3 TINYINT,
  n4 TINYINT, n5 TINYINT, n6 TINYINT,
  plus_ball         TINYINT,
  sum_total         SMALLINT,
  even_count        TINYINT,
  low_count         TINYINT,
  consecutive       TINYINT,
  decades_used      TINYINT,
  range_spread      TINYINT,
  last_digit_unique TINYINT,
  profile_hash      VARCHAR(30),
  INDEX idx_date    (draw_date),
  INDEX idx_profile (profile_hash)
);
```

### Tabela `mini_lotto_draws`

```sql
CREATE TABLE mini_lotto_draws (
  id                INT AUTO_INCREMENT PRIMARY KEY,
  draw_date         DATE    NOT NULL,
  draw_number       INT     UNIQUE,
  n1 TINYINT, n2 TINYINT, n3 TINYINT,
  n4 TINYINT, n5 TINYINT,
  sum_total         SMALLINT,
  even_count        TINYINT,
  low_count         TINYINT,
  consecutive       TINYINT,
  decades_used      TINYINT,
  range_spread      TINYINT,
  last_digit_unique TINYINT,
  profile_hash      VARCHAR(30),
  INDEX idx_date    (draw_date),
  INDEX idx_profile (profile_hash)
);
```

### Tabele `{game}_draw_profiles` (osobna per gra)

```sql
-- Analogiczne tabele: lotto_draw_profiles, lotto_plus_draw_profiles, mini_lotto_draw_profiles
CREATE TABLE lotto_draw_profiles (
  profile_hash VARCHAR(30) PRIMARY KEY,
  even_count   TINYINT,
  low_count    TINYINT,
  sum_bucket   CHAR(3),
  consecutive  TINYINT,
  range_bucket CHAR(3),
  total_draws  INT,
  pct_of_total DECIMAL(5,2),
  last_seen    DATE,
  first_seen   DATE
);
```

### Tabela `sync_log`

```sql
CREATE TABLE sync_log (
  id               INT AUTO_INCREMENT PRIMARY KEY,
  game_slug        VARCHAR(20),
  synced_at        DATETIME DEFAULT NOW(),
  draws_added      INT,
  last_draw_number INT,
  source_url       VARCHAR(200),
  status           ENUM('ok', 'error', 'no_new'),
  error_msg        TEXT
);
```

---

## 4. Metryki pre-computed (liczyć przy imporcie)

| Kolumna | Opis | Wzór |
|---|---|---|
| `sum_total` | Suma liczb | n1+…+n6 |
| `even_count` | Ile parzystych | count(n % 2 == 0) |
| `low_count` | Ile niskich | count(n <= pool_size/2) |
| `consecutive` | Ile par kolejnych | count(n[i+1] == n[i]+1) |
| `decades_used` | Ile różnych dziesiątek | count(distinct floor((n-1)/10)) |
| `range_spread` | Rozstęp | max(n) - min(n) |
| `last_digit_unique` | Unikalne ostatnie cyfry | count(distinct n%10) |
| `profile_hash` | Odcisk strukturalny | patrz sekcja 5 |

---

## 5. Profile Hash

Format: `{even}e{odd}o_{low}l{high}h_s{sum_bucket}_c{consecutive}_r{range_bucket}`  
Przykład: `3e3o_3l3h_sM_c1_rL`

### Buckety sumy (Lotto 6/49)

| Bucket | Zakres |
|---|---|
| sXS | 21–79 |
| sS  | 80–109 |
| sM  | 110–170 |
| sL  | 171–200 |
| sXL | 201–279 |

### Buckety rozstępu

| Bucket | Zakres |
|---|---|
| rXS | 0–19 |
| rS  | 20–29 |
| rM  | 30–39 |
| rL  | 40–44 |
| rXL | 45–48 |

---

## 6. Struktura plików PHP

```
public/
├── index.php          ← router (switch na ?page=)
├── draws.php          ← tabela historii losowań
├── stats.php          ← tabela częstości, hot/cold
├── generator.php      ← generator kuponów z filtrami
├── validator.php      ← walidator kuponu (profil + historia)
├── sync.php           ← ręczna synchronizacja delta (wywoływana z UI)
├── import.php         ← import inicjalny całej historii z mbnet.com.pl
├── db.php             ← połączenie PDO (require w każdym pliku)
└── helpers.php        ← obliczanie metryk, profile_hash itp.
```

### Routing

Każda strona to `index.php?page=draws&game=lotto`.  
`index.php` robi `switch($_GET['page'])` i `include` odpowiedniego pliku.  
Gra pobierana z `$_GET['game']` i walidowana przez whitelist.

```php
// index.php — uproszczony szkielet
$page = $_GET['page'] ?? 'dashboard';
$game = in_array($_GET['game'] ?? '', ['lotto','lotto_plus','mini_lotto'])
    ? $_GET['game']
    : 'lotto';

$allowedPages = ['dashboard','draws','stats','generator','validator','sync'];
if (!in_array($page, $allowedPages)) $page = 'dashboard';

require 'db.php';
include "{$page}.php";
```

---

## 7. Import danych (PHP)

### Plik `import.php`

- Pobiera cały plik z mbnet.com.pl (`file_get_contents` lub cURL)
- Parsuje format: `{numer}. {dd.mm.rrrr} {n1},{n2},{n3},...`
- Sortuje liczby ASC
- Oblicza wszystkie metryki + `profile_hash`
- INSERT IGNORE do tabeli `{game}_draws`
- Przelicza `{game}_draw_profiles` (total_draws, pct_of_total, last_seen)
- Uruchamiany ręcznie przez przeglądarkę lub CLI

### Plik `sync.php` (delta)

- Identyczna logika co import, ale pomija wiersze gdzie `draw_number <= MAX(draw_number)` z DB
- Wywoływany przyciskiem „Synchronizuj" w UI
- Zapisuje wynik do `sync_log`
- Dostęp chroniony tokenem: `?key=SECRET_TOKEN`

---

## 8. Widoki (strony PHP)

### 8.1 Dashboard (`?page=dashboard`)

- Ostatnie losowanie danej gry
- Skrót hot/cold: top 5 hot, top 5 cold
- Data ostatniej synchronizacji z `sync_log`
- Linki do pozostałych widoków

### 8.2 Historia losowań (`?page=draws`)

- Tabela HTML: draw_number, draw_date, n1–n6, sum_total, even_count
- Filtr zakresu dat (formularz GET)
- Paginacja (LIMIT/OFFSET)

### 8.3 Statystyki liczb (`?page=stats`)

- Tabela HTML per liczba: częstość całkowita, częstość okienkowa (last 500), hot/cold, aktualny gap, overdue score
- Sortowanie przez `?sort=frequency&dir=desc` (query params)
- Filtr zakresu dat

### 8.4 Generator kuponów (`?page=generator`)

Pełny generator z filtrami z briefu:

| Filtr | Kontrolka |
|---|---|
| Zakres sumy | dwa `<input type="number">` |
| Parzyste min/max | dwa `<select>` |
| Niskie/wysokie min/max | dwa `<select>` |
| Pary kolejne max | `<select>` |
| Dekady max per dekada | `<input>` |
| Hot numbers min z top-10 | `<select>` |
| Końcówki — max duplikatów | `<select>` |
| Profil hash (lista) | `<select multiple>` |

- Formularz POST → PHP generuje kupony (losowanie z wagami historycznymi + filtry)
- Wyniki w tabeli HTML

### 8.5 Walidator kuponu (`?page=validator`)

- Formularz: 6 pól `<input>` na liczby
- Po submit PHP zwraca:
  1. **Profil kuponu** (obliczony w locie) + jak często ten profil padał historycznie
  2. **Sprawdzenie historyczne** — czy dokładna kombinacja kiedykolwiek padła

### 8.6 Synchronizacja wbudowana

- Przycisk „Synchronizuj [gra]" w nawigacji
- Wywołuje `sync.php?key=TOKEN&game=lotto` przez formularz POST (lub link z tokenem)
- Wyświetla wynik: „Dodano N nowych losowań" lub błąd

---

## 9. Bezpieczeństwo

- Nazwy tabel budowane przez **whitelist** (`GAME_TABLES` array) — nie z user input
- Wszystkie zapytania SQL przez **PDO prepared statements**
- Sync chroniony tokenem w `.env` (plik poza `public_html`, w `.gitignore`)
- `$_GET['game']` i `$_GET['page']` walidowane przed użyciem
- `htmlspecialchars()` na wszystkich wartościach wyświetlanych w HTML

---

## 10. Plan budowy (fazy)

```
Faza 1 — Fundament
  □ Schema DB: games, lotto_draws, sync_log
  □ db.php (PDO connection z .env)
  □ helpers.php: obliczanie metryk + profile_hash
  □ import.php: parsowanie mbnet.com.pl + INSERT IGNORE
  □ Weryfikacja danych w phpMyAdmin

Faza 2 — Wyświetlanie
  □ index.php (router)
  □ draws.php: tabela historii losowań
  □ stats.php: tabela częstości + hot/cold

Faza 3 — Sync + pozostałe gry
  □ sync.php: delta import + sync_log
  □ Tabele i import dla lotto_plus i mini_lotto

Faza 4 — Generator + Walidator
  □ validator.php: profil + sprawdzenie historyczne
  □ generator.php: filtry + losowanie z wagami

Faza 5 — Dashboard
  □ dashboard.php: skrót + ostatnie losowanie + sync CTA
```

---

## 11. Co wykluczone z tego planu (vs pełny brief)

| Pominięte | Powód |
|---|---|
| React + Tailwind | Zastąpione surowym HTML |
| Chart.js / wykresy | Brak — tylko tabele |
| API REST endpoints | Brak — PHP generuje HTML bezpośrednio |
| Python (import lokalny) | Zastąpiony PHP import.php |
| cron automatyczny | Zastąpiony ręcznym przyciskiem sync |
| .htaccess clean URLs | Zastąpiony query params |
| EMA, RSI-14, chi-kwadrat, Markov | Post-MVP |
| Wheeling (Full/Abbreviated/Key) | Post-MVP |
| Multi Multi | Post-MVP |
| Analiza epok (heatmapy) | Post-MVP |
| Macierz par (heatmapa NxN) | Post-MVP |
