<?php
/**
 * Outil de diagnostic de base de données pour Enorehab
 * 
 * Ce script permet de vérifier la connexion à la base de données,
 * l'existence des tables et leur structure.
 * 
 * ATTENTION : À utiliser uniquement en développement.
 * Supprimer ou protéger ce fichier avant mise en production.
 */

// Activer l'affichage des erreurs pour le diagnostic
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Inclure le système de log
require_once 'includes/logger.php';

// Définir le mode debug
define('DEBUG_MODE', true);

// Configuration de la base de données IONOS
$db_host = 'db5017331779.hosting-data.io';
$db_name = 'dbs13898318';
$db_user = 'dbu2274689';
$db_pass = '17221722Df@@';

// Styles CSS simples pour la présentation
echo '<style>
    body { font-family: Arial, sans-serif; line-height: 1.6; padding: 20px; max-width: 800px; margin: 0 auto; }
    h1 { color: #333; border-bottom: 1px solid #ccc; padding-bottom: 10px; }
    h2 { color: #0ed0ff; margin-top: 30px; }
    .success { color: green; background: #e8f5e9; padding: 10px; border-radius: 4px; }
    .error { color: red; background: #ffebee; padding: 10px; border-radius: 4px; }
    .warning { color: orange; background: #fff8e1; padding: 10px; border-radius: 4px; }
    .info { color: blue; background: #e3f2fd; padding: 10px; border-radius: 4px; }
    table { border-collapse: collapse; width: 100%; margin-top: 20px; }
    th, td { text-align: left; padding: 8px; border: 1px solid #ddd; }
    th { background-color: #f2f2f2; }
    tr:nth-child(even) { background-color: #f9f9f9; }
    .back-link { display: inline-block; margin-top: 30px; padding: 10px 15px; background: #0ed0ff; color: white; text-decoration: none; border-radius: 4px; }
</style>';

echo '<h1>Diagnostic de la Base de Données Enorehab</h1>';

// Test de la connexion à la base de données
echo '<h2>1. Test de connexion à la base de données</h2>';

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo '<div class="success">✅ Connexion à la base de données réussie!</div>';
    log_info('Diagnostic: Connexion à la base de données réussie.');
    
    // Afficher les informations de connexion
    echo '<div class="info">
        <strong>Détails de connexion:</strong><br>
        Host: ' . $db_host . '<br>
        Base de données: ' . $db_name . '<br>
        Utilisateur: ' . $db_user . '<br>
        Version MySQL: ' . $pdo->getAttribute(PDO::ATTR_SERVER_VERSION) . '
    </div>';
    
    // Vérifier l'existence de la table enorehab_contacts
    echo '<h2>2. Vérification de la table enorehab_contacts</h2>';
    
    $stmt = $pdo->prepare("SHOW TABLES LIKE 'enorehab_contacts'");
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        echo '<div class="success">✅ La table enorehab_contacts existe!</div>';
        log_info('Diagnostic: La table enorehab_contacts existe.');
        
        // Vérifier la structure de la table
        echo '<h3>Structure de la table enorehab_contacts:</h3>';
        
        $stmt = $pdo->prepare("DESCRIBE enorehab_contacts");
        $stmt->execute();
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo '<table>
            <tr>
                <th>Champ</th>
                <th>Type</th>
                <th>Null</th>
                <th>Clé</th>
                <th>Défaut</th>
                <th>Extra</th>
            </tr>';
        
        foreach ($columns as $column) {
            echo '<tr>';
            echo '<td>' . htmlspecialchars($column['Field']) . '</td>';
            echo '<td>' . htmlspecialchars($column['Type']) . '</td>';
            echo '<td>' . htmlspecialchars($column['Null']) . '</td>';
            echo '<td>' . htmlspecialchars($column['Key']) . '</td>';
            echo '<td>' . (isset($column['Default']) ? htmlspecialchars($column['Default']) : 'NULL') . '</td>';
            echo '<td>' . htmlspecialchars($column['Extra']) . '</td>';
            echo '</tr>';
        }
        
        echo '</table>';
        
        // Vérifier le nombre d'entrées dans la table
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM enorehab_contacts");
        $stmt->execute();
        $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        echo '<div class="info">Nombre d\'entrées dans la table: ' . $count . '</div>';
        
        if ($count > 0) {
            // Afficher les 5 dernières entrées
            echo '<h3>Les 5 dernières entrées:</h3>';
            
            $stmt = $pdo->prepare("SELECT * FROM enorehab_contacts ORDER BY id DESC LIMIT 5");
            $stmt->execute();
            $lastEntries = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo '<table>
                <tr>
                    <th>ID</th>
                    <th>Nom</th>
                    <th>Email</th>
                    <th>Téléphone</th>
                    <th>Instagram</th>
                    <th>Date</th>
                    <th>IP</th>
                </tr>';
            
            foreach ($lastEntries as $entry) {
                echo '<tr>';
                echo '<td>' . htmlspecialchars($entry['id']) . '</td>';
                echo '<td>' . htmlspecialchars($entry['name']) . '</td>';
                echo '<td>' . htmlspecialchars($entry['email']) . '</td>';
                echo '<td>' . htmlspecialchars($entry['phone'] ?? '') . '</td>';
                echo '<td>' . htmlspecialchars($entry['instagram'] ?? '') . '</td>';
                echo '<td>' . htmlspecialchars($entry['submission_date']) . '</td>';
                echo '<td>' . htmlspecialchars($entry['ip_address']) . '</td>';
                echo '</tr>';
            }
            
            echo '</table>';
        }
    } else {
        echo '<div class="error">❌ La table enorehab_contacts n\'existe pas!</div>';
        log_error('Diagnostic: La table enorehab_contacts n\'existe pas.');
        
        echo '<div class="info">
            <strong>Solution :</strong> La table sera créée automatiquement lors de la première soumission de formulaire réussie. 
            Vous pouvez aussi l\'initialiser en cliquant ci-dessous :
        </div>';
        
        echo '<form method="post">
            <input type="hidden" name="create_table" value="1">
            <button type="submit" style="padding: 10px; background: #0ed0ff; color: white; border: none; border-radius: 4px; cursor: pointer; margin-top: 10px;">
                Créer la table enorehab_contacts
            </button>
        </form>';
        
        // Si le formulaire est soumis, créer la table
        if (isset($_POST['create_table'])) {
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
                
                echo '<div class="success">✅ Table enorehab_contacts créée avec succès! Rafraîchissez la page pour voir les détails.</div>';
                log_info('Diagnostic: Table enorehab_contacts créée manuellement.');
            } catch (PDOException $e) {
                echo '<div class="error">❌ Erreur lors de la création de la table: ' . htmlspecialchars($e->getMessage()) . '</div>';
                log_error('Diagnostic: Erreur lors de la création manuelle de la table', ['error' => $e->getMessage()]);
            }
        }
    }
    
    // Test d'envoi d'email
    echo '<h2>3. Test de la fonction mail()</h2>';
    
    if (function_exists('mail')) {
        echo '<div class="info">✅ La fonction mail() est disponible.</div>';
        
        echo '<form method="post">
            <input type="hidden" name="test_mail" value="1">
            <input type="email" name="test_email" placeholder="Votre email pour le test" required style="padding: 8px; width: 300px; margin-right: 10px;">
            <button type="submit" style="padding: 8px 15px; background: #0ed0ff; color: white; border: none; border-radius: 4px; cursor: pointer;">
                Tester l\'envoi d\'email
            </button>
        </form>';
        
        // Si le formulaire est soumis, tester l'envoi d'email
        if (isset($_POST['test_mail']) && isset($_POST['test_email'])) {
            $test_email = filter_input(INPUT_POST, 'test_email', FILTER_SANITIZE_EMAIL);
            
            if (filter_var($test_email, FILTER_VALIDATE_EMAIL)) {
                $subject = "Test email Enorehab";
                $message = "Ceci est un test d'envoi d'email depuis le diagnostic de base de données Enorehab.\n\nDate: " . date('Y-m-d H:i:s');
                $headers = "From: noreply@enorehab.fr\r\n";
                
                $mail_sent = mail($test_email, $subject, $message, $headers);
                
                if ($mail_sent) {
                    echo '<div class="success">✅ Email de test envoyé avec succès à ' . htmlspecialchars($test_email) . '. Vérifiez votre boîte de réception (et dossier spam).</div>';
                    log_info('Diagnostic: Email de test envoyé avec succès', ['email' => $test_email]);
                } else {
                    echo '<div class="error">❌ Échec de l\'envoi de l\'email de test. Vérifiez la configuration du serveur mail.</div>';
                    log_error('Diagnostic: Échec de l\'envoi de l\'email de test', ['email' => $test_email]);
                }
            } else {
                echo '<div class="error">❌ Adresse email invalide.</div>';
            }
        }
    } else {
        echo '<div class="warning">⚠️ La fonction mail() n\'est pas disponible sur ce serveur.</div>';
        log_warning('Diagnostic: La fonction mail() n\'est pas disponible');
        
        echo '<div class="info">
            <strong>Solution pour les tests locaux :</strong><br>
            En environnement local, la fonction mail() ne fonctionne généralement pas sans configuration supplémentaire. 
            Vous pouvez :<br>
            1. Configurer un serveur mail local comme MailHog ou Postfix<br>
            2. Utiliser un service tiers comme SendGrid ou Mailgun<br>
            3. Modifier temporairement votre code pour simuler l\'envoi d\'emails
        </div>';
    }
    
} catch (PDOException $e) {
    echo '<div class="error">❌ Erreur de connexion à la base de données: ' . htmlspecialchars($e->getMessage()) . '</div>';
    log_error('Diagnostic: Erreur de connexion à la base de données', ['error' => $e->getMessage()]);
    
    echo '<div class="info">
        <strong>Vérifiez :</strong><br>
        1. Que les informations de connexion sont correctes<br>
        2. Que la base de données existe<br>
        3. Que l\'utilisateur a les permissions nécessaires<br>
        4. Que le serveur de base de données est accessible depuis votre serveur actuel
    </div>';
}

// Informations sur le serveur
echo '<h2>4. Informations sur le serveur</h2>';

echo '<div class="info">
    <strong>Serveur PHP :</strong> ' . PHP_VERSION . '<br>
    <strong>Système :</strong> ' . PHP_OS . '<br>
    <strong>Serveur web :</strong> ' . ($_SERVER['SERVER_SOFTWARE'] ?? 'Inconnu') . '<br>
    <strong>Extensions PHP :</strong><br>
    - PDO MySQL : ' . (extension_loaded('pdo_mysql') ? '✅ Activé' : '❌ Désactivé') . '<br>
    - MySQLi : ' . (extension_loaded('mysqli') ? '✅ Activé' : '❌ Désactivé') . '<br>
    - cURL : ' . (extension_loaded('curl') ? '✅ Activé' : '❌ Désactivé') . '<br>
    - JSON : ' . (extension_loaded('json') ? '✅ Activé' : '❌ Désactivé') . '<br>
</div>';

// Lien vers le fichier de log
echo '<h2>5. Fichier de log</h2>';

if (file_exists(LOG_FILE)) {
    echo '<div class="info">
        <strong>Fichier de log :</strong> ' . LOG_FILE . '<br>
        <strong>Taille :</strong> ' . round(filesize(LOG_FILE) / 1024, 2) . ' Ko<br>
        <strong>Dernière modification :</strong> ' . date('Y-m-d H:i:s', filemtime(LOG_FILE)) . '
    </div>';
    
    echo '<h3>Dernières entrées du log :</h3>';
    
    $log_content = file_get_contents(LOG_FILE);
    $log_lines = explode("\n", $log_content);
    $last_lines = array_slice($log_lines, -20); // Afficher les 20 dernières lignes
    
    echo '<pre style="background: #f5f5f5; padding: 10px; border-radius: 4px; overflow: auto; max-height: 300px;">';
    foreach ($last_lines as $line) {
        if (!empty($line)) {
            echo htmlspecialchars($line) . "\n";
        }
    }
    echo '</pre>';
} else {
    echo '<div class="warning">⚠️ Le fichier de log n\'existe pas encore.</div>';
}

// Lien de retour
echo '<a href="index.php" class="back-link">Retour à la page d\'accueil</a>';