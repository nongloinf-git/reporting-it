<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/journal.php';
requireRole(['admin']);

$pdo = getPDO();

$utilisateurId = isset($_GET['utilisateur_id']) && $_GET['utilisateur_id'] !== '' ? (int) $_GET['utilisateur_id'] : null;
$actionFiltre = $_GET['action'] ?? '';
$limite = 200;

$conditions = [];
$parametres = [];

if ($utilisateurId !== null) {
    $conditions[] = 'j.utilisateur_id = ?';
    $parametres[] = $utilisateurId;
}
if ($actionFiltre !== '') {
    $conditions[] = 'j.action = ?';
    $parametres[] = $actionFiltre;
}

$ou = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

$stmt = $pdo->prepare(
    "SELECT j.*, u.nom AS utilisateur_nom
     FROM journal_activite j
     LEFT JOIN utilisateurs u ON u.id = j.utilisateur_id
     $ou
     ORDER BY j.date_action DESC
     LIMIT $limite"
);
$stmt->execute($parametres);
$entrees = $stmt->fetchAll();

$utilisateurs = $pdo->query('SELECT id, nom FROM utilisateurs ORDER BY nom')->fetchAll();
$actionsDistinctes = $pdo->query('SELECT DISTINCT action FROM journal_activite ORDER BY action')->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Journal d'activité - Reporting IT</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<?php require __DIR__ . '/../includes/navbar.php'; ?>
<div class="container">
    <h3>Journal d'activité</h3>
    <p class="text-muted">Connexions et actions sensibles (les <?= $limite ?> entrées les plus récentes, selon les filtres ci-dessous).</p>

    <form method="get" class="row g-2 mb-4">
        <div class="col-auto">
            <label class="form-label">Utilisateur</label>
            <select name="utilisateur_id" class="form-select">
                <option value="">Tous</option>
                <?php foreach ($utilisateurs as $ut): ?>
                    <option value="<?= (int)$ut['id'] ?>" <?= $utilisateurId === (int)$ut['id'] ? 'selected' : '' ?>><?= e($ut['nom']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-auto">
            <label class="form-label">Type d'action</label>
            <select name="action" class="form-select">
                <option value="">Toutes</option>
                <?php foreach ($actionsDistinctes as $a): ?>
                    <option value="<?= e($a) ?>" <?= $actionFiltre === $a ? 'selected' : '' ?>><?= e(libelleActionJournal($a)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-auto align-self-end">
            <button class="btn btn-secondary">Filtrer</button>
        </div>
        <div class="col-auto align-self-end">
            <a href="admin_journal.php" class="btn btn-outline-secondary">Réinitialiser</a>
        </div>
    </form>

    <?php if (!$entrees): ?>
        <p class="text-muted">Aucune entrée trouvée.</p>
    <?php else: ?>
        <table class="table table-bordered table-sm bg-white">
            <thead class="table-light">
                <tr><th>Date</th><th>Utilisateur</th><th>Action</th><th>Détails</th><th>Adresse IP</th></tr>
            </thead>
            <tbody>
            <?php foreach ($entrees as $entree): ?>
                <tr>
                    <td class="text-nowrap"><?= e(formatDateHeure($entree['date_action'])) ?></td>
                    <td>
                        <?= e($entree['utilisateur_nom'] ?? ($entree['email_tentative'] ? $entree['email_tentative'] . ' (inconnu)' : '-')) ?>
                    </td>
                    <td>
                        <?php
                        $classe = str_contains($entree['action'], 'echouee') || str_contains($entree['action'], 'suppression') ? 'bg-danger' : 'bg-secondary';
                        ?>
                        <span class="badge <?= $classe ?>"><?= e(libelleActionJournal($entree['action'])) ?></span>
                    </td>
                    <td><?= e($entree['details'] ?? '-') ?></td>
                    <td class="text-muted small"><?= e($entree['adresse_ip'] ?? '-') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
</body>
</html>
