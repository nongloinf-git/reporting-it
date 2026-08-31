<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/journal.php';
requireLogin();

$u = currentUser();
$pdo = getPDO();
$gestionnaire = peutGererTaches($u);

$tacheId = (int) ($_GET['id'] ?? 0);
$stmt = $pdo->prepare(
    'SELECT t.*, resp.nom AS responsable_nom, createur.nom AS createur_nom, r.titre AS reunion_titre, r.id AS reunion_id_lien,
            p.titre AS parent_titre, p.description AS parent_description
     FROM taches_reunion t
     LEFT JOIN utilisateurs resp ON resp.id = t.responsable_id
     LEFT JOIN utilisateurs createur ON createur.id = t.createur_id
     LEFT JOIN reunions r ON r.id = t.reunion_id
     LEFT JOIN taches_reunion p ON p.id = t.parent_tache_id
     WHERE t.id = ?'
);
$stmt->execute([$tacheId]);
$tache = $stmt->fetch();

if (!$tache) {
    die('Tâche introuvable.');
}

// Accès : gestionnaire (voit tout), responsable de la tâche, créateur, ou participant à la réunion d'origine
$estResponsable = (int) $tache['responsable_id'] === (int) $u['id'];
$estCreateur = (int) $tache['createur_id'] === (int) $u['id'];
$estParticipantReunion = false;
if ($tache['reunion_id']) {
    $stmtP = $pdo->prepare('SELECT 1 FROM reunion_participants WHERE reunion_id = ? AND utilisateur_id = ?');
    $stmtP->execute([$tache['reunion_id'], $u['id']]);
    $estParticipantReunion = (bool) $stmtP->fetchColumn();
}

if (!$gestionnaire && !$estResponsable && !$estCreateur && !$estParticipantReunion) {
    http_response_code(403);
    die('Vous n\'avez pas accès à cette tâche.');
}

$message = '';
$erreur = '';

// Mise à jour du statut (gestionnaire ou responsable)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'maj_statut') {
    requireCsrf();
    $nouveauStatut = $_POST['statut'] ?? 'a_faire';
    if (($gestionnaire || $estResponsable) && in_array($nouveauStatut, ['a_faire', 'en_cours', 'termine'], true)) {
        if ($nouveauStatut === 'termine' && !toutesSousTachesTerminees($pdo, $tacheId)) {
            $erreur = 'Impossible de terminer cette tâche : toutes ses sous-tâches doivent d\'abord être terminées.';
        } else {
            $pdo->prepare('UPDATE taches_reunion SET statut = ? WHERE id = ?')->execute([$nouveauStatut, $tacheId]);
            $tache['statut'] = $nouveauStatut;
            journaliser((int) $u['id'], 'modification_tache', "Statut de \"" . ($tache['titre'] ?: $tache['description']) . "\" -> " . libelleStatutTache($nouveauStatut));
            $message = 'Statut mis à jour.';
        }
    }
}

// Suppression (gestionnaire uniquement)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'supprimer') {
    requireCsrf();
    if ($gestionnaire) {
        journaliser((int) $u['id'], 'suppression_tache', $tache['titre'] ?: $tache['description']);
        $pdo->prepare('DELETE FROM taches_reunion WHERE id = ?')->execute([$tacheId]);
        header('Location: ' . ($tache['parent_tache_id'] ? 'tache_detail.php?id=' . $tache['parent_tache_id'] : 'taches.php'));
        exit;
    }
}

// Mise à jour du statut d'une sous-tâche directement depuis cette page
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'maj_statut_sous_tache') {
    requireCsrf();
    $sousTacheId = (int) $_POST['sous_tache_id'];
    $nouveauStatut = $_POST['statut'] ?? 'a_faire';
    $stmtST = $pdo->prepare('SELECT responsable_id, titre, description FROM taches_reunion WHERE id = ? AND parent_tache_id = ?');
    $stmtST->execute([$sousTacheId, $tacheId]);
    $sousTache = $stmtST->fetch();
    if ($sousTache && ($gestionnaire || (int) $sousTache['responsable_id'] === (int) $u['id'])
        && in_array($nouveauStatut, ['a_faire', 'en_cours', 'termine'], true)) {
        if ($nouveauStatut === 'termine' && !toutesSousTachesTerminees($pdo, $sousTacheId)) {
            $erreur = 'Impossible de terminer cette sous-tâche : ses propres sous-tâches doivent d\'abord être terminées.';
        } else {
            $pdo->prepare('UPDATE taches_reunion SET statut = ? WHERE id = ?')->execute([$nouveauStatut, $sousTacheId]);
            journaliser((int) $u['id'], 'modification_tache', "Statut de \"" . ($sousTache['titre'] ?: $sousTache['description']) . "\" -> " . libelleStatutTache($nouveauStatut));
            $message = 'Statut de la sous-tâche mis à jour.';
        }
    }
}

// Suppression d'une sous-tâche (gestionnaire uniquement)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'supprimer_sous_tache') {
    requireCsrf();
    if ($gestionnaire) {
        $sousTacheIdSuppr = (int) $_POST['sous_tache_id'];
        $stmtNom = $pdo->prepare('SELECT titre, description FROM taches_reunion WHERE id = ?');
        $stmtNom->execute([$sousTacheIdSuppr]);
        $nomCible = $stmtNom->fetch();
        $pdo->prepare('DELETE FROM taches_reunion WHERE id = ? AND parent_tache_id = ?')->execute([$sousTacheIdSuppr, $tacheId]);
        if ($nomCible) {
            journaliser((int) $u['id'], 'suppression_tache', $nomCible['titre'] ?: $nomCible['description']);
        }
        $message = 'Sous-tâche supprimée.';
    }
}

$stmtSous = $pdo->prepare(
    'SELECT t.*, resp.nom AS responsable_nom FROM taches_reunion t
     LEFT JOIN utilisateurs resp ON resp.id = t.responsable_id
     WHERE t.parent_tache_id = ?
     ORDER BY t.statut, t.echeance IS NULL, t.echeance'
);
$stmtSous->execute([$tacheId]);
$sousTaches = $stmtSous->fetchAll();
$nbSousTachesNonTerminees = count(array_filter($sousTaches, fn($st) => $st['statut'] !== 'termine'));

$titrePage = $tache['titre'] ?: 'Détail de la tâche';
require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/navbar.php';
?>
<div class="container">
    <?php if ($tache['parent_tache_id']): ?>
        <p class="text-muted small mb-1">
            Sous-tâche de :
            <a href="tache_detail.php?id=<?= (int)$tache['parent_tache_id'] ?>"><?= e($tache['parent_titre'] ?: $tache['parent_description']) ?></a>
        </p>
    <?php endif; ?>

    <div class="d-flex justify-content-between align-items-start mb-3">
        <div>
            <h3 class="mb-0"><?= e($tache['titre'] ?: $tache['description']) ?></h3>
            <p class="text-muted mb-0">
                <?php if ($tache['reunion_titre']): ?>
                    Issue de la réunion <a href="reunion_detail.php?id=<?= (int)$tache['reunion_id_lien'] ?>"><?= e($tache['reunion_titre']) ?></a>
                <?php else: ?>
                    Tâche directe
                <?php endif; ?>
                <?php if ($tache['createur_nom']): ?> — créée par <?= e($tache['createur_nom']) ?><?php endif; ?>
            </p>
        </div>
        <?php if ($gestionnaire): ?>
            <form method="post" onsubmit="return confirm('Supprimer cette tâche et toutes ses sous-tâches ?');">
                <?= champCsrf() ?>
                <input type="hidden" name="action" value="supprimer">
                <button class="btn btn-outline-danger btn-sm">Supprimer la tâche</button>
            </form>
        <?php endif; ?>
    </div>

    <?php if ($message): ?><div class="alert alert-info"><?= e($message) ?></div><?php endif; ?>
    <?php if ($erreur): ?><div class="alert alert-danger"><?= e($erreur) ?></div><?php endif; ?>

    <div class="card mb-4">
        <div class="card-body">
            <?php if ($tache['titre'] && $tache['description']): ?>
                <p style="white-space: pre-wrap;"><?= e($tache['description']) ?></p>
            <?php endif; ?>
            <div class="row">
                <div class="col-md-4">
                    <p class="text-muted small mb-1">Responsable</p>
                    <p><?= e($tache['responsable_nom'] ?? 'Non assigné') ?></p>
                </div>
                <div class="col-md-4">
                    <p class="text-muted small mb-1">Échéance</p>
                    <p><?= $tache['echeance'] ? (new DateTime($tache['echeance']))->format('d/m/Y') : '-' ?></p>
                </div>
                <div class="col-md-4">
                    <p class="text-muted small mb-1">Statut</p>
                    <?php if ($gestionnaire || $estResponsable): ?>
                        <form method="post" class="d-flex gap-1">
                            <?= champCsrf() ?>
                            <input type="hidden" name="action" value="maj_statut">
                            <select name="statut" class="form-select form-select-sm" onchange="this.form.submit()">
                                <option value="a_faire" <?= $tache['statut'] === 'a_faire' ? 'selected' : '' ?>>À faire</option>
                                <option value="en_cours" <?= $tache['statut'] === 'en_cours' ? 'selected' : '' ?>>En cours</option>
                                <option value="termine" <?= $tache['statut'] === 'termine' ? 'selected' : '' ?>>Terminée</option>
                            </select>
                        </form>
                        <?php if ($nbSousTachesNonTerminees > 0 && $tache['statut'] !== 'termine'): ?>
                            <p class="text-warning small mt-1 mb-0">⚠️ <?= $nbSousTachesNonTerminees ?> sous-tâche(s) encore non terminée(s)</p>
                        <?php endif; ?>
                    <?php else: ?>
                        <span class="badge bg-<?= classeBadgeStatutTache($tache['statut']) ?>"><?= libelleStatutTache($tache['statut']) ?></span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex justify-content-between align-items-center mb-2">
        <h5 class="mb-0">Sous-tâches</h5>
        <?php if ($gestionnaire): ?>
            <a href="tache_form.php?parent_id=<?= (int)$tacheId ?>" class="btn btn-sm btn-primary">+ Ajouter une sous-tâche</a>
        <?php endif; ?>
    </div>

    <?php if (!$sousTaches): ?>
        <p class="text-muted">Aucune sous-tâche.</p>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table table-bordered bg-white">
            <thead class="table-light">
                <tr><th>Sous-tâche</th><th>Responsable</th><th>Échéance</th><th>Statut</th><th></th></tr>
            </thead>
            <tbody>
            <?php foreach ($sousTaches as $st): ?>
                <tr>
                    <td><a href="tache_detail.php?id=<?= (int)$st['id'] ?>"><?= e($st['titre'] ?: $st['description']) ?></a></td>
                    <td><?= e($st['responsable_nom'] ?? '-') ?></td>
                    <td><?= $st['echeance'] ? (new DateTime($st['echeance']))->format('d/m/Y') : '-' ?></td>
                    <td>
                        <?php if ($gestionnaire || (int)$st['responsable_id'] === (int)$u['id']): ?>
                            <form method="post" class="d-flex gap-1">
                                <?= champCsrf() ?>
                                <input type="hidden" name="action" value="maj_statut_sous_tache">
                                <input type="hidden" name="sous_tache_id" value="<?= (int)$st['id'] ?>">
                                <select name="statut" class="form-select form-select-sm" onchange="this.form.submit()">
                                    <option value="a_faire" <?= $st['statut'] === 'a_faire' ? 'selected' : '' ?>>À faire</option>
                                    <option value="en_cours" <?= $st['statut'] === 'en_cours' ? 'selected' : '' ?>>En cours</option>
                                    <option value="termine" <?= $st['statut'] === 'termine' ? 'selected' : '' ?>>Terminée</option>
                                </select>
                            </form>
                        <?php else: ?>
                            <span class="badge bg-<?= classeBadgeStatutTache($st['statut']) ?>"><?= libelleStatutTache($st['statut']) ?></span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($gestionnaire): ?>
                            <form method="post" onsubmit="return confirm('Supprimer cette sous-tâche ?');">
                                <?= champCsrf() ?>
                                <input type="hidden" name="action" value="supprimer_sous_tache">
                                <input type="hidden" name="sous_tache_id" value="<?= (int)$st['id'] ?>">
                                <button class="btn btn-sm btn-outline-danger">Suppr.</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <a href="<?= $tache['parent_tache_id'] ? 'tache_detail.php?id=' . (int)$tache['parent_tache_id'] : 'taches.php' ?>" class="btn btn-outline-secondary">Retour</a>
</div>
</body>
</html>
