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

$parCollaborateur = [];
foreach ($rapports as $r) {
    $parCollaborateur[$r['nom']][] = $r;
}
ksort($parCollaborateur);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Export PDF - Historique (<?= (int)$nombreSemaines ?> semaines)</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 30px; color: #222; }
        h2 { margin-bottom: 0; }
        h3 { margin-top: 28px; border-bottom: 1px solid #ccc; padding-bottom: 4px; }
        .sous-titre { color: #666; margin-top: 4px; margin-bottom: 24px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
        th, td { border: 1px solid #ccc; padding: 6px 8px; text-align: left; vertical-align: top; font-size: 13px; }
        th { background: #f2f2f2; }
        .contenu { white-space: pre-wrap; }
        .barre-actions { margin-bottom: 20px; }
        .badge { padding: 2px 8px; border-radius: 4px; font-size: 11px; color: #fff; }
        .badge-brouillon { background: #6c757d; }
        .badge-soumis { background: #d39e00; }
        .badge-valide { background: #198754; }
        @media print {
            .barre-actions { display: none; }
            body { margin: 0; }
            h3 { page-break-before: auto; }
        }
    </style>
</head>
<body>
    <div class="barre-actions">
        <button onclick="window.print()">Enregistrer en PDF / Imprimer</button>
        <a href="rapports_historique.php?nb_semaines=<?= (int)$nombreSemaines ?>&collaborateur_id=<?= $collaborateurId ?? '' ?>">Retour</a>
    </div>

    <h2>Historique des rapports hebdomadaires</h2>
    <p class="sous-titre"><?= (int)$nombreSemaines ?> dernières semaines</p>

    <?php if (!$parCollaborateur): ?>
        <p>Aucun rapport trouvé sur cette période.</p>
    <?php endif; ?>

    <?php foreach ($parCollaborateur as $nom => $rapportsCollaborateur): ?>
        <h3><?= e($nom) ?></h3>
        <table>
            <thead>
                <tr>
                    <th>Semaine</th>
                    <th>Statut</th>
                    <th>Temps passé</th>
                    <th>Contenu</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($rapportsCollaborateur as $r): ?>
                <tr>
                    <td>S<?= (int)$r['semaine_numero'] ?> - <?= (int)$r['annee'] ?></td>
                    <td><span class="badge badge-<?= $r['statut'] ?>"><?= libelleStatut($r['statut']) ?></span></td>
                    <td><?= $r['temps_passe'] !== null ? e((string)$r['temps_passe']) . ' h' : '-' ?></td>
                    <td class="contenu">
                        <?= !empty($r['contenu']) ? e($r['contenu']) : (!empty($r['fichier_word']) ? '(rapport joint au format Word : ' . e($r['fichier_word']) . ')' : '-') ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endforeach; ?>
</body>
</html>
