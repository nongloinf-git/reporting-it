<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireLogin();

$u = currentUser();
$pdo = getPDO();
$gestionnaire = peutGererTaches($u);

$collaborateurId = $gestionnaire && ($_GET['collaborateur_id'] ?? '') !== '' ? (int) $_GET['collaborateur_id'] : null;
if (!$gestionnaire) {
    $collaborateurId = (int) $u['id'];
}
$statutFiltre = $_GET['statut'] ?? '';
$origineFiltre = $_GET['origine'] ?? '';

$taches = recupererTachesFiltrees($pdo, $collaborateurId, $statutFiltre, $origineFiltre);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Export PDF - Tâches</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 30px; color: #222; }
        h2 { margin-bottom: 0; }
        .sous-titre { color: #666; margin-top: 4px; margin-bottom: 24px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 24px; }
        th, td { border: 1px solid #ccc; padding: 8px; text-align: left; vertical-align: top; }
        th { background: #f2f2f2; }
        .barre-actions { margin-bottom: 20px; }
        .badge { padding: 2px 8px; border-radius: 4px; font-size: 12px; color: #fff; }
        .badge-a_faire { background: #6c757d; }
        .badge-en_cours { background: #d39e00; }
        .badge-termine { background: #198754; }
        @media print {
            .barre-actions { display: none; }
            body { margin: 0; }
        }
    </style>
</head>
<body>
    <div class="barre-actions">
        <button onclick="window.print()">Enregistrer en PDF / Imprimer</button>
        <a href="taches.php">Retour</a>
    </div>

    <h2>Liste des tâches</h2>
    <p class="sous-titre">
        <?= $collaborateurId ? 'Filtré par collaborateur' : 'Toutes' ?>
        <?php if ($statutFiltre): ?> — statut : <?= e(libelleStatutTache($statutFiltre)) ?><?php endif; ?>
        <?php if ($origineFiltre === 'reunion'): ?> — issues d'une réunion<?php elseif ($origineFiltre === 'directe'): ?> — tâches directes<?php endif; ?>
    </p>

    <table>
        <thead>
            <tr>
                <th>Titre</th>
                <th>Responsable</th>
                <th>Origine</th>
                <th>Échéance</th>
                <th>Statut</th>
                <th>Sous-tâches</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($taches as $t): ?>
            <tr>
                <td><?= e($t['titre'] ?: $t['description']) ?></td>
                <td><?= e($t['responsable_nom'] ?? '-') ?></td>
                <td><?= $t['reunion_titre'] ? e($t['reunion_titre']) : 'Tâche directe' ?></td>
                <td><?= $t['echeance'] ? (new DateTime($t['echeance']))->format('d/m/Y') : '-' ?></td>
                <td><span class="badge badge-<?= $t['statut'] ?>"><?= libelleStatutTache($t['statut']) ?></span></td>
                <td><?= (int)$t['nb_sous_taches'] ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$taches): ?>
            <tr><td colspan="6">Aucune tâche pour ces filtres.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</body>
</html>
