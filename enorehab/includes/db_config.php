<?php
/**
 * Configuration de la base de données
 * 
 * Ce fichier contient les paramètres de connexion à la base de données IONOS.
 * IMPORTANT: Ne jamais mettre ce fichier dans un dépôt public.
 */

// Paramètres de connexion à la base de données IONOS
$db_config = [
    'host'     => 'db5017331779.hosting-data.io', // Remplacer par l'hôte de DB fourni par IONOS
    'dbname'   => 'dbs13898318',               // Remplacer par le nom de votre base de données
    'username' => 'dbu2274689',          // Remplacer par votre nom d'utilisateur
    'password' => '17221722Df@@',         // Remplacer par votre mot de passe
    'charset'  => 'utf8mb4'                     // Le jeu de caractères recommandé
];

// Fonction pour créer une connexion PDO
function getDbConnection() {
    global $db_config;
    
    try {
        $dsn = "mysql:host={$db_config['host']};dbname={$db_config['dbname']};charset={$db_config['charset']}";
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        
        return new PDO($dsn, $db_config['username'], $db_config['password'], $options);
    } catch (PDOException $e) {
        // En production, vous devriez logger l'erreur plutôt que de l'afficher
        error_log("Erreur de connexion à la base de données: " . $e->getMessage());
        
        // Retourner null en cas d'échec de connexion
        return null;
    }
}