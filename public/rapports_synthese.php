<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/rapport_document.php';
requireRole(['manager', 'admin']);

$u = currentUser();
$pdo = getPDO();

$sem = semaineCourante();
$annee = isset($_GET['annee']) ? (int) $_GET['annee'] : $sem['annee'];
$semaine = isset($_GET['semaine']) ? max(1, min(53, (int) $_GET['semaine'])) : $sem['semaine'];

// Rapports VALIDÉS de la semaine choisie, scopés à l'équipe (comme rapports_manager.php)
if ($u['role'] === 'manager') {
    $stmt = $pdo->prepare(
        "SELECT r.*, ut.nom, ut.fonction, ut.equipe FROM rapports r
         JOIN utilisateurs ut ON ut.id = r.utilisateur_id
         WHERE ut.manager_id = ? AND r.annee = ? AND r.semaine_numero = ? AND r.statut = 'valide'
         ORDER BY ut.nom"
    );
    $stmt->execute([$u['id'], $annee, $semaine]);
} else {
    $stmt = $pdo->prepare(
        "SELECT r.*, ut.nom, ut.fonction, ut.equipe FROM rapports r
         JOIN utilisateurs ut ON ut.id = r.utilisateur_id
         WHERE r.annee = ? AND r.semaine_numero = ? AND r.statut = 'valide'
         ORDER BY ut.nom"
    );
    $stmt->execute([$annee, $semaine]);
}
$rapportsValides = $stmt->fetchAll();

// Prépare les données JS (id, nom, fonction, html de section déjà construit côté serveur)
$donneesJs = [];
foreach ($rapportsValides as $r) {
    $donnees = decoderDonneesRapport($r['donnees_structurees'] ?? null);
    $donneesJs[] = [
        'id' => (int) $r['id'],
        'nom' => $r['nom'],
        'fonction' => $r['fonction'] ?: 'Non renseignée',
        'equipe' => $r['equipe'] ?: '',
        'html' => construireSectionRapportHtml(['nom' => $r['nom'], 'fonction' => $r['fonction']], $r, $donnees, false),
    ];
}

$bornes = bornesSemaineIso($annee, $semaine);

$titrePage = 'Rapport de synthèse';
require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/navbar.php';
?>
<div class="container">
    <h3>Rapport de synthèse hebdomadaire</h3>
    <p class="text-muted">Compile les rapports <strong>validés</strong> d'une semaine en un seul document. Cochez les rapports à inclure.</p>

    <form method="get" class="row g-2 mb-4">
        <div class="col-auto">
            <label class="form-label">Année</label>
            <input type="number" name="annee" value="<?= (int)$annee ?>" class="form-control">
        </div>
        <div class="col-auto">
            <label class="form-label">Semaine</label>
            <input type="number" name="semaine" min="1" max="53" value="<?= (int)$semaine ?>" class="form-control">
        </div>
        <div class="col-auto align-self-end">
            <button class="btn btn-secondary">Changer de semaine</button>
        </div>
    </form>

    <p class="text-muted small">Semaine ISO <?= (int)$semaine ?> - <?= (int)$annee ?> (du <?= e($bornes['debut']->format('d/m/Y')) ?> au <?= e($bornes['fin']->format('d/m/Y')) ?>)</p>

    <?php if (!$rapportsValides): ?>
        <p class="text-muted">Aucun rapport validé pour cette semaine.</p>
    <?php else: ?>
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>Rapports validés disponibles</span>
                <span class="badge bg-primary" id="compteurSelection"><?= count($rapportsValides) ?>/<?= count($rapportsValides) ?> sélectionné(s)</span>
            </div>
            <div class="card-body">
                <div class="mb-2">
                    <button type="button" id="btnToutCocher" class="btn btn-sm btn-outline-secondary">Tout cocher</button>
                    <button type="button" id="btnToutDecocher" class="btn btn-sm btn-outline-secondary">Tout décocher</button>
                </div>
                <?php foreach ($rapportsValides as $r): ?>
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input case-rapport" id="case_<?= (int)$r['id'] ?>" data-id="<?= (int)$r['id'] ?>" checked>
                        <label class="form-check-label" for="case_<?= (int)$r['id'] ?>">
                            <?= e($r['nom']) ?><?= $r['fonction'] ? ' — ' . e($r['fonction']) : '' ?><?= $r['equipe'] ? ' (' . e($r['equipe']) . ')' : '' ?>
                        </label>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <button type="button" id="btnGenererWordSynthese" class="btn btn-primary">Télécharger la synthèse en Word</button>
        <button type="button" id="btnGenererPdfSynthese" class="btn btn-outline-danger">Télécharger la synthèse en PDF</button>
    <?php endif; ?>
</div>

<script>
    var rapportsDisponibles = <?= json_encode($donneesJs, JSON_UNESCAPED_UNICODE) ?>;
    var logoBase64 = <?= json_encode(logoSocieteEnBase64()) ?>;
    var styleDocument = <?= json_encode(styleRapportDocument()) ?>;
    var nomSociete = <?= json_encode(getParametre('nom_societe') ?: 'Reporting IT', JSON_UNESCAPED_UNICODE) ?>;

    document.querySelectorAll('.case-rapport').forEach(function (c) {
        c.addEventListener('change', mettreAJourCompteur);
    });
    var btnTout = document.getElementById('btnToutCocher');
    if (btnTout) btnTout.addEventListener('click', function () {
        document.querySelectorAll('.case-rapport').forEach(function (c) { c.checked = true; });
        mettreAJourCompteur();
    });
    var btnAucun = document.getElementById('btnToutDecocher');
    if (btnAucun) btnAucun.addEventListener('click', function () {
        document.querySelectorAll('.case-rapport').forEach(function (c) { c.checked = false; });
        mettreAJourCompteur();
    });

    function mettreAJourCompteur() {
        var total = document.querySelectorAll('.case-rapport').length;
        var coches = document.querySelectorAll('.case-rapport:checked').length;
        var compteur = document.getElementById('compteurSelection');
        if (compteur) compteur.textContent = coches + '/' + total + ' sélectionné(s)';
    }

    function idsSelectionnes() {
        return Array.prototype.map.call(document.querySelectorAll('.case-rapport:checked'), function (c) {
            return parseInt(c.getAttribute('data-id'), 10);
        });
    }

    function construireDocumentSynthese() {
        var ids = idsSelectionnes();
        var selection = rapportsDisponibles.filter(function (r) { return ids.indexOf(r.id) !== -1; });

        var logoHtml = logoBase64 ? ('<div class="logo-conteneur"><img src="' + logoBase64 + '" alt="Logo"></div>') : '';
        var corps = '<h1>RAPPORT DE SYNTHÈSE HEBDOMADAIRE</h1>'
            + '<p style="text-align:center; color:#666;">' + nomSociete + ' — Semaine <?= (int)$semaine ?> / <?= (int)$annee ?><br>'
            + 'Du <?= e($bornes['debut']->format('d/m/Y')) ?> au <?= e($bornes['fin']->format('d/m/Y')) ?><br>'
            + selection.length + ' rapport(s) sur <?= count($rapportsValides) ?> validé(s) inclus dans cette synthèse</p>';

        if (selection.length === 0) {
            corps += '<p>Aucun rapport sélectionné.</p>';
        } else {
            selection.forEach(function (r) { corps += r.html; });
        }

        corps += '<br><p style="text-align:right;"><strong>Document généré le :</strong> ' + new Date().toLocaleDateString('fr-FR') + '</p>';

        return '<html><head><meta charset="utf-8"><title>Synthèse hebdomadaire</title><style>' + styleDocument + '</style></head><body>'
            + logoHtml + corps + '</body></html>';
    }

    var btnWord = document.getElementById('btnGenererWordSynthese');
    if (btnWord) btnWord.addEventListener('click', function () {
        if (idsSelectionnes().length === 0) {
            alert('Veuillez sélectionner au moins un rapport.');
            return;
        }
        var html = construireDocumentSynthese();
        var blob = new Blob(['\ufeff', html], { type: 'application/msword' });
        var url = URL.createObjectURL(blob);
        var lien = document.createElement('a');
        lien.href = url;
        lien.download = 'Synthese_Hebdomadaire_S<?= (int)$semaine ?>_<?= (int)$annee ?>.doc';
        document.body.appendChild(lien);
        lien.click();
        document.body.removeChild(lien);
        URL.revokeObjectURL(url);
    });

    var btnPdf = document.getElementById('btnGenererPdfSynthese');
    if (btnPdf) btnPdf.addEventListener('click', function () {
        if (idsSelectionnes().length === 0) {
            alert('Veuillez sélectionner au moins un rapport.');
            return;
        }
        var html = construireDocumentSynthese();
        var fenetre = window.open('', '_blank');
        fenetre.document.write(html);
        fenetre.document.close();
        setTimeout(function () { fenetre.print(); }, 300);
    });
</script>
</body>
</html>
