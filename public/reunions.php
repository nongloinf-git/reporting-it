<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/reunions_helpers.php';
requireLogin();

$u = currentUser();
$pdo = getPDO();
$gestionnaire = peutGererReunions($u);

$reunions = reunionsVisibles($pdo, $u);
// Tri décroissant pour la vue liste (les plus récentes en premier)
$reunions = array_reverse($reunions);
$titrePage = 'Réunions';
require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/navbar.php';
?>
<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="mb-0">Réunions</h3>
        <div class="d-flex gap-2">
            <a href="reunions_calendrier.php" class="btn btn-outline-primary">📅 Vue calendrier</a>
            <?php if ($gestionnaire): ?>
                <a href="reunion_form.php" class="btn btn-primary">+ Nouvelle réunion</a>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!$reunions): ?>
        <p class="text-muted">Aucune réunion pour le moment.</p>
    <?php endif; ?>

    <div class="table-responsive">
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
</div>
</body>
</html>
