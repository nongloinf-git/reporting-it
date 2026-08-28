<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireLogin();

$u = currentUser();
$pdo = getPDO();
$sem = semaineCourante();

if ($u['role'] === 'collaborateur') {
    // Historique des rapports du collaborateur
    $stmt = $pdo->prepare('SELECT * FROM rapports WHERE utilisateur_id = ? ORDER BY annee DESC, semaine_numero DESC LIMIT 10');
    $stmt->execute([$u['id']]);
    $rapports = $stmt->fetchAll();
} else {
    // Vue manager/admin : état de soumission de l'équipe pour la semaine courante
    if ($u['role'] === 'manager') {
        $stmt = $pdo->prepare('SELECT id, nom, equipe FROM utilisateurs WHERE manager_id = ?');
        $stmt->execute([$u['id']]);
    } else {
        $stmt = $pdo->query("SELECT id, nom, equipe FROM utilisateurs WHERE role = 'collaborateur'");
    }
    $membres = $stmt->fetchAll();

    $stmtRapport = $pdo->prepare('SELECT * FROM rapports WHERE utilisateur_id = ? AND annee = ? AND semaine_numero = ?');
    foreach ($membres as &$m) {
        $stmtRapport->execute([$m['id'], $sem['annee'], $sem['semaine']]);
        $m['rapport'] = $stmtRapport->fetch();
    }
    unset($m);
}

// Mes tâches de réunions (tous rôles) : tâches qui me sont assignées, non terminées en priorité
$stmtTaches = $pdo->prepare(
    "SELECT t.*, r.titre AS reunion_titre, r.id AS reunion_id
     FROM taches_reunion t
     JOIN reunions r ON r.id = t.reunion_id
     WHERE t.responsable_id = ?
     ORDER BY (t.statut = 'termine'), t.echeance IS NULL, t.echeance
     LIMIT 10"
);
$stmtTaches->execute([$u['id']]);
$mesTaches = $stmtTaches->fetchAll();

// --- Données pour le graphique de charge de travail (Chart.js) ---
if ($u['role'] === 'collaborateur') {
    $rapportsChrono = array_reverse($rapports); // du plus ancien au plus récent
    $labelsGraphique = array_map(fn($r) => 'S' . (int)$r['semaine_numero'] . '-' . (int)$r['annee'], $rapportsChrono);
    $donneesGraphique = array_map(fn($r) => $r['temps_passe'] !== null ? (float) $r['temps_passe'] : null, $rapportsChrono);
} else {
    $labelsGraphique = array_map(fn($m) => $m['nom'], $membres);
    $donneesGraphique = array_map(fn($m) => $m['rapport']['temps_passe'] ?? 0, $membres);
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Tableau de bord - Reporting IT</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
</head>
<body>
<?php require __DIR__ . '/../includes/navbar.php'; ?>
<div class="container">
    <h3>Tableau de bord</h3>
    <p class="text-muted">Semaine ISO <?= $sem['semaine'] ?> - <?= $sem['annee'] ?></p>

    <?php if ($u['role'] === 'collaborateur'): ?>
        <table class="table table-bordered bg-white">
            <thead class="table-light">
                <tr><th>Semaine</th><th>Statut</th><th>Temps passé</th><th>Aperçu</th></tr>
            </thead>
            <tbody>
            <?php foreach ($rapports as $r): ?>
                <tr>
                    <td>S<?= (int)$r['semaine_numero'] ?> - <?= (int)$r['annee'] ?></td>
                    <td><span class="badge bg-<?= classeBadgeStatut($r['statut']) ?>"><?= libelleStatut($r['statut']) ?></span></td>
                    <td><?= $r['temps_passe'] !== null ? e((string)$r['temps_passe']) . ' h' : '-' ?></td>
                    <td><?= !empty($r['contenu']) ? e(mb_strimwidth($r['contenu'], 0, 60, '...')) : (!empty($r['fichier_word']) ? '📄 Fichier Word' : '-') ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$rapports): ?>
                <tr><td colspan="4" class="text-center text-muted">Aucun rapport pour le moment.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    <?php else: ?>
        <table class="table table-bordered bg-white">
            <thead class="table-light">
                <tr><th>Collaborateur</th><th>Équipe</th><th>Statut semaine en cours</th><th>Action</th></tr>
            </thead>
            <tbody>
            <?php foreach ($membres as $m): ?>
                <tr>
                    <td><?= e($m['nom']) ?></td>
                    <td><?= e($m['equipe'] ?? '-') ?></td>
                    <td>
                        <?php if ($m['rapport']): ?>
                            <span class="badge bg-<?= classeBadgeStatut($m['rapport']['statut']) ?>"><?= libelleStatut($m['rapport']['statut']) ?></span>
                        <?php else: ?>
                            <span class="badge bg-danger">Pas encore soumis</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($m['rapport']): ?>
                            <a href="rapports_manager.php#rapport-<?= (int)$m['rapport']['id'] ?>" class="btn btn-sm btn-outline-primary">Voir</a>
                        <?php else: ?>
                            <span class="text-muted small">-</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <h5 class="mt-5"><?= $u['role'] === 'collaborateur' ? 'Mon temps déclaré par semaine' : 'Charge de travail de l\'équipe (semaine en cours)' ?></h5>
    <div class="card mb-4">
        <div class="card-body">
            <canvas id="graphiqueCharge" height="90"></canvas>
        </div>
    </div>
    <script>
        new Chart(document.getElementById('graphiqueCharge'), {
            type: '<?= $u['role'] === 'collaborateur' ? 'line' : 'bar' ?>',
            data: {
                labels: <?= json_encode($labelsGraphique, JSON_UNESCAPED_UNICODE) ?>,
                datasets: [{
                    label: 'Temps passé (heures)',
                    data: <?= json_encode($donneesGraphique) ?>,
                    backgroundColor: 'rgba(13, 110, 253, 0.5)',
                    borderColor: 'rgba(13, 110, 253, 1)',
                    borderWidth: 2,
                    tension: 0.2,
                    spanGaps: true
                }]
            },
            options: {
                responsive: true,
                plugins: { legend: { display: false } },
                scales: { y: { beginAtZero: true, title: { display: true, text: 'Heures' } } }
            }
        });
    </script>

    <h5 class="mt-5">Mes tâches de réunions</h5>
    <?php if (!$mesTaches): ?>
        <p class="text-muted">Aucune tâche de réunion ne vous est assignée.</p>
    <?php else: ?>
        <table class="table table-bordered bg-white">
            <thead class="table-light">
                <tr><th>Tâche</th><th>Réunion</th><th>Échéance</th><th>Statut</th></tr>
            </thead>
            <tbody>
            <?php foreach ($mesTaches as $t): ?>
                <tr>
                    <td><?= e($t['description']) ?></td>
                    <td><a href="reunion_detail.php?id=<?= (int)$t['reunion_id'] ?>"><?= e($t['reunion_titre']) ?></a></td>
                    <td><?= $t['echeance'] ? (new DateTime($t['echeance']))->format('d/m/Y') : '-' ?></td>
                    <td><span class="badge bg-<?= classeBadgeStatutTache($t['statut']) ?>"><?= libelleStatutTache($t['statut']) ?></span></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
</body>
</html>
