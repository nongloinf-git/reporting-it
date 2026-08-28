# Reporting IT - Application de suivi hebdomadaire

## Installation avec WampServer

1. Copiez le dossier `reporting-it` dans `C:\wamp64\www\` (ou l'équivalent selon votre installation).
2. Démarrez WampServer (icône verte).
3. Ouvrez **phpMyAdmin** (http://localhost/phpmyadmin), onglet **Importer**, et importez le fichier `database/schema.sql`.
   - Cela crée la base `reporting_it` et un compte administrateur par défaut.
   - Si vous aviez déjà installé une version précédente, importez plutôt les migrations dans l'ordre (uniquement celles que vous n'avez pas encore appliquées) :
     - `database/migration_2.sql` (fichiers Word joints aux rapports)
     - `database/migration_3.sql` (photo de profil, désactivation de compte, permission réunions, logo société, module réunions)
4. Vérifiez les identifiants dans `config/database.php` si votre MySQL n'utilise pas `root` sans mot de passe.
5. Ouvrez votre navigateur sur : **http://localhost/reporting-it/public/**

## Connexion par défaut

- Email : `admin@local.test`
- Mot de passe : `Admin123!`

⚠️ Pensez à changer ce mot de passe (ou à créer un nouveau compte admin puis supprimer celui-ci) une fois connecté.

## Rôles

- **Admin** : gère les utilisateurs (créer/supprimer collaborateurs et managers), voit tous les rapports.
- **Manager** : voit et valide/commente les rapports des collaborateurs qui lui sont rattachés.
- **Collaborateur** : saisit son rapport hebdomadaire (brouillon ou soumission), consulte son historique.

## Mon profil (tous les utilisateurs)

Chaque utilisateur peut, via **[son nom en haut à droite] → Mon profil** :
- Modifier son nom affiché
- Ajouter/changer sa **photo de profil** (JPG/PNG/WEBP, 3 Mo max) — affichée dans la navbar et dans la gestion des utilisateurs. Sans photo, un avatar généré à partir des initiales est utilisé automatiquement.
- Changer son mot de passe (avec vérification de l'ancien mot de passe).

## Administration avancée des utilisateurs

Sur la page **Utilisateurs** (admin), en plus de la création/suppression déjà présentes :
- **Modifier le rôle** directement depuis la liste (select + bouton ✓).
- **Activer / Désactiver** un compte : un compte désactivé ne peut plus se connecter, et une session déjà ouverte est coupée immédiatement à la requête suivante (pas besoin d'attendre une déconnexion).
- **Réinitialiser le mot de passe** d'un utilisateur (l'admin saisit un nouveau mot de passe à lui communiquer).
- **Permission "Réunions"** : autoriser ou non un utilisateur (y compris un simple collaborateur) à organiser des réunions et à y assigner des tâches, indépendamment de son rôle. Les admins ont toujours cette permission.
- Par sécurité, un admin ne peut ni se désactiver, ni se supprimer, ni retirer son propre rôle admin depuis cette page.

## Logo et nom de la société (admin)

Page **Paramètres** (admin) : uploadez le logo de votre société (PNG/JPG/SVG/WEBP, 2 Mo max) et définissez un nom affiché dans la barre de navigation de toute l'application.

## Module Réunions

Nouvelle rubrique **Réunions**, accessible à tous mais avec des droits différenciés :
- **Gestionnaires de réunions** (admin, ou tout utilisateur avec la permission "Réunions" activée par un admin — typiquement les managers) peuvent :
  - Créer une réunion (titre, date/heure, lieu, description, liste de participants) ;
  - Modifier une réunion qu'ils ont organisée (l'admin peut modifier toutes les réunions) ;
  - Ajouter des tâches issues de la réunion, avec un responsable et une échéance ;
  - Supprimer des tâches.
- **Tout participant** (même sans la permission de gestion) peut :
  - Consulter les réunions auxquelles il est convié et leurs tâches ;
  - Mettre à jour le statut des tâches dont il est responsable (À faire / En cours / Terminée).
- Le tableau de bord de chaque utilisateur affiche désormais une section **"Mes tâches de réunions"** listant les tâches qui lui sont assignées, non terminées en priorité.

## Fonctionnement du reporting hebdomadaire

- Chaque rapport est identifié par une semaine ISO (année + numéro de semaine).
- Le collaborateur peut enregistrer un brouillon puis le soumettre.
- Une fois validé par le manager, le rapport n'est plus modifiable.
- Le tableau de bord du manager/admin affiche en temps réel qui a soumis (ou non) son rapport pour la semaine en cours.

## Export des rapports

Sur la page **Rapports de l'équipe** (manager/admin), deux boutons permettent d'exporter les rapports de la semaine filtrée :

- **Exporter CSV** : téléchargement direct d'un fichier `.csv` compatible Excel (ouvre proprement les accents).
- **Exporter PDF** : ouvre une page imprimable dans un nouvel onglet ; cliquez sur "Enregistrer en PDF / Imprimer" puis choisissez "Enregistrer au format PDF" comme imprimante dans la boîte de dialogue du navigateur. Aucune librairie externe n'est nécessaire.

## Rapports au format Word (.docx)

- Sur la page **Mon rapport de la semaine**, le collaborateur peut soit taper son texte, soit joindre un fichier `.docx` (10 Mo max), soit les deux.
- Les fichiers sont stockés dans `public/uploads/rapports_word/`.
- Le manager/admin voit, sur la page **Rapports de l'équipe**, un **aperçu visuel avec mise en forme conservée** (gras, titres, listes...), généré côté navigateur via [mammoth.js](https://github.com/mwilliamson/mammoth.js) (chargé depuis un CDN, aucune installation requise). Un aperçu texte brut (extrait côté PHP, sans dépendance) s'affiche automatiquement en repli si l'aperçu visuel échoue (pas d'accès internet pour charger la librairie, fichier corrompu...). Un lien **Télécharger / Ouvrir** reste disponible pour consulter le fichier original.
- Le manager/admin peut ensuite :
  - **Valider le rapport** (le rapport devient définitif, non modifiable) ;
  - **Renvoyer pour révision (refuser)** : le rapport redevient un brouillon modifiable par le collaborateur — pensez à laisser un commentaire expliquant pourquoi.

## Notifications par email (rapports non soumis)

Une page **Rappels email** (visible par manager/admin) permet d'envoyer en un clic un email de rappel à chaque collaborateur n'ayant pas encore soumis son rapport de la semaine sélectionnée.

**Configuration requise sous WampServer** (la fonction `mail()` de PHP n'envoie rien par défaut sous Windows) :

1. Ouvrez `php.ini` (icône WampServer > PHP > php.ini), section `[mail function]`.
2. Renseignez un serveur SMTP, par exemple avec un compte Gmail (nécessite un "mot de passe d'application") ou tout autre relais SMTP de votre entreprise :
   ```ini
   SMTP = smtp.gmail.com
   smtp_port = 587
   sendmail_from = votre-adresse@gmail.com
   ```
   PHP natif ne gère pas l'authentification SMTP nativement sous Windows ; pour un envoi fiable, l'alternative recommandée est d'installer un petit relais local comme **[sendmail pour Windows](https://github.com/protich/sendmail-win32)** configuré avec `sendmail.ini`, puis de pointer `sendmail_path` vers celui-ci dans `php.ini`.
   Pour tester sans compte email réel, un outil comme **Mailhog** ou **Mailtrap** capture les emails localement.
3. Redémarrez WampServer après modification de `php.ini`.
4. Adaptez l'expéditeur dans `config/mail.php` (`MAIL_EXPEDITEUR`, `MAIL_NOM_EXPEDITEUR`).

**Envoi automatique hebdomadaire (optionnel)** : le script `scripts/envoyer_rappels.php` peut être exécuté en ligne de commande. Pour l'automatiser chaque semaine, créez une tâche dans le **Planificateur de tâches Windows** qui exécute :
```
"C:\wamp64\bin\php\phpX.Y.Z\php.exe" "C:\wamp64\www\reporting-it\scripts\envoyer_rappels.php"
```
(remplacez `phpX.Y.Z` par votre version de PHP installée avec WampServer)

## Historique des semaines (manager/admin)

La page **Historique** affiche les rapports de l'équipe sur plusieurs semaines passées (au lieu d'une seule semaine comme sur la page "Rapports de l'équipe") :
- Filtre par nombre de semaines à afficher (par défaut : 8 dernières semaines).
- Filtre optionnel par collaborateur.
- **Graphique comparatif** (Chart.js) : une courbe de temps déclaré par semaine et par collaborateur, superposées pour comparer la charge de travail dans le temps.
- Résultats regroupés par collaborateur, avec statut, temps passé et aperçu du contenu (texte, ou aperçu Word visuel via mammoth.js).
- **Exporter CSV / Exporter PDF** directement depuis cette page, en respectant les mêmes filtres (nombre de semaines, collaborateur) que ceux affichés à l'écran.

## Graphiques de charge de travail (Chart.js)

- **Tableau de bord du collaborateur** : courbe de son temps déclaré sur ses 10 derniers rapports.
- **Tableau de bord du manager/admin** : histogramme comparant le temps déclaré par chaque membre de l'équipe pour la semaine en cours.
- **Page Historique** : courbes multi-collaborateurs sur la période sélectionnée (voir ci-dessus).

Ces graphiques utilisent [Chart.js](https://www.chartjs.org/) chargé depuis un CDN (aucune installation locale requise).

## Vue calendrier des réunions

En plus de la vue liste, la page **Réunions** propose une **vue calendrier** (bouton "📅 Vue calendrier") :
- Grille mensuelle démarrant le lundi, avec navigation "Mois précédent / Mois suivant" et retour rapide au mois en cours.
- Chaque réunion apparaît sous forme de badge coloré sur son jour, avec l'heure et le titre ; un clic ouvre le détail de la réunion.
- Respecte les mêmes règles de visibilité que la vue liste (un simple participant ne voit que les réunions auxquelles il est convié).

## Prochaines pistes d'évolution possibles

- Notification email automatique à l'assignation d'une tâche de réunion
- Rappel email automatique avant l'échéance d'une tâche de réunion
- Export CSV/PDF des tâches de réunion
