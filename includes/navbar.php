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
                            <li><a class="dropdown-item" href="statistiques.php">📊 Statistiques</a></li>
                            <li><a class="dropdown-item" href="notifications.php">Rappels email</a></li>
                            <?php if ($u['role'] === 'admin'): ?>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="admin_users.php">Utilisateurs</a></li>
                                <li><a class="dropdown-item" href="admin_parametres.php">Paramètres</a></li>
                                <li><a class="dropdown-item" href="admin_journal.php">Historique des modifications</a></li>
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
<script>
    // Tri des tableaux au clic sur l'en-tête de colonne. Activé sur toute
    // <table class="table-triable">. Détecte automatiquement si la colonne
    // contient du texte, un nombre (avec unité éventuelle, ex "35 h") ou une
    // date au format JJ/MM/AAAA (avec heure optionnelle "à HH:mm") — sauf si
    // l'en-tête précise data-type="texte|nombre|date_fr". Les en-têtes avec
    // data-no-tri ne sont pas rendus triables (colonnes d'actions/boutons).
    (function () {
        function detecterType(texte) {
            texte = texte.trim();
            if (texte === '' || texte === '-') return 'texte';
            if (/^\d{1,2}\/\d{1,2}\/\d{4}(\s*\D{0,3}\s*\d{1,2}:\d{2})?$/.test(texte)) return 'date_fr';
            if (/^-?[\d\s]+([.,]\d+)?\s*(h|€|%)?$/.test(texte)) return 'nombre';
            return 'texte';
        }

        function valeurComparaison(cellule, type) {
            const texte = cellule.getAttribute('data-tri') !== null
                ? cellule.getAttribute('data-tri')
                : cellule.textContent.trim();

            if (type === 'date_fr') {
                const m = texte.match(/(\d{1,2})\/(\d{1,2})\/(\d{4})(?:\D{0,3}(\d{1,2}):(\d{2}))?/);
                if (!m) return -Infinity;
                return new Date(+m[3], +m[2] - 1, +m[1], +(m[4] || 0), +(m[5] || 0)).getTime();
            }
            if (type === 'nombre') {
                const nettoye = texte.replace(/[^\d,.-]/g, '').replace(',', '.');
                const n = parseFloat(nettoye);
                return isNaN(n) ? -Infinity : n;
            }
            return texte.toLowerCase();
        }

        function initTableau(table) {
            const thead = table.querySelector('thead');
            const tbody = table.querySelector('tbody');
            if (!thead || !tbody) return;
            const ths = Array.from(thead.querySelectorAll('th'));

            ths.forEach(function (th, index) {
                if (th.hasAttribute('data-no-tri')) return;
                th.style.cursor = 'pointer';
                th.style.userSelect = 'none';

                const indicateur = document.createElement('span');
                indicateur.className = 'ms-1 text-muted';
                indicateur.style.fontSize = '0.7em';
                indicateur.textContent = '⇅';
                th.appendChild(indicateur);

                th.addEventListener('click', function () {
                    const lignes = Array.from(tbody.querySelectorAll(':scope > tr'))
                        .filter(function (tr) { return tr.children.length > index; });
                    if (lignes.length < 2) return;

                    let type = th.getAttribute('data-type');
                    if (!type) {
                        const premiereCellule = lignes[0].children[index];
                        type = detecterType(premiereCellule.getAttribute('data-tri') ?? premiereCellule.textContent);
                    }

                    const nouvelOrdre = th.getAttribute('data-ordre') === 'asc' ? 'desc' : 'asc';
                    ths.forEach(function (autreTh) {
                        autreTh.removeAttribute('data-ordre');
                        const span = autreTh.querySelector('span');
                        if (span) span.textContent = '⇅';
                    });
                    th.setAttribute('data-ordre', nouvelOrdre);
                    indicateur.textContent = nouvelOrdre === 'asc' ? '↑' : '↓';

                    lignes.sort(function (a, b) {
                        const va = valeurComparaison(a.children[index], type);
                        const vb = valeurComparaison(b.children[index], type);
                        let cmp = (typeof va === 'number' && typeof vb === 'number')
                            ? va - vb
                            : String(va).localeCompare(String(vb), 'fr');
                        return nouvelOrdre === 'asc' ? cmp : -cmp;
                    });

                    lignes.forEach(function (tr) { tbody.appendChild(tr); });
                });
            });
        }

        document.querySelectorAll('table.table-triable').forEach(initTableau);
    })();
</script>
