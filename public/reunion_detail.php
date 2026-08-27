<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
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
    if (!$peutCreerTaches) {
        http_response_code(403);
        die('Seul l\'organisateur ou un admin peut ajouter des tâches à cette réunion.');
    }
    $description = trim($_POST['description'] ?? '');
    $responsableId = ($_POST['responsable_id'] ?? '') !== '' ? (int) $_POST['responsable_id'] : null;
    $echeance = ($_POST['echeance'] ?? '') !== '' ? $_POST['echeance'] : null;

    if ($description === '') {
        $message = 'La description de la tâche est obligatoire.';
    } else {
        $stmt = $pdo->prepare('INSERT INTO taches_reunion (reunion_id, description, responsable_id, echeance) VALUES (?, ?, ?, ?)');
        $stmt->execute([$reunionId, $description, $responsableId, $echeance]);
        $message = 'Tâche ajoutée.';
    }
}

// Mise à jour du statut d'une tâche (par le responsable ou un gestionnaire)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'maj_statut_tache') {
    $tacheId = (int) $_POST['tache_id'];
    $nouveauStatut = $_POST['statut'] ?? 'a_faire';

    $stmtT = $pdo->prepare('SELECT responsable_id FROM taches_reunion WHERE id = ? AND reunion_id = ?');
    $stmtT->execute([$tacheId, $reunionId]);
    $tache = $stmtT->fetch();

    if ($tache && ($gestionnaire || (int) $tache['responsable_id'] === (int) $u['id'])
        && in_array($nouveauStatut, ['a_faire', 'en_cours', 'termine'], true)) {
        $stmt = $pdo->prepare('UPDATE taches_reunion SET statut = ? WHERE id = ?');
        $stmt->execute([$nouveauStatut, $tacheId]);
        $message = 'Statut de la tâche mis à jour.';
    }
}

// Suppression d'une tâche (gestionnaires uniquement)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'supprimer_tache') {
    if ($peutCreerTaches) {
        $stmt = $pdo->prepare('DELETE FROM taches_reunion WHERE id = ? AND reunion_id = ?');
        $stmt->execute([(int) $_POST['tache_id'], $reunionId]);
        $message = 'Tâche supprimée.';
    }
}

$stmtT = $pdo->prepare('SELECT t.*, resp.nom AS responsable_nom FROM taches_reunion t LEFT JOIN utilisateurs resp ON resp.id = t.responsable_id WHERE t.reunion_id = ? ORDER BY t.statut, t.echeance IS NULL, t.echeance');
$stmtT->execute([$reunionId]);
$taches = $stmtT->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title><?= e($reunion['titre']) ?> - Reporting IT</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<?php require __DIR__ . '/../includes/navbar.php'; ?>
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
        <table class="table table-bordered bg-white">
            <thead class="table-light">
                <tr><th>Tâche</th><th>Responsable</th><th>Échéance</th><th>Statut</th><th></th></tr>
            </thead>
            <tbody>
            <?php foreach ($taches as $t): ?>
                <tr>
                    <td><?= e($t['description']) ?></td>
                    <td><?= e($t['responsable_nom'] ?? '-') ?></td>
                    <td><?= $t['echeance'] ? (new DateTime($t['echeance']))->format('d/m/Y') : '-' ?></td>
                    <td>
                        <?php if ($gestionnaire || (int)$t['responsable_id'] === (int)$u['id']): ?>
                            <form method="post" class="d-flex gap-1">
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
                                <input type="hidden" name="action" value="supprimer_tache">
                                <input type="hidden" name="tache_id" value="<?= (int)$t['id'] ?>">
                                <button class="btn btn-sm btn-outline-danger">Suppr.</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <a href="reunions.php" class="btn btn-outline-secondary">Retour à la liste</a>
</div>
</body>
</html>
