<?php
/**
 * Traitement du formulaire de contact Enorehab
 *
 * Ce script gère la réception des données du formulaire, effectue une validation,
 * enregistre les données dans une base de données et envoie une notification par email HTML.
 *
 * @version 2.0
 */

// Gestion des erreurs et timeout
ini_set('display_errors', 0); // Désactivé en production
error_reporting(E_ALL);
set_time_limit(30);
ini_set('max_execution_time', 30);

// Démarrer la session (nécessaire pour CSRF)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Inclure les dépendances
require_once 'includes/logger.php';
require_once 'includes/email_functions.php';
require_once 'includes/db_config.php'; // Inclut la configuration DB basée sur .env

// Configuration
define('DEBUG_MODE', false);

// Configuration des emails
$email_to = "enora.lenez@enorehab.fr";
$email_from = "noreply@enorehab.fr";

// Journal de débogage en développement uniquement
if (DEBUG_MODE) {
    log_debug('Accès à process_form.php', [
        'method' => $_SERVER['REQUEST_METHOD'],
        'ip' => $_SERVER['REMOTE_ADDR'],
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'
    ]);
}

// Vérifier si le formulaire a été soumis via POST
if ($_SERVER["REQUEST_METHOD"] != "POST") {
    // Redirection en cas d'accès direct
    log_warning('Tentative d\'accès direct', ['ip' => $_SERVER['REMOTE_ADDR']]);
    header("Location: index.php");
    exit;
}

// Début du traitement du formulaire
log_info('Traitement du formulaire démarré', ['ip' => $_SERVER['REMOTE_ADDR']]);

// Initialiser les variables
$errors = [];
$db_success = false;
$db_error_message = "";

try {
    // 1. VÉRIFICATION DU HONEYPOT (protection anti-bot)
    if (!empty($_POST['website'])) {
        // C'est probablement un bot, rejeter silencieusement
        log_warning('Honeypot rempli - probable bot', ['ip' => $_SERVER['REMOTE_ADDR']]);
        // Simuler un succès pour ne pas alerter le bot
        header("Location: index.php?success=true#booking");
        exit;
    }

    // 2. VÉRIFICATION DU TOKEN CSRF (protection contre les attaques CSRF)
    if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $errors[] = "Erreur de sécurité, veuillez réessayer";
        log_warning('Token CSRF invalide', [
            'provided' => isset($_POST['csrf_token']) ? substr($_POST['csrf_token'], 0, 8) . '...' : 'none',
            'expected' => isset($_SESSION['csrf_token']) ? substr($_SESSION['csrf_token'], 0, 8) . '...' : 'none'
        ]);
        throw new Exception("CSRF validation failed");
    }

    // 3. RÉCUPÉRATION ET NETTOYAGE DES DONNÉES
    $name = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $phone = filter_input(INPUT_POST, 'phone', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?: null;
    $instagram = filter_input(INPUT_POST, 'instagram', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?: null;

    // Stocker les données dans un tableau
    $form_data = [
        'name' => $name,
        'email' => $email,
        'phone' => $phone,
        'instagram' => $instagram
    ];

    // 4. VALIDATION DES CHAMPS OBLIGATOIRES
    if (empty($name)) {
        $errors[] = "Le nom est requis";
    }

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Une adresse email valide est requise";
    }

    // 5. REDIRECTION EN CAS D'ERREURS DE VALIDATION
    if (!empty($errors)) {
        $errorString = implode('|', $errors);
        log_warning('Validation échouée', ['errors' => $errors]);
        header("Location: index.php?error=" . urlencode($errorString) . "#booking");
        exit;
    }

    // 6. ENREGISTREMENT EN BASE DE DONNÉES
    try {
        log_info('Connexion à la base de données');
        $pdo = getDbConnection(); // Utilise la fonction du fichier db_config.php

        if (!$pdo) {
            throw new Exception("Impossible d'établir une connexion à la base de données");
        }

        // Créer la table si elle n'existe pas
        $pdo->exec("CREATE TABLE IF NOT EXISTS enorehab_contacts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            email VARCHAR(100) NOT NULL,
            phone VARCHAR(20),
            instagram VARCHAR(100),
            submission_date DATETIME DEFAULT CURRENT_TIMESTAMP,
            ip_address VARCHAR(45)
        )");

        // Préparation et exécution de la requête d'insertion
        $stmt = $pdo->prepare("INSERT INTO enorehab_contacts (name, email, phone, instagram, ip_address) 
                               VALUES (:name, :email, :phone, :instagram, :ip)");

        $stmt->execute([
            ':name' => $name,
            ':email' => $email,
            ':phone' => $phone,
            ':instagram' => $instagram,
            ':ip' => $_SERVER['REMOTE_ADDR']
        ]);

        // Récupérer l'ID inséré et marquer comme succès
        $inserted_id = $pdo->lastInsertId();
        $db_success = true;
        log_info('Enregistrement en DB réussi', ['id' => $inserted_id]);

    } catch (PDOException $e) {
        $db_error_message = $e->getMessage();
        log_error('Erreur de base de données', [
            'message' => $e->getMessage(),
            'code' => $e->getCode()
        ]);
    }

    // 7. ENVOI DES EMAILS DE NOTIFICATION
    log_info('Préparation des emails');

    // Email à l'administrateur
    $mailSent = send_admin_notification_email($form_data, $db_success, $db_error_message);

    // Email de confirmation au client
    $clientMailSent = send_client_confirmation_email($email, $name, $phone, $instagram);

    // 8. LOGS DE RÉSULTAT
    log_info('Résultat du traitement', [
        'db_success' => $db_success,
        'admin_email_sent' => $mailSent,
        'client_email_sent' => $clientMailSent
    ]);

    // 9. REDIRECTION FINALE
    if ($mailSent || $db_success || $clientMailSent) {
        // Au moins une opération a réussi
        header("Location: index.php?success=true#booking");
    } else {
        // Échec complet
        header("Location: index.php?error=sending#booking");
    }

} catch (Exception $e) {
    // Gestion des erreurs générales
    log_error('Exception non gérée', [
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);

    header("Location: index.php?error=Une+erreur+inattendue+est+survenue#booking");
}

exit;