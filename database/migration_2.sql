-- Migration à exécuter UNIQUEMENT si la base reporting_it existe déjà
-- (installation neuve : schema.sql suffit, ce fichier n'est pas nécessaire)
USE reporting_it;

ALTER TABLE rapports
    MODIFY contenu TEXT DEFAULT NULL,
    ADD COLUMN fichier_word VARCHAR(255) DEFAULT NULL COMMENT 'nom du fichier .docx uploadé, si le rapport a été soumis au format Word' AFTER contenu;
