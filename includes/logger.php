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
function log_message($message, $level = 'INFO', $context = [])
{
    // CORRECTION: Fonction incomplète dans le fichier original
    // Vérifier la taille du fichier de log
    if (file_exists(LOG_FILE) && filesize(LOG_FILE) > 5242880) { // 5 MB
        // Rotation du log
        $backup_file = LOG_FILE . '.' . date('Y-m-d-H-i-s') . '.bak';
        rename(LOG_FILE, $backup_file);

        // Limiter le nombre de fichiers de backup (garder les 5 derniers)
        $log_files = glob(dirname(LOG_FILE) . '/*.bak');
        if (count($log_files) > 5) {
            // Trier par date de modification
            usort($log_files, function ($a, $b) {
                return filemtime($a) - filemtime($b);
            });

            // Supprimer les plus anciens
            $files_to_delete = array_slice($log_files, 0, count($log_files) - 5);
            foreach ($files_to_delete as $file) {
                unlink($file);
            }
        }
    }

    // CORRECTION: Ajouter la fonctionnalité d'écriture du log qui manquait
    $date = date('Y-m-d H:i:s');
    $context_string = !empty($context) ? ' | Context: ' . json_encode($context, JSON_UNESCAPED_UNICODE) : '';
    $log_entry = "[$date] [$level] $message$context_string" . PHP_EOL;

    // Écrire dans le fichier de log
    file_put_contents(LOG_FILE, $log_entry, FILE_APPEND);
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