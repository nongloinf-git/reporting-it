<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/journal.php';

if (isLoggedIn() && currentUser() !== null) {
    header('Location: dashboard.php');
    exit;
}

$erreur = '';
if (isset($_GET['desactive'])) {
    $erreur = 'Votre session a été fermée : votre compte est peut-être désactivé. Contactez votre administrateur si besoin.';
} elseif (isset($_GET['inactivite'])) {
    $erreur = 'Vous avez été déconnecté après 5 minutes d\'inactivité. Merci de vous reconnecter.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $motDePasse = $_POST['mot_de_passe'] ?? '';

    $stmt = getPDO()->prepare('SELECT * FROM utilisateurs WHERE email = ?');
    $stmt->execute([$email]);
    $utilisateur = $stmt->fetch();

    if ($utilisateur && password_verify($motDePasse, $utilisateur['mot_de_passe'])) {
        if ((int) $utilisateur['actif'] !== 1) {
            $erreur = 'Ce compte a été désactivé. Contactez votre administrateur.';
            journaliser((int) $utilisateur['id'], 'connexion_echouee', 'Compte désactivé', $email);
        } else {
            session_regenerate_id(true);
            $_SESSION['user_id'] = $utilisateur['id'];
            $_SESSION['derniere_activite'] = time();
            journaliser((int) $utilisateur['id'], 'connexion_reussie');
            header('Location: dashboard.php');
            exit;
        }
    } else {
        $erreur = 'Email ou mot de passe incorrect.';
        journaliser($utilisateur['id'] ?? null, 'connexion_echouee', 'Mot de passe incorrect ou email inconnu', $email);
    }
}
$titrePage = 'Connexion';
require __DIR__ . '/../includes/header.php';
?>
<div class="container">
    <div class="row justify-content-center mt-5">
        <div class="col-md-4">
            <div class="card shadow-sm">
                <div class="card-body p-4">
                    <h4 class="mb-3 text-center">Reporting IT</h4>
                    <?php if ($erreur): ?>
                        <div class="alert alert-danger"><?= e($erreur) ?></div>
                    <?php endif; ?>
                    <form method="post">
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control" required autofocus>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Mot de passe</label>
                            <input type="password" name="mot_de_passe" class="form-control" required>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Se connecter</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
