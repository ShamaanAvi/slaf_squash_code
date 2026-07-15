-- Fresh database setup for SLAF Squash ranking system.
-- WARNING: This script drops and recreates the database named below.

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP DATABASE IF EXISTS slaf_squash_test;
CREATE DATABASE slaf_squash_test
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE slaf_squash_test;

SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'player') NOT NULL DEFAULT 'player',
    player_id INT UNSIGNED NULL,
    is_first_login TINYINT(1) NOT NULL DEFAULT 1,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_users_player (player_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE user_sessions (
    session_id VARCHAR(128) PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    last_activity INT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_sessions_user_activity (user_id, last_activity),
    CONSTRAINT fk_user_sessions_user
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE players (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL UNIQUE,
    full_name VARCHAR(150) NOT NULL,
    identity_type ENUM('NIC', 'Passport') NOT NULL DEFAULT 'NIC',
    nic VARCHAR(20) NULL UNIQUE,
    passport_expiry_date DATE NULL,
    dob DATE NULL,
    date_of_birth DATE NULL,
    gender ENUM('Male', 'Female') NULL,
    calculated_category_id INT UNSIGNED NULL,
    address TEXT NULL,
    phone VARCHAR(20) NULL,
    email VARCHAR(100) NULL,
    psa_wsf_ranking INT UNSIGNED DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_players_calculated_category (calculated_category_id),
    CONSTRAINT fk_players_user
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE age_categories (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(60) NOT NULL UNIQUE,
    gender ENUM('Male', 'Female', 'Mixed') NOT NULL DEFAULT 'Mixed'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE player_categories (
    player_id INT UNSIGNED NOT NULL,
    category_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (player_id, category_id),
    INDEX idx_player_categories_category (category_id),
    CONSTRAINT fk_player_categories_player
        FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE,
    CONSTRAINT fk_player_categories_category
        FOREIGN KEY (category_id) REFERENCES age_categories(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE tournaments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tournament_name VARCHAR(200) NOT NULL,
    name VARCHAR(200) NOT NULL,
    tier ENUM('A', 'B') NOT NULL DEFAULT 'B',
    draw_size INT UNSIGNED NOT NULL DEFAULT 0,
    held_on DATE NOT NULL,
    end_on DATE NOT NULL,
    category_id INT UNSIGNED NOT NULL,
    is_archived TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_tournaments_held_on (held_on),
    INDEX idx_tournaments_category (category_id),
    CONSTRAINT fk_tournaments_category
        FOREIGN KEY (category_id) REFERENCES age_categories(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE player_tournaments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    player_id INT UNSIGNED NOT NULL,
    tournament_id INT UNSIGNED NOT NULL,
    category VARCHAR(60) NOT NULL,
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_player_tournament (player_id, tournament_id),
    INDEX idx_player_tournaments_tournament (tournament_id),
    CONSTRAINT fk_player_tournaments_player
        FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE,
    CONSTRAINT fk_player_tournaments_tournament
        FOREIGN KEY (tournament_id) REFERENCES tournaments(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE tournament_results (
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
    CONSTRAINT fk_results_tournament
        FOREIGN KEY (tournament_id) REFERENCES tournaments(id) ON DELETE CASCADE,
    CONSTRAINT fk_results_player
        FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE rankings (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    player_id INT UNSIGNED NOT NULL,
    category_id INT UNSIGNED NOT NULL,
    ranking_average DECIMAL(8,4) NOT NULL DEFAULT 0.0000,
    rank_position INT UNSIGNED NOT NULL,
    calculated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    period_label VARCHAR(20) NOT NULL,
    INDEX idx_rankings_period_category (period_label, category_id),
    INDEX idx_rankings_player (player_id),
    CONSTRAINT fk_rankings_player
        FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE,
    CONSTRAINT fk_rankings_category
        FOREIGN KEY (category_id) REFERENCES age_categories(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE system_settings (
    setting_key VARCHAR(80) PRIMARY KEY,
    setting_value VARCHAR(255) NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE audit_logs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    action VARCHAR(100) NOT NULL,
    target_type VARCHAR(50) NULL,
    target_id INT UNSIGNED NULL,
    detail TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_audit_created (created_at),
    INDEX idx_audit_user (user_id),
    CONSTRAINT fk_audit_user
        FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Kept for compatibility with older migration scripts. Current code uses tournament_results.
CREATE TABLE scores (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    player_id INT UNSIGNED NOT NULL,
    tournament_id INT UNSIGNED NOT NULL,
    score INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_scores_player (player_id),
    INDEX idx_scores_tournament (tournament_id),
    CONSTRAINT fk_scores_player
        FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE,
    CONSTRAINT fk_scores_tournament
        FOREIGN KEY (tournament_id) REFERENCES tournaments(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO age_categories (name, gender) VALUES
('Boys U9', 'Male'), ('Girls U9', 'Female'),
('Boys U11', 'Male'), ('Girls U11', 'Female'),
('Boys U 11 Novice', 'Male'), ('Girls U 11 Novice', 'Female'),
('Boys U13', 'Male'), ('Girls U13', 'Female'),
('Boys U15', 'Male'), ('Girls U15', 'Female'),
('Boys U 15 Novice', 'Male'), ('Girls U 15 Novice', 'Female'),
('Boys U17', 'Male'), ('Girls U17', 'Female'),
('Boys U19', 'Male'), ('Girls U19', 'Female'),
('Men''s Open', 'Male'), ('Women''s Open', 'Female'),
('Women''s Over 35', 'Female'),
('Men''s Over 35', 'Male'), ('Men''s Over 40', 'Male'),
('Men''s Over 45', 'Male'), ('Men''s Masters Over 50', 'Male'),
('Men''s Masters Over 55', 'Male'), ('Men''s Masters Over 60', 'Male'),
('Men''s Masters Over 65', 'Male');

INSERT INTO system_settings (setting_key, setting_value) VALUES
('ranking_divisor', '4'),
('transition_mode', '0'),
('last_ranking_run', NULL);

-- Default admin login:
-- username: admin
-- password: admin123
INSERT INTO users (username, password, role, is_first_login, is_active)
VALUES (
    'admin',
    '$2y$12$zk6v/JnLqHsQG6VZxAfZPuo42XrRiigKC/m6eZ1tyEm7DIgL0W6Rm',
    'admin',
    0,
    1
);
