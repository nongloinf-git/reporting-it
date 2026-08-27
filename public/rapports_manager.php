<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireRole(['manager', 'admin']);

$u = currentUser();
$pdo = getPDO();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rapportId = (int) $_POST['rapport_id'];
    $commentaire = trim($_POST['commentaire'] ?? '');
    $action = $_POST['action'] ?? '';

    if ($action === 'valider') {
        $stmt = $pdo->prepare("UPDATE rapports SET statut = 'valide' WHERE id = ?");
        $stmt->execute([$rapportId]);
    }
    if ($action === 'renvoyer') {
        // Renvoie le rapport au collaborateur pour révision (redevient modifiable)
        $stmt = $pdo->prepare("UPDATE rapports SET statut = 'brouillon' WHERE id = ?");
        $stmt->execute([$rapportId]);
    }
    if ($commentaire !== '') {
        $stmt = $pdo->prepare('INSERT INTO commentaires_validation (rapport_id, manager_id, commentaire) VALUES (?, ?, ?)');
        $stmt->execute([$rapportId, $u['id'], $commentaire]);
    }
    header('Location: rapports_manager.php#rapport-' . $rapportId);
    exit;
}

// Filtre semaine (par défaut : semaine courante)
$sem = semaineCourante();
$annee = isset($_GET['annee']) ? (int) $_GET['annee'] : $sem['annee'];
$semaine = isset($_GET['semaine']) ? (int) $_GET['semaine'] : $sem['semaine'];

if ($u['role'] === 'manager') {
    $stmt = $pdo->prepare(
        'SELECT r.*, ut.nom FROM rapports r
         JOIN utilisateurs ut ON ut.id = r.utilisateur_id
         WHERE ut.manager_id = ? AND r.annee = ? AND r.semaine_numero = ?
         ORDER BY r.statut, ut.nom'
    );
    $stmt->execute([$u['id'], $annee, $semaine]);
} else {
    $stmt = $pdo->prepare(
        "SELECT r.*, ut.nom FROM rapports r
         JOIN utilisateurs ut ON ut.id = r.utilisateur_id
         WHERE r.annee = ? AND r.semaine_numero = ?
         ORDER BY r.statut, ut.nom"
    );
    $stmt->execute([$annee, $semaine]);
}
$rapports = $stmt->fetchAll();

// Commentaires liés à ces rapports
$commentairesParRapport = [];
if ($rapports) {
    $ids = array_column($rapports, 'id');
    $in = implode(',', array_fill(0, count($ids), '?'));
    $stmtC = $pdo->prepare("SELECT c.*, m.nom AS manager_nom FROM commentaires_validation c JOIN utilisateurs m ON m.id = c.manager_id WHERE rapport_id IN ($in) ORDER BY c.date_creation");
    $stmtC->execute($ids);
    foreach ($stmtC->fetchAll() as $c) {
        $commentairesParRapport[$c['rapport_id']][] = $c;
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Rapports de l'équipe - Reporting IT</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<?php require __DIR__ . '/../includes/navbar.php'; ?>
<div class="container">
    <h3>Rapports de l'équipe</h3>

    <form method="get" class="row g-2 mb-4">
        <div class="col-auto">
            <label class="form-label">Année</label>
            <input type="number" name="annee" value="<?= (int)$annee ?>" class="form-control">
        </div>
        <div class="col-auto">
            <label class="form-label">Semaine</label>
            <input type="number" name="semaine" min="1" max="53" value="<?= (int)$semaine ?>" class="form-control">
        </div>
        <div class="col-auto align-self-end">
            <button class="btn btn-secondary">Filtrer</button>
        </div>
        <div class="col-auto align-self-end">
            <a class="btn btn-outline-success" href="export_csv.php?annee=<?= (int)$annee ?>&semaine=<?= (int)$semaine ?>">Exporter CSV</a>
        </div>
        <div class="col-auto align-self-end">
            <a class="btn btn-outline-danger" href="export_pdf.php?annee=<?= (int)$annee ?>&semaine=<?= (int)$semaine ?>" target="_blank">Exporter PDF</a>
        </div>
        <div class="col-auto align-self-end ms-auto">
            <a class="btn btn-outline-primary" href="rapports_historique.php">Historique des semaines</a>
        </div>
    </form>

    <?php if (!$rapports): ?>
        <p class="text-muted">Aucun rapport soumis pour cette semaine.</p>
    <?php endif; ?>

    <?php foreach ($rapports as $r): ?>
        <div class="card mb-3" id="rapport-<?= (int)$r['id'] ?>">
            <div class="card-header d-flex justify-content-between align-items-center">
                <strong><?= e($r['nom']) ?></strong>
                <span class="badge bg-<?= classeBadgeStatut($r['statut']) ?>"><?= libelleStatut($r['statut']) ?></span>
            </div>
            <div class="card-body">
                <?php if (!empty($r['contenu'])): ?>
                    <p style="white-space: pre-wrap;"><?= e($r['contenu']) ?></p>
                <?php endif; ?>

                <?php if (!empty($r['fichier_word'])):
                    $cheminFichier = __DIR__ . '/uploads/rapports_word/' . $r['fichier_word'];
                    $apercu = file_exists($cheminFichier) ? extraireApercuDocx($cheminFichier) : null;
                ?>
                    <div class="border rounded p-3 bg-light mb-2">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <strong>📄 Rapport Word joint</strong>
                            <a href="uploads/rapports_word/<?= e($r['fichier_word']) ?>" target="_blank" class="btn btn-sm btn-outline-primary">Télécharger / Ouvrir</a>
                        </div>
                        <?php if ($apercu): ?>
                            <p class="text-muted small mb-1">Aperçu du contenu (extraction automatique) :</p>
                            <div style="white-space: pre-wrap; max-height: 300px; overflow-y: auto;" class="small bg-white border rounded p-2"><?= e($apercu) ?></div>
                        <?php else: ?>
                            <p class="text-muted small mb-0">Aperçu indisponible — utilisez "Télécharger / Ouvrir" pour consulter le fichier.</p>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <p class="text-muted small">Temps passé : <?= $r['temps_passe'] !== null ? e((string)$r['temps_passe']) . ' h' : 'non renseigné' ?></p>

                <?php if (!empty($commentairesParRapport[$r['id']])): ?>
                    <hr>
                    <h6>Commentaires</h6>
                    <?php foreach ($commentairesParRapport[$r['id']] as $c): ?>
                        <p class="mb-1"><strong><?= e($c['manager_nom']) ?> :</strong> <?= e($c['commentaire']) ?></p>
                    <?php endforeach; ?>
                <?php endif; ?>

                <form method="post" class="mt-3">
                    <input type="hidden" name="rapport_id" value="<?= (int)$r['id'] ?>">
                    <div class="mb-2">
                        <textarea name="commentaire" class="form-control" rows="2" placeholder="Ajouter un commentaire..."></textarea>
                    </div>
                    <button type="submit" name="action" value="commenter" class="btn btn-outline-secondary btn-sm">Commenter</button>
                    <?php if ($r['statut'] !== 'valide'): ?>
                        <button type="submit" name="action" value="valider" class="btn btn-success btn-sm">Valider le rapport</button>
                    <?php endif; ?>
                    <?php if ($r['statut'] === 'soumis'): ?>
                        <button type="submit" name="action" value="renvoyer" class="btn btn-outline-warning btn-sm" onclick="return confirm('Renvoyer ce rapport au collaborateur pour révision ? Un commentaire expliquant pourquoi est recommandé.');">Renvoyer pour révision (refuser)</button>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    <?php endforeach; ?>
</div>
</body>
</html>
