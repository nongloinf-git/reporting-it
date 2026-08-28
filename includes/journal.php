<?php
require_once __DIR__ . '/../config/database.php';

/**
 * Enregistre une entrée dans le journal d'activité (connexions, actions
 * d'administration, validations de rapports...).
 *
 * @param int|null $utilisateurId  ID de l'utilisateur concerné, ou null (ex: échec de connexion sur un email inconnu)
 * @param string   $action         Code court de l'action (ex: 'connexion_reussie', 'suppression_utilisateur'...)
 * @param string|null $details     Détail lisible optionnel (ex: nom de l'utilisateur modifié, ancien/nouveau rôle...)
 * @param string|null $emailTentative Email saisi lors d'une tentative de connexion (utile si $utilisateurId est null)
 */
function journaliser(?int $utilisateurId, string $action, ?string $details = null, ?string $emailTentative = null): void
{
    try {
        $stmt = getPDO()->prepare(
            'INSERT INTO journal_activite (utilisateur_id, email_tentative, action, details, adresse_ip) VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([$utilisateurId, $emailTentative, $action, $details, $_SERVER['REMOTE_ADDR'] ?? null]);
    } catch (Throwable $e) {
        // Le journal ne doit jamais faire planter l'application (ex: table pas encore migrée).
        error_log('Journalisation impossible : ' . $e->getMessage());
    }
}

/**
 * Libellés lisibles pour les codes d'action stockés en base.
 */
function libelleActionJournal(string $action): string
{
    return match ($action) {
        'connexion_reussie' => 'Connexion réussie',
        'connexion_echouee' => 'Échec de connexion',
        'deconnexion' => 'Déconnexion',
        'deconnexion_inactivite' => 'Déconnexion (inactivité)',
        'creation_utilisateur' => 'Création utilisateur',
        'suppression_utilisateur' => 'Suppression utilisateur',
        'modification_role' => 'Modification de rôle',
        'activation_compte' => 'Activation de compte',
        'desactivation_compte' => 'Désactivation de compte',
        'modification_permission_reunions' => 'Modification permission réunions',
        'reinitialisation_mot_de_passe' => 'Réinitialisation mot de passe (admin)',
        'validation_rapport' => 'Validation de rapport',
        'renvoi_rapport' => 'Renvoi de rapport pour révision',
        default => ucfirst(str_replace('_', ' ', $action)),
    };
}
