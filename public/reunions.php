<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireLogin();

$u = currentUser();
$pdo = getPDO();
$gestionnaire = peutGererReunions($u);

if ($gestionnaire) {
    // Un gestionnaire voit toutes les réunions qu'il a organisées ou auxquelles il participe.
    // L'admin voit systématiquement toutes les réunions de l'application.
    if ($u['role'] === 'admin') {
        $stmt = $pdo->query(
            'SELECT r.*, org.nom AS organisateur_nom,
                    (SELECT COUNT(*) FROM taches_reunion t WHERE t.reunion_id = r.id) AS nb_taches
             FROM reunions r
             JOIN utilisateurs org ON org.id = r.organisateur_id
             ORDER BY r.date_reunion DESC'
        );
    } else {
        $stmt = $pdo->prepare(
            'SELECT DISTINCT r.*, org.nom AS organisateur_nom,
                    (SELECT COUNT(*) FROM taches_reunion t WHERE t.reunion_id = r.id) AS nb_taches
             FROM reunions r
             JOIN utilisateurs org ON org.id = r.organisateur_id
             LEFT JOIN reunion_participants rp ON rp.reunion_id = r.id
             WHERE r.organisateur_id = ? OR rp.utilisateur_id = ?
             ORDER BY r.date_reunion DESC'
        );
        $stmt->execute([$u['id'], $u['id']]);
    }
} else {
    // Un simple participant ne voit que les réunions auxquelles il est convié.
    $stmt = $pdo->prepare(
        'SELECT r.*, org.nom AS organisateur_nom,
                (SELECT COUNT(*) FROM taches_reunion t WHERE t.reunion_id = r.id) AS nb_taches
         FROM reunions r
         JOIN utilisateurs org ON org.id = r.organisateur_id
         JOIN reunion_participants rp ON rp.reunion_id = r.id
         WHERE rp.utilisateur_id = ?
         ORDER BY r.date_reunion DESC'
    );
    $stmt->execute([$u['id']]);
}
$reunions = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Réunions - Reporting IT</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<?php require __DIR__ . '/../includes/navbar.php'; ?>
<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="mb-0">Réunions</h3>
        <?php if ($gestionnaire): ?>
            <a href="reunion_form.php" class="btn btn-primary">+ Nouvelle réunion</a>
        <?php endif; ?>
    </div>

    <?php if (!$reunions): ?>
        <p class="text-muted">Aucune réunion pour le moment.</p>
    <?php endif; ?>

    <table class="table table-bordered bg-white">
        <thead class="table-light">
            <tr>
                <th>Titre</th>
                <th>Date</th>
                <th>Lieu</th>
                <th>Organisateur</th>
                <th>Tâches</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($reunions as $r): ?>
            <tr>
                <td><?= e($r['titre']) ?></td>
                <td><?= (new DateTime($r['date_reunion']))->format('d/m/Y H:i') ?></td>
                <td><?= e($r['lieu'] ?? '-') ?></td>
                <td><?= e($r['organisateur_nom']) ?></td>
                <td><?= (int)$r['nb_taches'] ?></td>
                <td><a href="reunion_detail.php?id=<?= (int)$r['id'] ?>" class="btn btn-sm btn-outline-primary">Voir</a></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
</body>
</html>
