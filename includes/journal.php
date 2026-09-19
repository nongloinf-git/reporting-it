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
 * Récupère les entrées du journal d'activité selon les filtres demandés.
 * Utilisé par la page Historique des modifications et par ses exports CSV/PDF.
 */
function recupererJournalFiltre(PDO $pdo, ?int $utilisateurId, string $actionFiltre, int $limite = 200): array
{
    $conditions = [];
    $parametres = [];

    if ($utilisateurId !== null) {
        $conditions[] = 'j.utilisateur_id = ?';
        $parametres[] = $utilisateurId;
    }
    if ($actionFiltre !== '') {
        $conditions[] = 'j.action = ?';
        $parametres[] = $actionFiltre;
    }

    $ou = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';
    $limite = max(1, min(2000, $limite));

    $stmt = $pdo->prepare(
        "SELECT j.*, u.nom AS utilisateur_nom
         FROM journal_activite j
         LEFT JOIN utilisateurs u ON u.id = j.utilisateur_id
         $ou
         ORDER BY j.date_action DESC
         LIMIT $limite"
    );
    $stmt->execute($parametres);
    return $stmt->fetchAll();
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
        'modification_fonction' => 'Modification de fonction',
        'reinitialisation_mot_de_passe' => 'Réinitialisation mot de passe (admin)',
        'validation_rapport' => 'Validation de rapport',
        'renvoi_rapport' => 'Renvoi de rapport pour révision',
        'commentaire_rapport' => 'Commentaire sur un rapport',
        'enregistrement_rapport' => 'Enregistrement de rapport (brouillon)',
        'soumission_rapport' => 'Soumission de rapport',
        'creation_reunion' => 'Création de réunion',
        'modification_reunion' => 'Modification de réunion',
        'creation_tache' => 'Création de tâche',
        'modification_tache' => 'Modification de tâche',
        'suppression_tache' => 'Suppression de tâche',
        'modification_profil' => 'Modification du profil',
        'changement_mot_de_passe' => 'Changement de mot de passe',
        'modification_apparence' => "Modification de l'apparence",
        'modification_parametres' => 'Modification des paramètres de la société',
        'envoi_rappels_email' => 'Envoi de rappels par email',
        'generation_synthese' => 'Génération de rapport de synthèse',
        default => ucfirst(str_replace('_', ' ', $action)),
    };
}
