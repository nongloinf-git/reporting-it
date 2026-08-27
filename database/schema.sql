-- Base de données : reporting_it
-- A importer via phpMyAdmin (WampServer) ou en ligne de commande MySQL

CREATE DATABASE IF NOT EXISTS reporting_it CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE reporting_it;

-- Table des utilisateurs (admin, manager, collaborateur)
CREATE TABLE utilisateurs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    mot_de_passe VARCHAR(255) NOT NULL,
    role ENUM('admin', 'manager', 'collaborateur') NOT NULL DEFAULT 'collaborateur',
    equipe VARCHAR(100) DEFAULT NULL,
    manager_id INT DEFAULT NULL,
    photo_profil VARCHAR(255) DEFAULT NULL,
    actif TINYINT(1) NOT NULL DEFAULT 1,
    peut_gerer_reunions TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'permission d''organiser des réunions et d''y assigner des tâches, indépendamment du rôle',
    date_creation TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (manager_id) REFERENCES utilisateurs(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Table des rapports hebdomadaires
CREATE TABLE rapports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    utilisateur_id INT NOT NULL,
    annee INT NOT NULL,
    semaine_numero TINYINT NOT NULL,
    contenu TEXT DEFAULT NULL,
    fichier_word VARCHAR(255) DEFAULT NULL COMMENT 'nom du fichier .docx uploadé, si le rapport a été soumis au format Word',
    temps_passe DECIMAL(5,2) DEFAULT NULL COMMENT 'en heures',
    statut ENUM('brouillon', 'soumis', 'valide') NOT NULL DEFAULT 'brouillon',
    date_soumission TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_rapport_semaine (utilisateur_id, annee, semaine_numero),
    FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Table des commentaires de validation (manager -> rapport)
CREATE TABLE commentaires_validation (
    id INT AUTO_INCREMENT PRIMARY KEY,
    rapport_id INT NOT NULL,
    manager_id INT NOT NULL,
    commentaire TEXT NOT NULL,
    date_creation TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (rapport_id) REFERENCES rapports(id) ON DELETE CASCADE,
    FOREIGN KEY (manager_id) REFERENCES utilisateurs(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Paramètres globaux de l'application (logo société, nom société, etc.)
CREATE TABLE parametres (
    cle VARCHAR(100) PRIMARY KEY,
    valeur VARCHAR(255) DEFAULT NULL
) ENGINE=InnoDB;

-- Table des réunions
CREATE TABLE reunions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    titre VARCHAR(200) NOT NULL,
    description TEXT DEFAULT NULL,
    date_reunion DATETIME NOT NULL,
    lieu VARCHAR(200) DEFAULT NULL,
    organisateur_id INT NOT NULL,
    date_creation TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (organisateur_id) REFERENCES utilisateurs(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Participants d'une réunion
CREATE TABLE reunion_participants (
    reunion_id INT NOT NULL,
    utilisateur_id INT NOT NULL,
    PRIMARY KEY (reunion_id, utilisateur_id),
    FOREIGN KEY (reunion_id) REFERENCES reunions(id) ON DELETE CASCADE,
    FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Tâches issues d'une réunion, assignées à un participant
CREATE TABLE taches_reunion (
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

-- Compte admin par défaut : email admin@local.test / mot de passe : Admin123!
-- (hash généré avec password_hash - à changer après la première connexion)
INSERT INTO utilisateurs (nom, email, mot_de_passe, role, equipe, peut_gerer_reunions)
VALUES ('Administrateur', 'admin@local.test', '$2y$10$wr7DWu900yTQ6kybAGwWC.qVVUsgbRINGzLZpKzM3T3WqSZWMjxKq', 'admin', 'IT', 1);
