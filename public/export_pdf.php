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
        'SELECT ut.nom, ut.equipe, r.statut, r.temps_passe, r.contenu
         FROM rapports r
         JOIN utilisateurs ut ON ut.id = r.utilisateur_id
         WHERE ut.manager_id = ? AND r.annee = ? AND r.semaine_numero = ?
         ORDER BY ut.nom'
    );
    $stmt->execute([$u['id'], $annee, $semaine]);
} else {
    $stmt = $pdo->prepare(
        'SELECT ut.nom, ut.equipe, r.statut, r.temps_passe, r.contenu
         FROM rapports r
         JOIN utilisateurs ut ON ut.id = r.utilisateur_id
         WHERE r.annee = ? AND r.semaine_numero = ?
         ORDER BY ut.nom'
    );
    $stmt->execute([$annee, $semaine]);
}
$rapports = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Export PDF - Semaine <?= (int)$semaine ?> - <?= (int)$annee ?></title>
    <style>
        body { font-family: Arial, sans-serif; margin: 30px; color: #222; }
        h2 { margin-bottom: 0; }
        .sous-titre { color: #666; margin-top: 4px; margin-bottom: 24px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 24px; }
        th, td { border: 1px solid #ccc; padding: 8px; text-align: left; vertical-align: top; }
        th { background: #f2f2f2; }
        .contenu { white-space: pre-wrap; }
        .barre-actions { margin-bottom: 20px; }
        .badge { padding: 2px 8px; border-radius: 4px; font-size: 12px; color: #fff; }
        .badge-brouillon { background: #6c757d; }
        .badge-soumis { background: #d39e00; }
        .badge-valide { background: #198754; }
        @media print {
            .barre-actions { display: none; }
            body { margin: 0; }
        }
    </style>
</head>
<body>
    <div class="barre-actions">
        <button onclick="window.print()">Enregistrer en PDF / Imprimer</button>
        <a href="rapports_manager.php?annee=<?= (int)$annee ?>&semaine=<?= (int)$semaine ?>">Retour</a>
    </div>

    <h2>Rapports hebdomadaires</h2>
    <p class="sous-titre">Semaine ISO <?= (int)$semaine ?> - <?= (int)$annee ?></p>

    <table>
        <thead>
            <tr>
                <th>Collaborateur</th>
                <th>Équipe</th>
                <th>Statut</th>
                <th>Temps passé</th>
                <th>Contenu</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($rapports as $r): ?>
            <tr>
                <td><?= e($r['nom']) ?></td>
                <td><?= e($r['equipe'] ?? '-') ?></td>
                <td><span class="badge badge-<?= $r['statut'] ?>"><?= libelleStatut($r['statut']) ?></span></td>
                <td><?= $r['temps_passe'] !== null ? e((string)$r['temps_passe']) . ' h' : '-' ?></td>
                <td class="contenu"><?= e($r['contenu']) ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$rapports): ?>
            <tr><td colspan="5">Aucun rapport pour cette semaine.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</body>
</html>
