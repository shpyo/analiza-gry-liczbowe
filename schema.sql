-- Lotto Analysis Application - Database Schema

CREATE TABLE IF NOT EXISTS games (
  slug VARCHAR(20) PRIMARY KEY,
  name VARCHAR(50) NOT NULL,
  pick_count TINYINT NOT NULL,
  pool_size TINYINT NOT NULL,
  has_bonus BOOLEAN DEFAULT FALSE,
  low_threshold TINYINT NOT NULL,
  supports_profiles BOOLEAN DEFAULT FALSE,
  sync_url VARCHAR(200),
  active BOOLEAN DEFAULT TRUE
);

INSERT IGNORE INTO games VALUES
  ('lotto','Lotto',6,49,FALSE,24,TRUE,'https://mbnet.com.pl/dl.txt',TRUE),
  ('lotto_plus','Lotto Plus',6,49,TRUE,24,TRUE,'https://mbnet.com.pl/dl_plus.txt',TRUE),
  ('mini_lotto','Mini Lotto',5,42,FALSE,21,TRUE,'https://mbnet.com.pl/ml.txt',TRUE);

CREATE TABLE IF NOT EXISTS lotto_draws (
  id INT AUTO_INCREMENT PRIMARY KEY,
  draw_date DATE NOT NULL,
  draw_number INT UNIQUE,
  n1 TINYINT, n2 TINYINT, n3 TINYINT,
  n4 TINYINT, n5 TINYINT, n6 TINYINT,
  sum_total SMALLINT,
  even_count TINYINT,
  low_count TINYINT,
  consecutive TINYINT,
  decades_used TINYINT,
  range_spread TINYINT,
  last_digit_unique TINYINT,
  profile_hash VARCHAR(30),
  INDEX idx_date (draw_date),
  INDEX idx_profile (profile_hash)
);

CREATE TABLE IF NOT EXISTS lotto_plus_draws (
  id INT AUTO_INCREMENT PRIMARY KEY,
  draw_date DATE NOT NULL,
  draw_number INT UNIQUE,
  n1 TINYINT, n2 TINYINT, n3 TINYINT,
  n4 TINYINT, n5 TINYINT, n6 TINYINT,
  plus_ball TINYINT,
  sum_total SMALLINT,
  even_count TINYINT,
  low_count TINYINT,
  consecutive TINYINT,
  decades_used TINYINT,
  range_spread TINYINT,
  last_digit_unique TINYINT,
  profile_hash VARCHAR(30),
  INDEX idx_date (draw_date),
  INDEX idx_profile (profile_hash)
);

CREATE TABLE IF NOT EXISTS mini_lotto_draws (
  id INT AUTO_INCREMENT PRIMARY KEY,
  draw_date DATE NOT NULL,
  draw_number INT UNIQUE,
  n1 TINYINT, n2 TINYINT, n3 TINYINT,
  n4 TINYINT, n5 TINYINT,
  sum_total SMALLINT,
  even_count TINYINT,
  low_count TINYINT,
  consecutive TINYINT,
  decades_used TINYINT,
  range_spread TINYINT,
  last_digit_unique TINYINT,
  profile_hash VARCHAR(30),
  INDEX idx_date (draw_date),
  INDEX idx_profile (profile_hash)
);

CREATE TABLE IF NOT EXISTS lotto_draw_profiles (
  profile_hash VARCHAR(30) PRIMARY KEY,
  even_count TINYINT,
  low_count TINYINT,
  sum_bucket CHAR(3),
  consecutive TINYINT,
  range_bucket CHAR(3),
  total_draws INT,
  pct_of_total DECIMAL(5,2),
  last_seen DATE,
  first_seen DATE
);

CREATE TABLE IF NOT EXISTS lotto_plus_draw_profiles (
  profile_hash VARCHAR(30) PRIMARY KEY,
  even_count TINYINT,
  low_count TINYINT,
  sum_bucket CHAR(3),
  consecutive TINYINT,
  range_bucket CHAR(3),
  total_draws INT,
  pct_of_total DECIMAL(5,2),
  last_seen DATE,
  first_seen DATE
);

CREATE TABLE IF NOT EXISTS mini_lotto_draw_profiles (
  profile_hash VARCHAR(30) PRIMARY KEY,
  even_count TINYINT,
  low_count TINYINT,
  sum_bucket CHAR(3),
  consecutive TINYINT,
  range_bucket CHAR(3),
  total_draws INT,
  pct_of_total DECIMAL(5,2),
  last_seen DATE,
  first_seen DATE
);

CREATE TABLE IF NOT EXISTS sync_log (
  id INT AUTO_INCREMENT PRIMARY KEY,
  game_slug VARCHAR(20),
  synced_at DATETIME DEFAULT NOW(),
  draws_added INT,
  last_draw_number INT,
  source_url VARCHAR(200),
  status ENUM('ok','error','no_new'),
  error_msg TEXT
);
