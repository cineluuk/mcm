-- MCM / Matchday Content Manager
-- In phpMyAdmin die MCM-Datenbank auswählen, dann diese Datei importieren.
-- Kein CREATE DATABASE: Bei Infomaniak wird die DB im Manager angelegt.

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS mcm_photos;
DROP TABLE IF EXISTS mcm_matches;
DROP TABLE IF EXISTS mcm_users;

SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE mcm_users (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    username VARCHAR(64) NOT NULL,
    email VARCHAR(190) DEFAULT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin', 'photographer', 'social') NOT NULL DEFAULT 'social',
    club VARCHAR(190) DEFAULT NULL,
    is_active TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_mcm_users_username (username),
    UNIQUE KEY uq_mcm_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE mcm_matches (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    title VARCHAR(190) NOT NULL,
    kickoff_at DATETIME NOT NULL,
    venue VARCHAR(190) DEFAULT NULL,
    club VARCHAR(190) DEFAULT NULL,
    created_by INT UNSIGNED DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_mcm_matches_kickoff (kickoff_at),
    KEY idx_mcm_matches_created_by (created_by),
    CONSTRAINT fk_mcm_matches_created_by
        FOREIGN KEY (created_by) REFERENCES mcm_users (id)
        ON DELETE SET NULL
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE mcm_photos (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    match_id INT UNSIGNED DEFAULT NULL,
    filename VARCHAR(255) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_mcm_photos_filename (filename),
    KEY idx_mcm_photos_match (match_id),
    CONSTRAINT fk_mcm_photos_match
        FOREIGN KEY (match_id) REFERENCES mcm_matches (id)
        ON DELETE SET NULL
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
