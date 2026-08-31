<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/reunions_helpers.php';
requireLogin();

$u = currentUser();
$pdo = getPDO();
$gestionnaire = peutGererReunions($u);

$mois = isset($_GET['mois']) ? max(1, min(12, (int) $_GET['mois'])) : (int) date('n');
$annee = isset($_GET['annee']) ? (int) $_GET['annee'] : (int) date('Y');

$premierJourMois = new DateTime(sprintf('%04d-%02d-01', $annee, $mois));
$dernierJourMois = (clone $premierJourMois)->modify('last day of this month');

// La grille commence le lundi de la semaine du 1er, et finit le dimanche de la semaine du dernier jour
$jourSemaine1er = (int) $premierJourMois->format('N'); // 1 = lundi ... 7 = dimanche
$debutGrille = (clone $premierJourMois)->modify('-' . ($jourSemaine1er - 1) . ' days');

$jourSemaineDernier = (int) $dernierJourMois->format('N');
$finGrille = (clone $dernierJourMois)->modify('+' . (7 - $jourSemaineDernier) . ' days');

// Réunions visibles sur toute la plage affichée (y compris les jours "hors mois" en bordure de grille)
$reunionsPeriode = reunionsVisibles(
    $pdo,
    $u,
    $debutGrille->format('Y-m-d 00:00:00'),
    $finGrille->format('Y-m-d 23:59:59')
);

$reunionsParJour = [];
foreach ($reunionsPeriode as $r) {
    $cle = (new DateTime($r['date_reunion']))->format('Y-m-d');
    $reunionsParJour[$cle][] = $r;
}

// Construction des semaines de la grille
$semainesGrille = [];
$curseur = clone $debutGrille;
while ($curseur <= $finGrille) {
    $semaine = [];
    for ($i = 0; $i < 7; $i++) {
        $semaine[] = clone $curseur;
        $curseur->modify('+1 day');
    }
    $semainesGrille[] = $semaine;
}

$moisPrecedent = (clone $premierJourMois)->modify('-1 month');
$moisSuivant = (clone $premierJourMois)->modify('+1 month');
$nomsMois = [1 => 'Janvier', 2 => 'Février', 3 => 'Mars', 4 => 'Avril', 5 => 'Mai', 6 => 'Juin', 7 => 'Juillet', 8 => 'Août', 9 => 'Septembre', 10 => 'Octobre', 11 => 'Novembre', 12 => 'Décembre'];
$aujourdhui = (new DateTime())->format('Y-m-d');
$titrePage = 'Calendrier des réunions';
require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/navbar.php';
?>
<style>
    .jour-cellule { min-height: 110px; vertical-align: top; padding: 4px !important; }
    .jour-hors-mois { background: #f8f9fa; color: #adb5bd; }
    .jour-aujourdhui { background: #e7f1ff; }
    .numero-jour { font-weight: 600; font-size: 0.85rem; }
    .reunion-badge { display: block; font-size: 0.72rem; padding: 2px 4px; margin-top: 2px; border-radius: 3px; background: #0d6efd; color: #fff; text-decoration: none; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .reunion-badge:hover { background: #0a58ca; color: #fff; }
</style>
<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="mb-0">Calendrier des réunions</h3>
        <div class="d-flex gap-2">
            <a href="reunions.php" class="btn btn-outline-secondary">☰ Vue liste</a>
            <?php if ($gestionnaire): ?>
                <a href="reunion_form.php" class="btn btn-primary">+ Nouvelle réunion</a>
            <?php endif; ?>
        </div>
    </div>

    <div class="d-flex justify-content-between align-items-center mb-3">
        <a class="btn btn-outline-secondary btn-sm" href="?mois=<?= (int)$moisPrecedent->format('n') ?>&annee=<?= (int)$moisPrecedent->format('Y') ?>">← Mois précédent</a>
        <h5 class="mb-0"><?= $nomsMois[$mois] ?> <?= $annee ?></h5>
        <a class="btn btn-outline-secondary btn-sm" href="?mois=<?= (int)$moisSuivant->format('n') ?>&annee=<?= (int)$moisSuivant->format('Y') ?>">Mois suivant →</a>
    </div>
    <div class="text-center mb-3">
        <a class="btn btn-sm btn-link" href="reunions_calendrier.php">Revenir au mois en cours</a>
    </div>

    <div class="table-responsive">
    <table class="table table-bordered bg-white" style="table-layout: fixed; min-width: 700px;">
        <thead class="table-light">
            <tr>
                <th>Lun</th><th>Mar</th><th>Mer</th><th>Jeu</th><th>Ven</th><th>Sam</th><th>Dim</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($semainesGrille as $semaineJours): ?>
            <tr>
                <?php foreach ($semaineJours as $jour):
                    $cleJour = $jour->format('Y-m-d');
                    $horsMois = (int) $jour->format('n') !== $mois;
                    $estAujourdhui = $cleJour === $aujourdhui;
                    $classes = 'jour-cellule' . ($horsMois ? ' jour-hors-mois' : '') . ($estAujourdhui && !$horsMois ? ' jour-aujourdhui' : '');
                ?>
                    <td class="<?= $classes ?>">
                        <div class="numero-jour"><?= (int)$jour->format('j') ?></div>
                        <?php if (!empty($reunionsParJour[$cleJour])): ?>
                            <?php foreach ($reunionsParJour[$cleJour] as $r): ?>
                                <a href="reunion_detail.php?id=<?= (int)$r['id'] ?>" class="reunion-badge" title="<?= e($r['titre']) ?> — <?= (new DateTime($r['date_reunion']))->format('H:i') ?>">
                                    <?= (new DateTime($r['date_reunion']))->format('H:i') ?> <?= e($r['titre']) ?>
                                </a>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </td>
                <?php endforeach; ?>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</div>
</body>
</html>
