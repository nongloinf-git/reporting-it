<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/journal.php';
requireRole(['admin']);

$pdo = getPDO();

$utilisateurId = isset($_GET['utilisateur_id']) && $_GET['utilisateur_id'] !== '' ? (int) $_GET['utilisateur_id'] : null;
$actionFiltre = $_GET['action'] ?? '';

$entrees = recupererJournalFiltre($pdo, $utilisateurId, $actionFiltre, 2000);

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="historique_modifications.csv"');

$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF");

fputcsv($out, ['Date', 'Utilisateur', 'Action', 'Détails', 'Adresse IP'], ';');

foreach ($entrees as $entree) {
    fputcsv($out, [
        $entree['date_action'],
        $entree['utilisateur_nom'] ?? ($entree['email_tentative'] ? $entree['email_tentative'] . ' (inconnu)' : '-'),
        libelleActionJournal($entree['action']),
        $entree['details'] ?? '',
        $entree['adresse_ip'] ?? '',
    ], ';');
}

fclose($out);
exit;
