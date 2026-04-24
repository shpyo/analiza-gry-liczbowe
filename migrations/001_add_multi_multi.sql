-- Migration 001 – Add Multi Multi game
-- Run once on an existing database to add Multi Multi support.
-- Safe to re-run: all statements use INSERT IGNORE / CREATE TABLE IF NOT EXISTS.

-- 1. Register the game
INSERT IGNORE INTO games
  (slug, name, pick_count, pool_size, has_bonus, low_threshold, supports_profiles, sync_url, active)
VALUES
  ('multi_multi', 'Multi Multi', 20, 80, FALSE, 40, TRUE, 'http://mbnet.com.pl/ml.txt', TRUE);

-- 2. Draws table (20 number columns)
CREATE TABLE IF NOT EXISTS multi_multi_draws (
  id               INT AUTO_INCREMENT PRIMARY KEY,
  draw_date        DATE NOT NULL,
  draw_number      INT UNIQUE,
  n1  TINYINT, n2  TINYINT, n3  TINYINT, n4  TINYINT, n5  TINYINT,
  n6  TINYINT, n7  TINYINT, n8  TINYINT, n9  TINYINT, n10 TINYINT,
  n11 TINYINT, n12 TINYINT, n13 TINYINT, n14 TINYINT, n15 TINYINT,
  n16 TINYINT, n17 TINYINT, n18 TINYINT, n19 TINYINT, n20 TINYINT,
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

-- 3. Draw profiles table
CREATE TABLE IF NOT EXISTS multi_multi_draw_profiles (
  profile_hash  VARCHAR(30) PRIMARY KEY,
  even_count    TINYINT,
  low_count     TINYINT,
  sum_bucket    CHAR(3),
  consecutive   TINYINT,
  range_bucket  CHAR(3),
  total_draws   INT,
  pct_of_total  DECIMAL(5,2),
  last_seen     DATE,
  first_seen    DATE
);

-- 4. Co-occurrence: pairs
CREATE TABLE IF NOT EXISTS multi_multi_pairs (
  n1        TINYINT NOT NULL,
  n2        TINYINT NOT NULL,
  count     INT     NOT NULL DEFAULT 0,
  last_seen DATE,
  PRIMARY KEY (n1, n2)
);

-- 5. Co-occurrence: triples
CREATE TABLE IF NOT EXISTS multi_multi_triples (
  n1        TINYINT NOT NULL,
  n2        TINYINT NOT NULL,
  n3        TINYINT NOT NULL,
  count     INT     NOT NULL DEFAULT 0,
  last_seen DATE,
  PRIMARY KEY (n1, n2, n3)
);
