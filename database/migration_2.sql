-- Migration à exécuter UNIQUEMENT si la base reporting_it existe déjà
-- (installation neuve : schema.sql suffit, ce fichier n'est pas nécessaire)
-- Ce script est idempotent et compatible avec toutes les versions de MySQL/MariaDB
-- (voir migration_4.sql pour le détail de la technique utilisée).
USE reporting_it;

ALTER TABLE rapports
    MODIFY contenu TEXT DEFAULT NULL;

SET @colonne_existe = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = 'reporting_it' AND TABLE_NAME = 'rapports' AND COLUMN_NAME = 'fichier_word'
);
SET @sql = IF(@colonne_existe = 0,
    'ALTER TABLE rapports ADD COLUMN fichier_word VARCHAR(255) DEFAULT NULL COMMENT ''nom du fichier .docx uploadé, si le rapport a été soumis au format Word'' AFTER contenu',
    'SELECT ''Colonne fichier_word déjà présente, rien à faire.'''
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
