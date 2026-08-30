<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/validation.php';
requireReunionPermission();

$u = currentUser();
$pdo = getPDO();

$reunionId = isset($_GET['id']) ? (int) $_GET['id'] : null;
$reunion = null;
$participantsActuels = [];

if ($reunionId) {
    $stmt = $pdo->prepare('SELECT * FROM reunions WHERE id = ?');
    $stmt->execute([$reunionId]);
    $reunion = $stmt->fetch();

    if (!$reunion) {
        die('Réunion introuvable.');
    }
    // Seul l'organisateur ou un admin peut modifier une réunion
    if ($reunion['organisateur_id'] != $u['id'] && $u['role'] !== 'admin') {
        http_response_code(403);
        die('Seul l\'organisateur ou un admin peut modifier cette réunion.');
    }

    $stmtP = $pdo->prepare('SELECT utilisateur_id FROM reunion_participants WHERE reunion_id = ?');
    $stmtP->execute([$reunionId]);
    $participantsActuels = array_column($stmtP->fetchAll(), 'utilisateur_id');
}

$erreur = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $titre = limiterLongueur($_POST['titre'] ?? '', 200);
    $description = limiterLongueur($_POST['description'] ?? '', 5000);
    $dateReunion = $_POST['date_reunion'] ?? '';
    $lieu = limiterLongueur($_POST['lieu'] ?? '', 200);
    $participants = array_map('intval', $_POST['participants'] ?? []);

    // Valide le format datetime-local (YYYY-MM-DDTHH:MM) avant de le transmettre à MySQL
    $dateValideFormat = (bool) preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $dateReunion);

    if ($titre === '' || $dateReunion === '') {
        $erreur = 'Le titre et la date sont obligatoires.';
    } elseif (!$dateValideFormat) {
        $erreur = 'La date et l\'heure saisies sont invalides.';
    } else {
        if ($reunionId) {
            $stmt = $pdo->prepare('UPDATE reunions SET titre = ?, description = ?, date_reunion = ?, lieu = ? WHERE id = ?');
            $stmt->execute([$titre, $description ?: null, $dateReunion, $lieu ?: null, $reunionId]);
        } else {
            $stmt = $pdo->prepare('INSERT INTO reunions (titre, description, date_reunion, lieu, organisateur_id) VALUES (?, ?, ?, ?, ?)');
            $stmt->execute([$titre, $description ?: null, $dateReunion, $lieu ?: null, $u['id']]);
            $reunionId = (int) $pdo->lastInsertId();
        }

        // Remplace la liste des participants
        $pdo->prepare('DELETE FROM reunion_participants WHERE reunion_id = ?')->execute([$reunionId]);
        if ($participants) {
            $stmtInsertP = $pdo->prepare('INSERT IGNORE INTO reunion_participants (reunion_id, utilisateur_id) VALUES (?, ?)');
            foreach ($participants as $pid) {
                $stmtInsertP->execute([$reunionId, $pid]);
            }
        }

        header('Location: reunion_detail.php?id=' . $reunionId);
        exit;
    }
}

$tousLesUtilisateurs = $pdo->query("SELECT id, nom, role FROM utilisateurs WHERE actif = 1 ORDER BY nom")->fetchAll();
$titrePage = ($reunionId ? 'Modifier' : 'Nouvelle') . ' réunion';
require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/navbar.php';
?>
<div class="container">
    <h3><?= $reunionId ? 'Modifier la réunion' : 'Nouvelle réunion' ?></h3>

    <?php if ($erreur): ?><div class="alert alert-danger"><?= e($erreur) ?></div><?php endif; ?>

    <form method="post">
        <?= champCsrf() ?>
        <div class="row g-3 mb-3">
            <div class="col-md-6">
                <label class="form-label">Titre</label>
                <input type="text" name="titre" class="form-control" value="<?= e($reunion['titre'] ?? '') ?>" required>
            </div>
            <div class="col-md-3">
                <label class="form-label">Date et heure</label>
                <input type="datetime-local" name="date_reunion" class="form-control"
                       value="<?= $reunion ? (new DateTime($reunion['date_reunion']))->format('Y-m-d\TH:i') : '' ?>" required>
            </div>
            <div class="col-md-3">
                <label class="form-label">Lieu</label>
                <input type="text" name="lieu" class="form-control" value="<?= e($reunion['lieu'] ?? '') ?>" placeholder="Salle, visio...">
            </div>
        </div>

        <div class="mb-3">
            <label class="form-label">Description / ordre du jour</label>
            <textarea name="description" rows="4" class="form-control"><?= e($reunion['description'] ?? '') ?></textarea>
        </div>

        <div class="mb-3">
            <label class="form-label">Participants</label>
            <div class="row">
                <?php foreach ($tousLesUtilisateurs as $participant): ?>
                    <div class="col-md-4">
                        <div class="form-check">
                            <input type="checkbox" name="participants[]" value="<?= (int)$participant['id'] ?>"
                                   class="form-check-input" id="p<?= (int)$participant['id'] ?>"
                                   <?= in_array($participant['id'], $participantsActuels) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="p<?= (int)$participant['id'] ?>">
                                <?= e($participant['nom']) ?> <span class="text-muted small">(<?= e($participant['role']) ?>)</span>
                            </label>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <button type="submit" class="btn btn-primary"><?= $reunionId ? 'Enregistrer les modifications' : 'Créer la réunion' ?></button>
        <a href="reunions.php" class="btn btn-outline-secondary">Annuler</a>
    </form>
</div>
</body>
</html>
