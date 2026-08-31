<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/validation.php';
require_once __DIR__ . '/../includes/journal.php';
requireLogin();

$u = currentUser();
$pdo = getPDO();
$gestionnaire = peutGererReunions($u);

$reunionId = (int) ($_GET['id'] ?? 0);
$stmt = $pdo->prepare('SELECT r.*, org.nom AS organisateur_nom FROM reunions r JOIN utilisateurs org ON org.id = r.organisateur_id WHERE r.id = ?');
$stmt->execute([$reunionId]);
$reunion = $stmt->fetch();

if (!$reunion) {
    die('Réunion introuvable.');
}

// Vérification d'accès : gestionnaire (voit tout), organisateur, ou participant
$stmtP = $pdo->prepare('SELECT ut.id, ut.nom FROM reunion_participants rp JOIN utilisateurs ut ON ut.id = rp.utilisateur_id WHERE rp.reunion_id = ? ORDER BY ut.nom');
$stmtP->execute([$reunionId]);
$participants = $stmtP->fetchAll();
$idsParticipants = array_column($participants, 'id');

$estOrganisateur = (int) $reunion['organisateur_id'] === (int) $u['id'];
$estParticipant = in_array((int) $u['id'], $idsParticipants, true);

if (!$gestionnaire && !$estOrganisateur && !$estParticipant) {
    http_response_code(403);
    die('Vous n\'avez pas accès à cette réunion.');
}

$peutModifierReunion = $estOrganisateur || $u['role'] === 'admin';
$peutCreerTaches = $gestionnaire && ($estOrganisateur || $u['role'] === 'admin' || $peutModifierReunion);

$message = '';

// Création d'une tâche
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'creer_tache') {
    requireCsrf();
    if (!$peutCreerTaches) {
        http_response_code(403);
        die('Seul l\'organisateur ou un admin peut ajouter des tâches à cette réunion.');
    }
    $description = limiterLongueur($_POST['description'] ?? '', 2000);
    $responsableId = ($_POST['responsable_id'] ?? '') !== '' ? (int) $_POST['responsable_id'] : null;
    $echeanceBrute = trim($_POST['echeance'] ?? '');
    $echeance = $echeanceBrute !== '' ? $echeanceBrute : null;

    if ($responsableId !== null && !in_array($responsableId, $idsParticipants, true)) {
        $message = 'Le responsable choisi ne fait pas partie des participants de la réunion.';
    } elseif ($description === '') {
        $message = 'La description de la tâche est obligatoire.';
    } elseif ($echeance !== null && !dateValide($echeance)) {
        $message = "L'échéance saisie est invalide.";
    } else {
        $stmt = $pdo->prepare('INSERT INTO taches_reunion (reunion_id, description, responsable_id, echeance, createur_id) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([$reunionId, $description, $responsableId, $echeance, $u['id']]);
        journaliser((int) $u['id'], 'creation_tache', mb_strimwidth($description, 0, 100, '...') . " (réunion \"{$reunion['titre']}\")");
        $message = 'Tâche ajoutée.';
    }
}

// Mise à jour du statut d'une tâche (par le responsable ou un gestionnaire)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'maj_statut_tache') {
    requireCsrf();
    $tacheId = (int) $_POST['tache_id'];
    $nouveauStatut = $_POST['statut'] ?? 'a_faire';

    $stmtT = $pdo->prepare('SELECT responsable_id, titre, description FROM taches_reunion WHERE id = ? AND reunion_id = ?');
    $stmtT->execute([$tacheId, $reunionId]);
    $tache = $stmtT->fetch();

    if ($tache && ($gestionnaire || (int) $tache['responsable_id'] === (int) $u['id'])
        && in_array($nouveauStatut, ['a_faire', 'en_cours', 'termine'], true)) {
        if ($nouveauStatut === 'termine' && !toutesSousTachesTerminees($pdo, $tacheId)) {
            $message = 'Impossible de terminer cette tâche : toutes ses sous-tâches doivent d\'abord être terminées.';
        } else {
            $stmt = $pdo->prepare('UPDATE taches_reunion SET statut = ? WHERE id = ?');
            $stmt->execute([$nouveauStatut, $tacheId]);
            journaliser((int) $u['id'], 'modification_tache', "Statut de \"" . ($tache['titre'] ?: $tache['description']) . "\" -> " . libelleStatutTache($nouveauStatut));
            $message = 'Statut de la tâche mis à jour.';
        }
    }
}

// Suppression d'une tâche (gestionnaires uniquement)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'supprimer_tache') {
    requireCsrf();
    if ($peutCreerTaches) {
        $tacheIdSuppr = (int) $_POST['tache_id'];
        $stmtNom = $pdo->prepare('SELECT titre, description FROM taches_reunion WHERE id = ?');
        $stmtNom->execute([$tacheIdSuppr]);
        $nomCible = $stmtNom->fetch();
        $stmt = $pdo->prepare('DELETE FROM taches_reunion WHERE id = ? AND reunion_id = ?');
        $stmt->execute([$tacheIdSuppr, $reunionId]);
        if ($nomCible) {
            journaliser((int) $u['id'], 'suppression_tache', $nomCible['titre'] ?: $nomCible['description']);
        }
        $message = 'Tâche supprimée.';
    }
}


$stmtT = $pdo->prepare('SELECT t.*, resp.nom AS responsable_nom, (SELECT COUNT(*) FROM taches_reunion st WHERE st.parent_tache_id = t.id) AS nb_sous_taches FROM taches_reunion t LEFT JOIN utilisateurs resp ON resp.id = t.responsable_id WHERE t.reunion_id = ? AND t.parent_tache_id IS NULL ORDER BY t.statut, t.echeance IS NULL, t.echeance');
$stmtT->execute([$reunionId]);
$taches = $stmtT->fetchAll();

$titrePage = $reunion['titre'];
require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/navbar.php';
?>
<div class="container">
    <div class="d-flex justify-content-between align-items-start mb-3">
        <div>
            <h3 class="mb-0"><?= e($reunion['titre']) ?></h3>
            <p class="text-muted mb-0">
                <?= (new DateTime($reunion['date_reunion']))->format('d/m/Y à H:i') ?>
                <?php if ($reunion['lieu']): ?> — <?= e($reunion['lieu']) ?><?php endif; ?>
                — Organisée par <?= e($reunion['organisateur_nom']) ?>
            </p>
        </div>
        <?php if ($peutModifierReunion): ?>
            <a href="reunion_form.php?id=<?= (int)$reunion['id'] ?>" class="btn btn-outline-secondary btn-sm">Modifier la réunion</a>
        <?php endif; ?>
    </div>

    <?php if ($message): ?><div class="alert alert-info"><?= e($message) ?></div><?php endif; ?>

    <?php if ($reunion['description']): ?>
        <div class="card mb-3">
            <div class="card-body">
                <h6>Description / ordre du jour</h6>
                <p style="white-space: pre-wrap;" class="mb-0"><?= e($reunion['description']) ?></p>
            </div>
        </div>
    <?php endif; ?>

    <div class="card mb-4">
        <div class="card-header">Participants</div>
        <div class="card-body">
            <?php if ($participants): ?>
                <?php foreach ($participants as $p): ?>
                    <span class="badge bg-light text-dark border me-1"><?= e($p['nom']) ?></span>
                <?php endforeach; ?>
            <?php else: ?>
                <span class="text-muted">Aucun participant renseigné.</span>
            <?php endif; ?>
        </div>
    </div>

    <h5>Tâches issues de la réunion</h5>

    <?php if ($peutCreerTaches): ?>
        <div class="card mb-3">
            <div class="card-body">
                <form method="post" class="row g-2">
                    <?= champCsrf() ?>
                    <input type="hidden" name="action" value="creer_tache">
                    <div class="col-md-5">
                        <input type="text" name="description" class="form-control" placeholder="Description de la tâche" required>
                    </div>
                    <div class="col-md-3">
                        <select name="responsable_id" class="form-select">
                            <option value="">Responsable (optionnel)</option>
                            <?php foreach ($participants as $p): ?>
                                <option value="<?= (int)$p['id'] ?>"><?= e($p['nom']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <input type="date" name="echeance" class="form-control">
                    </div>
                    <div class="col-md-2">
                        <button class="btn btn-primary w-100">Ajouter</button>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <?php if (!$taches): ?>
        <p class="text-muted">Aucune tâche pour cette réunion.</p>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table table-bordered bg-white">
            <thead class="table-light">
                <tr><th>Tâche</th><th>Responsable</th><th>Échéance</th><th>Statut</th><th></th><th></th></tr>
            </thead>
            <tbody>
            <?php foreach ($taches as $t): ?>
                <tr>
                    <td><?= e($t['titre'] ?: $t['description']) ?></td>
                    <td><?= e($t['responsable_nom'] ?? '-') ?></td>
                    <td><?= $t['echeance'] ? (new DateTime($t['echeance']))->format('d/m/Y') : '-' ?></td>
                    <td>
                        <?php if ($gestionnaire || (int)$t['responsable_id'] === (int)$u['id']): ?>
                            <form method="post" class="d-flex gap-1">
                                <?= champCsrf() ?>
                                <input type="hidden" name="action" value="maj_statut_tache">
                                <input type="hidden" name="tache_id" value="<?= (int)$t['id'] ?>">
                                <select name="statut" class="form-select form-select-sm" onchange="this.form.submit()">
                                    <option value="a_faire" <?= $t['statut'] === 'a_faire' ? 'selected' : '' ?>>À faire</option>
                                    <option value="en_cours" <?= $t['statut'] === 'en_cours' ? 'selected' : '' ?>>En cours</option>
                                    <option value="termine" <?= $t['statut'] === 'termine' ? 'selected' : '' ?>>Terminée</option>
                                </select>
                            </form>
                        <?php else: ?>
                            <span class="badge bg-<?= classeBadgeStatutTache($t['statut']) ?>"><?= libelleStatutTache($t['statut']) ?></span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($peutCreerTaches): ?>
                            <form method="post" onsubmit="return confirm('Supprimer cette tâche ?');">
                                <?= champCsrf() ?>
                                <input type="hidden" name="action" value="supprimer_tache">
                                <input type="hidden" name="tache_id" value="<?= (int)$t['id'] ?>">
                                <button class="btn btn-sm btn-outline-danger">Suppr.</button>
                            </form>
                        <?php endif; ?>
                    </td>
                    <td>
                        <a href="tache_detail.php?id=<?= (int)$t['id'] ?>" class="btn btn-sm btn-outline-secondary">
                            Détail<?= $t['nb_sous_taches'] > 0 ? ' (' . (int)$t['nb_sous_taches'] . ')' : '' ?>
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <a href="reunions.php" class="btn btn-outline-secondary">Retour à la liste</a>
</div>
</body>
</html>
