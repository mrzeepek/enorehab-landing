<?php
/**
 * Page de débogage Enorehab
 * 
 * ATTENTION : Cette page ne doit pas être accessible en production !
 * À utiliser uniquement en développement local.
 */

// Activer l'affichage des erreurs pour le débogage
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Démarrer la session pour récupérer les erreurs stockées
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Inclure le système de log
require_once 'includes/logger.php';

// Définir le mode debug
define('DEBUG_MODE', true);

// Style CSS de base pour la page
echo '<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Débogage Enorehab</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; padding: 20px; max-width: 800px; margin: 0 auto; background: #111; color: #fff; }
        h1 { color: #0ed0ff; border-bottom: 1px solid #333; padding-bottom: 10px; }
        h2 { color: #0ed0ff; margin-top: 30px; }
        pre { background: #222; padding: 15px; border-radius: 4px; overflow: auto; color: #eee; }
        .card { background: #222; padding: 15px; border-radius: 4px; margin-bottom: 20px; border-left: 4px solid #0ed0ff; }
        .error { border-left: 4px solid #ff4d4d; }
        .success { border-left: 4px solid #4dff4d; }
        .warning { border-left: 4px solid #ffdd4d; }
        .button { display: inline-block; padding: 10px 15px; background: #0ed0ff; color: #000; text-decoration: none; border-radius: 4px; font-weight: bold; margin-top: 10px; }
        .button:hover { background: #00b5e2; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        th, td { text-align: left; padding: 12px; border-bottom: 1px solid #333; }
        th { background-color: #0ed0ff; color: #000; }
    </style>
</head>
<body>
    <h1>Page de débogage Enorehab</h1>
    <p>Cette page affiche les informations de débogage pour le formulaire et la base de données.</p>';

// Fonction pour afficher les variables de manière formatée
function debug_var($var, $title = 'Variable') {
    echo '<div class="card">';
    echo '<h3>' . htmlspecialchars($title) . '</h3>';
    echo '<pre>';
    print_r($var);
    echo '</pre>';
    echo '</div>';
}

// Afficher les informations de session
echo '<h2>1. Informations de session</h2>';

if (!empty($_SESSION)) {
    debug_var($_SESSION, 'Contenu de la session');
    
    // Afficher les erreurs de formulaire spécifiques
    if (isset($_SESSION['form_error'])) {
        echo '<div class="card error">';
        echo '<h3>Erreur de formulaire</h3>';
        echo '<p>Date: ' . htmlspecialchars($_SESSION['form_error']['time']) . '</p>';
        echo '<ul>';
        echo '<li>Base de données: ' . ($_SESSION['form_error']['db_success'] ? '✅ Succès' : '❌ Échec') . '</li>';
        echo '<li>Email admin: ' . ($_SESSION['form_error']['mail_success'] ? '✅ Envoyé' : '❌ Échec') . '</li>';
        echo '<li>Email client: ' . ($_SESSION['form_error']['client_mail_success'] ? '✅ Envoyé' : '❌ Échec') . '</li>';
        echo '</ul>';
        echo '</div>';
    }
    
    // Afficher les erreurs de base de données
    if (isset($_SESSION['db_error'])) {
        echo '<div class="card error">';
        echo '<h3>Erreur de base de données</h3>';
        echo '<p>Date: ' . htmlspecialchars($_SESSION['db_error']['time']) . '</p>';
        echo '<p>Code: ' . htmlspecialchars($_SESSION['db_error']['code']) . '</p>';
        echo '<p>Message: ' . htmlspecialchars($_SESSION['db_error']['message']) . '</p>';
        echo '</div>';
    }
    
    // Afficher les erreurs d'email
    if (isset($_SESSION['mail_error'])) {
        echo '<div class="card error">';
        echo '<h3>Erreur d\'envoi d\'email</h3>';
        echo '<p>Date: ' . htmlspecialchars($_SESSION['mail_error']['time']) . '</p>';
        echo '<p>Destinataire: ' . htmlspecialchars($_SESSION['mail_error']['to']) . '</p>';
        echo '<p>Sujet: ' . htmlspecialchars($_SESSION['mail_error']['subject']) . '</p>';
        echo '</div>';
    }
} else {
    echo '<div class="card warning">';
    echo '<p>Aucune donnée de session n\'est disponible.</p>';
    echo '</div>';
}

// Afficher les paramètres de requête
echo '<h2>2. Paramètres de requête</h2>';

if (!empty($_GET)) {
    debug_var($_GET, 'Paramètres GET');
} else {
    echo '<div class="card warning">';
    echo '<p>Aucun paramètre GET n\'est disponible.</p>';
    echo '</div>';
}

// Vérifier la configuration de la fonction mail()
echo '<h2>3. Vérification de la fonction mail()</h2>';

if (function_exists('mail')) {
    echo '<div class="card success">';
    echo '<p>✅ La fonction mail() est disponible sur ce serveur.</p>';
    
    // Formulaire de test d'envoi d'email
    echo '<form method="post">';
    echo '<input type="hidden" name="action" value="test_mail">';
    echo '<label for="test_email">Email de test:</label> ';
    echo '<input type="email" id="test_email" name="test_email" required style="padding: 5px; width: 250px; margin-right: 10px;">';
    echo '<button type="submit" class="button">Tester l\'envoi d\'email</button>';
    echo '</form>';
    echo '</div>';
    
    // Traitement du formulaire de test
    if (isset($_POST['action']) && $_POST['action'] === 'test_mail') {
        $test_email = filter_input(INPUT_POST, 'test_email', FILTER_SANITIZE_EMAIL);
        
        if (filter_var($test_email, FILTER_VALIDATE_EMAIL)) {
            $subject = "Test de débogage Enorehab";
            $message = "Ceci est un email de test envoyé depuis la page de débogage.\n\n";
            $message .= "Date: " . date('Y-m-d H:i:s') . "\n";
            $message .= "Serveur: " . ($_SERVER['SERVER_NAME'] ?? 'Inconnu') . "\n";
            $headers = "From: noreply@enorehab.fr\r\n";
            
            $mail_sent = mail($test_email, $subject, $message, $headers);
            
            if ($mail_sent) {
                echo '<div class="card success">';
                echo '<p>✅ Email de test envoyé avec succès à ' . htmlspecialchars($test_email) . '.</p>';
                echo '<p>Vérifiez votre boîte de réception (et éventuellement le dossier spam).</p>';
                echo '</div>';
                
                log_info('Test de débogage: Email envoyé avec succès', ['to' => $test_email]);
            } else {
                echo '<div class="card error">';
                echo '<p>❌ Échec de l\'envoi de l\'email de test.</p>';
                echo '<p>Raisons possibles:</p>';
                echo '<ul>';
                echo '<li>Configuration incorrecte du serveur de messagerie</li>';
                echo '<li>Blocage par un pare-feu</li>';
                echo '<li>Restrictions de l\'hébergeur</li>';
                echo '</ul>';
                echo '</div>';
                
                log_error('Test de débogage: Échec de l\'envoi d\'email', ['to' => $test_email]);
            }
        }
    }
} else {
    echo '<div class="card error">';
    echo '<p>❌ La fonction mail() n\'est pas disponible sur ce serveur.</p>';
    echo '<p>Solutions possibles:</p>';
    echo '<ul>';
    echo '<li>Configurer un serveur SMTP local (comme MailHog pour le développement)</li>';
    echo '<li>Utiliser une bibliothèque comme PHPMailer avec SMTP</li>';
    echo '<li>Utiliser un service tiers comme SendGrid, Mailgun, etc.</li>';
    echo '</ul>';
    echo '</div>';
    
    log_warning('Test de débogage: La fonction mail() n\'est pas disponible');
}

// Vérification simplifiée de la base de données
echo '<h2>4. Vérification rapide de la base de données</h2>';

// Configuration de la base de données (reprendre les mêmes valeurs que process_form.php)
$db_host = 'db5017331779.hosting-data.io';
$db_name = 'dbs13898318';
$db_user = 'dbu2274689';
$db_pass = '17221722Df@@';

try {
    // Tentative de connexion
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo '<div class="card success">';
    echo '<p>✅ Connexion à la base de données réussie!</p>';
    echo '<p>Serveur: ' . htmlspecialchars($db_host) . '</p>';
    echo '<p>Base de données: ' . htmlspecialchars($db_name) . '</p>';
    echo '</div>';
    
    // Vérifier l'existence de la table
    $stmt = $pdo->prepare("SHOW TABLES LIKE 'enorehab_contacts'");
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        echo '<div class="card success">';
        echo '<p>✅ La table enorehab_contacts existe.</p>';
        
        // Compter les entrées
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM enorehab_contacts");
        $stmt->execute();
        $count = $stmt->fetchColumn();
        
        echo '<p>Nombre d\'entrées: ' . $count . '</p>';
        
        if ($count > 0) {
            // Afficher les 5 dernières entrées
            $stmt = $pdo->prepare("SELECT * FROM enorehab_contacts ORDER BY id DESC LIMIT 5");
            $stmt->execute();
            $entries = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo '<h3>5 dernières entrées:</h3>';
            echo '<table>';
            echo '<tr>';
            echo '<th>ID</th>';
            echo '<th>Nom</th>';
            echo '<th>Email</th>';
            echo '<th>Date</th>';
            echo '</tr>';
            
            foreach ($entries as $entry) {
                echo '<tr>';
                echo '<td>' . htmlspecialchars($entry['id']) . '</td>';
                echo '<td>' . htmlspecialchars($entry['name']) . '</td>';
                echo '<td>' . htmlspecialchars($entry['email']) . '</td>';
                echo '<td>' . htmlspecialchars($entry['submission_date']) . '</td>';
                echo '</tr>';
            }
            
            echo '</table>';
        }
        
        echo '</div>';
    } else {
        echo '<div class="card warning">';
        echo '<p>⚠️ La table enorehab_contacts n\'existe pas encore.</p>';
        echo '<p>Elle sera créée automatiquement lors de la première soumission réussie du formulaire.</p>';
        
        // Bouton pour créer la table manuellement
        echo '<form method="post">';
        echo '<input type="hidden" name="action" value="create_table">';
        echo '<button type="submit" class="button">Créer la table maintenant</button>';
        echo '</form>';
        echo '</div>';
        
        // Traitement de la création de table
        if (isset($_POST['action']) && $_POST['action'] === 'create_table') {
            try {
                $pdo->exec("CREATE TABLE IF NOT EXISTS enorehab_contacts (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    name VARCHAR(100) NOT NULL,
                    email VARCHAR(100) NOT NULL,
                    phone VARCHAR(20),
                    instagram VARCHAR(100),
                    submission_date DATETIME DEFAULT CURRENT_TIMESTAMP,
                    ip_address VARCHAR(45)
                )");
                
                echo '<div class="card success">';
                echo '<p>✅ Table enorehab_contacts créée avec succès!</p>';
                echo '<p>Rafraîchissez la page pour voir les détails.</p>';
                echo '</div>';
                
                log_info('Débogage: Table enorehab_contacts créée manuellement');
            } catch (PDOException $e) {
                echo '<div class="card error">';
                echo '<p>❌ Erreur lors de la création de la table:</p>';
                echo '<pre>' . htmlspecialchars($e->getMessage()) . '</pre>';
                echo '</div>';
                
                log_error('Débogage: Erreur lors de la création de la table', ['error' => $e->getMessage()]);
            }
        }
    }
    
} catch (PDOException $e) {
    echo '<div class="card error">';
    echo '<p>❌ Erreur de connexion à la base de données:</p>';
    echo '<pre>' . htmlspecialchars($e->getMessage()) . '</pre>';
    echo '<p>Vérifiez les paramètres de connexion dans votre fichier process_form.php.</p>';
    echo '</div>';
    
    log_error('Débogage: Erreur de connexion à la base de données', ['error' => $e->getMessage()]);
}

// Afficher le contenu du fichier de log
echo '<h2>5. Fichier de log</h2>';

if (file_exists(LOG_FILE)) {
    $log_content = file_get_contents(LOG_FILE);
    $log_lines = explode("\n", $log_content);
    $last_lines = array_slice($log_lines, -30); // Afficher les 30 dernières lignes
    
    echo '<div class="card">';
    echo '<h3>Dernières entrées du log:</h3>';
    echo '<pre>';
    foreach ($last_lines as $line) {
        if (!empty($line)) {
            // Colorer différemment selon le type de log
            if (strpos($line, '[ERROR]') !== false) {
                echo '<span style="color: #ff6666;">' . htmlspecialchars($line) . '</span>' . "\n";
            } elseif (strpos($line, '[WARNING]') !== false) {
                echo '<span style="color: #ffcc66;">' . htmlspecialchars($line) . '</span>' . "\n";
            } elseif (strpos($line, '[DEBUG]') !== false) {
                echo '<span style="color: #66ccff;">' . htmlspecialchars($line) . '</span>' . "\n";
            } else {
                echo htmlspecialchars($line) . "\n";
            }
        }
    }
    echo '</pre>';
    echo '</div>';
    
    // Bouton pour effacer le fichier de log
    echo '<form method="post">';
    echo '<input type="hidden" name="action" value="clear_log">';
    echo '<button type="submit" class="button">Effacer le fichier de log</button>';
    echo '</form>';
    
    // Traitement de l'effacement du log
    if (isset($_POST['action']) && $_POST['action'] === 'clear_log') {
        file_put_contents(LOG_FILE, '');
        echo '<div class="card success">';
        echo '<p>✅ Fichier de log effacé avec succès!</p>';
        echo '<p>Rafraîchissez la page pour voir les changements.</p>';
        echo '</div>';
    }
} else {
    echo '<div class="card warning">';
    echo '<p>⚠️ Le fichier de log n\'existe pas encore.</p>';
    echo '<p>Il sera créé automatiquement lors de la première utilisation du système de log.</p>';
    echo '</div>';
}

// Bouton pour effacer les données de débogage en session
echo '<h2>6. Actions</h2>';
echo '<form method="post">';
echo '<input type="hidden" name="action" value="clear_session">';
echo '<button type="submit" class="button">Effacer les données de débogage en session</button>';
echo '</form>';

// Traitement de l'effacement de la session
if (isset($_POST['action']) && $_POST['action'] === 'clear_session') {
    // Sauvegarder l'ID de session actuel
    $session_id = session_id();
    
    // Détruire la session
    session_unset();
    session_destroy();
    
    // Redémarrer une nouvelle session
    session_id($session_id);
    session_start();
    
    echo '<div class="card success">';
    echo '<p>✅ Données de session effacées avec succès!</p>';
    echo '<p>Rafraîchissez la page pour voir les changements.</p>';
    echo '</div>';
    
    log_info('Débogage: Données de session effacées');
}

// Lien vers d'autres pages utiles
echo '<h2>7. Liens utiles</h2>';
echo '<p><a href="db_diagnostic.php" class="button">Diagnostic complet de la base de données</a></p>';
echo '<p><a href="index.php" class="button">Retour à la page d\'accueil</a></p>';

echo '</body></html>';