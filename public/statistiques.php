<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireRole(['manager', 'admin']);

$u = currentUser();
$pdo = getPDO();
$sem = semaineCourante();

// --- Périmètre : l'équipe du manager, ou tout le monde pour l'admin ---
if ($u['role'] === 'manager') {
    $stmt = $pdo->prepare("SELECT id FROM utilisateurs WHERE manager_id = ?");
    $stmt->execute([$u['id']]);
} else {
    $stmt = $pdo->query("SELECT id FROM utilisateurs WHERE role = 'collaborateur'");
}
$idsEquipe = array_column($stmt->fetchAll(), 'id');
$placeholders = $idsEquipe ? implode(',', array_fill(0, count($idsEquipe), '?')) : 'NULL';

// --- 1. Taux de soumission de la semaine en cours (soumis+validé vs pas soumis) ---
$soumis = 0;
$nonSoumis = 0;
if ($idsEquipe) {
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM rapports WHERE utilisateur_id IN ($placeholders) AND annee = ? AND semaine_numero = ? AND statut IN ('soumis','valide')"
    );
    $stmt->execute([...$idsEquipe, $sem['annee'], $sem['semaine']]);
    $soumis = (int) $stmt->fetchColumn();
    $nonSoumis = max(0, count($idsEquipe) - $soumis);
}

// --- 2. Répartition des tâches par statut (équipe, tous statuts confondus, tâches de premier niveau) ---
$repartitionTaches = ['a_faire' => 0, 'en_cours' => 0, 'termine' => 0];
if ($idsEquipe) {
    $stmt = $pdo->prepare(
        "SELECT statut, COUNT(*) AS nb FROM taches_reunion WHERE responsable_id IN ($placeholders) AND parent_tache_id IS NULL GROUP BY statut"
    );
    $stmt->execute($idsEquipe);
    foreach ($stmt->fetchAll() as $ligne) {
        $repartitionTaches[$ligne['statut']] = (int) $ligne['nb'];
    }
}

// --- 3. Réunions organisées par mois (12 derniers mois) ---
$moisLabels = [];
$moisCles = [];
$curseur = new DateTime('first day of this month');
for ($i = 11; $i >= 0; $i--) {
    $d = (clone $curseur)->modify("-$i months");
    $moisCles[] = $d->format('Y-m');
    $moisLabels[] = ucfirst($d->format('M Y'));
}
$reunionsParMois = array_fill_keys($moisCles, 0);
if ($u['role'] === 'admin') {
    $stmt = $pdo->query("SELECT DATE_FORMAT(date_reunion, '%Y-%m') AS mois, COUNT(*) AS nb FROM reunions GROUP BY mois");
} else {
    $stmt = $pdo->prepare("SELECT DATE_FORMAT(date_reunion, '%Y-%m') AS mois, COUNT(*) AS nb FROM reunions WHERE organisateur_id = ? GROUP BY mois");
    $stmt->execute([$u['id']]);
}
foreach ($stmt->fetchAll() as $ligne) {
    if (isset($reunionsParMois[$ligne['mois']])) {
        $reunionsParMois[$ligne['mois']] = (int) $ligne['nb'];
    }
}

// --- 4. Temps moyen déclaré par collaborateur (8 dernières semaines soumises) ---
$moyenneParCollaborateur = [];
if ($idsEquipe) {
    $stmt = $pdo->prepare(
        "SELECT ut.nom, AVG(r.temps_passe) AS moyenne
         FROM rapports r JOIN utilisateurs ut ON ut.id = r.utilisateur_id
         WHERE r.utilisateur_id IN ($placeholders) AND r.temps_passe IS NOT NULL
         AND r.date_envoi > (NOW() - INTERVAL 8 WEEK)
         GROUP BY ut.id, ut.nom ORDER BY ut.nom"
    );
    $stmt->execute($idsEquipe);
    $moyenneParCollaborateur = $stmt->fetchAll();
}

$titrePage = 'Statistiques';
require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/navbar.php';
?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<div class="container">
    <h3>Statistiques</h3>
    <p class="text-muted">Vue d'ensemble de <?= $u['role'] === 'admin' ? "l'ensemble des collaborateurs" : 'votre équipe' ?>.</p>

    <div class="row g-4 mb-4">
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header">Taux de soumission — semaine en cours</div>
                <div class="card-body">
                    <canvas id="graphiqueSoumission" height="200"></canvas>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header">Répartition des tâches par statut</div>
                <div class="card-body">
                    <canvas id="graphiqueTaches" height="200"></canvas>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-md-7">
            <div class="card h-100">
                <div class="card-header">Réunions organisées par mois (12 derniers mois)</div>
                <div class="card-body">
                    <canvas id="graphiqueReunions" height="180"></canvas>
                </div>
            </div>
        </div>
        <div class="col-md-5">
            <div class="card h-100">
                <div class="card-header">Temps moyen déclaré (8 dernières semaines)</div>
                <div class="card-body">
                    <?php if (!$moyenneParCollaborateur): ?>
                        <p class="text-muted mb-0">Pas encore assez de données.</p>
                    <?php else: ?>
                        <canvas id="graphiqueMoyenne" height="180"></canvas>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
new Chart(document.getElementById('graphiqueSoumission'), {
    type: 'doughnut',
    data: {
        labels: ['Soumis', 'Non soumis'],
        datasets: [{
            data: [<?= (int)$soumis ?>, <?= (int)$nonSoumis ?>],
            backgroundColor: ['#198754', '#dc3545']
        }]
    },
    options: { responsive: true, plugins: { legend: { position: 'bottom' } } }
});

new Chart(document.getElementById('graphiqueTaches'), {
    type: 'doughnut',
    data: {
        labels: ['À faire', 'En cours', 'Terminée'],
        datasets: [{
            data: [<?= (int)$repartitionTaches['a_faire'] ?>, <?= (int)$repartitionTaches['en_cours'] ?>, <?= (int)$repartitionTaches['termine'] ?>],
            backgroundColor: ['#6c757d', '#ffc107', '#198754']
        }]
    },
    options: { responsive: true, plugins: { legend: { position: 'bottom' } } }
});

new Chart(document.getElementById('graphiqueReunions'), {
    type: 'bar',
    data: {
        labels: <?= json_encode($moisLabels, JSON_UNESCAPED_UNICODE) ?>,
        datasets: [{
            label: 'Réunions',
            data: <?= json_encode(array_values($reunionsParMois)) ?>,
            backgroundColor: 'rgba(13, 110, 253, 0.6)'
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }
    }
});

<?php if ($moyenneParCollaborateur): ?>
new Chart(document.getElementById('graphiqueMoyenne'), {
    type: 'bar',
    data: {
        labels: <?= json_encode(array_column($moyenneParCollaborateur, 'nom'), JSON_UNESCAPED_UNICODE) ?>,
        datasets: [{
            label: 'Heures / semaine (moyenne)',
            data: <?= json_encode(array_map(fn($m) => round((float)$m['moyenne'], 1), $moyenneParCollaborateur)) ?>,
            backgroundColor: 'rgba(111, 66, 193, 0.6)'
        }]
    },
    options: {
        indexAxis: 'y',
        responsive: true,
        plugins: { legend: { display: false } },
        scales: { x: { beginAtZero: true, title: { display: true, text: 'Heures' } } }
    }
});
<?php endif; ?>
</script>
</body>
</html>
