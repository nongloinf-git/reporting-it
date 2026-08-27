<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/mail.php';
require_once __DIR__ . '/functions.php';

/**
 * Envoie un rappel par email à chaque collaborateur n'ayant pas encore soumis
 * (statut différent de 'soumis' ou 'valide') son rapport pour la semaine donnée.
 * Retourne la liste des collaborateurs relancés (nom + email).
 */
function envoyerRappelsRapportsNonSoumis(?int $annee = null, ?int $semaine = null): array
{
    $pdo = getPDO();
    $sem = semaineCourante();
    $annee = $annee ?? $sem['annee'];
    $semaine = $semaine ?? $sem['semaine'];

    $stmt = $pdo->prepare(
        "SELECT ut.id, ut.nom, ut.email FROM utilisateurs ut
         WHERE ut.role = 'collaborateur'
         AND NOT EXISTS (
             SELECT 1 FROM rapports r
             WHERE r.utilisateur_id = ut.id
             AND r.annee = ? AND r.semaine_numero = ?
             AND r.statut IN ('soumis', 'valide')
         )"
    );
    $stmt->execute([$annee, $semaine]);
    $collaborateursARelancer = $stmt->fetchAll();

    $relances = [];
    foreach ($collaborateursARelancer as $c) {
        $corps = "<p>Bonjour " . htmlspecialchars($c['nom']) . ",</p>"
            . "<p>Votre rapport hebdomadaire pour la semaine ISO {$semaine} - {$annee} n'a pas encore été soumis.</p>"
            . "<p>Merci de le compléter dès que possible sur l'application Reporting IT.</p>";

        $envoye = envoyerEmail($c['email'], MAIL_SUJET_RAPPEL, $corps);
        $relances[] = ['nom' => $c['nom'], 'email' => $c['email'], 'envoye' => $envoye];
    }

    return $relances;
}
