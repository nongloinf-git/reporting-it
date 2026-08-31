<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireRole(['manager', 'admin']);

$u = currentUser();
$pdo = getPDO();

$nombreSemaines = isset($_GET['nb_semaines']) ? max(1, min(52, (int) $_GET['nb_semaines'])) : 8;
$collaborateurId = isset($_GET['collaborateur_id']) && $_GET['collaborateur_id'] !== '' ? (int) $_GET['collaborateur_id'] : null;

// Liste des collaborateurs pour le filtre
if ($u['role'] === 'manager') {
    $stmt = $pdo->prepare("SELECT id, nom FROM utilisateurs WHERE manager_id = ? ORDER BY nom");
    $stmt->execute([$u['id']]);
} else {
    $stmt = $pdo->query("SELECT id, nom FROM utilisateurs WHERE role = 'collaborateur' ORDER BY nom");
}
$collaborateurs = $stmt->fetchAll();

// Tous les rapports de l'équipe (le volume reste faible pour un outil interne : filtrage en PHP)
if ($u['role'] === 'manager') {
    $stmt = $pdo->prepare(
        'SELECT r.*, ut.nom FROM rapports r
         JOIN utilisateurs ut ON ut.id = r.utilisateur_id
         WHERE ut.manager_id = ?
         ORDER BY r.annee DESC, r.semaine_numero DESC, ut.nom'
    );
    $stmt->execute([$u['id']]);
} else {
    $stmt = $pdo->query(
        "SELECT r.*, ut.nom FROM rapports r
         JOIN utilisateurs ut ON ut.id = r.utilisateur_id
         ORDER BY r.annee DESC, r.semaine_numero DESC, ut.nom"
    );
}
$tousLesRapports = $stmt->fetchAll();

// Filtrage sur la plage de semaines demandée (+ collaborateur si sélectionné)
$clesSemainesRecentesEnPremier = semainesPrecedentes($nombreSemaines);
$clesSemaines = array_flip($clesSemainesRecentesEnPremier);
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

// Regroupement par collaborateur pour l'affichage
$parCollaborateur = [];
foreach ($rapports as $r) {
    $parCollaborateur[$r['nom']][] = $r;
}
ksort($parCollaborateur);

// --- Données pour le graphique de charge de travail (Chart.js) ---
// Semaines dans l'ordre chronologique (la plus ancienne en premier) pour l'axe X
$clesSemainesChrono = array_reverse($clesSemainesRecentesEnPremier);
$labelsSemaines = array_map(function ($cle) {
    [$annee, $semaine] = explode('-', $cle);
    return 'S' . $semaine . '-' . $annee;
}, $clesSemainesChrono);

$donneesParCollaborateur = [];
foreach ($parCollaborateur as $nom => $rapportsCollaborateur) {
    $valeurParCle = [];
    foreach ($rapportsCollaborateur as $r) {
        $valeurParCle[$r['annee'] . '-' . $r['semaine_numero']] = $r['temps_passe'] !== null ? (float) $r['temps_passe'] : null;
    }
    $donneesParCollaborateur[$nom] = array_map(fn($cle) => $valeurParCle[$cle] ?? null, $clesSemainesChrono);
}

$parametresExport = http_build_query(['nb_semaines' => $nombreSemaines, 'collaborateur_id' => $collaborateurId ?? '']);
$titrePage = 'Historique des rapports';
require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/navbar.php';
?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/mammoth/1.11.0/mammoth.browser.min.js"></script>
<style>.docx-preview img { max-width: 100%; }</style>
<div class="container">
    <h3>Historique des rapports</h3>
    <p class="text-muted">Affiche les rapports sur plusieurs semaines passées (au lieu d'une seule semaine à la fois).</p>

    <form method="get" class="row g-2 mb-4">
        <div class="col-auto">
            <label class="form-label">Nombre de semaines à afficher</label>
            <input type="number" name="nb_semaines" min="1" max="52" value="<?= (int)$nombreSemaines ?>" class="form-control">
        </div>
        <div class="col-auto">
            <label class="form-label">Collaborateur</label>
            <select name="collaborateur_id" class="form-select">
                <option value="">Tous</option>
                <?php foreach ($collaborateurs as $c): ?>
                    <option value="<?= (int)$c['id'] ?>" <?= $collaborateurId === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['nom']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-auto align-self-end">
            <button class="btn btn-secondary">Filtrer</button>
        </div>
        <div class="col-auto align-self-end">
            <a class="btn btn-outline-success" href="export_historique_csv.php?<?= e($parametresExport) ?>">Exporter CSV</a>
        </div>
        <div class="col-auto align-self-end">
            <a class="btn btn-outline-danger" href="export_historique_pdf.php?<?= e($parametresExport) ?>" target="_blank">Exporter PDF</a>
        </div>
        <div class="col-auto align-self-end ms-auto">
            <a href="rapports_manager.php" class="btn btn-outline-secondary">Retour à la vue semaine</a>
        </div>
    </form>

    <h5>Charge de travail (heures déclarées par semaine)</h5>
    <div class="card mb-4">
        <div class="card-body">
            <canvas id="graphiqueHistorique" height="90"></canvas>
        </div>
    </div>
    <script>
        (function () {
            const labels = <?= json_encode($labelsSemaines, JSON_UNESCAPED_UNICODE) ?>;
            const donneesParNom = <?= json_encode($donneesParCollaborateur, JSON_UNESCAPED_UNICODE) ?>;
            const noms = Object.keys(donneesParNom);
            const datasets = noms.map((nom, i) => {
                const teinte = Math.round((360 / Math.max(noms.length, 1)) * i);
                return {
                    label: nom,
                    data: donneesParNom[nom],
                    borderColor: `hsl(${teinte}, 65%, 45%)`,
                    backgroundColor: `hsl(${teinte}, 65%, 45%)`,
                    spanGaps: true,
                    tension: 0.2
                };
            });
            new Chart(document.getElementById('graphiqueHistorique'), {
                type: 'line',
                data: { labels, datasets },
                options: {
                    responsive: true,
                    scales: { y: { beginAtZero: true, title: { display: true, text: 'Heures' } } }
                }
            });
        })();

        async function chargerApercuWord(boutonElement, url, cibleId) {
            const cible = document.getElementById(cibleId);
            if (cible.dataset.charge === '1') {
                cible.classList.toggle('d-none');
                return;
            }
            cible.classList.remove('d-none');
            cible.innerHTML = '<p class="text-muted small">Chargement de l\'aperçu...</p>';
            try {
                const reponse = await fetch(url);
                const buffer = await reponse.arrayBuffer();
                const resultat = await mammoth.convertToHtml({ arrayBuffer: buffer });
                cible.innerHTML = resultat.value || '<p class="text-muted small">(document vide)</p>';
                cible.dataset.charge = '1';
            } catch (e) {
                cible.innerHTML = '<p class="text-muted small">Aperçu visuel indisponible pour ce fichier.</p>';
            }
        }
    </script>

    <?php if (!$rapports): ?>
        <p class="text-muted">Aucun rapport trouvé sur cette période.</p>
    <?php endif; ?>

    <?php $compteur = 0; foreach ($parCollaborateur as $nom => $rapportsCollaborateur): ?>
        <h5 class="mt-4"><?= e($nom) ?></h5>
    <div class="table-responsive">
        <table class="table table-bordered bg-white">
            <thead class="table-light">
                <tr>
                    <th>Semaine</th>
                    <th>Statut</th>
                    <th>Temps passé</th>
                    <th>Type</th>
                    <th>Aperçu</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($rapportsCollaborateur as $r): $compteur++; $cibleId = 'apercu-hist-' . $compteur; ?>
                <tr>
                    <td>S<?= (int)$r['semaine_numero'] ?> - <?= (int)$r['annee'] ?></td>
                    <td><span class="badge bg-<?= classeBadgeStatut($r['statut']) ?>"><?= libelleStatut($r['statut']) ?></span></td>
                    <td><?= $r['temps_passe'] !== null ? e((string)$r['temps_passe']) . ' h' : '-' ?></td>
                    <td><?= !empty($r['fichier_word']) ? 'Word' : 'Texte' ?></td>
                    <td>
                        <?php if (!empty($r['contenu'])): ?>
                            <?= e(mb_strimwidth($r['contenu'], 0, 60, '...')) ?>
                        <?php elseif (!empty($r['fichier_word'])): ?>
                            <button type="button" class="btn btn-sm btn-outline-primary"
                                    onclick="chargerApercuWord(this, 'uploads/rapports_word/<?= e($r['fichier_word']) ?>', '<?= $cibleId ?>')">
                                Aperçu Word
                            </button>
                            <a href="uploads/rapports_word/<?= e($r['fichier_word']) ?>" target="_blank" class="small">télécharger</a>
                            <div id="<?= $cibleId ?>" class="docx-preview d-none border rounded p-2 bg-light mt-2" style="max-height:300px; overflow-y:auto;"></div>
                        <?php else: ?>
                            -
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endforeach; ?>
</div>
</body>
</html>
