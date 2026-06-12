-- Sri Lanka Squash ranking system migration
-- Review against your current schema before running on production data.

ALTER TABLE users
    MODIFY password VARCHAR(255) NOT NULL,
    ADD COLUMN player_id INT UNSIGNED NULL,
    ADD COLUMN is_first_login TINYINT(1) NOT NULL DEFAULT 1,
    ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1;

ALTER TABLE players
    ADD COLUMN nic VARCHAR(20) NULL,
    ADD COLUMN dob DATE NULL,
    ADD COLUMN date_of_birth DATE NULL,
    ADD COLUMN gender ENUM('Male','Female') NULL,
    ADD COLUMN calculated_category_id INT UNSIGNED NULL,
    ADD COLUMN address TEXT NULL,
    ADD COLUMN phone VARCHAR(20) NULL,
    ADD COLUMN email VARCHAR(100) NULL,
    ADD COLUMN psa_wsf_ranking INT UNSIGNED DEFAULT NULL;

CREATE TABLE IF NOT EXISTS age_categories (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(60) NOT NULL UNIQUE,
    gender ENUM('Male', 'Female', 'Mixed') NOT NULL DEFAULT 'Mixed'
);

INSERT IGNORE INTO age_categories (name, gender) VALUES
('Boys U9', 'Male'), ('Girls U9', 'Female'),
('Boys U11', 'Male'), ('Girls U11', 'Female'),
('Boys U13', 'Male'), ('Girls U13', 'Female'),
('Boys U15', 'Male'), ('Girls U15', 'Female'),
('Boys U17', 'Male'), ('Girls U17', 'Female'),
('Boys U19', 'Male'), ('Girls U19', 'Female'),
('Men''s Open', 'Male'), ('Women''s Open', 'Female'),
('Women''s Over 35', 'Female'),
('Men''s Over 35', 'Male'), ('Men''s Over 40', 'Male'),
('Men''s Over 45', 'Male'), ('Men''s Masters Over 50', 'Male'),
('Men''s Masters Over 55', 'Male'), ('Men''s Masters Over 60', 'Male'),
('Men''s Masters Over 65', 'Male');

CREATE TABLE IF NOT EXISTS player_categories (
    player_id INT UNSIGNED NOT NULL,
    category_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (player_id, category_id),
    INDEX idx_player_categories_category (category_id),
    FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES age_categories(id) ON DELETE CASCADE
);

ALTER TABLE tournaments
    ADD COLUMN name VARCHAR(200) NULL,
    ADD COLUMN tier ENUM('A','B') NOT NULL DEFAULT 'B',
    ADD COLUMN draw_size INT UNSIGNED NOT NULL DEFAULT 0,
    ADD COLUMN held_on DATE NULL,
    ADD COLUMN category_id INT UNSIGNED NULL,
    ADD COLUMN is_archived TINYINT(1) NOT NULL DEFAULT 0;

UPDATE tournaments
SET name = COALESCE(name, tournament_name),
    held_on = COALESCE(held_on, STR_TO_DATE(RIGHT(tournament_name, 4), '%Y'));

CREATE TABLE IF NOT EXISTS tournament_results (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tournament_id INT UNSIGNED NOT NULL,
    player_id INT UNSIGNED NOT NULL,
    finish_position TINYINT UNSIGNED DEFAULT NULL,
    points_awarded DECIMAL(8,2) NOT NULL DEFAULT 0.00,
    is_penalty TINYINT(1) NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_tournament_player (tournament_id, player_id),
    INDEX idx_results_player (player_id),
    INDEX idx_results_tournament (tournament_id),
    FOREIGN KEY (tournament_id) REFERENCES tournaments(id),
    FOREIGN KEY (player_id) REFERENCES players(id)
);

INSERT IGNORE INTO tournament_results (id, tournament_id, player_id, finish_position, points_awarded, is_penalty, is_active, created_at)
SELECT id, tournament_id, player_id, NULL, CAST(score AS DECIMAL(8,2)), IF(score = 0, 1, 0), 1, NOW()
FROM scores;

CREATE TABLE IF NOT EXISTS rankings (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    player_id INT UNSIGNED NOT NULL,
    category_id INT UNSIGNED NOT NULL,
    ranking_average DECIMAL(8,4) NOT NULL DEFAULT 0.0000,
    rank_position INT UNSIGNED NOT NULL,
    calculated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    period_label VARCHAR(20) NOT NULL,
    INDEX idx_rankings_period_category (period_label, category_id),
    INDEX idx_rankings_player (player_id),
    FOREIGN KEY (player_id) REFERENCES players(id),
    FOREIGN KEY (category_id) REFERENCES age_categories(id)
);

CREATE TABLE IF NOT EXISTS system_settings (
    setting_key VARCHAR(80) PRIMARY KEY,
    setting_value VARCHAR(255) NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

INSERT INTO system_settings (setting_key, setting_value) VALUES
('ranking_divisor', '4'),
('transition_mode', '0'),
('last_ranking_run', NULL)
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);

CREATE TABLE IF NOT EXISTS audit_logs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    action VARCHAR(100) NOT NULL,
    target_type VARCHAR(50),
    target_id INT UNSIGNED,
    detail TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_audit_created (created_at),
    FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE INDEX idx_tournaments_held_on ON tournaments (held_on);
CREATE INDEX idx_tournaments_category ON tournaments (category_id);
CREATE INDEX idx_players_calculated_category ON players (calculated_category_id);
