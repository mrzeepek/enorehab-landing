<?php
/**
 * Configuration de la base de données
 * Ce fichier contient les paramètres de connexion à la base de données.
 */

// Charger les variables d'environnement depuis le fichier .env
$env_file = __DIR__ . '/../.env';
if (file_exists($env_file)) {
    $lines = file($env_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) {
            continue;
        }

        // Vérifier si la ligne contient un signe "="
        if (strpos($line, '=') !== false) {
            list($name, $value) = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value);
            if (!empty($name)) {
                putenv(sprintf('%s=%s', $name, $value));
                $_ENV[$name] = $value;
            }
        }
    }
}

// Paramètres de connexion à la base de données
$db_config = [
    'host'     => getenv('DB_HOST') ?: 'localhost',
    'dbname'   => getenv('DB_NAME') ?: 'enorehab',
    'username' => getenv('DB_USER') ?: 'root',
    'password' => getenv('DB_PASS') ?: '',
    'charset'  => 'utf8mb4'
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
            PDO::ATTR_TIMEOUT            => 5,     // Timeout en secondes
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
        ];

        // Tentative de connexion
        $pdo = new PDO($dsn, $db_config['username'], $db_config['password'], $options);

        // Vérification rapide que la connexion fonctionne
        $pdo->query('SELECT 1');

        return $pdo;
    } catch (PDOException $e) {
        // Log détaillé de l'erreur
        $error_msg = "Erreur de connexion à la base de données: " . $e->getMessage();
        error_log($error_msg);

        // Si logger.php est inclus, utiliser cette fonction
        if (function_exists('log_error')) {
            log_error("Échec de connexion à la base de données", [
                'message' => $e->getMessage(),
                'code' => $e->getCode(),
                'dsn' => "mysql:host={$db_config['host']};dbname={$db_config['dbname']};charset={$db_config['charset']}",
                'debug' => [
                    'host_defined' => !empty($db_config['host']),
                    'dbname_defined' => !empty($db_config['dbname']),
                    'username_defined' => !empty($db_config['username']),
                    'password_defined' => !empty($db_config['password']),
                    'env_file_exists' => file_exists(__DIR__ . '/../.env')
                ]
            ]);
        }

        return null;
    }
}