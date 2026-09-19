<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/journal.php';
requireRole(['admin']);

$pdo = getPDO();

$utilisateurId = isset($_GET['utilisateur_id']) && $_GET['utilisateur_id'] !== '' ? (int) $_GET['utilisateur_id'] : null;
$actionFiltre = $_GET['action'] ?? '';

$entrees = recupererJournalFiltre($pdo, $utilisateurId, $actionFiltre, 2000);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Export PDF - Historique des modifications</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 30px; color: #222; }
        h2 { margin-bottom: 0; }
        .sous-titre { color: #666; margin-top: 4px; margin-bottom: 24px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 24px; }
        th, td { border: 1px solid #ccc; padding: 6px 8px; text-align: left; vertical-align: top; font-size: 13px; }
        th { background: #f2f2f2; }
        .barre-actions { margin-bottom: 20px; }
        @media print {
            .barre-actions { display: none; }
            body { margin: 0; }
        }
    </style>
</head>
<body>
    <div class="barre-actions">
        <button onclick="window.print()">Enregistrer en PDF / Imprimer</button>
        <a href="admin_journal.php">Retour</a>
    </div>

    <h2>Historique des modifications</h2>
    <p class="sous-titre"><?= count($entrees) ?> entrée(s)</p>

    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Utilisateur</th>
                <th>Action</th>
                <th>Détails</th>
                <th>Adresse IP</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($entrees as $entree): ?>
            <tr>
                <td><?= e(formatDateHeure($entree['date_action'])) ?></td>
                <td><?= e($entree['utilisateur_nom'] ?? ($entree['email_tentative'] ? $entree['email_tentative'] . ' (inconnu)' : '-')) ?></td>
                <td><?= e(libelleActionJournal($entree['action'])) ?></td>
                <td><?= e($entree['details'] ?? '-') ?></td>
                <td><?= e($entree['adresse_ip'] ?? '-') ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$entrees): ?>
            <tr><td colspan="5">Aucune entrée.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</body>
</html>
