<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireLogin();

$u = currentUser();
$pdo = getPDO();
$gestionnaire = peutGererTaches($u);

$collaborateurId = $gestionnaire && ($_GET['collaborateur_id'] ?? '') !== '' ? (int) $_GET['collaborateur_id'] : null;
if (!$gestionnaire) {
    $collaborateurId = (int) $u['id'];
}
$statutFiltre = $_GET['statut'] ?? '';
$origineFiltre = $_GET['origine'] ?? '';

$taches = recupererTachesFiltrees($pdo, $collaborateurId, $statutFiltre, $origineFiltre);

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="taches.csv"');

$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF");

fputcsv($out, ['Titre', 'Description', 'Responsable', 'Origine', 'Créateur', 'Échéance', 'Statut', 'Sous-tâches'], ';');

foreach ($taches as $t) {
    fputcsv($out, [
        $t['titre'] ?: '',
        $t['description'],
        $t['responsable_nom'] ?? '',
        $t['reunion_titre'] ? 'Réunion : ' . $t['reunion_titre'] : 'Tâche directe',
        $t['createur_nom'] ?? '',
        $t['echeance'] ?? '',
        libelleStatutTache($t['statut']),
        $t['nb_sous_taches'],
    ], ';');
}

fclose($out);
exit;
