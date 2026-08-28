-- Migration à exécuter UNIQUEMENT si la base reporting_it existe déjà.
-- (installation neuve : schema.sql suffit, ce fichier n'est pas nécessaire)
-- Ce script est idempotent (le relancer sur une base déjà à jour ne provoque pas
-- d'erreur) et compatible avec toutes les versions de MySQL/MariaDB : la syntaxe
-- "ADD COLUMN IF NOT EXISTS" n'existe que depuis MariaDB 10.0.2 / MySQL 8.0.29,
-- on utilise donc ici une vérification via information_schema + requête préparée,
-- qui fonctionne aussi sur des versions plus anciennes (ex: MySQL 5.7).
USE reporting_it;

-- Colonne date_envoi sur rapports
SET @colonne_existe = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = 'reporting_it' AND TABLE_NAME = 'rapports' AND COLUMN_NAME = 'date_envoi'
);
SET @sql = IF(@colonne_existe = 0,
    'ALTER TABLE rapports ADD COLUMN date_envoi DATETIME NULL COMMENT ''date/heure de la dernière soumission au manager'' AFTER statut',
    'SELECT ''Colonne date_envoi déjà présente, rien à faire.'''
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Colonne date_validation sur rapports
SET @colonne_existe = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = 'reporting_it' AND TABLE_NAME = 'rapports' AND COLUMN_NAME = 'date_validation'
);
SET @sql = IF(@colonne_existe = 0,
    'ALTER TABLE rapports ADD COLUMN date_validation DATETIME NULL COMMENT ''date/heure de validation par le manager/admin'' AFTER date_envoi',
    'SELECT ''Colonne date_validation déjà présente, rien à faire.'''
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Table journal_activite (CREATE TABLE IF NOT EXISTS est lui compatible avec toutes les versions)
CREATE TABLE IF NOT EXISTS journal_activite (
    id INT AUTO_INCREMENT PRIMARY KEY,
    utilisateur_id INT DEFAULT NULL COMMENT 'NULL si connexion échouée sur un email inconnu',
    email_tentative VARCHAR(150) DEFAULT NULL COMMENT 'email saisi, utile en cas d''échec de connexion',
    action VARCHAR(50) NOT NULL,
    details TEXT DEFAULT NULL,
    adresse_ip VARCHAR(45) DEFAULT NULL,
    date_action TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id) ON DELETE SET NULL,
    INDEX idx_journal_utilisateur (utilisateur_id),
    INDEX idx_journal_action (action),
    INDEX idx_journal_date (date_action)
) ENGINE=InnoDB;
