<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/validation.php';
requireLogin();

$u = currentUser();
$pdo = getPDO();

define('DOSSIER_PHOTOS', __DIR__ . '/uploads/photos_profil');
if (!is_dir(DOSSIER_PHOTOS)) {
    mkdir(DOSSIER_PHOTOS, 0775, true);
}

$messageInfos = '';
$erreurInfos = '';
$messageMdp = '';
$erreurMdp = '';

// --- Mise à jour des informations (nom + photo) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'infos') {
    requireCsrf();
    $nom = limiterLongueur($_POST['nom'] ?? '', 100);

    if ($nom === '') {
        $erreurInfos = 'Le nom ne peut pas être vide.';
    } else {
        $nomPhoto = $u['photo_profil'];

        if (!empty($_FILES['photo']['name'])) {
            $fichier = $_FILES['photo'];
            $extension = strtolower(pathinfo($fichier['name'], PATHINFO_EXTENSION));
            $extensionsAutorisees = ['jpg', 'jpeg', 'png', 'webp'];

            if ($fichier['error'] !== UPLOAD_ERR_OK) {
                $erreurInfos = "Erreur lors de l'envoi de la photo.";
            } elseif (!in_array($extension, $extensionsAutorisees, true)) {
                $erreurInfos = 'Formats acceptés pour la photo : JPG, PNG, WEBP.';
            } elseif ($fichier['size'] > 3 * 1024 * 1024) {
                $erreurInfos = 'La photo dépasse la taille maximale autorisée (3 Mo).';
            } elseif (@getimagesize($fichier['tmp_name']) === false) {
                $erreurInfos = "Le fichier envoyé n'est pas une image valide.";
            } else {
                $nouveauNom = 'photo_' . $u['id'] . '_' . time() . '.' . $extension;
                if (move_uploaded_file($fichier['tmp_name'], DOSSIER_PHOTOS . '/' . $nouveauNom)) {
                    if ($nomPhoto && file_exists(DOSSIER_PHOTOS . '/' . $nomPhoto)) {
                        unlink(DOSSIER_PHOTOS . '/' . $nomPhoto);
                    }
                    $nomPhoto = $nouveauNom;
                } else {
                    $erreurInfos = "Impossible d'enregistrer la photo envoyée.";
                }
            }
        }

        if (!$erreurInfos) {
            $stmt = $pdo->prepare('UPDATE utilisateurs SET nom = ?, photo_profil = ? WHERE id = ?');
            $stmt->execute([$nom, $nomPhoto, $u['id']]);
            $messageInfos = 'Informations mises à jour.';
            $u = currentUser(); // valeur mise en cache : on force un rechargement affichage ci-dessous via variables locales
            $u['nom'] = $nom;
            $u['photo_profil'] = $nomPhoto;
        }
    }
}

// --- Changement de mot de passe ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'mot_de_passe') {
    requireCsrf();
    $ancien = $_POST['ancien_mot_de_passe'] ?? '';
    $nouveau = $_POST['nouveau_mot_de_passe'] ?? '';
    $confirmation = $_POST['confirmation_mot_de_passe'] ?? '';

    $stmt = $pdo->prepare('SELECT mot_de_passe FROM utilisateurs WHERE id = ?');
    $stmt->execute([$u['id']]);
    $hashActuel = $stmt->fetchColumn();

    $erreurRobustesse = erreurForceMotDePasse($nouveau);

    if (!password_verify($ancien, $hashActuel)) {
        $erreurMdp = 'Mot de passe actuel incorrect.';
    } elseif ($erreurRobustesse !== null) {
        $erreurMdp = $erreurRobustesse;
    } elseif ($nouveau !== $confirmation) {
        $erreurMdp = 'La confirmation ne correspond pas au nouveau mot de passe.';
    } else {
        $stmt = $pdo->prepare('UPDATE utilisateurs SET mot_de_passe = ? WHERE id = ?');
        $stmt->execute([password_hash($nouveau, PASSWORD_DEFAULT), $u['id']]);
        $messageMdp = 'Mot de passe modifié avec succès.';
    }
}

// --- Personnalisation de l'apparence (couleur, mode sombre) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'apparence') {
    requireCsrf();
    $couleursValides = ['bleu', 'vert', 'violet', 'orange', 'rouge'];
    $couleur = in_array($_POST['theme_couleur'] ?? '', $couleursValides, true) ? $_POST['theme_couleur'] : 'bleu';
    $modeSombre = isset($_POST['mode_sombre']) ? 1 : 0;

    $stmt = $pdo->prepare('UPDATE utilisateurs SET theme_couleur = ?, mode_sombre = ? WHERE id = ?');
    $stmt->execute([$couleur, $modeSombre, $u['id']]);

    // Redirection nécessaire pour que le nouveau thème s'applique dès cette page
    // (currentUser() est mis en cache pour la durée de la requête en cours).
    header('Location: profil.php?apparence=1');
    exit;
}

$messageApparence = isset($_GET['apparence']) ? 'Apparence mise à jour.' : '';

$titrePage = 'Mon profil';
require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/navbar.php';
?>
<div class="container">
    <h3>Mon profil</h3>

    <div class="card mb-4">
        <div class="card-header">Apparence de l'interface</div>
        <div class="card-body">
            <?php if ($messageApparence): ?><div class="alert alert-info"><?= e($messageApparence) ?></div><?php endif; ?>
            <form method="post">
                <?= champCsrf() ?>
                <input type="hidden" name="action" value="apparence">
                <div class="mb-3">
                    <label class="form-label d-block">Couleur d'accent</label>
                    <?php
                    $couleursDisponibles = [
                        'bleu' => ['#0d6efd', 'Bleu'],
                        'vert' => ['#198754', 'Vert'],
                        'violet' => ['#6f42c1', 'Violet'],
                        'orange' => ['#fd7e14', 'Orange'],
                        'rouge' => ['#dc3545', 'Rouge'],
                    ];
                    ?>
                    <div class="d-flex gap-3 flex-wrap">
                        <?php foreach ($couleursDisponibles as $cle => [$hex, $libelle]): ?>
                            <label class="d-flex flex-column align-items-center gap-1" style="cursor:pointer;">
                                <input type="radio" name="theme_couleur" value="<?= $cle ?>" class="form-check-input" style="width:0;height:0;opacity:0;position:absolute;"
                                       <?= ($u['theme_couleur'] ?? 'bleu') === $cle ? 'checked' : '' ?>
                                       onclick="this.closest('form').querySelectorAll('.pastille-couleur').forEach(function(p){p.style.outline='none';}); this.nextElementSibling.style.outline='3px solid #333';">
                                <span class="pastille-couleur" style="display:inline-block;width:32px;height:32px;border-radius:50%;background:<?= $hex ?>;<?= ($u['theme_couleur'] ?? 'bleu') === $cle ? 'outline:3px solid #333;' : '' ?>"></span>
                                <span class="small"><?= $libelle ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="mb-3 form-check form-switch">
                    <input type="checkbox" name="mode_sombre" value="1" class="form-check-input" id="modeSombre" <?= !empty($u['mode_sombre']) ? 'checked' : '' ?>>
                    <label class="form-check-label" for="modeSombre">Mode sombre</label>
                </div>
                <button class="btn btn-primary">Enregistrer l'apparence</button>
            </form>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header">Informations personnelles</div>
        <div class="card-body">
            <?php if ($messageInfos): ?><div class="alert alert-info"><?= e($messageInfos) ?></div><?php endif; ?>
            <?php if ($erreurInfos): ?><div class="alert alert-danger"><?= e($erreurInfos) ?></div><?php endif; ?>

            <form method="post" enctype="multipart/form-data" class="row g-3 align-items-center">
                <?= champCsrf() ?>
                <input type="hidden" name="action" value="infos">
                <div class="col-auto">
                    <img src="<?= e(urlPhotoProfil($u['photo_profil'], $u['nom'])) ?>" alt="" style="height:64px; width:64px; object-fit:cover; border-radius:50%;">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Nom affiché</label>
                    <input type="text" name="nom" class="form-control" value="<?= e($u['nom']) ?>" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Nouvelle photo de profil</label>
                    <input type="file" name="photo" accept=".jpg,.jpeg,.png,.webp" class="form-control">
                </div>
                <div class="col-md-2 align-self-end">
                    <button class="btn btn-primary w-100">Enregistrer</button>
                </div>
            </form>
            <p class="text-muted small mt-2 mb-0">Email : <?= e($u['email']) ?> (contactez votre administrateur pour le modifier)</p>
        </div>
    </div>

    <div class="card">
        <div class="card-header">Changer mon mot de passe</div>
        <div class="card-body">
            <?php if ($messageMdp): ?><div class="alert alert-info"><?= e($messageMdp) ?></div><?php endif; ?>
            <?php if ($erreurMdp): ?><div class="alert alert-danger"><?= e($erreurMdp) ?></div><?php endif; ?>

            <form method="post" class="row g-3">
                <?= champCsrf() ?>
                <input type="hidden" name="action" value="mot_de_passe">
                <div class="col-md-4">
                    <label class="form-label">Mot de passe actuel</label>
                    <input type="password" name="ancien_mot_de_passe" class="form-control" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Nouveau mot de passe</label>
                    <input type="password" name="nouveau_mot_de_passe" class="form-control" minlength="8" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Confirmer le nouveau mot de passe</label>
                    <input type="password" name="confirmation_mot_de_passe" class="form-control" minlength="8" required>
                </div>
                <div class="col-auto">
                    <button class="btn btn-primary">Changer le mot de passe</button>
                </div>
            </form>
        </div>
    </div>
</div>
</body>
</html>
