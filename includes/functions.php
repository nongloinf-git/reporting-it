<?php

/**
 * Formate une date/heure MySQL (DATETIME/TIMESTAMP) en "JJ/MM/AAAA à HH:mm".
 * Retourne null si la valeur est vide (permet d'afficher "En attente" ailleurs).
 */
function formatDateHeure(?string $valeurMysql): ?string
{
    if (!$valeurMysql) {
        return null;
    }
    try {
        return (new DateTime($valeurMysql))->format('d/m/Y \à H:i');
    } catch (Exception $e) {
        return null;
    }
}

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
 * Convertit un fichier .docx en HTML avec mise en forme de base (gras, italique,
 * souligné, sauts de paragraphe, puces), en lisant directement le XML interne du
 * fichier Word (aucune dépendance externe : ZipArchive + DOMDocument, tous deux
 * inclus dans PHP par défaut). Tout le texte est échappé (htmlspecialchars) avant
 * d'être ré-inséré dans les balises HTML générées : le résultat est donc sûr à
 * afficher tel quel. Retourne null si la conversion échoue.
 */
function convertirDocxEnHtml(string $cheminFichier, int $nbParagraphesMax = 200): ?string
{
    if (!class_exists('ZipArchive') || !class_exists('DOMDocument')) {
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

    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    $charge = $dom->loadXML($xml);
    libxml_clear_errors();
    if (!$charge) {
        return null;
    }

    $xpath = new DOMXPath($dom);
    $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');

    $paragraphes = $xpath->query('//w:body/w:p');
    if ($paragraphes === false || $paragraphes->length === 0) {
        return null;
    }

    $html = '';
    $compteur = 0;

    foreach ($paragraphes as $p) {
        if (++$compteur > $nbParagraphesMax) {
            $html .= '<p class="text-muted small">(aperçu tronqué — ouvrez le fichier pour voir la suite)</p>';
            break;
        }

        // Détecte une liste à puces/numérotée (présence de w:numPr dans les propriétés du paragraphe)
        $estListe = $xpath->query('.//w:pPr/w:numPr', $p)->length > 0;

        $contenuParagraphe = '';
        $runs = $xpath->query('.//w:r', $p);
        foreach ($runs as $run) {
            $texteNoeuds = $xpath->query('.//w:t', $run);
            $texte = '';
            foreach ($texteNoeuds as $t) {
                $texte .= $t->textContent;
            }
            if ($texte === '') {
                // Gère les tabulations et sauts de ligne internes au run
                if ($xpath->query('.//w:tab', $run)->length > 0) {
                    $texte = "\t";
                }
                if ($xpath->query('.//w:br', $run)->length > 0) {
                    $contenuParagraphe .= '<br>';
                }
                if ($texte === '') {
                    continue;
                }
            }

            $texteEchappe = nl2br(e($texte));

            $gras = $xpath->query('.//w:rPr/w:b[not(@w:val="false") and not(@w:val="0")]', $run)->length > 0;
            $italique = $xpath->query('.//w:rPr/w:i[not(@w:val="false") and not(@w:val="0")]', $run)->length > 0;
            $souligne = $xpath->query('.//w:rPr/w:u[not(@w:val="none")]', $run)->length > 0;

            if ($gras) {
                $texteEchappe = '<strong>' . $texteEchappe . '</strong>';
            }
            if ($italique) {
                $texteEchappe = '<em>' . $texteEchappe . '</em>';
            }
            if ($souligne) {
                $texteEchappe = '<u>' . $texteEchappe . '</u>';
            }

            $contenuParagraphe .= $texteEchappe;
        }

        if (trim($contenuParagraphe) === '') {
            $html .= '<p>&nbsp;</p>';
            continue;
        }

        if ($estListe) {
            $html .= '<p class="mb-1">• ' . $contenuParagraphe . '</p>';
        } else {
            $html .= '<p>' . $contenuParagraphe . '</p>';
        }
    }

    return $html !== '' ? $html : null;
}

/**
 * Encode une valeur PHP en JSON prêt à être inséré dans un bloc <script>,
 * en échappant les caractères qui pourraient casser hors de la balise
 * (ex: un nom d'utilisateur contenant "</script>").
 */
function jsonPourScript($valeur): string
{
    return json_encode(
        $valeur,
        JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE
    );
}

/**
 * Vérifie qu'une tâche peut être marquée "Terminée" : c'est le cas si elle n'a
 * aucune sous-tâche, ou si toutes ses sous-tâches directes sont déjà au statut
 * "Terminée". Utilisé pour empêcher de clôturer une tâche tant que son travail
 * délégué n'est pas fini.
 */
function toutesSousTachesTerminees(PDO $pdo, int $tacheId): bool
{
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM taches_reunion WHERE parent_tache_id = ? AND statut <> 'termine'");
    $stmt->execute([$tacheId]);
    return (int) $stmt->fetchColumn() === 0;
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
