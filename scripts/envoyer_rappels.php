<?php
// Script à exécuter en ligne de commande (CLI), par exemple via une tâche planifiée Windows.
// Commande : "C:\wamp64\bin\php\phpX.Y.Z\php.exe" "C:\wamp64\www\reporting-it\scripts\envoyer_rappels.php"
//
// Voir le README (section "Notifications email") pour la configuration de php.ini
// et pour la mise en place de la tâche planifiée Windows.

require_once __DIR__ . '/../includes/notifications.php';

$resultats = envoyerRappelsRapportsNonSoumis();

$dateHeure = date('Y-m-d H:i:s');
if (!$resultats) {
    echo "[$dateHeure] Aucun rappel nécessaire, tous les rapports de la semaine ont été soumis.\n";
    exit(0);
}

foreach ($resultats as $r) {
    $statut = $r['envoye'] ? 'OK' : 'ECHEC';
    echo "[$dateHeure] [$statut] Rappel envoyé à {$r['nom']} ({$r['email']})\n";
}
