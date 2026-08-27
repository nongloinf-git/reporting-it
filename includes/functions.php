<?php

function semaineCourante(): array
{
    return [
        'annee' => (int) date('o'),   // année ISO-8601
        'semaine' => (int) date('W'), // numéro de semaine ISO-8601
    ];
}

function libelleStatut(string $statut): string
{
    return match ($statut) {
        'brouillon' => 'Brouillon',
        'soumis' => 'Soumis',
        'valide' => 'Validé',
        default => ucfirst($statut),
    };
}

function classeBadgeStatut(string $statut): string
{
    return match ($statut) {
        'brouillon' => 'secondary',
        'soumis' => 'warning',
        'valide' => 'success',
        default => 'secondary',
    };
}

/**
 * Lit un paramètre global de l'application (ex: logo, nom de la société).
 * Retourne null si non défini. Utilise un cache statique pour éviter les
 * requêtes répétées sur une même page (ex: navbar affichée sur chaque page).
 */
function getParametre(string $cle): ?string
{
    static $cache = [];
    if (array_key_exists($cle, $cache)) {
        return $cache[$cle];
    }
    $stmt = getPDO()->prepare('SELECT valeur FROM parametres WHERE cle = ?');
    $stmt->execute([$cle]);
    $ligne = $stmt->fetch();
    return $cache[$cle] = $ligne ? $ligne['valeur'] : null;
}

function setParametre(string $cle, ?string $valeur): void
{
    $stmt = getPDO()->prepare(
        'INSERT INTO parametres (cle, valeur) VALUES (?, ?) ON DUPLICATE KEY UPDATE valeur = VALUES(valeur)'
    );
    $stmt->execute([$cle, $valeur]);
}

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function libelleStatutTache(string $statut): string
{
    return match ($statut) {
        'a_faire' => 'À faire',
        'en_cours' => 'En cours',
        'termine' => 'Terminée',
        default => ucfirst($statut),
    };
}

function classeBadgeStatutTache(string $statut): string
{
    return match ($statut) {
        'a_faire' => 'secondary',
        'en_cours' => 'warning',
        'termine' => 'success',
        default => 'secondary',
    };
}

/**
 * Chemin (relatif à public/) vers la photo de profil, ou un avatar par défaut
 * généré via ui-avatars.com à partir des initiales si l'utilisateur n'en a pas.
 */
function urlPhotoProfil(?string $fichier, string $nom): string
{
    if ($fichier) {
        return 'uploads/photos_profil/' . rawurlencode($fichier);
    }
    return 'https://ui-avatars.com/api/?background=0d6efd&color=fff&name=' . rawurlencode($nom);
}

/**
 * Extrait un aperçu texte brut d'un fichier .docx (sans dépendance externe),
 * en lisant word/document.xml à l'intérieur de l'archive zip du fichier Word.
 * Retourne null si l'extraction échoue (fichier corrompu, extension zip absente...).
 */
function extraireApercuDocx(string $cheminFichier, int $longueurMax = 3000): ?string
{
    if (!class_exists('ZipArchive')) {
        return null;
    }

    $zip = new ZipArchive();
    if ($zip->open($cheminFichier) !== true) {
        return null;
    }

    $xml = $zip->getFromName('word/document.xml');
    $zip->close();

    if ($xml === false) {
        return null;
    }

    // Convertit les sauts de paragraphe en retours à la ligne avant de retirer les balises
    $xml = str_replace('</w:p>', "\n", $xml);
    $texte = strip_tags($xml);
    $texte = html_entity_decode($texte, ENT_QUOTES, 'UTF-8');
    $texte = trim(preg_replace('/\n{3,}/', "\n\n", $texte));

    if (mb_strlen($texte) > $longueurMax) {
        $texte = mb_substr($texte, 0, $longueurMax) . '…';
    }

    return $texte;
}

/**
 * Calcule la liste des semaines ISO (annee, semaine) sur les $nombreSemaines
 * dernières semaines, en partant de la semaine courante (incluse).
 * Retourne un tableau de clés "annee-semaine" (ex: "2026-35").
 */
function semainesPrecedentes(int $nombreSemaines): array
{
    $cles = [];
    $date = new DateTime('now');
    for ($i = 0; $i < $nombreSemaines; $i++) {
        $cles[] = $date->format('o') . '-' . (int) $date->format('W');
        $date->modify('-1 week');
    }
    return $cles;
}
