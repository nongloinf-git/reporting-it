<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/notifications.php';
requireRole(['manager', 'admin']);

$u = currentUser();
$sem = semaineCourante();
$annee = isset($_GET['annee']) ? (int) $_GET['annee'] : $sem['annee'];
$semaine = isset($_GET['semaine']) ? (int) $_GET['semaine'] : $sem['semaine'];

$resultats = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $resultats = envoyerRappelsRapportsNonSoumis($annee, $semaine);
    // Un manager ne relance que son équipe : filtre après coup si besoin
    if ($u['role'] === 'manager') {
        $pdo = getPDO();
        $stmt = $pdo->prepare("SELECT email FROM utilisateurs WHERE manager_id = ?");
        $stmt->execute([$u['id']]);
        $emailsEquipe = array_column($stmt->fetchAll(), 'email');
        $resultats = array_values(array_filter($resultats, fn($r) => in_array($r['email'], $emailsEquipe, true)));
    }
}
$titrePage = 'Notifications';
require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/navbar.php';
?>
<div class="container">
    <h3>Rappels par email</h3>
    <p class="text-muted">Envoie un email de rappel à chaque collaborateur n'ayant pas encore soumis son rapport pour la semaine sélectionnée.</p>

    <form method="get" class="row g-2 mb-3">
        <div class="col-auto">
            <label class="form-label">Année</label>
            <input type="number" name="annee" value="<?= (int)$annee ?>" class="form-control">
        </div>
        <div class="col-auto">
            <label class="form-label">Semaine</label>
            <input type="number" name="semaine" min="1" max="53" value="<?= (int)$semaine ?>" class="form-control">
        </div>
        <div class="col-auto align-self-end">
            <button class="btn btn-secondary">Changer de semaine</button>
        </div>
    </form>

    <form method="post">
        <?= champCsrf() ?>
        <input type="hidden" name="annee" value="<?= (int)$annee ?>">
        <input type="hidden" name="semaine" value="<?= (int)$semaine ?>">
        <button type="submit" class="btn btn-warning" onclick="return confirm('Envoyer un email de rappel à tous les collaborateurs n\'ayant pas soumis leur rapport ?');">
            Envoyer les rappels maintenant (semaine <?= (int)$semaine ?> - <?= (int)$annee ?>)
        </button>
    </form>

    <?php if ($resultats !== null): ?>
        <div class="mt-4">
            <?php if (!$resultats): ?>
                <div class="alert alert-success">Tout le monde a déjà soumis son rapport — aucun rappel nécessaire.</div>
            <?php else: ?>
                <table class="table table-bordered bg-white">
                    <thead class="table-light">
                        <tr><th>Collaborateur</th><th>Email</th><th>Statut de l'envoi</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($resultats as $r): ?>
                        <tr>
                            <td><?= e($r['nom']) ?></td>
                            <td><?= e($r['email']) ?></td>
                            <td>
                                <?php if ($r['envoye']): ?>
                                    <span class="badge bg-success">Envoyé</span>
                                <?php else: ?>
                                    <span class="badge bg-danger">Échec (voir configuration email dans le README)</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>
</body>
</html>
