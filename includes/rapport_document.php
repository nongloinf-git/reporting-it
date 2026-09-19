<?php
require_once __DIR__ . '/functions.php';

/**
 * Décode le JSON des données structurées d'un rapport, avec des valeurs par
 * défaut sûres si le rapport est nouveau, vide, ou au format libre (ancien).
 */
function decoderDonneesRapport(?string $json): array
{
    $defaut = [
        'site' => '',
        'activites' => [['titre' => '', 'details' => '']],
        'difficultes' => '',
        'actions_prevues' => '',
        'conclusion' => '',
    ];
    if (!$json) {
        return $defaut;
    }
    $decode = json_decode($json, true);
    if (!is_array($decode)) {
        return $defaut;
    }
    return array_merge($defaut, $decode);
}

/**
 * Construit un résumé texte lisible à partir des données structurées, stocké
 * dans la colonne "contenu" pour rester compatible avec les vues qui affichent
 * un aperçu texte brut (tableau de bord, historique, exports CSV/PDF existants).
 */
function resumerDonneesRapportEnTexte(array $donnees): string
{
    $lignes = [];
    if ($donnees['site']) {
        $lignes[] = 'Site : ' . $donnees['site'];
    }
    foreach ($donnees['activites'] as $a) {
        if (trim($a['titre'] ?? '') === '' && trim($a['details'] ?? '') === '') {
            continue;
        }
        $lignes[] = '• ' . ($a['titre'] ?: 'Activité');
        if (!empty($a['details'])) {
            $lignes[] = '  ' . str_replace("\n", ' / ', $a['details']);
        }
    }
    if ($donnees['difficultes']) {
        $lignes[] = 'Difficultés : ' . $donnees['difficultes'];
    }
    if ($donnees['actions_prevues']) {
        $lignes[] = 'Actions prévues : ' . $donnees['actions_prevues'];
    }
    if ($donnees['conclusion']) {
        $lignes[] = 'Conclusion : ' . $donnees['conclusion'];
    }
    return implode("\n", $lignes);
}

/**
 * Retourne le logo de la société encodé en base64 (data URI), prêt à être
 * intégré directement dans un document Word généré (pour qu'il reste visible
 * même une fois le fichier ouvert hors ligne, sans dépendre du serveur).
 * Retourne null si aucun logo n'est configuré ou si le fichier est introuvable.
 */
function logoSocieteEnBase64(): ?string
{
    $nomFichier = getParametre('logo_societe');
    if (!$nomFichier) {
        return null;
    }
    $chemin = __DIR__ . '/../public/uploads/logo/' . $nomFichier;
    if (!file_exists($chemin)) {
        return null;
    }
    $extension = strtolower(pathinfo($chemin, PATHINFO_EXTENSION));
    $typesMime = ['png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'webp' => 'image/webp', 'svg' => 'image/svg+xml'];
    $mime = $typesMime[$extension] ?? 'image/png';
    $contenu = @file_get_contents($chemin);
    if ($contenu === false) {
        return null;
    }
    return 'data:' . $mime . ';base64,' . base64_encode($contenu);
}

/**
 * Calcule le premier (lundi) et dernier (dimanche) jour d'une semaine ISO donnée.
 * Retourne ['debut' => DateTime, 'fin' => DateTime].
 */
function bornesSemaineIso(int $annee, int $semaine): array
{
    $debut = new DateTime();
    $debut->setISODate($annee, $semaine, 1);
    $fin = (clone $debut)->modify('+6 days');
    return ['debut' => $debut, 'fin' => $fin];
}

/**
 * Génère le bloc HTML (tableau d'en-tête + activités + difficultés + actions +
 * conclusion) pour UN collaborateur, à partir de son rapport et des données
 * structurées décodées. Réutilisé pour le rapport individuel et pour chaque
 * section du rapport de synthèse.
 */
function construireSectionRapportHtml(array $utilisateur, array $rapport, array $donnees, bool $avecEnteteTitre = true): string
{
    $bornes = bornesSemaineIso((int) $rapport['annee'], (int) $rapport['semaine_numero']);
    $fonction = $utilisateur['fonction'] ?: 'Non renseignée';

    $html = '';
    if ($avecEnteteTitre) {
        $html .= '<h1>RAPPORT HEBDOMADAIRE D\'ACTIVITÉS</h1>';
    } else {
        $html .= '<h2 style="margin-top:50px; border-top: 2px solid #1f4e79; padding-top:20px;">' . e($utilisateur['nom']) . '</h2>';
    }

    $html .= '<table class="entete">
        <tr><th>Période du rapport</th><td>Du <strong>' . e($bornes['debut']->format('d/m/Y')) . '</strong> au <strong>' . e($bornes['fin']->format('d/m/Y')) . '</strong></td></tr>
        <tr><th>Collaborateur &amp; fonction</th><td><strong>' . e($utilisateur['nom']) . '</strong> — ' . e($fonction) . '</td></tr>
        <tr><th>Site de rattachement</th><td>' . e($donnees['site'] ?: 'Non renseigné') . '</td></tr>
    </table>';

    $html .= '<h2>1. Activités réalisées</h2><div>';
    $activitesRenseignees = array_filter($donnees['activites'], fn($a) => trim($a['titre'] ?? '') !== '' || trim($a['details'] ?? '') !== '');
    if (!$activitesRenseignees) {
        $html .= '<p>Aucune activité renseignée.</p>';
    } else {
        foreach ($activitesRenseignees as $a) {
            $html .= '<p class="titre-activite">• ' . e($a['titre'] ?: 'Activité sans titre') . '</p>';
            if (!empty($a['details'])) {
                $html .= '<p class="details-activite">' . nl2br(e($a['details'])) . '</p>';
            }
        }
    }
    $html .= '</div>';

    $html .= '<h2>2. Difficultés rencontrées</h2><div class="section-contenu"><p>' . nl2br(e($donnees['difficultes'] ?: 'Aucune difficulté majeure signalée.')) . '</p></div>';
    $html .= '<h2>3. Actions prévues pour la semaine suivante</h2><div class="section-contenu"><p>' . nl2br(e($donnees['actions_prevues'] ?: 'Aucune action planifiée.')) . '</p></div>';
    $html .= '<h2>4. Conclusion</h2><div class="section-contenu"><p>' . nl2br(e($donnees['conclusion'] ?: 'Semaine globalement satisfaisante.')) . '</p></div>';

    return $html;
}

/**
 * Feuille de style partagée entre le rapport individuel et le rapport de
 * synthèse, pour les deux formats (Word et PDF).
 */
function styleRapportDocument(): string
{
    return "
        body { font-family: Arial, sans-serif; padding: 20px; line-height: 1.5; color: #333; }
        .entete { width: 100%; border-collapse: collapse; margin: 20px 0; font-size: 11pt; }
        .entete th { background: #1f4e79; color: white; text-align: left; padding: 8px; width: 30%; border: 1px solid #1f4e79; }
        .entete td { padding: 8px; border: 1px solid #cbd5e1; background: #f8fafc; }
        h1 { color: #1f4e79; text-align: center; font-size: 20pt; margin-top: 10px; }
        h2 { color: #1f4e79; font-size: 14pt; border-bottom: 2px solid #1f4e79; padding-bottom: 5px; margin-top: 30px; }
        p, li { font-size: 11pt; }
        .titre-activite { margin-top:15px; margin-bottom:5px; font-weight:bold; color:#1f4e79; }
        .details-activite { margin-left:20px; margin-top:0; color:#333; }
        .section-contenu { background: #fafafa; padding: 12px; border-left: 4px solid #1f4e79; margin-top: 10px; }
        .logo-conteneur { text-align:center; }
        .logo-conteneur img { max-width: 200px; height: auto; }
    ";
}
