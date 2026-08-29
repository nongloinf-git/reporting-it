<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireTachePermission();

$u = currentUser();
$pdo = getPDO();

$parentId = isset($_GET['parent_id']) ? (int) $_GET['parent_id'] : null;
$tacheParente = null;
if ($parentId) {
    $stmt = $pdo->prepare('SELECT * FROM taches_reunion WHERE id = ?');
    $stmt->execute([$parentId]);
    $tacheParente = $stmt->fetch();
    if (!$tacheParente) {
        die('Tâche parente introuvable.');
    }
}

$erreur = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $titre = trim($_POST['titre'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $responsableId = ($_POST['responsable_id'] ?? '') !== '' ? (int) $_POST['responsable_id'] : null;
    $echeance = ($_POST['echeance'] ?? '') !== '' ? $_POST['echeance'] : null;
    $parentIdPost = ($_POST['parent_tache_id'] ?? '') !== '' ? (int) $_POST['parent_tache_id'] : null;

    if ($titre === '' && $description === '') {
        $erreur = 'Veuillez indiquer au moins un titre ou une description.';
    } else {
        $stmt = $pdo->prepare(
            'INSERT INTO taches_reunion (reunion_id, parent_tache_id, createur_id, titre, description, responsable_id, echeance)
             VALUES (NULL, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$parentIdPost, $u['id'], $titre ?: null, $description ?: ($titre ?: 'Tâche'), $responsableId, $echeance]);
        $nouvelId = (int) $pdo->lastInsertId();

        header('Location: ' . ($parentIdPost ? 'tache_detail.php?id=' . $parentIdPost : 'tache_detail.php?id=' . $nouvelId));
        exit;
    }
}

$tousLesUtilisateurs = $pdo->query('SELECT id, nom FROM utilisateurs WHERE actif = 1 ORDER BY nom')->fetchAll();

$titrePage = $tacheParente ? 'Nouvelle sous-tâche' : 'Nouvelle tâche';
require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/navbar.php';
?>
<div class="container">
    <h3><?= $tacheParente ? 'Nouvelle sous-tâche' : 'Nouvelle tâche directe' ?></h3>
    <?php if ($tacheParente): ?>
        <p class="text-muted">Rattachée à : <strong><?= e($tacheParente['titre'] ?: $tacheParente['description']) ?></strong></p>
    <?php else: ?>
        <p class="text-muted">Cette tâche ne sera pas liée à une réunion.</p>
    <?php endif; ?>

    <?php if ($erreur): ?><div class="alert alert-danger"><?= e($erreur) ?></div><?php endif; ?>

    <form method="post">
        <?php if ($parentId): ?>
            <input type="hidden" name="parent_tache_id" value="<?= (int)$parentId ?>">
        <?php endif; ?>
        <div class="row g-3 mb-3">
            <div class="col-md-6">
                <label class="form-label">Titre</label>
                <input type="text" name="titre" class="form-control" placeholder="Ex : Mettre à jour le serveur de sauvegarde">
            </div>
            <div class="col-md-3">
                <label class="form-label">Responsable</label>
                <select name="responsable_id" class="form-select">
                    <option value="">Non assigné</option>
                    <?php foreach ($tousLesUtilisateurs as $ut): ?>
                        <option value="<?= (int)$ut['id'] ?>"><?= e($ut['nom']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Échéance</label>
                <input type="date" name="echeance" class="form-control">
            </div>
        </div>
        <div class="mb-3">
            <label class="form-label">Description</label>
            <textarea name="description" rows="4" class="form-control" placeholder="Détails de la tâche (facultatif si un titre est renseigné)"></textarea>
        </div>
        <button type="submit" class="btn btn-primary">Créer</button>
        <a href="<?= $tacheParente ? 'tache_detail.php?id=' . (int)$parentId : 'taches.php' ?>" class="btn btn-outline-secondary">Annuler</a>
    </form>
</div>
</body>
</html>
