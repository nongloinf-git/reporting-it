<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/validation.php';
requireRole(['admin']);

$pdo = getPDO();

define('DOSSIER_LOGO', __DIR__ . '/uploads/logo');
if (!is_dir(DOSSIER_LOGO)) {
    mkdir(DOSSIER_LOGO, 0775, true);
}

$message = '';
$erreur = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $nomSociete = limiterLongueur($_POST['nom_societe'] ?? '', 150);
    setParametre('nom_societe', $nomSociete !== '' ? $nomSociete : null);

    if (!empty($_FILES['logo']['name'])) {
        $fichier = $_FILES['logo'];
        $extension = strtolower(pathinfo($fichier['name'], PATHINFO_EXTENSION));
        $extensionsAutorisees = ['png', 'jpg', 'jpeg', 'svg', 'webp'];

        if ($fichier['error'] !== UPLOAD_ERR_OK) {
            $erreur = "Erreur lors de l'envoi du logo.";
        } elseif (!in_array($extension, $extensionsAutorisees, true)) {
            $erreur = 'Formats acceptés pour le logo : PNG, JPG, SVG, WEBP.';
        } elseif ($fichier['size'] > 2 * 1024 * 1024) {
            $erreur = 'Le logo dépasse la taille maximale autorisée (2 Mo).';
        } elseif ($extension !== 'svg' && @getimagesize($fichier['tmp_name']) === false) {
            // Vérifie que le contenu est réellement une image (et pas un fichier
            // renommé avec une extension trompeuse) — le SVG n'est pas concerné
            // par ce contrôle car ce n'est pas un format bitmap.
            $erreur = "Le fichier envoyé n'est pas une image valide.";
        } else {
            $ancienLogo = getParametre('logo_societe');
            $nouveauNom = 'logo_societe_' . time() . '.' . $extension;
            if (move_uploaded_file($fichier['tmp_name'], DOSSIER_LOGO . '/' . $nouveauNom)) {
                if ($ancienLogo && file_exists(DOSSIER_LOGO . '/' . $ancienLogo)) {
                    unlink(DOSSIER_LOGO . '/' . $ancienLogo);
                }
                setParametre('logo_societe', $nouveauNom);
            } else {
                $erreur = "Impossible d'enregistrer le logo envoyé.";
            }
        }
    }

    if (!$erreur) {
        $message = 'Paramètres enregistrés.';
    }
}

$logoActuel = getParametre('logo_societe');
$nomSocieteActuel = getParametre('nom_societe');
$titrePage = 'Paramètres';
require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/navbar.php';
?>
<div class="container">
    <h3>Paramètres de l'application</h3>

    <?php if ($message): ?><div class="alert alert-info"><?= e($message) ?></div><?php endif; ?>
    <?php if ($erreur): ?><div class="alert alert-danger"><?= e($erreur) ?></div><?php endif; ?>

    <div class="card">
        <div class="card-header">Identité visuelle de la société</div>
        <div class="card-body">
            <?php if ($logoActuel): ?>
                <div class="mb-3">
                    <p class="text-muted small mb-1">Logo actuel :</p>
                    <img src="uploads/logo/<?= e($logoActuel) ?>" alt="Logo actuel" style="max-height:60px;">
                </div>
            <?php endif; ?>
            <form method="post" enctype="multipart/form-data" class="row g-3">
                <?= champCsrf() ?>
                <div class="col-md-4">
                    <label class="form-label">Nom de la société (affiché dans le menu)</label>
                    <input type="text" name="nom_societe" class="form-control" value="<?= e($nomSocieteActuel ?? '') ?>" placeholder="Ex : Acme SARL">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Logo de la société</label>
                    <input type="file" name="logo" accept=".png,.jpg,.jpeg,.svg,.webp" class="form-control">
                </div>
                <div class="col-md-2 align-self-end">
                    <button class="btn btn-primary w-100">Enregistrer</button>
                </div>
            </form>
        </div>
    </div>
</div>
</body>
</html>
