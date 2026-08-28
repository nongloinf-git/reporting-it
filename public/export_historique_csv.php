<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireRole(['manager', 'admin']);

$u = currentUser();
$pdo = getPDO();

$nombreSemaines = isset($_GET['nb_semaines']) ? max(1, min(52, (int) $_GET['nb_semaines'])) : 8;
$collaborateurId = isset($_GET['collaborateur_id']) && $_GET['collaborateur_id'] !== '' ? (int) $_GET['collaborateur_id'] : null;

if ($u['role'] === 'manager') {
    $stmt = $pdo->prepare(
        'SELECT r.*, ut.nom, ut.equipe FROM rapports r
         JOIN utilisateurs ut ON ut.id = r.utilisateur_id
         WHERE ut.manager_id = ?
         ORDER BY ut.nom, r.annee, r.semaine_numero'
    );
    $stmt->execute([$u['id']]);
} else {
    $stmt = $pdo->query(
        "SELECT r.*, ut.nom, ut.equipe FROM rapports r
         JOIN utilisateurs ut ON ut.id = r.utilisateur_id
         ORDER BY ut.nom, r.annee, r.semaine_numero"
    );
}
$tousLesRapports = $stmt->fetchAll();

$clesSemaines = array_flip(semainesPrecedentes($nombreSemaines));
$rapports = array_filter($tousLesRapports, function ($r) use ($clesSemaines, $collaborateurId) {
    $cle = $r['annee'] . '-' . $r['semaine_numero'];
    if (!isset($clesSemaines[$cle])) {
        return false;
    }
    if ($collaborateurId !== null && (int) $r['utilisateur_id'] !== $collaborateurId) {
        return false;
    }
    return true;
});

$nomFichier = "historique_rapports_{$nombreSemaines}semaines.csv";

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $nomFichier . '"');

$out = fopen('php://output', 'w');
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
