<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function isLoggedIn(): bool
{
    return isset($_SESSION['user_id']);
}

/**
 * Retourne l'utilisateur connecté en le relisant depuis la base de données
 * (mis en cache pour la durée de la requête). Cela garantit que les
 * changements faits par un admin (rôle, désactivation, permission réunions,
 * photo) sont pris en compte immédiatement, sans attendre une reconnexion.
 * Si le compte n'existe plus ou a été désactivé, la session est fermée.
 */
function currentUser(): ?array
{
    static $utilisateur = null;
    static $charge = false;

    if (!isLoggedIn()) {
        return null;
    }

    if (!$charge) {
        $charge = true;
        require_once __DIR__ . '/../config/database.php';

        $stmt = getPDO()->prepare(
            'SELECT id, nom, email, role, equipe, photo_profil, actif, peut_gerer_reunions
             FROM utilisateurs WHERE id = ?'
        );
        $stmt->execute([$_SESSION['user_id']]);
        $ligne = $stmt->fetch();

        if ($ligne && (int) $ligne['actif'] === 1) {
            $utilisateur = $ligne;
        } else {
            // Compte supprimé ou désactivé entre-temps : on coupe la session.
            session_destroy();
            $utilisateur = null;
        }
    }

    return $utilisateur;
}

function requireLogin(): void
{
    if (!isLoggedIn() || currentUser() === null) {
        header('Location: login.php?desactive=1');
        exit;
    }
}

function requireRole(array $roles): void
{
    requireLogin();
    $u = currentUser();
    if (!in_array($u['role'], $roles, true)) {
        http_response_code(403);
        die('Accès refusé : vous n\'avez pas les droits nécessaires.');
    }
}

/**
 * Un utilisateur peut gérer les réunions (créer, assigner des tâches) s'il est
 * admin, ou si la permission spécifique lui a été accordée par un admin
 * (indépendamment de son rôle, ex: un collaborateur référent ou un manager
 * à qui on aurait retiré ce droit).
 */
function peutGererReunions(array $u): bool
{
    return $u['role'] === 'admin' || !empty($u['peut_gerer_reunions']);
}

function requireReunionPermission(): void
{
    requireLogin();
    $u = currentUser();
    if (!peutGererReunions($u)) {
        http_response_code(403);
        die('Accès refusé : vous n\'avez pas la permission de gérer les réunions.');
    }
}
