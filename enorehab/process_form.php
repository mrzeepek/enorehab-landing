<?php
/**
 * Traitement du formulaire de contact Enorehab
 * 
 * Ce script gère la réception des données du formulaire, effectue une validation,
 * enregistre les données dans une base de données et envoie une notification par email HTML.
 * 
 * @version 1.5 - Avec template d'email HTML
 */

// Inclure les fonctions d'email HTML
require_once 'includes/email_functions.php';

// Inclure le système de log
require_once 'includes/logger.php';

// Mode debug (à désactiver en production)
define('DEBUG_MODE', false);

// IMPORTANT: Activez/désactivez le reCAPTCHA ici
// true = désactivé (pour tests locaux)
// false = activé (pour la production)
$DISABLE_RECAPTCHA = true;

// Configuration de la base de données IONOS
$db_host = 'db5017331779.hosting-data.io';
$db_name = 'dbs13898318';
$db_user = 'dbu2274689';
$db_pass = '17221722Df@@';

// Configuration de l'email
$email_to = "enora.lenez@enorehab.fr";  // Adresse email de réception
$email_from = "noreply@enorehab.fr";    // Adresse email d'expédition

// Configuration reCAPTCHA (utilisé seulement si $DISABLE_RECAPTCHA = false)
$recaptcha_site_key = "VOTRE_CLE_SITE_RECAPTCHA";
$recaptcha_secret = "VOTRE_CLE_SECRETE_RECAPTCHA";

// Démarrer une session pour stocker des informations de débogage si nécessaire
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Vérifier si le formulaire a été soumis via POST
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    log_info('Début du traitement du formulaire', ['ip' => $_SERVER['REMOTE_ADDR']]);
    
    // Récupération et nettoyage des données du formulaire
    $name = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $phone = filter_input(INPUT_POST, 'phone', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $instagram = filter_input(INPUT_POST, 'instagram', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    
    // Stocker les données du formulaire pour utilisation ultérieure
    $form_data = [
        'name' => $name,
        'email' => $email,
        'phone' => $phone,
        'instagram' => $instagram
    ];
    
    log_debug('Données du formulaire reçues', $form_data);
    
    // Récupération de la réponse reCAPTCHA (si elle existe)
    $recaptcha_response = $_POST['g-recaptcha-response'] ?? '';
    
    // ====================================================
    // VALIDATION DES DONNÉES
    // ====================================================
    
    $errors = [];
    
    // Validation des champs obligatoires
    if (empty($name)) {
        $errors[] = "Le nom est requis";
        log_warning('Validation: nom manquant');
    }
    
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Une adresse email valide est requise";
        log_warning('Validation: email invalide ou manquant', ['email' => $email]);
    }
    
    // ====================================================
    // VÉRIFICATION DU RECAPTCHA
    // ====================================================
    
    $recaptcha_valid = false;
    
    if ($DISABLE_RECAPTCHA) {
        // Mode test: ignorer la vérification du reCAPTCHA
        $recaptcha_valid = true;
        log_info('reCAPTCHA désactivé pour les tests');
    } else {
        // Mode production: vérifier le reCAPTCHA
        if (empty($recaptcha_response)) {
            $errors[] = "Veuillez confirmer que vous n'êtes pas un robot";
            log_warning('reCAPTCHA: réponse manquante');
        } else {
            // Vérifier la réponse reCAPTCHA avec l'API Google
            log_debug('Vérification du reCAPTCHA', ['response' => substr($recaptcha_response, 0, 15) . '...']);
            
            $recaptcha_url = 'https://www.google.com/recaptcha/api/siteverify';
            $recaptcha_data = [
                'secret' => $recaptcha_secret,
                'response' => $recaptcha_response,
                'remoteip' => $_SERVER['REMOTE_ADDR']
            ];
            
            $recaptcha_options = [
                'http' => [
                    'header' => "Content-type: application/x-www-form-urlencoded\r\n",
                    'method' => 'POST',
                    'content' => http_build_query($recaptcha_data)
                ]
            ];
            
            $recaptcha_context = stream_context_create($recaptcha_options);
            $recaptcha_result = file_get_contents($recaptcha_url, false, $recaptcha_context);
            $recaptcha_json = json_decode($recaptcha_result, true);
            
            if ($recaptcha_json && isset($recaptcha_json['success']) && $recaptcha_json['success'] === true) {
                $recaptcha_valid = true;
                log_info('reCAPTCHA: vérification réussie');
            } else {
                $errors[] = "La vérification reCAPTCHA a échoué. Veuillez réessayer.";
                log_warning('reCAPTCHA: vérification échouée', [
                    'result' => $recaptcha_json ?? 'No result'
                ]);
            }
        }
    }
    
    // Si des erreurs sont détectées, rediriger vers le formulaire avec messages
    if (!empty($errors)) {
        $errorString = implode('|', $errors);
        log_warning('Formulaire: erreurs de validation, redirection', ['errors' => $errors]);
        
        header("Location: index.php?error=" . urlencode($errorString) . "#booking");
        exit;
    }
    
    // ====================================================
    // ENREGISTREMENT EN BASE DE DONNÉES
    // ====================================================
    
    $db_success = false;
    $db_error_message = "";
    
    try {
        log_info('Tentative de connexion à la base de données');
        
        $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8", $db_user, $db_pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        log_info('Connexion à la base de données réussie');
        
        // Créer la table si elle n'existe pas
        log_debug('Vérification/création de la table enorehab_contacts');
        
        $pdo->exec("CREATE TABLE IF NOT EXISTS enorehab_contacts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            email VARCHAR(100) NOT NULL,
            phone VARCHAR(20),
            instagram VARCHAR(100),
            submission_date DATETIME DEFAULT CURRENT_TIMESTAMP,
            ip_address VARCHAR(45)
        )");
        
        // Préparation de la requête d'insertion
        log_debug('Préparation de la requête d\'insertion');
        
        $stmt = $pdo->prepare("INSERT INTO enorehab_contacts (name, email, phone, instagram, ip_address) 
                               VALUES (:name, :email, :phone, :instagram, :ip)");
        
        // Exécution de la requête
        log_debug('Exécution de la requête d\'insertion');
        
        $stmt->execute([
            ':name' => $name,
            ':email' => $email,
            ':phone' => $phone ?? null,
            ':instagram' => $instagram ?? null,
            ':ip' => $_SERVER['REMOTE_ADDR']
        ]);
        
        // Récupérer l'ID inséré
        $inserted_id = $pdo->lastInsertId();
        
        // Log de la réussite en base de données
        $db_success = true;
        log_info('Données insérées avec succès dans la base de données', ['id' => $inserted_id]);
        
    } catch (PDOException $e) {
        // Log de l'erreur détaillée
        $db_error_message = $e->getMessage();
        log_error('Erreur de base de données', [
            'code' => $e->getCode(),
            'message' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        $db_success = false;
        
        // Stocker l'erreur en session pour le débogage
        $_SESSION['db_error'] = [
            'time' => date('Y-m-d H:i:s'),
            'message' => $e->getMessage(),
            'code' => $e->getCode()
        ];
    }
    
    // ====================================================
    // ENVOI D'EMAILS HTML
    // ====================================================
    
    log_info('Préparation de l\'envoi d\'emails HTML');
    
    // 1. Email de notification à l'administrateur avec le template HTML admin
    $mailSent = send_admin_notification_email($form_data, $db_success, $db_error_message);
    
    if ($mailSent) {
        log_info('Email de notification HTML envoyé avec succès à l\'administrateur');
    } else {
        log_error('Échec de l\'envoi d\'email HTML à l\'administrateur');
    }
    
    // 2. Email de confirmation au client avec le template HTML client
    $clientMailSent = send_client_confirmation_email($email, $name, $phone, $instagram);
    
    if ($clientMailSent) {
        log_info('Email de confirmation HTML envoyé avec succès au client');
    } else {
        log_error('Échec de l\'envoi d\'email HTML au client');
    }
    
    // ====================================================
    // REDIRECTION POST-TRAITEMENT
    // ====================================================
    
    // Si l'envoi d'email ou l'enregistrement en base de données réussit, considérer comme succès
    if ($mailSent || $db_success || $clientMailSent) {
        log_info('Traitement du formulaire terminé avec succès (au moins partiel)', [
            'db_success' => $db_success,
            'admin_mail_success' => $mailSent,
            'client_mail_success' => $clientMailSent
        ]);
        
        header("Location: index.php?success=true#booking");
        exit;
    } else {
        // En cas d'échec complet, afficher un message d'erreur
        log_error('Échec complet: ni email ni base de données');
        
        header("Location: index.php?error=sending#booking");
        exit;
    }
    
} else {
    // Si quelqu'un tente d'accéder directement à ce fichier sans soumettre le formulaire
    log_warning('Tentative d\'accès direct au fichier process_form.php', [
        'ip' => $_SERVER['REMOTE_ADDR'],
        'method' => $_SERVER['REQUEST_METHOD']
    ]);
    
    header("Location: index.php");
    exit;
}