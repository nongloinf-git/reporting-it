-- Migration à exécuter UNIQUEMENT si la base reporting_it existe déjà
-- (installation neuve : schema.sql suffit, ce fichier n'est pas nécessaire)
-- Ce script est idempotent : le relancer sur une base où il a déjà été appliqué
-- (ou sur une base créée avec un schema.sql déjà à jour) ne provoque pas d'erreur.
USE reporting_it;

ALTER TABLE rapports
    MODIFY contenu TEXT DEFAULT NULL;

ALTER TABLE rapports
    ADD COLUMN IF NOT EXISTS fichier_word VARCHAR(255) DEFAULT NULL COMMENT 'nom du fichier .docx uploadé, si le rapport a été soumis au format Word' AFTER contenu;
