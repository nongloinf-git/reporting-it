<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/validation.php';
require_once __DIR__ . '/../includes/journal.php';
require_once __DIR__ . '/../includes/rapport_document.php';
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
    $statut = $_POST['action'] === 'soumettre' ? 'soumis' : 'brouillon';

    $stmtExistant = $pdo->prepare('SELECT * FROM rapports WHERE utilisateur_id = ? AND annee = ? AND semaine_numero = ?');
    $stmtExistant->execute([$u['id'], $annee, $semaine]);
    $existant = $stmtExistant->fetch();

    if ($existant && $existant['statut'] === 'valide') {
        $erreur = 'Ce rapport a déjà été validé par votre manager et ne peut plus être modifié.';
    }

    $site = limiterLongueur($_POST['site'] ?? '', 150);
    $titresActivites = $_POST['activite_titre'] ?? [];
    $detailsActivites = $_POST['activite_details'] ?? [];
    $activites = [];
    foreach ($titresActivites as $index => $titre) {
        $titre = limiterLongueur($titre, 200);
        $details = limiterLongueur($detailsActivites[$index] ?? '', 3000);
        if ($titre !== '' || $details !== '') {
            $activites[] = ['titre' => $titre, 'details' => $details];
        }
    }
    $difficultes = limiterLongueur($_POST['difficultes'] ?? '', 3000);
    $actionsPrevues = limiterLongueur($_POST['actions_prevues'] ?? '', 3000);
    $conclusion = limiterLongueur($_POST['conclusion'] ?? '', 3000);

    $donneesStructurees = [
        'site' => $site,
        'activites' => $activites,
        'difficultes' => $difficultes,
        'actions_prevues' => $actionsPrevues,
        'conclusion' => $conclusion,
    ];

    $tempsPasseBrut = trim($_POST['temps_passe'] ?? '');
    $erreurTemps = erreurNombreDansPlage($tempsPasseBrut, 0, 168, 'Le temps passé');
    if (!$erreur && $erreurTemps !== null) {
        $erreur = $erreurTemps;
    }
    $tempsPasse = $tempsPasseBrut !== '' ? (float) $tempsPasseBrut : null;

    $nomFichierWord = $existant['fichier_word'] ?? null;

    if (!$erreur && !empty($_FILES['fichier_word']['name'])) {
        $fichier = $_FILES['fichier_word'];
        $extension = strtolower(pathinfo($fichier['name'], PATHINFO_EXTENSION));

        if ($fichier['error'] !== UPLOAD_ERR_OK) {
            $erreur = "Erreur lors de l'envoi du fichier.";
        } elseif ($extension !== 'docx') {
            $erreur = 'Seuls les fichiers .docx sont acceptés.';
        } elseif ($fichier['size'] > 10 * 1024 * 1024) {
            $erreur = 'Le fichier dépasse la taille maximale autorisée (10 Mo).';
        } else {
            $zipValide = false;
            if (class_exists('ZipArchive')) {
                $zip = new ZipArchive();
                if ($zip->open($fichier['tmp_name']) === true) {
                    $zipValide = true;
                    $zip->close();
                }
            }
            if (!$zipValide) {
                $erreur = "Le fichier envoyé n'est pas un document Word (.docx) valide.";
            } else {
                $nouveauNom = 'rapport_' . $u['id'] . '_' . $annee . '_S' . $semaine . '_' . time() . '.docx';
                if (move_uploaded_file($fichier['tmp_name'], DOSSIER_UPLOADS . '/' . $nouveauNom)) {
                    if ($nomFichierWord && file_exists(DOSSIER_UPLOADS . '/' . $nomFichierWord)) {
                        unlink(DOSSIER_UPLOADS . '/' . $nomFichierWord);
                    }
                    $nomFichierWord = $nouveauNom;
                } else {
                    $erreur = "Impossible d'enregistrer le fichier envoyé.";
                }
            }
        }
    }

    if (!$erreur) {
        $auMoinsUneActivite = !empty($activites);
        $texteLibreRenseigne = $difficultes !== '' || $actionsPrevues !== '' || $conclusion !== '';
        if (!$auMoinsUneActivite && !$texteLibreRenseigne && !$nomFichierWord) {
            $erreur = "Veuillez renseigner au moins une activité (ou une autre section), ou joindre un fichier Word (.docx).";
        } else {
            $contenuResume = resumerDonneesRapportEnTexte($donneesStructurees);
            $jsonStructure = json_encode($donneesStructurees, JSON_UNESCAPED_UNICODE);

            if ($statut === 'soumis') {
                $stmt = $pdo->prepare(
                    'INSERT INTO rapports (utilisateur_id, annee, semaine_numero, contenu, donnees_structurees, fichier_word, temps_passe, statut, date_envoi)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
                     ON DUPLICATE KEY UPDATE contenu = VALUES(contenu), donnees_structurees = VALUES(donnees_structurees), fichier_word = VALUES(fichier_word), temps_passe = VALUES(temps_passe), statut = VALUES(statut), date_envoi = NOW()'
                );
            } else {
                $stmt = $pdo->prepare(
                    'INSERT INTO rapports (utilisateur_id, annee, semaine_numero, contenu, donnees_structurees, fichier_word, temps_passe, statut)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                     ON DUPLICATE KEY UPDATE contenu = VALUES(contenu), donnees_structurees = VALUES(donnees_structurees), fichier_word = VALUES(fichier_word), temps_passe = VALUES(temps_passe), statut = VALUES(statut)'
                );
            }
            $stmt->execute([$u['id'], $annee, $semaine, $contenuResume ?: null, $jsonStructure, $nomFichierWord, $tempsPasse, $statut]);
            journaliser((int) $u['id'], $statut === 'soumis' ? 'soumission_rapport' : 'enregistrement_rapport', "Semaine $semaine/$annee");
            $message = $statut === 'soumis' ? 'Rapport soumis avec succès.' : 'Brouillon enregistré.';
        }
    }
}

$stmt = $pdo->prepare('SELECT * FROM rapports WHERE utilisateur_id = ? AND annee = ? AND semaine_numero = ?');
$stmt->execute([$u['id'], $annee, $semaine]);
$rapport = $stmt->fetch();
$verrouille = ($rapport['statut'] ?? '') === 'valide';

$donnees = decoderDonneesRapport($rapport['donnees_structurees'] ?? null);
$bornes = bornesSemaineIso($annee, $semaine);

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

    <form method="post" enctype="multipart/form-data" id="formRapport">
        <?= champCsrf() ?>
        <input type="hidden" name="annee" value="<?= (int)$annee ?>">
        <input type="hidden" name="semaine" value="<?= (int)$semaine ?>">

        <h5 class="mt-4">1. En-tête du rapport</h5>
        <div class="row g-3 mb-3">
            <div class="col-md-6">
                <label class="form-label">Date de début</label>
                <input type="text" id="dateDebutAffiche" class="form-control" value="<?= e($bornes['debut']->format('d/m/Y')) ?>" disabled>
            </div>
            <div class="col-md-6">
                <label class="form-label">Date de fin</label>
                <input type="text" id="dateFinAffiche" class="form-control" value="<?= e($bornes['fin']->format('d/m/Y')) ?>" disabled>
            </div>
        </div>
        <div class="row g-3 mb-3">
            <div class="col-md-4">
                <label class="form-label">Nom complet</label>
                <input type="text" id="champNom" class="form-control" value="<?= e($u['nom']) ?>" disabled>
                <div class="form-text">Renseigné automatiquement depuis votre compte.</div>
            </div>
            <div class="col-md-4">
                <label class="form-label">Fonction</label>
                <input type="text" id="champFonction" class="form-control" value="<?= e($u['fonction'] ?? '') ?>" placeholder="Non renseignée" disabled>
                <div class="form-text">
                    <?= $u['fonction'] ? '' : 'Non renseignée — demandez à votre administrateur de la compléter sur votre fiche.' ?>
                </div>
            </div>
            <div class="col-md-4">
                <label class="form-label">Site de rattachement</label>
                <input type="text" name="site" id="champSite" class="form-control" value="<?= e($donnees['site']) ?>" placeholder="Ex : Direction Générale - Douala" <?= $verrouille ? 'disabled' : '' ?>>
            </div>
        </div>

        <h5 class="mt-4">2. Activités réalisées</h5>
        <div id="activitesContainer">
            <?php foreach ($donnees['activites'] as $i => $a): ?>
                <div class="card mb-2 activity-block">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <label class="form-label mb-0 fw-bold">Titre de l'activité</label>
                            <?php if (!$verrouille): ?>
                                <button type="button" class="btn btn-sm btn-outline-danger btn-supprimer-activite">Supprimer</button>
                            <?php endif; ?>
                        </div>
                        <input type="text" name="activite_titre[]" class="form-control mb-2" value="<?= e($a['titre'] ?? '') ?>" placeholder="Ex : Monitoring des sauvegardes" <?= $verrouille ? 'disabled' : '' ?>>
                        <label class="form-label small text-muted">Points clés / Détails de l'activité</label>
                        <textarea name="activite_details[]" class="form-control" rows="3" placeholder="Point 1, Point 2..." <?= $verrouille ? 'disabled' : '' ?>><?= e($a['details'] ?? '') ?></textarea>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php if (!$verrouille): ?>
            <button type="button" id="btnAjouterActivite" class="btn btn-success btn-sm mb-4">+ Ajouter une activité</button>
        <?php endif; ?>

        <h5 class="mt-4">3. Difficultés rencontrées</h5>
        <div class="mb-3">
            <textarea name="difficultes" class="form-control" rows="4" placeholder="Décrivez les difficultés rencontrées cette semaine..." <?= $verrouille ? 'disabled' : '' ?>><?= e($donnees['difficultes']) ?></textarea>
        </div>

        <h5 class="mt-4">4. Actions prévues pour la semaine suivante</h5>
        <div class="mb-3">
            <textarea name="actions_prevues" class="form-control" rows="4" placeholder="Décrivez les actions planifiées pour la semaine prochaine..." <?= $verrouille ? 'disabled' : '' ?>><?= e($donnees['actions_prevues']) ?></textarea>
        </div>

        <h5 class="mt-4">5. Conclusion</h5>
        <div class="mb-3">
            <textarea name="conclusion" class="form-control" rows="3" placeholder="Synthèse globale de la semaine..." <?= $verrouille ? 'disabled' : '' ?>><?= e($donnees['conclusion']) ?></textarea>
        </div>

        <div class="row g-3 mb-3">
            <div class="col-md-3">
                <label class="form-label">Temps passé (heures)</label>
                <input type="number" step="0.5" name="temps_passe" class="form-control" value="<?= e((string)($rapport['temps_passe'] ?? '')) ?>" <?= $verrouille ? 'disabled' : '' ?>>
            </div>
        </div>

        <div class="mb-3">
            <label class="form-label">Ou joindre directement un fichier Word (.docx) déjà rédigé</label>
            <input type="file" name="fichier_word" accept=".docx" class="form-control" <?= $verrouille ? 'disabled' : '' ?>>
            <?php if (!empty($rapport['fichier_word'])): ?>
                <div class="form-text">
                    Fichier actuellement joint : <strong><?= e($rapport['fichier_word']) ?></strong>
                    — <a href="uploads/rapports_word/<?= e($rapport['fichier_word']) ?>" target="_blank">télécharger</a>
                    <?php if (!$verrouille): ?>(l'envoi d'un nouveau fichier remplacera celui-ci)<?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

        <?php if (!$verrouille): ?>
            <button type="submit" name="action" value="brouillon" class="btn btn-outline-secondary">Enregistrer en brouillon</button>
            <button type="submit" name="action" value="soumettre" class="btn btn-primary">Soumettre au manager</button>
        <?php endif; ?>
        <button type="button" id="btnGenererWord" class="btn btn-outline-primary">Télécharger en Word</button>
        <button type="button" id="btnGenererPdf" class="btn btn-outline-danger">Télécharger en PDF</button>
    </form>
</div>

<script>
    document.addEventListener('click', function (evenement) {
        if (evenement.target && evenement.target.id === 'btnAjouterActivite') {
            var conteneur = document.getElementById('activitesContainer');
            var bloc = document.createElement('div');
            bloc.className = 'card mb-2 activity-block';
            bloc.innerHTML = '<div class="card-body">'
                + '<div class="d-flex justify-content-between align-items-center mb-2">'
                + '<label class="form-label mb-0 fw-bold">Titre de l\'activité</label>'
                + '<button type="button" class="btn btn-sm btn-outline-danger btn-supprimer-activite">Supprimer</button>'
                + '</div>'
                + '<input type="text" name="activite_titre[]" class="form-control mb-2" placeholder="Ex : Configuration de serveurs">'
                + '<label class="form-label small text-muted">Points clés / Détails de l\'activité</label>'
                + '<textarea name="activite_details[]" class="form-control" rows="3" placeholder="Point 1, Point 2..."></textarea>'
                + '</div>';
            conteneur.appendChild(bloc);
        }
        if (evenement.target && evenement.target.classList.contains('btn-supprimer-activite')) {
            evenement.target.closest('.activity-block').remove();
        }
    });

    var logoBase64 = <?= json_encode(logoSocieteEnBase64()) ?>;
    var styleDocument = <?= json_encode(styleRapportDocument()) ?>;

    function echapperHtml(texte) {
        var div = document.createElement('div');
        div.textContent = texte;
        return div.innerHTML;
    }

    function collecterDonneesFormulaire() {
        var titres = Array.prototype.map.call(document.querySelectorAll('input[name="activite_titre[]"]'), function (i) { return i.value.trim(); });
        var details = Array.prototype.map.call(document.querySelectorAll('textarea[name="activite_details[]"]'), function (i) { return i.value.trim(); });
        var activitesHtml = '';
        var auMoinsUne = false;
        titres.forEach(function (titre, index) {
            var detail = details[index] || '';
            if (titre || detail) {
                auMoinsUne = true;
                activitesHtml += '<p class="titre-activite">- ' + echapperHtml(titre || 'Activité sans titre') + '</p>';
                if (detail) {
                    activitesHtml += '<p class="details-activite">' + echapperHtml(detail).replace(/\n/g, '<br>') + '</p>';
                }
            }
        });
        if (!auMoinsUne) activitesHtml = '<p>Aucune activité renseignée.</p>';

        return {
            nom: document.getElementById('champNom').value || '_______________________',
            fonction: document.getElementById('champFonction').value || 'Non renseignée',
            site: document.getElementById('champSite').value || 'Non renseigné',
            dateDebut: document.getElementById('dateDebutAffiche').value,
            dateFin: document.getElementById('dateFinAffiche').value,
            activitesHtml: activitesHtml,
            difficultes: (document.querySelector('textarea[name="difficultes"]').value.trim() || 'Aucune difficulté majeure signalée.'),
            actions: (document.querySelector('textarea[name="actions_prevues"]').value.trim() || 'Aucune action planifiée.'),
            conclusion: (document.querySelector('textarea[name="conclusion"]').value.trim() || 'Semaine globalement satisfaisante.')
        };
    }

    function construireDocumentHtml(d) {
        var logoHtml = logoBase64 ? ('<div class="logo-conteneur"><img src="' + logoBase64 + '" alt="Logo"></div>') : '';
        return '<html><head><meta charset="utf-8"><title>Rapport hebdomadaire</title><style>' + styleDocument + '</style></head><body>'
            + logoHtml
            + '<h1>RAPPORT HEBDOMADAIRE D\'ACTIVITÉS</h1>'
            + '<table class="entete">'
            + '<tr><th>Période du rapport</th><td>Du <strong>' + d.dateDebut + '</strong> au <strong>' + d.dateFin + '</strong></td></tr>'
            + '<tr><th>Collaborateur &amp; fonction</th><td><strong>' + echapperHtml(d.nom) + '</strong> - ' + echapperHtml(d.fonction) + '</td></tr>'
            + '<tr><th>Site de rattachement</th><td>' + echapperHtml(d.site) + '</td></tr>'
            + '</table>'
            + '<h2>1. Activités réalisées</h2><div>' + d.activitesHtml + '</div>'
            + '<h2>2. Difficultés rencontrées</h2><div class="section-contenu"><p>' + echapperHtml(d.difficultes).replace(/\n/g, '<br>') + '</p></div>'
            + '<h2>3. Actions prévues pour la semaine suivante</h2><div class="section-contenu"><p>' + echapperHtml(d.actions).replace(/\n/g, '<br>') + '</p></div>'
            + '<h2>4. Conclusion</h2><div class="section-contenu"><p>' + echapperHtml(d.conclusion).replace(/\n/g, '<br>') + '</p></div>'
            + '<br><p style="text-align:right;"><strong>Fait le :</strong> ' + new Date().toLocaleDateString('fr-FR') + '</p>'
            + '</body></html>';
    }

    document.getElementById('btnGenererWord').addEventListener('click', function () {
        var html = construireDocumentHtml(collecterDonneesFormulaire());
        var blob = new Blob(['\ufeff', html], { type: 'application/msword' });
        var url = URL.createObjectURL(blob);
        var lien = document.createElement('a');
        lien.href = url;
        lien.download = 'Rapport_Hebdomadaire_S<?= (int)$semaine ?>_<?= (int)$annee ?>.doc';
        document.body.appendChild(lien);
        lien.click();
        document.body.removeChild(lien);
        URL.revokeObjectURL(url);
    });

    document.getElementById('btnGenererPdf').addEventListener('click', function () {
        var html = construireDocumentHtml(collecterDonneesFormulaire());
        var fenetre = window.open('', '_blank');
        fenetre.document.write(html);
        fenetre.document.close();
        setTimeout(function () { fenetre.print(); }, 300);
    });
</script>
</body>
</html>
