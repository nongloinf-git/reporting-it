<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireRole(['manager', 'admin']);

$u = currentUser();
$pdo = getPDO();

$sem = semaineCourante();
$annee = isset($_GET['annee']) ? (int) $_GET['annee'] : $sem['annee'];
$semaine = isset($_GET['semaine']) ? (int) $_GET['semaine'] : $sem['semaine'];

if ($u['role'] === 'manager') {
    $stmt = $pdo->prepare(
        'SELECT ut.nom, ut.equipe, r.annee, r.semaine_numero, r.statut, r.temps_passe, r.contenu, r.fichier_word, r.date_soumission
         FROM rapports r
         JOIN utilisateurs ut ON ut.id = r.utilisateur_id
         WHERE ut.manager_id = ? AND r.annee = ? AND r.semaine_numero = ?
         ORDER BY ut.nom'
    );
    $stmt->execute([$u['id'], $annee, $semaine]);
} else {
    $stmt = $pdo->prepare(
        'SELECT ut.nom, ut.equipe, r.annee, r.semaine_numero, r.statut, r.temps_passe, r.contenu, r.fichier_word, r.date_soumission
         FROM rapports r
         JOIN utilisateurs ut ON ut.id = r.utilisateur_id
         WHERE r.annee = ? AND r.semaine_numero = ?
         ORDER BY ut.nom'
    );
    $stmt->execute([$annee, $semaine]);
}
$rapports = $stmt->fetchAll();

$nomFichier = "rapports_S{$semaine}-{$annee}.csv";

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $nomFichier . '"');

$out = fopen('php://output', 'w');
// BOM UTF-8 pour qu'Excel affiche correctement les accents
fwrite($out, "\xEF\xBB\xBF");

fputcsv($out, ['Collaborateur', 'Équipe', 'Année', 'Semaine', 'Statut', 'Temps passé (h)', 'Contenu', 'Fichier Word joint', 'Date de soumission'], ';');

foreach ($rapports as $r) {
    fputcsv($out, [
        $r['nom'],
        $r['equipe'] ?? '',
        $r['annee'],
        $r['semaine_numero'],
        libelleStatut($r['statut']),
        $r['temps_passe'] ?? '',
        $r['contenu'] ?? '',
        $r['fichier_word'] ?? '',
        $r['date_soumission'],
    ], ';');
}

fclose($out);
exit;
