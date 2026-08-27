<?php
// Configuration des notifications par email
// -------------------------------------------------------------
// WampServer utilise la fonction mail() native de PHP.
// Par défaut, PHP essaie d'envoyer via un serveur SMTP local qui n'existe pas
// sous Windows : il faut configurer php.ini (voir README section "Notifications email").
// -------------------------------------------------------------

define('MAIL_EXPEDITEUR', 'reporting-it@local.test');
define('MAIL_NOM_EXPEDITEUR', 'Reporting IT');
define('MAIL_SUJET_RAPPEL', 'Rappel : rapport hebdomadaire non soumis');

/**
 * Envoie un email simple. Retourne true/false selon le succès de mail().
 * Sous WampServer, configurez SMTP/smtp_port dans php.ini (voir README).
 */
function envoyerEmail(string $destinataire, string $sujet, string $corpsHtml): bool
{
    $entetes = "MIME-Version: 1.0\r\n";
    $entetes .= "Content-Type: text/html; charset=UTF-8\r\n";
    $entetes .= 'From: ' . MAIL_NOM_EXPEDITEUR . ' <' . MAIL_EXPEDITEUR . ">\r\n";

    return @mail($destinataire, $sujet, $corpsHtml, $entetes);
}
