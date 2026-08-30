<?php
// Configuration de connexion à la base de données MySQL (WampServer)
define('DB_HOST', 'localhost');
define('DB_NAME', 'reporting_it');
define('DB_USER', 'root');
define('DB_PASS', ''); // par défaut, WampServer utilise root sans mot de passe

function getPDO(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        try {
            $pdo = new PDO(
                'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    // Désactive l'émulation des requêtes préparées : les valeurs sont
                    // envoyées séparément de la requête SQL par le driver MySQL natif
                    // (protection supplémentaire contre les injections SQL, même si
                    // du code futur construisait une requête de façon moins rigoureuse).
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );
        } catch (PDOException $e) {
            error_log('Erreur de connexion à la base de données : ' . $e->getMessage());
            die('Erreur de connexion à la base de données. Contactez votre administrateur.');
        }
    }
    return $pdo;
}
