<?php

/**
 * Retourne les réunions visibles par l'utilisateur $u, triées par date.
 * - admin : toutes les réunions
 * - gestionnaire (peutGererReunions) : celles qu'il organise + celles où il participe
 * - autre utilisateur : uniquement celles où il participe
 *
 * $debut / $fin (format 'Y-m-d H:i:s') permettent de restreindre à une plage de dates,
 * utilisé par la vue calendrier.
 */
function reunionsVisibles(PDO $pdo, array $u, ?string $debut = null, ?string $fin = null): array
{
    $filtreDate = '';
    $paramsDate = [];
    if ($debut !== null && $fin !== null) {
        $filtreDate = ' AND r.date_reunion BETWEEN ? AND ? ';
        $paramsDate = [$debut, $fin];
    }

    if ($u['role'] === 'admin') {
        $sql = "SELECT r.*, org.nom AS organisateur_nom,
                       (SELECT COUNT(*) FROM taches_reunion t WHERE t.reunion_id = r.id) AS nb_taches
                FROM reunions r
                JOIN utilisateurs org ON org.id = r.organisateur_id
                WHERE 1=1 $filtreDate
                ORDER BY r.date_reunion";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($paramsDate);
    } elseif (peutGererReunions($u)) {
        $sql = "SELECT DISTINCT r.*, org.nom AS organisateur_nom,
                       (SELECT COUNT(*) FROM taches_reunion t WHERE t.reunion_id = r.id) AS nb_taches
                FROM reunions r
                JOIN utilisateurs org ON org.id = r.organisateur_id
                LEFT JOIN reunion_participants rp ON rp.reunion_id = r.id
                WHERE (r.organisateur_id = ? OR rp.utilisateur_id = ?) $filtreDate
                ORDER BY r.date_reunion";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array_merge([$u['id'], $u['id']], $paramsDate));
    } else {
        $sql = "SELECT r.*, org.nom AS organisateur_nom,
                       (SELECT COUNT(*) FROM taches_reunion t WHERE t.reunion_id = r.id) AS nb_taches
                FROM reunions r
                JOIN utilisateurs org ON org.id = r.organisateur_id
                JOIN reunion_participants rp ON rp.reunion_id = r.id
                WHERE rp.utilisateur_id = ? $filtreDate
                ORDER BY r.date_reunion";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array_merge([$u['id']], $paramsDate));
    }

    return $stmt->fetchAll();
}
