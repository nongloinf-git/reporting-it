-- Migration à exécuter UNIQUEMENT si la base reporting_it existe déjà.
-- (installation neuve : schema.sql suffit, ce fichier n'est pas nécessaire)
-- Idempotent et compatible avec toutes les versions de MySQL/MariaDB
-- (voir migration_4.sql pour le détail de la technique utilisée : information_schema + requête préparée).
USE reporting_it;

-- --- Personnalisation d'interface (couleur, mode sombre) ---

SET @colonne_existe = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = 'reporting_it' AND TABLE_NAME = 'utilisateurs' AND COLUMN_NAME = 'theme_couleur'
);
SET @sql = IF(@colonne_existe = 0,
    'ALTER TABLE utilisateurs ADD COLUMN theme_couleur VARCHAR(20) NOT NULL DEFAULT ''bleu'' COMMENT ''couleur d''''accent de l''''interface : bleu, vert, violet, orange, rouge'' AFTER peut_gerer_reunions',
    'SELECT ''Colonne theme_couleur déjà présente, rien à faire.'''
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @colonne_existe = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = 'reporting_it' AND TABLE_NAME = 'utilisateurs' AND COLUMN_NAME = 'mode_sombre'
);
SET @sql = IF(@colonne_existe = 0,
    'ALTER TABLE utilisateurs ADD COLUMN mode_sombre TINYINT(1) NOT NULL DEFAULT 0 AFTER theme_couleur',
    'SELECT ''Colonne mode_sombre déjà présente, rien à faire.'''
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- --- Généralisation des tâches : réunion optionnelle, sous-tâches, créateur, titre ---

-- La tâche peut ne plus être liée à une réunion (tâche créée directement)
ALTER TABLE taches_reunion
    MODIFY reunion_id INT DEFAULT NULL COMMENT 'NULL si tâche créée directement, sans passer par une réunion';

SET @colonne_existe = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = 'reporting_it' AND TABLE_NAME = 'taches_reunion' AND COLUMN_NAME = 'titre'
);
SET @sql = IF(@colonne_existe = 0,
    'ALTER TABLE taches_reunion ADD COLUMN titre VARCHAR(255) DEFAULT NULL COMMENT ''titre court ; si NULL, la description tient lieu de titre dans les listes'' AFTER reunion_id',
    'SELECT ''Colonne titre déjà présente, rien à faire.'''
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @colonne_existe = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = 'reporting_it' AND TABLE_NAME = 'taches_reunion' AND COLUMN_NAME = 'createur_id'
);
SET @sql = IF(@colonne_existe = 0,
    'ALTER TABLE taches_reunion ADD COLUMN createur_id INT DEFAULT NULL COMMENT ''utilisateur ayant créé la tâche'' AFTER titre, ADD CONSTRAINT fk_taches_createur FOREIGN KEY (createur_id) REFERENCES utilisateurs(id) ON DELETE SET NULL',
    'SELECT ''Colonne createur_id déjà présente, rien à faire.'''
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @colonne_existe = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = 'reporting_it' AND TABLE_NAME = 'taches_reunion' AND COLUMN_NAME = 'parent_tache_id'
);
SET @sql = IF(@colonne_existe = 0,
    'ALTER TABLE taches_reunion ADD COLUMN parent_tache_id INT DEFAULT NULL COMMENT ''renseigné pour une sous-tâche'' AFTER createur_id, ADD CONSTRAINT fk_taches_parent FOREIGN KEY (parent_tache_id) REFERENCES taches_reunion(id) ON DELETE CASCADE',
    'SELECT ''Colonne parent_tache_id déjà présente, rien à faire.'''
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
