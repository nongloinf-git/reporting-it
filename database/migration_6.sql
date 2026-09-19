-- Migration à exécuter UNIQUEMENT si la base reporting_it existe déjà.
-- (installation neuve : schema.sql suffit, ce fichier n'est pas nécessaire)
-- Idempotent et compatible avec toutes les versions de MySQL/MariaDB
-- (voir migration_4.sql pour le détail de la technique utilisée).
USE reporting_it;

-- Fonction du collaborateur (renseignée par l'admin sur la fiche utilisateur),
-- utilisée pour pré-remplir automatiquement le rapport hebdomadaire structuré.
SET @colonne_existe = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = 'reporting_it' AND TABLE_NAME = 'utilisateurs' AND COLUMN_NAME = 'fonction'
);
SET @sql = IF(@colonne_existe = 0,
    'ALTER TABLE utilisateurs ADD COLUMN fonction VARCHAR(150) DEFAULT NULL AFTER equipe',
    'SELECT ''Colonne fonction déjà présente, rien à faire.'''
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Données structurées du rapport hebdomadaire (site, activités, difficultés,
-- actions prévues, conclusion) au format JSON, utilisées par le formulaire de
-- rapport structuré et par la génération Word/PDF. Le champ "contenu" existant
-- continue de recevoir un résumé texte lisible pour rester compatible avec les
-- vues existantes (tableau de bord, historique, exports).
SET @colonne_existe = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = 'reporting_it' AND TABLE_NAME = 'rapports' AND COLUMN_NAME = 'donnees_structurees'
);
SET @sql = IF(@colonne_existe = 0,
    'ALTER TABLE rapports ADD COLUMN donnees_structurees TEXT DEFAULT NULL COMMENT ''JSON : site, activites, difficultes, actions_prevues, conclusion'' AFTER contenu',
    'SELECT ''Colonne donnees_structurees déjà présente, rien à faire.'''
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
