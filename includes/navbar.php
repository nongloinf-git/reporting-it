<?php
$u = $utilisateurConnecte ?? currentUser();
$logo = getParametre('logo_societe');
$nomSociete = getParametre('nom_societe');
$estGestionnaire = in_array($u['role'], ['manager', 'admin'], true);
?>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center gap-2" href="dashboard.php">
            <?php if ($logo): ?>
                <img src="uploads/logo/<?= e($logo) ?>" alt="Logo" style="height:32px; width:auto;">
            <?php endif; ?>
            <span><?= $nomSociete ? e($nomSociete) . ' — ' : '' ?>Reporting IT</span>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navPrincipale">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navPrincipale">
            <div class="navbar-nav me-auto">
                <a class="nav-link" href="dashboard.php">Tableau de bord</a>
                <?php if ($u['role'] === 'collaborateur'): ?>
                    <a class="nav-link" href="rapport_form.php">Mon rapport de la semaine</a>
                <?php endif; ?>
                <?php if ($estGestionnaire): ?>
                    <a class="nav-link" href="rapports_manager.php">Rapports de l'équipe</a>
                    <a class="nav-link" href="rapports_historique.php">Historique</a>
                <?php endif; ?>
                <a class="nav-link" href="reunions.php">Réunions</a>
                <a class="nav-link" href="taches.php">Tâches</a>

                <?php if ($estGestionnaire): ?>
                    <div class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">Options</a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="notifications.php">Rappels email</a></li>
                            <?php if ($u['role'] === 'admin'): ?>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="admin_users.php">Utilisateurs</a></li>
                                <li><a class="dropdown-item" href="admin_parametres.php">Paramètres</a></li>
                                <li><a class="dropdown-item" href="admin_journal.php">Journal d'activité</a></li>
                            <?php endif; ?>
                        </ul>
                    </div>
                <?php endif; ?>
            </div>
            <a href="profil.php" class="d-flex align-items-center gap-2 text-white text-decoration-none me-3">
                <img src="<?= e(urlPhotoProfil($u['photo_profil'], $u['nom'])) ?>" alt="" style="height:28px; width:28px; object-fit:cover; border-radius:50%;">
                <span><?= e($u['nom']) ?> (<?= e($u['role']) ?>)</span>
            </a>
            <a class="btn btn-outline-light btn-sm" href="logout.php">Déconnexion</a>
        </div>
    </div>
</nav>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Déconnexion automatique après 5 minutes sans aucune action de l'utilisateur
    // (souris, clavier, clic, défilement), en plus du contrôle fait côté serveur
    // à chaque requête (défense en profondeur : fonctionne même si l'onglet reste
    // ouvert sans qu'aucune page ne soit rechargée).
    (function () {
        const DUREE_INACTIVITE_MS = 5 * 60 * 1000;
        let minuteur = null;

        function reinitialiserMinuteur() {
            if (minuteur) clearTimeout(minuteur);
            minuteur = setTimeout(function () {
                window.location.href = 'logout.php';
            }, DUREE_INACTIVITE_MS);
        }

        ['mousemove', 'keydown', 'click', 'scroll', 'touchstart'].forEach(function (evenement) {
            document.addEventListener(evenement, reinitialiserMinuteur, { passive: true });
        });

        reinitialiserMinuteur();
    })();
</script>
