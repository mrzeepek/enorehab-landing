<?php
/**
 * Logger - Système de journalisation pour Enorehab
 * 
 * Ce fichier permet de logger les erreurs et informations importantes
 * de manière détaillée pour faciliter le débogage.
 */

// Définir le chemin du fichier de log
define('LOG_FILE', __DIR__ . '/../logs/enorehab.log');

// Créer le dossier de logs s'il n'existe pas
if (!is_dir(__DIR__ . '/../logs')) {
    mkdir(__DIR__ . '/../logs', 0755, true);
}

/**
 * Fonction pour ajouter une entrée dans le fichier de log
 * 
 * @param string $message Le message à logger
 * @param string $level Le niveau de log (INFO, WARNING, ERROR)
 * @param array $context Contexte supplémentaire (optionnel)
 */
function log_message($message, $level = 'INFO', $context = []) {
    $date = date('Y-m-d H:i:s');
    $log_message = "[$date] [$level] $message";
    
    // Ajouter le contexte si fourni
    if (!empty($context)) {
        $log_message .= " | Context: " . json_encode($context, JSON_UNESCAPED_UNICODE);
    }
    
    // Ajouter un retour à la ligne
    $log_message .= PHP_EOL;
    
    // Écrire dans le fichier de log
    file_put_contents(LOG_FILE, $log_message, FILE_APPEND);
    
    // Si en mode debug, afficher dans la console PHP
    if (defined('DEBUG_MODE') && DEBUG_MODE === true) {
        error_log($log_message);
    }
}

/**
 * Fonction pour logger une erreur
 */
function log_error($message, $context = []) {
    log_message($message, 'ERROR', $context);
}

/**
 * Fonction pour logger un avertissement
 */
function log_warning($message, $context = []) {
    log_message($message, 'WARNING', $context);
}

/**
 * Fonction pour logger une information
 */
function log_info($message, $context = []) {
    log_message($message, 'INFO', $context);
}

/**
 * Fonction pour logger des informations de débogage
 */
function log_debug($message, $context = []) {
    if (defined('DEBUG_MODE') && DEBUG_MODE === true) {
        log_message($message, 'DEBUG', $context);
    }
}