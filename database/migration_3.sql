-- Migration à exécuter UNIQUEMENT si la base reporting_it existe déjà
-- avec les tables de base + fichier_word (migration_2).
-- (installation neuve : schema.sql suffit, ce fichier n'est pas nécessaire)
-- Ce script est idempotent et compatible avec toutes les versions de MySQL/MariaDB
-- (voir migration_4.sql pour le détail de la technique utilisée).
USE reporting_it;

SET @colonne_existe = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = 'reporting_it' AND TABLE_NAME = 'utilisateurs' AND COLUMN_NAME = 'photo_profil'
);
SET @sql = IF(@colonne_existe = 0,
    'ALTER TABLE utilisateurs ADD COLUMN photo_profil VARCHAR(255) DEFAULT NULL AFTER manager_id',
    'SELECT ''Colonne photo_profil déjà présente, rien à faire.'''
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @colonne_existe = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = 'reporting_it' AND TABLE_NAME = 'utilisateurs' AND COLUMN_NAME = 'actif'
);
SET @sql = IF(@colonne_existe = 0,
    'ALTER TABLE utilisateurs ADD COLUMN actif TINYINT(1) NOT NULL DEFAULT 1 AFTER photo_profil',
    'SELECT ''Colonne actif déjà présente, rien à faire.'''
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @colonne_existe = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = 'reporting_it' AND TABLE_NAME = 'utilisateurs' AND COLUMN_NAME = 'peut_gerer_reunions'
);
SET @sql = IF(@colonne_existe = 0,
    'ALTER TABLE utilisateurs ADD COLUMN peut_gerer_reunions TINYINT(1) NOT NULL DEFAULT 0 COMMENT ''permission d''''organiser des réunions et d''''y assigner des tâches, indépendamment du rôle'' AFTER actif',
    'SELECT ''Colonne peut_gerer_reunions déjà présente, rien à faire.'''
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Donne la permission de gérer les réunions à tous les comptes admin existants
UPDATE utilisateurs SET peut_gerer_reunions = 1 WHERE role = 'admin';

CREATE TABLE IF NOT EXISTS parametres (
    cle VARCHAR(100) PRIMARY KEY,
    valeur VARCHAR(255) DEFAULT NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS reunions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    titre VARCHAR(200) NOT NULL,
    description TEXT DEFAULT NULL,
    date_reunion DATETIME NOT NULL,
    lieu VARCHAR(200) DEFAULT NULL,
    organisateur_id INT NOT NULL,
    date_creation TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (organisateur_id) REFERENCES utilisateurs(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS reunion_participants (
    reunion_id INT NOT NULL,
    utilisateur_id INT NOT NULL,
    PRIMARY KEY (reunion_id, utilisateur_id),
    FOREIGN KEY (reunion_id) REFERENCES reunions(id) ON DELETE CASCADE,
    FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS taches_reunion (
    id INT AUTO_INCREMENT PRIMARY KEY,
    reunion_id INT NOT NULL,
    description TEXT NOT NULL,
    responsable_id INT DEFAULT NULL,
    echeance DATE DEFAULT NULL,
    statut ENUM('a_faire', 'en_cours', 'termine') NOT NULL DEFAULT 'a_faire',
    date_creation TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (reunion_id) REFERENCES reunions(id) ON DELETE CASCADE,
    FOREIGN KEY (responsable_id) REFERENCES utilisateurs(id) ON DELETE SET NULL
) ENGINE=InnoDB;
