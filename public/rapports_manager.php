<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/journal.php';
require_once __DIR__ . '/../includes/validation.php';
requireRole(['manager', 'admin']);

$u = currentUser();
$pdo = getPDO();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $rapportId = (int) $_POST['rapport_id'];
    $commentaire = limiterLongueur($_POST['commentaire'] ?? '', 2000);
    $action = $_POST['action'] ?? '';

    // Sécurité : un manager ne peut agir que sur les rapports de ses propres
    // collaborateurs (l'admin peut agir sur tous les rapports). Sans ce contrôle,
    // un manager pourrait valider/commenter le rapport d'une autre équipe en
    // forgeant simplement l'identifiant dans la requête (faille de type IDOR).
    $stmtProprietaire = $pdo->prepare('SELECT ut.manager_id FROM rapports r JOIN utilisateurs ut ON ut.id = r.utilisateur_id WHERE r.id = ?');
    $stmtProprietaire->execute([$rapportId]);
    $proprietaire = $stmtProprietaire->fetch();

    $autorise = $proprietaire && ($u['role'] === 'admin' || (int) $proprietaire['manager_id'] === (int) $u['id']);

    if (!$autorise) {
        http_response_code(403);
        die('Vous n\'avez pas accès à ce rapport.');
    }

    if ($action === 'valider') {
        $stmt = $pdo->prepare("UPDATE rapports SET statut = 'valide', date_validation = NOW() WHERE id = ?");
        $stmt->execute([$rapportId]);

        $stmtInfo = $pdo->prepare('SELECT ut.nom, r.annee, r.semaine_numero FROM rapports r JOIN utilisateurs ut ON ut.id = r.utilisateur_id WHERE r.id = ?');
        $stmtInfo->execute([$rapportId]);
        $info = $stmtInfo->fetch();
        if ($info) {
            journaliser((int) $u['id'], 'validation_rapport', "{$info['nom']} — semaine {$info['semaine_numero']}/{$info['annee']}");
        }
    }
    if ($action === 'renvoyer') {
        // Renvoie le rapport au collaborateur pour révision (redevient modifiable)
        $stmt = $pdo->prepare("UPDATE rapports SET statut = 'brouillon' WHERE id = ?");
        $stmt->execute([$rapportId]);

        $stmtInfo = $pdo->prepare('SELECT ut.nom, r.annee, r.semaine_numero FROM rapports r JOIN utilisateurs ut ON ut.id = r.utilisateur_id WHERE r.id = ?');
        $stmtInfo->execute([$rapportId]);
        $info = $stmtInfo->fetch();
        if ($info) {
            journaliser((int) $u['id'], 'renvoi_rapport', "{$info['nom']} — semaine {$info['semaine_numero']}/{$info['annee']}");
        }
    }
    if ($commentaire !== '') {
        $stmt = $pdo->prepare('INSERT INTO commentaires_validation (rapport_id, manager_id, commentaire) VALUES (?, ?, ?)');
        $stmt->execute([$rapportId, $u['id'], $commentaire]);
        journaliser((int) $u['id'], 'commentaire_rapport', "Rapport #$rapportId");
    }
    header('Location: rapports_manager.php#rapport-' . $rapportId);
    exit;
}

// Filtre semaine (par défaut : semaine courante)
$sem = semaineCourante();
$annee = isset($_GET['annee']) ? (int) $_GET['annee'] : $sem['annee'];
$semaine = isset($_GET['semaine']) ? (int) $_GET['semaine'] : $sem['semaine'];

if ($u['role'] === 'manager') {
    $stmt = $pdo->prepare(
        'SELECT r.*, ut.nom FROM rapports r
         JOIN utilisateurs ut ON ut.id = r.utilisateur_id
         WHERE ut.manager_id = ? AND r.annee = ? AND r.semaine_numero = ?
         ORDER BY r.statut, ut.nom'
    );
    $stmt->execute([$u['id'], $annee, $semaine]);
} else {
    $stmt = $pdo->prepare(
        "SELECT r.*, ut.nom FROM rapports r
         JOIN utilisateurs ut ON ut.id = r.utilisateur_id
         WHERE r.annee = ? AND r.semaine_numero = ?
         ORDER BY r.statut, ut.nom"
    );
    $stmt->execute([$annee, $semaine]);
}
$rapports = $stmt->fetchAll();

// Commentaires liés à ces rapports
$commentairesParRapport = [];
if ($rapports) {
    $ids = array_column($rapports, 'id');
    $in = implode(',', array_fill(0, count($ids), '?'));
    $stmtC = $pdo->prepare("SELECT c.*, m.nom AS manager_nom FROM commentaires_validation c JOIN utilisateurs m ON m.id = c.manager_id WHERE rapport_id IN ($in) ORDER BY c.date_creation");
    $stmtC->execute($ids);
    foreach ($stmtC->fetchAll() as $c) {
        $commentairesParRapport[$c['rapport_id']][] = $c;
    }
}
$titrePage = "Rapports de l'équipe";
require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/navbar.php';
?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/mammoth/1.11.0/mammoth.browser.min.js"></script>
<style>.docx-preview img { max-width: 100%; }</style>
<div class="container">
    <h3>Rapports de l'équipe</h3>

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
            <button class="btn btn-secondary">Filtrer</button>
        </div>
        <div class="col-auto align-self-end">
            <a class="btn btn-outline-success" href="export_csv.php?annee=<?= (int)$annee ?>&semaine=<?= (int)$semaine ?>">Exporter CSV</a>
        </div>
        <div class="col-auto align-self-end">
            <a class="btn btn-outline-danger" href="export_pdf.php?annee=<?= (int)$annee ?>&semaine=<?= (int)$semaine ?>" target="_blank">Exporter PDF</a>
        </div>
        <div class="col-auto align-self-end ms-auto">
            <a class="btn btn-outline-primary" href="rapports_historique.php">Historique des semaines</a>
        </div>
    </form>

    <?php if (!$rapports): ?>
        <p class="text-muted">Aucun rapport soumis pour cette semaine.</p>
    <?php endif; ?>

    <script>
        async function chargerApercuWordVisuel(cibleId, url, cibleTexteId) {
            const cible = document.getElementById(cibleId);
            const cibleTexte = document.getElementById(cibleTexteId);
            try {
                const reponse = await fetch(url);
                const buffer = await reponse.arrayBuffer();
                const resultat = await mammoth.convertToHtml({ arrayBuffer: buffer });
                cible.innerHTML = resultat.value || '<p class="text-muted small">(document vide)</p>';
                cible.classList.remove('d-none');
                if (cibleTexte) cibleTexte.classList.add('d-none');
            } catch (e) {
                // En cas d'échec (JS désactivé, réseau...), on garde l'aperçu texte déjà affiché en repli.
                console.warn('Aperçu visuel Word indisponible, repli sur le texte extrait.', e);
            }
        }
        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('[data-docx-url]').forEach(function (el) {
                chargerApercuWordVisuel(el.dataset.docxCible, el.dataset.docxUrl, el.dataset.docxTexteCible);
            });
        });
    </script>

    <?php foreach ($rapports as $r): ?>
        <div class="card mb-3" id="rapport-<?= (int)$r['id'] ?>">
            <div class="card-header d-flex justify-content-between align-items-center">
                <strong><?= e($r['nom']) ?></strong>
                <span class="badge bg-<?= classeBadgeStatut($r['statut']) ?>"><?= libelleStatut($r['statut']) ?></span>
            </div>
            <div class="card-body">
                <?php if (!empty($r['contenu'])): ?>
                    <p style="white-space: pre-wrap;"><?= e($r['contenu']) ?></p>
                <?php endif; ?>

                <?php if (!empty($r['fichier_word'])):
                    $cheminFichier = __DIR__ . '/uploads/rapports_word/' . $r['fichier_word'];
                    $apercuTexte = file_exists($cheminFichier) ? extraireApercuDocx($cheminFichier) : null;
                    $idVisuel = 'docx-visuel-' . (int) $r['id'];
                    $idTexte = 'docx-texte-' . (int) $r['id'];
                ?>
                    <div class="border rounded p-3 bg-light mb-2">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <strong>📄 Rapport Word joint</strong>
                            <a href="uploads/rapports_word/<?= e($r['fichier_word']) ?>" target="_blank" class="btn btn-sm btn-outline-primary">Télécharger / Ouvrir</a>
                        </div>

                        <p class="text-muted small mb-1">Aperçu (mise en forme conservée) :</p>
                        <div id="<?= $idVisuel ?>" class="docx-preview d-none small bg-white border rounded p-2" style="max-height:400px; overflow-y:auto;"
                             data-docx-url="uploads/rapports_word/<?= e($r['fichier_word']) ?>" data-docx-cible="<?= $idVisuel ?>" data-docx-texte-cible="<?= $idTexte ?>"></div>

                        <?php if ($apercuTexte): ?>
                            <div id="<?= $idTexte ?>" style="white-space: pre-wrap; max-height: 300px; overflow-y: auto;" class="small bg-white border rounded p-2">
                                <p class="text-muted mb-1"><em>Chargement de l'aperçu visuel... (repli texte ci-dessous si indisponible)</em></p><?= e($apercuTexte) ?>
                            </div>
                        <?php else: ?>
                            <p class="text-muted small mb-0">Chargement de l'aperçu visuel... si rien ne s'affiche, utilisez "Télécharger / Ouvrir".</p>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <p class="text-muted small mb-1">Temps passé : <?= $r['temps_passe'] !== null ? e((string)$r['temps_passe']) . ' h' : 'non renseigné' ?></p>
                <p class="text-muted small">
                    <?php if ($r['date_envoi']): ?>
                        Envoyé le <?= e(formatDateHeure($r['date_envoi'])) ?>
                    <?php else: ?>
                        Pas encore envoyé
                    <?php endif; ?>
                    —
                    <?php if ($r['date_validation']): ?>
                        Validé le <?= e(formatDateHeure($r['date_validation'])) ?>
                    <?php else: ?>
                        En attente de validation
                    <?php endif; ?>
                </p>

                <?php if (!empty($commentairesParRapport[$r['id']])): ?>
                    <hr>
                    <h6>Commentaires</h6>
                    <?php foreach ($commentairesParRapport[$r['id']] as $c): ?>
                        <p class="mb-1"><strong><?= e($c['manager_nom']) ?> :</strong> <?= e($c['commentaire']) ?></p>
                    <?php endforeach; ?>
                <?php endif; ?>

                <form method="post" class="mt-3">
                    <?= champCsrf() ?>
                    <input type="hidden" name="rapport_id" value="<?= (int)$r['id'] ?>">
                    <div class="mb-2">
                        <textarea name="commentaire" class="form-control" rows="2" placeholder="Ajouter un commentaire..."></textarea>
                    </div>
                    <button type="submit" name="action" value="commenter" class="btn btn-outline-secondary btn-sm">Commenter</button>
                    <?php if ($r['statut'] !== 'valide'): ?>
                        <button type="submit" name="action" value="valider" class="btn btn-success btn-sm">Valider le rapport</button>
                    <?php endif; ?>
                    <?php if ($r['statut'] === 'soumis'): ?>
                        <button type="submit" name="action" value="renvoyer" class="btn btn-outline-warning btn-sm" onclick="return confirm('Renvoyer ce rapport au collaborateur pour révision ? Un commentaire expliquant pourquoi est recommandé.');">Renvoyer pour révision (refuser)</button>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    <?php endforeach; ?>
</div>
</body>
</html>
