<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireLogin();

$u = currentUser();
$pdo = getPDO();
$gestionnaire = peutGererTaches($u);

// Filtres. Un simple collaborateur (sans permission) est toujours restreint à ses propres tâches.
$collaborateurId = $gestionnaire && ($_GET['collaborateur_id'] ?? '') !== '' ? (int) $_GET['collaborateur_id'] : null;
if (!$gestionnaire) {
    $collaborateurId = (int) $u['id'];
}
$statutFiltre = $_GET['statut'] ?? '';
$origineFiltre = $_GET['origine'] ?? ''; // '', 'reunion', 'directe'

$taches = recupererTachesFiltrees($pdo, $collaborateurId, $statutFiltre, $origineFiltre);

$collaborateursDisponibles = $gestionnaire
    ? $pdo->query("SELECT id, nom FROM utilisateurs WHERE actif = 1 ORDER BY nom")->fetchAll()
    : [];

$titrePage = 'Tâches';
require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/navbar.php';
?>
<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="mb-0">Tâches</h3>
        <?php if ($gestionnaire): ?>
            <a href="tache_form.php" class="btn btn-primary">+ Nouvelle tâche</a>
        <?php endif; ?>
    </div>

    <form method="get" class="row g-2 mb-4">
        <?php if ($gestionnaire): ?>
            <div class="col-auto">
                <label class="form-label">Collaborateur</label>
                <select name="collaborateur_id" class="form-select">
                    <option value="">Tous</option>
                    <?php foreach ($collaborateursDisponibles as $c): ?>
                        <option value="<?= (int)$c['id'] ?>" <?= $collaborateurId === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['nom']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php endif; ?>
        <div class="col-auto">
            <label class="form-label">Statut</label>
            <select name="statut" class="form-select">
                <option value="">Tous</option>
                <option value="a_faire" <?= $statutFiltre === 'a_faire' ? 'selected' : '' ?>>À faire</option>
                <option value="en_cours" <?= $statutFiltre === 'en_cours' ? 'selected' : '' ?>>En cours</option>
                <option value="termine" <?= $statutFiltre === 'termine' ? 'selected' : '' ?>>Terminée</option>
            </select>
        </div>
        <div class="col-auto">
            <label class="form-label">Origine</label>
            <select name="origine" class="form-select">
                <option value="">Toutes</option>
                <option value="reunion" <?= $origineFiltre === 'reunion' ? 'selected' : '' ?>>Issue d'une réunion</option>
                <option value="directe" <?= $origineFiltre === 'directe' ? 'selected' : '' ?>>Tâche directe</option>
            </select>
        </div>
        <div class="col-auto align-self-end">
            <button class="btn btn-secondary">Filtrer</button>
        </div>
        <div class="col-auto align-self-end">
            <a href="taches.php" class="btn btn-outline-secondary">Réinitialiser</a>
        </div>
        <?php $qs = http_build_query(['collaborateur_id' => $collaborateurId ?? '', 'statut' => $statutFiltre, 'origine' => $origineFiltre]); ?>
        <div class="col-auto align-self-end">
            <a class="btn btn-outline-success" href="export_taches_csv.php?<?= $qs ?>">Exporter CSV</a>
        </div>
        <div class="col-auto align-self-end">
            <a class="btn btn-outline-danger" href="export_taches_pdf.php?<?= $qs ?>" target="_blank">Exporter PDF</a>
        </div>
    </form>

    <?php if (!$taches): ?>
        <p class="text-muted">Aucune tâche ne correspond à ces filtres.</p>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table table-bordered bg-white table-triable">
            <thead class="table-light">
                <tr>
                    <th>Tâche</th>
                    <?php if ($gestionnaire && $collaborateurId === null): ?><th>Responsable</th><?php endif; ?>
                    <th>Origine</th>
                    <th data-type="date_fr">Échéance</th>
                    <th>Statut</th>
                    <th data-no-tri></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($taches as $t): ?>
                <tr>
                    <td><?= e($t['titre'] ?: mb_strimwidth($t['description'], 0, 60, '...')) ?></td>
                    <?php if ($gestionnaire && $collaborateurId === null): ?>
                        <td><?= e($t['responsable_nom'] ?? '-') ?></td>
                    <?php endif; ?>
                    <td>
                        <?php if ($t['reunion_titre']): ?>
                            <a href="reunion_detail.php?id=<?= (int)$t['reunion_id'] ?>"><?= e($t['reunion_titre']) ?></a>
                        <?php else: ?>
                            <span class="text-muted">Tâche directe</span>
                        <?php endif; ?>
                    </td>
                    <td><?= $t['echeance'] ? (new DateTime($t['echeance']))->format('d/m/Y') : '-' ?></td>
                    <td><span class="badge bg-<?= classeBadgeStatutTache($t['statut']) ?>"><?= libelleStatutTache($t['statut']) ?></span></td>
                    <td>
                        <a href="tache_detail.php?id=<?= (int)$t['id'] ?>" class="btn btn-sm btn-outline-primary">
                            Détail<?= $t['nb_sous_taches'] > 0 ? ' (' . (int)$t['nb_sous_taches'] . ')' : '' ?>
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>
</body>
</html>
