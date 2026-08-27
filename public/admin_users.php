<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireRole(['admin']);

$admin = currentUser();
$pdo = getPDO();
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Création d'un utilisateur
    if ($action === 'creer') {
        $nom = trim($_POST['nom'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $motDePasse = $_POST['mot_de_passe'] ?? '';
        $role = $_POST['role'] ?? 'collaborateur';
        $equipe = trim($_POST['equipe'] ?? '');
        $managerId = ($_POST['manager_id'] ?? '') !== '' ? (int) $_POST['manager_id'] : null;
        $peutGererReunions = isset($_POST['peut_gerer_reunions']) ? 1 : 0;

        if ($nom === '' || $email === '' || $motDePasse === '') {
            $message = 'Tous les champs obligatoires doivent être remplis.';
        } else {
            try {
                $stmt = $pdo->prepare(
                    'INSERT INTO utilisateurs (nom, email, mot_de_passe, role, equipe, manager_id, peut_gerer_reunions) VALUES (?, ?, ?, ?, ?, ?, ?)'
                );
                $stmt->execute([$nom, $email, password_hash($motDePasse, PASSWORD_DEFAULT), $role, $equipe ?: null, $managerId, $peutGererReunions]);
                $message = 'Utilisateur créé avec succès.';
            } catch (PDOException $e) {
                $message = str_contains($e->getMessage(), 'Duplicate entry')
                    ? 'Erreur : cet email existe déjà.'
                    : 'Erreur lors de la création : ' . $e->getMessage();
            }
        }
    }

    // Suppression d'un utilisateur
    if ($action === 'supprimer') {
        $id = (int) $_POST['id'];
        if ($id === (int) $admin['id']) {
            $message = 'Vous ne pouvez pas supprimer votre propre compte.';
        } else {
            $stmt = $pdo->prepare('DELETE FROM utilisateurs WHERE id = ?');
            $stmt->execute([$id]);
            $message = 'Utilisateur supprimé.';
        }
    }

    // Activer / désactiver un compte
    if ($action === 'basculer_actif') {
        $id = (int) $_POST['id'];
        if ($id === (int) $admin['id']) {
            $message = 'Vous ne pouvez pas désactiver votre propre compte.';
        } else {
            $stmt = $pdo->prepare('UPDATE utilisateurs SET actif = NOT actif WHERE id = ?');
            $stmt->execute([$id]);
            $message = 'Statut du compte mis à jour.';
        }
    }

    // Modifier le rôle
    if ($action === 'modifier_role') {
        $id = (int) $_POST['id'];
        $role = $_POST['role'] ?? 'collaborateur';
        if ($id === (int) $admin['id'] && $role !== 'admin') {
            $message = 'Vous ne pouvez pas retirer votre propre rôle admin.';
        } else {
            $stmt = $pdo->prepare('UPDATE utilisateurs SET role = ? WHERE id = ?');
            $stmt->execute([$role, $id]);
            $message = 'Rôle mis à jour.';
        }
    }

    // Activer/désactiver la permission de gestion des réunions
    if ($action === 'basculer_permission_reunions') {
        $id = (int) $_POST['id'];
        $stmt = $pdo->prepare('UPDATE utilisateurs SET peut_gerer_reunions = NOT peut_gerer_reunions WHERE id = ?');
        $stmt->execute([$id]);
        $message = 'Permission mise à jour.';
    }

    // Réinitialiser le mot de passe
    if ($action === 'reinitialiser_mdp') {
        $id = (int) $_POST['id'];
        $nouveauMdp = $_POST['nouveau_mot_de_passe'] ?? '';
        if (mb_strlen($nouveauMdp) < 8) {
            $message = 'Le nouveau mot de passe doit contenir au moins 8 caractères.';
        } else {
            $stmt = $pdo->prepare('UPDATE utilisateurs SET mot_de_passe = ? WHERE id = ?');
            $stmt->execute([password_hash($nouveauMdp, PASSWORD_DEFAULT), $id]);
            $message = 'Mot de passe réinitialisé.';
        }
    }
}

$utilisateurs = $pdo->query('SELECT u.*, m.nom AS manager_nom FROM utilisateurs u LEFT JOIN utilisateurs m ON m.id = u.manager_id ORDER BY u.role, u.nom')->fetchAll();
$managers = $pdo->query("SELECT id, nom FROM utilisateurs WHERE role = 'manager'")->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Utilisateurs - Reporting IT</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<?php require __DIR__ . '/../includes/navbar.php'; ?>
<div class="container">
    <h3>Gestion des utilisateurs</h3>

    <?php if ($message): ?>
        <div class="alert alert-info"><?= e($message) ?></div>
    <?php endif; ?>

    <div class="card mb-4">
        <div class="card-header">Ajouter un utilisateur</div>
        <div class="card-body">
            <form method="post" class="row g-3">
                <input type="hidden" name="action" value="creer">
                <div class="col-md-4">
                    <label class="form-label">Nom</label>
                    <input type="text" name="nom" class="form-control" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-control" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Mot de passe</label>
                    <input type="password" name="mot_de_passe" class="form-control" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Rôle</label>
                    <select name="role" class="form-select">
                        <option value="collaborateur">Collaborateur</option>
                        <option value="manager">Manager</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Équipe</label>
                    <input type="text" name="equipe" class="form-control">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Manager rattaché</label>
                    <select name="manager_id" class="form-select">
                        <option value="">Aucun</option>
                        <?php foreach ($managers as $m): ?>
                            <option value="<?= (int)$m['id'] ?>"><?= e($m['nom']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <div class="form-check">
                        <input type="checkbox" name="peut_gerer_reunions" value="1" class="form-check-input" id="permReunionCreation">
                        <label class="form-check-label" for="permReunionCreation">Peut gérer les réunions</label>
                    </div>
                </div>
                <div class="col-md-12">
                    <button class="btn btn-primary">Créer</button>
                </div>
            </form>
        </div>
    </div>

    <table class="table table-bordered bg-white align-middle">
        <thead class="table-light">
            <tr>
                <th></th>
                <th>Nom</th>
                <th>Email</th>
                <th>Rôle</th>
                <th>Équipe</th>
                <th>Manager</th>
                <th>Statut</th>
                <th>Réunions</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($utilisateurs as $ut): ?>
            <tr>
                <td><img src="<?= e(urlPhotoProfil($ut['photo_profil'], $ut['nom'])) ?>" style="height:32px;width:32px;object-fit:cover;border-radius:50%;" alt=""></td>
                <td><?= e($ut['nom']) ?></td>
                <td><?= e($ut['email']) ?></td>
                <td>
                    <form method="post" class="d-flex gap-1">
                        <input type="hidden" name="action" value="modifier_role">
                        <input type="hidden" name="id" value="<?= (int)$ut['id'] ?>">
                        <select name="role" class="form-select form-select-sm">
                            <option value="collaborateur" <?= $ut['role'] === 'collaborateur' ? 'selected' : '' ?>>Collaborateur</option>
                            <option value="manager" <?= $ut['role'] === 'manager' ? 'selected' : '' ?>>Manager</option>
                            <option value="admin" <?= $ut['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                        </select>
                        <button class="btn btn-sm btn-outline-secondary">✓</button>
                    </form>
                </td>
                <td><?= e($ut['equipe'] ?? '-') ?></td>
                <td><?= e($ut['manager_nom'] ?? '-') ?></td>
                <td>
                    <?php if ((int)$ut['actif'] === 1): ?>
                        <span class="badge bg-success">Actif</span>
                    <?php else: ?>
                        <span class="badge bg-secondary">Désactivé</span>
                    <?php endif; ?>
                </td>
                <td>
                    <form method="post">
                        <input type="hidden" name="action" value="basculer_permission_reunions">
                        <input type="hidden" name="id" value="<?= (int)$ut['id'] ?>">
                        <button class="btn btn-sm <?= $ut['peut_gerer_reunions'] || $ut['role'] === 'admin' ? 'btn-success' : 'btn-outline-secondary' ?>" <?= $ut['role'] === 'admin' ? 'disabled title="Les admins gèrent toujours les réunions"' : '' ?>>
                            <?= ($ut['peut_gerer_reunions'] || $ut['role'] === 'admin') ? 'Autorisé' : 'Non autorisé' ?>
                        </button>
                    </form>
                </td>
                <td>
                    <div class="d-flex flex-column gap-1" style="min-width: 220px;">
                        <form method="post" onsubmit="return confirm('<?= $ut['actif'] ? 'Désactiver' : 'Réactiver' ?> ce compte ?');">
                            <input type="hidden" name="action" value="basculer_actif">
                            <input type="hidden" name="id" value="<?= (int)$ut['id'] ?>">
                            <button class="btn btn-sm btn-outline-warning w-100" <?= (int)$ut['id'] === (int)$admin['id'] ? 'disabled' : '' ?>>
                                <?= (int)$ut['actif'] === 1 ? 'Désactiver' : 'Réactiver' ?>
                            </button>
                        </form>
                        <form method="post" class="d-flex gap-1">
                            <input type="hidden" name="action" value="reinitialiser_mdp">
                            <input type="hidden" name="id" value="<?= (int)$ut['id'] ?>">
                            <input type="password" name="nouveau_mot_de_passe" class="form-control form-control-sm" placeholder="Nouveau mot de passe" minlength="8">
                            <button class="btn btn-sm btn-outline-primary">Réinit.</button>
                        </form>
                        <form method="post" onsubmit="return confirm('Supprimer définitivement cet utilisateur ?');">
                            <input type="hidden" name="action" value="supprimer">
                            <input type="hidden" name="id" value="<?= (int)$ut['id'] ?>">
                            <button class="btn btn-sm btn-outline-danger w-100" <?= (int)$ut['id'] === (int)$admin['id'] ? 'disabled' : '' ?>>Supprimer</button>
                        </form>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
</body>
</html>
