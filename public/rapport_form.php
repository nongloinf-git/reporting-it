<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/validation.php';
require_once __DIR__ . '/../includes/journal.php';
requireRole(['collaborateur']);

$u = currentUser();
$pdo = getPDO();

define('DOSSIER_UPLOADS', __DIR__ . '/uploads/rapports_word');
if (!is_dir(DOSSIER_UPLOADS)) {
    mkdir(DOSSIER_UPLOADS, 0775, true);
}

// Semaine choisie (par défaut : semaine courante)
$sem = semaineCourante();
$annee = isset($_GET['annee']) ? (int) $_GET['annee'] : $sem['annee'];
$semaine = isset($_GET['semaine']) ? (int) $_GET['semaine'] : $sem['semaine'];

$message = '';
$erreur = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $annee = (int) $_POST['annee'];
    $semaine = (int) $_POST['semaine'];
    $contenu = limiterLongueur($_POST['contenu'] ?? '', 20000);
    $tempsPasseBrut = trim($_POST['temps_passe'] ?? '');
    $statut = $_POST['action'] === 'soumettre' ? 'soumis' : 'brouillon';

    // Rapport existant pour cette semaine (pour conserver le fichier déjà uploadé si aucun nouveau fichier)
    $stmtExistant = $pdo->prepare('SELECT * FROM rapports WHERE utilisateur_id = ? AND annee = ? AND semaine_numero = ?');
    $stmtExistant->execute([$u['id'], $annee, $semaine]);
    $existant = $stmtExistant->fetch();

    // Sécurité : un rapport déjà validé ne doit plus pouvoir être modifié, même via
    // une requête forgée directement (le formulaire désactivé côté HTML n'est qu'un
    // confort d'affichage, pas une protection).
    if ($existant && $existant['statut'] === 'valide') {
        $erreur = 'Ce rapport a déjà été validé par votre manager et ne peut plus être modifié.';
    }

    $erreurTemps = erreurNombreDansPlage($tempsPasseBrut, 0, 168, 'Le temps passé');
    if (!$erreur && $erreurTemps !== null) {
        $erreur = $erreurTemps;
    }
    $tempsPasse = $tempsPasseBrut !== '' ? (float) $tempsPasseBrut : null;

    $nomFichierWord = $existant['fichier_word'] ?? null;

    // Gestion de l'upload du fichier Word
    if (!$erreur && !empty($_FILES['fichier_word']['name'])) {
        $fichier = $_FILES['fichier_word'];
        $extension = strtolower(pathinfo($fichier['name'], PATHINFO_EXTENSION));

        if ($fichier['error'] !== UPLOAD_ERR_OK) {
            $erreur = "Erreur lors de l'envoi du fichier.";
        } elseif ($extension !== 'docx') {
            $erreur = 'Seuls les fichiers .docx sont acceptés.';
        } elseif ($fichier['size'] > 10 * 1024 * 1024) {
            $erreur = 'Le fichier dépasse la taille maximale autorisée (10 Mo).';
        } elseif (!class_exists('ZipArchive') || !(function() use ($fichier) {
            $zip = new ZipArchive();
            $ouvert = $zip->open($fichier['tmp_name']) === true;
            if ($ouvert) {
                $zip->close();
            }
            return $ouvert;
        })()) {
            // Un fichier .docx est une archive ZIP : si l'ouverture échoue, ce n'est
            // pas un fichier Word valide (extension trompeuse, fichier corrompu...).
            $erreur = "Le fichier envoyé n'est pas un document Word (.docx) valide.";
        } else {
            $nouveauNom = 'rapport_' . $u['id'] . '_' . $annee . '_S' . $semaine . '_' . time() . '.docx';
            if (move_uploaded_file($fichier['tmp_name'], DOSSIER_UPLOADS . '/' . $nouveauNom)) {
                // Supprime l'ancien fichier s'il existait
                if ($nomFichierWord && file_exists(DOSSIER_UPLOADS . '/' . $nomFichierWord)) {
                    unlink(DOSSIER_UPLOADS . '/' . $nomFichierWord);
                }
                $nomFichierWord = $nouveauNom;
            } else {
                $erreur = "Impossible d'enregistrer le fichier envoyé.";
            }
        }
    }

    if (!$erreur) {
        if ($contenu === '' && !$nomFichierWord) {
            $erreur = "Veuillez saisir le contenu de votre rapport ou joindre un fichier Word (.docx).";
        } else {
            if ($statut === 'soumis') {
                // Chaque (re)soumission met à jour la date d'envoi.
                $stmt = $pdo->prepare(
                    'INSERT INTO rapports (utilisateur_id, annee, semaine_numero, contenu, fichier_word, temps_passe, statut, date_envoi)
                     VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
                     ON DUPLICATE KEY UPDATE contenu = VALUES(contenu), fichier_word = VALUES(fichier_word), temps_passe = VALUES(temps_passe), statut = VALUES(statut), date_envoi = NOW()'
                );
            } else {
                // Enregistrement en brouillon : la date d'envoi n'est pas touchée.
                $stmt = $pdo->prepare(
                    'INSERT INTO rapports (utilisateur_id, annee, semaine_numero, contenu, fichier_word, temps_passe, statut)
                     VALUES (?, ?, ?, ?, ?, ?, ?)
                     ON DUPLICATE KEY UPDATE contenu = VALUES(contenu), fichier_word = VALUES(fichier_word), temps_passe = VALUES(temps_passe), statut = VALUES(statut)'
                );
            }
            $stmt->execute([$u['id'], $annee, $semaine, $contenu ?: null, $nomFichierWord, $tempsPasse, $statut]);
            journaliser((int) $u['id'], $statut === 'soumis' ? 'soumission_rapport' : 'enregistrement_rapport', "Semaine $semaine/$annee");
            $message = $statut === 'soumis' ? 'Rapport soumis avec succès.' : 'Brouillon enregistré.';
        }
    }
}

// Charger le rapport existant pour la semaine sélectionnée (après traitement éventuel du POST)
$stmt = $pdo->prepare('SELECT * FROM rapports WHERE utilisateur_id = ? AND annee = ? AND semaine_numero = ?');
$stmt->execute([$u['id'], $annee, $semaine]);
$rapport = $stmt->fetch();
$verrouille = ($rapport['statut'] ?? '') === 'valide';
$titrePage = 'Mon rapport';
require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/navbar.php';
?>
<div class="container">
    <h3>Mon rapport hebdomadaire</h3>

    <?php if ($message): ?>
        <div class="alert alert-info"><?= e($message) ?></div>
    <?php endif; ?>
    <?php if ($erreur): ?>
        <div class="alert alert-danger"><?= e($erreur) ?></div>
    <?php endif; ?>

    <?php if ($verrouille): ?>
        <div class="alert alert-success">Ce rapport a déjà été validé par votre manager et n'est plus modifiable.</div>
    <?php endif; ?>

    <?php if ($rapport): ?>
        <p class="text-muted small">
            <?php if ($rapport['date_envoi']): ?>
                Envoyé le <?= e(formatDateHeure($rapport['date_envoi'])) ?>
            <?php else: ?>
                Pas encore envoyé (brouillon)
            <?php endif; ?>
            —
            <?php if ($rapport['date_validation']): ?>
                Validé le <?= e(formatDateHeure($rapport['date_validation'])) ?>
            <?php else: ?>
                En attente de validation
            <?php endif; ?>
        </p>
    <?php endif; ?>

    <form method="get" class="row g-2 mb-4">
        <div class="col-auto">
            <label class="form-label">Année</label>
            <input type="number" name="annee" value="<?= (int)$annee ?>" class="form-control">
        </div>
        <div class="col-auto">
            <label class="form-label">Semaine (1-53)</label>
            <input type="number" name="semaine" min="1" max="53" value="<?= (int)$semaine ?>" class="form-control">
        </div>
        <div class="col-auto align-self-end">
            <button class="btn btn-secondary">Changer de semaine</button>
        </div>
    </form>

    <form method="post" enctype="multipart/form-data">
        <?= champCsrf() ?>
        <input type="hidden" name="annee" value="<?= (int)$annee ?>">
        <input type="hidden" name="semaine" value="<?= (int)$semaine ?>">

        <div class="mb-3">
            <label class="form-label">Tâches réalisées / activités de la semaine</label>
            <textarea name="contenu" rows="8" class="form-control" <?= $verrouille ? 'disabled' : '' ?>><?= e($rapport['contenu'] ?? '') ?></textarea>
            <div class="form-text">Facultatif si vous joignez un fichier Word ci-dessous.</div>
        </div>

        <div class="mb-3">
            <label class="form-label">Ou joindre un fichier Word (.docx)</label>
            <input type="file" name="fichier_word" accept=".docx" class="form-control" <?= $verrouille ? 'disabled' : '' ?>>
            <?php if (!empty($rapport['fichier_word'])): ?>
                <div class="form-text">
                    Fichier actuellement joint : <strong><?= e($rapport['fichier_word']) ?></strong>
                    — <a href="uploads/rapports_word/<?= e($rapport['fichier_word']) ?>" target="_blank">télécharger</a>
                    <?php if (!$verrouille): ?>(l'envoi d'un nouveau fichier remplacera celui-ci)<?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="mb-3 col-md-3">
            <label class="form-label">Temps passé (heures)</label>
            <input type="number" step="0.5" name="temps_passe" class="form-control" value="<?= e((string)($rapport['temps_passe'] ?? '')) ?>" <?= $verrouille ? 'disabled' : '' ?>>
        </div>

        <?php if (!$verrouille): ?>
            <button type="submit" name="action" value="brouillon" class="btn btn-outline-secondary">Enregistrer en brouillon</button>
            <button type="submit" name="action" value="soumettre" class="btn btn-primary">Soumettre au manager</button>
        <?php endif; ?>
    </form>
</div>
</body>
</html>
