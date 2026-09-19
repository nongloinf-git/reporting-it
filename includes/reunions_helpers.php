<?php

/**
 * Retourne les réunions visibles par l'utilisateur $u, triées par date.
 * - admin : toutes les réunions
 * - gestionnaire (peutGererReunions) : celles qu'il organise + celles où il participe
 * - autre utilisateur : uniquement celles où il participe
 *
 * $debut / $fin (format 'Y-m-d H:i:s') permettent de restreindre à une plage de dates,
 * utilisé par la vue calendrier.
 *
 * $collaborateurId : ne garde que les réunions où ce collaborateur est organisateur
 * ou participant (filtre "par collaborateur" du calendrier).
 * $equipe : ne garde que les réunions où l'organisateur ou un participant appartient
 * à cette équipe (filtre "par équipe" du calendrier).
 */
function reunionsVisibles(
    PDO $pdo,
    array $u,
    ?string $debut = null,
    ?string $fin = null,
    ?int $collaborateurId = null,
    ?string $equipe = null
): array {
    $filtreDate = '';
    $paramsDate = [];
    if ($debut !== null && $fin !== null) {
        $filtreDate = ' AND r.date_reunion BETWEEN ? AND ? ';
        $paramsDate = [$debut, $fin];
    }

    $filtreCollab = '';
    $paramsCollab = [];
    if ($collaborateurId !== null) {
        $filtreCollab = ' AND (r.organisateur_id = ? OR EXISTS (
            SELECT 1 FROM reunion_participants rpc WHERE rpc.reunion_id = r.id AND rpc.utilisateur_id = ?
        )) ';
        $paramsCollab = [$collaborateurId, $collaborateurId];
    }

    $filtreEquipe = '';
    $paramsEquipe = [];
    if ($equipe !== null && $equipe !== '') {
        $filtreEquipe = ' AND (org.equipe = ? OR EXISTS (
            SELECT 1 FROM reunion_participants rpe JOIN utilisateurs ute ON ute.id = rpe.utilisateur_id
            WHERE rpe.reunion_id = r.id AND ute.equipe = ?
        )) ';
        $paramsEquipe = [$equipe, $equipe];
    }

    $filtresSupplementaires = $filtreCollab . $filtreEquipe;
    $paramsSupplementaires = array_merge($paramsCollab, $paramsEquipe);

    if ($u['role'] === 'admin') {
        $sql = "SELECT r.*, org.nom AS organisateur_nom,
                       (SELECT COUNT(*) FROM taches_reunion t WHERE t.reunion_id = r.id) AS nb_taches
                FROM reunions r
                JOIN utilisateurs org ON org.id = r.organisateur_id
                WHERE 1=1 $filtreDate $filtresSupplementaires
                ORDER BY r.date_reunion";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array_merge($paramsDate, $paramsSupplementaires));
    } elseif (peutGererReunions($u)) {
        $sql = "SELECT DISTINCT r.*, org.nom AS organisateur_nom,
                       (SELECT COUNT(*) FROM taches_reunion t WHERE t.reunion_id = r.id) AS nb_taches
                FROM reunions r
                JOIN utilisateurs org ON org.id = r.organisateur_id
                LEFT JOIN reunion_participants rp ON rp.reunion_id = r.id
                WHERE (r.organisateur_id = ? OR rp.utilisateur_id = ?) $filtreDate $filtresSupplementaires
                ORDER BY r.date_reunion";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array_merge([$u['id'], $u['id']], $paramsDate, $paramsSupplementaires));
    } else {
        $sql = "SELECT r.*, org.nom AS organisateur_nom,
                       (SELECT COUNT(*) FROM taches_reunion t WHERE t.reunion_id = r.id) AS nb_taches
                FROM reunions r
                JOIN utilisateurs org ON org.id = r.organisateur_id
                JOIN reunion_participants rp ON rp.reunion_id = r.id
                WHERE rp.utilisateur_id = ? $filtreDate $filtresSupplementaires
                ORDER BY r.date_reunion";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array_merge([$u['id']], $paramsDate, $paramsSupplementaires));
    }

    return $stmt->fetchAll();
}
