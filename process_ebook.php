<?php
/**
 * Traitement du formulaire de téléchargement d'ebook Enorehab
 *
 * Ce script gère la réception des données du formulaire d'ebook,
 * effectue une validation, enregistre les données et envoie l'ebook par email.
 */

// Gestion des erreurs et timeout
ini_set('display_errors', 0);
error_reporting(E_ALL);
set_time_limit(30);

// Démarrer la session (nécessaire pour CSRF)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Inclure les dépendances
require_once 'includes/logger.php';
require_once 'includes/email_system.php';
require_once 'includes/db_config.php';

// Journal de débogage
log_info('Accès à process_ebook.php', [
    'method' => $_SERVER['REQUEST_METHOD'],
    'ip' => $_SERVER['REMOTE_ADDR']
]);

// Vérifier si le formulaire a été soumis via POST
if ($_SERVER["REQUEST_METHOD"] != "POST") {
    // Redirection en cas d'accès direct
    log_warning('Tentative d\'accès direct à process_ebook.php', ['ip' => $_SERVER['REMOTE_ADDR']]);
    header("Location: index.php");
    exit;
}

// Initialiser les variables
$errors = [];
$db_success = false;
$email_success = false;

try {
    // 1. VÉRIFICATION DU TOKEN CSRF
    if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $errors[] = "Erreur de sécurité, veuillez réessayer";
        log_warning('Token CSRF invalide pour ebook', [
            'ip' => $_SERVER['REMOTE_ADDR']
        ]);
        throw new Exception("CSRF validation failed");
    }

    // 2. RÉCUPÉRATION ET NETTOYAGE DES DONNÉES
    $name = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $consent = filter_input(INPUT_POST, 'consent', FILTER_VALIDATE_BOOLEAN);

    // 3. VALIDATION DES CHAMPS
    if (empty($name)) {
        $errors[] = "Le nom est requis";
    }

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Une adresse email valide est requise";
    }

    if (!$consent) {
        $errors[] = "Vous devez accepter les conditions pour recevoir l'ebook";
    }

    // 4. REDIRECTION EN CAS D'ERREURS
    if (!empty($errors)) {
        $errorString = implode('|', $errors);
        log_warning('Validation du formulaire ebook échouée', ['errors' => $errors]);
        header("Location: index.php?error_ebook=" . urlencode($errorString));
        exit;
    }

    // 5. ENREGISTREMENT EN BASE DE DONNÉES
    try {
        $pdo = getDbConnection();

        if (!$pdo) {
            throw new Exception("Impossible d'établir une connexion à la base de données");
        }

        // Vérifier si l'email existe déjà pour éviter les doublons
        $stmt = $pdo->prepare("SELECT id FROM enorehab_ebook_subscribers WHERE email = :email");
        $stmt->execute([':email' => $email]);
        $existingUser = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$existingUser) {
            // Créer la table si elle n'existe pas
            $pdo->exec("CREATE TABLE IF NOT EXISTS enorehab_ebook_subscribers (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(100) NOT NULL,
                email VARCHAR(100) NOT NULL UNIQUE,
                download_date DATETIME DEFAULT CURRENT_TIMESTAMP,
                ip_address VARCHAR(45),
                consent BOOLEAN DEFAULT 1,
                mail_list BOOLEAN DEFAULT 1
            )");

            // Insertion des données
            $stmt = $pdo->prepare("INSERT INTO enorehab_ebook_subscribers (name, email, ip_address, consent, mail_list) 
                                   VALUES (:name, :email, :ip, :consent, :mail_list)");

            $stmt->execute([
                ':name' => $name,
                ':email' => $email,
                ':ip' => $_SERVER['REMOTE_ADDR'],
                ':consent' => $consent ? 1 : 0,
                ':mail_list' => $consent ? 1 : 0
            ]);

            $db_success = true;
            log_info('Nouvel abonné ebook enregistré en DB', [
                'email' => $email,
                'id' => $pdo->lastInsertId()
            ]);
        } else {
            // Mettre à jour la date de téléchargement
            $stmt = $pdo->prepare("UPDATE enorehab_ebook_subscribers SET download_date = NOW() WHERE email = :email");
            $stmt->execute([':email' => $email]);

            $db_success = true;
            log_info('Abonné ebook existant, mise à jour', [
                'email' => $email
            ]);
        }
    } catch (PDOException $e) {
        log_error('Erreur DB lors de l\'enregistrement d\'un abonné ebook', [
            'message' => $e->getMessage(),
            'code' => $e->getCode()
        ]);
    }

    // 6. ENVOI DE L'EBOOK PAR EMAIL
    $email_success = send_ebook_email($email, $name);

    if ($email_success) {
        log_info('Email ebook envoyé avec succès', ['to' => $email]);
    } else {
        log_error('Échec de l\'envoi de l\'email ebook', ['to' => $email]);
    }

    // 7. ENVOI D'UN EMAIL DE NOTIFICATION À L'ADMIN
    $admin_email_sent = send_admin_notification_email([
        'name' => $name,
        'email' => $email,
        'type' => 'download_ebook'
    ], $db_success);

    // 8. REDIRECTION AVEC MESSAGE DE SUCCÈS
    header("Location: index.php?ebook_success=true");
    exit;

} catch (Exception $e) {
    // Gestion des erreurs générales
    log_error('Exception dans process_ebook.php', [
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);

    header("Location: index.php?error_ebook=Une+erreur+inattendue+est+survenue");
    exit;
}