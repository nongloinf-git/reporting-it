<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Retourne le jeton CSRF de la session en cours, en le générant s'il n'existe
 * pas encore. Un seul jeton par session (pas un par formulaire) : plus simple
 * et suffisant pour se protéger contre les requêtes forgées depuis un autre site.
 */
function csrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Génère le champ caché à insérer dans chaque <form method="post">.
 */
function champCsrf(): string
{
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') . '">';
}

/**
 * À appeler en tout début de traitement d'une requête POST. Arrête
 * immédiatement l'exécution (403) si le jeton est absent ou invalide —
 * typiquement une requête forgée depuis un autre site, ou un formulaire
 * resté ouvert trop longtemps après expiration de la session.
 */
function requireCsrf(): void
{
    $tokenRecu = $_POST['csrf_token'] ?? '';
    $tokenAttendu = $_SESSION['csrf_token'] ?? '';

    if ($tokenAttendu === '' || !hash_equals($tokenAttendu, $tokenRecu)) {
        http_response_code(403);
        die('Jeton de sécurité invalide ou expiré. Merci de recharger la page et de réessayer.');
    }
}
