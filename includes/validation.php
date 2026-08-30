<?php
require_once __DIR__ . '/../config/database.php';

/**
 * Valide un format d'email. Retourne true si valide.
 */
function emailValide(string $email): bool
{
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false && mb_strlen($email) <= 150;
}

/**
 * Vérifie la robustesse d'un mot de passe. Retourne null si valide,
 * ou un message d'erreur explicite sinon.
 * Règle : au moins 8 caractères, avec au moins une lettre et un chiffre.
 */
function erreurForceMotDePasse(string $motDePasse): ?string
{
    if (mb_strlen($motDePasse) < 8) {
        return 'Le mot de passe doit contenir au moins 8 caractères.';
    }
    if (mb_strlen($motDePasse) > 200) {
        return 'Le mot de passe est trop long.';
    }
    if (!preg_match('/[a-zA-Z]/', $motDePasse) || !preg_match('/[0-9]/', $motDePasse)) {
        return 'Le mot de passe doit contenir au moins une lettre et un chiffre.';
    }
    return null;
}

/**
 * Valide qu'une chaîne représente un nombre décimal dans une plage donnée
 * (utilisé par exemple pour le temps passé sur un rapport, en heures).
 * Retourne null si vide (valeur optionnelle) ou valide, un message d'erreur sinon.
 */
function erreurNombreDansPlage(string $valeur, float $min, float $max, string $libelleChamp): ?string
{
    if ($valeur === '') {
        return null;
    }
    if (!is_numeric($valeur)) {
        return "$libelleChamp doit être un nombre.";
    }
    $nombre = (float) $valeur;
    if ($nombre < $min || $nombre > $max) {
        return "$libelleChamp doit être compris entre $min et $max.";
    }
    return null;
}

/**
 * Valide qu'une chaîne est une date au format YYYY-MM-DD réellement valide
 * (rejette par exemple "2026-02-30"). Retourne true si vide (optionnel) ou valide.
 */
function dateValide(string $valeur): bool
{
    if ($valeur === '') {
        return true;
    }
    $d = DateTime::createFromFormat('Y-m-d', $valeur);
    return $d && $d->format('Y-m-d') === $valeur;
}

/**
 * Tronque une chaîne à une longueur maximale (protection basique contre des
 * champs texte anormalement longs, en complément des colonnes VARCHAR bornées).
 */
function limiterLongueur(string $valeur, int $max): string
{
    return mb_substr(trim($valeur), 0, $max);
}

/**
 * Protection basique contre le bourrage d'identifiants (brute-force) : compte le
 * nombre d'échecs de connexion récents pour un email donné, et refuse une nouvelle
 * tentative au-delà d'un seuil, pendant une fenêtre de temps glissante.
 */
function tropDeTentativesConnexion(string $email, int $seuil = 5, int $fenetreMinutes = 15): bool
{
    try {
        $stmt = getPDO()->prepare(
            "SELECT COUNT(*) FROM journal_activite
             WHERE action = 'connexion_echouee'
             AND email_tentative = ?
             AND date_action > (NOW() - INTERVAL ? MINUTE)"
        );
        $stmt->execute([$email, $fenetreMinutes]);
        return (int) $stmt->fetchColumn() >= $seuil;
    } catch (Throwable $e) {
        // Si le journal est indisponible (migration pas encore appliquée...), on ne
        // bloque jamais la connexion pour autant : mieux vaut un throttling absent
        // qu'une application inutilisable.
        return false;
    }
}
