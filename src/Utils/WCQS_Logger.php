<?php
namespace WcQualiopiSteps\Utils;

defined( 'ABSPATH' ) || exit;

/**
 * Système de logging centralisé pour WC Qualiopi Steps
 * 
 * TOUTES les logs du plugin passent par cette classe
 * Fichier unique consultable depuis admin et SSH
 *
 * @package WcQualiopiSteps\Utils
 * @since 0.6.37
 */
class WCQS_Logger {
    
    /**
     * Instance singleton
     */
    private static ?WCQS_Logger $instance = null;
    
    /**
     * Chemin du fichier de log
     */
    private string $log_file;
    
    /**
     * Niveau de log actuel
     */
    private string $log_level = 'DEBUG';
    
    /**
     * Constructeur
     */
    private function __construct() {
        $this->init_log_file();
    }
    
    /**
     * Obtenir l'instance
     */
    public static function get_instance(): WCQS_Logger {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Initialiser le fichier de log
     */
    private function init_log_file(): void {
        // Créer un dossier dédié dans wp-content/uploads
        $upload_dir = wp_upload_dir();
        $log_dir = $upload_dir['basedir'] . '/wcqs-logs';
        
        // Créer le dossier s'il n'existe pas
        if ( ! file_exists( $log_dir ) ) {
            wp_mkdir_p( $log_dir );
            
            // Créer un .htaccess pour protéger les logs
            $htaccess = $log_dir . '/.htaccess';
            if ( ! file_exists( $htaccess ) ) {
                file_put_contents( $htaccess, "Deny from all\n" );
            }
        }
        
        // Nom du fichier avec date
        $date = date( 'Y-m-d' );
        $this->log_file = $log_dir . '/wcqs-' . $date . '.log';
        
        // Log d'initialisation
        $this->log_raw( 'INFO', 'WCQS_Logger initialized - Log file: ' . $this->log_file );
    }
    
    /**
     * Logger une entrée brute
     */
    private function log_raw( string $level, string $message, array $context = [] ): void {
        $timestamp = current_time( 'c' );
        $user_id = get_current_user_id();
        $request_uri = $_SERVER['REQUEST_URI'] ?? 'CLI';
        
        // Format: [TIMESTAMP] LEVEL [USER:ID] [URI] MESSAGE {CONTEXT}
        $log_entry = sprintf(
            "[%s] %s [USER:%d] [%s] %s",
            $timestamp,
            str_pad( $level, 7 ),
            $user_id,
            $request_uri,
            $message
        );
        
        if ( ! empty( $context ) ) {
            $log_entry .= ' ' . json_encode( $context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
        }
        
        $log_entry .= PHP_EOL;
        
        // Écrire dans le fichier
        file_put_contents( $this->log_file, $log_entry, FILE_APPEND | LOCK_EX );
        
        // Si niveau ERROR ou CRITICAL, aussi dans error_log PHP
        if ( in_array( $level, ['ERROR', 'CRITICAL'] ) ) {
            error_log( '[WCQS] ' . $level . ': ' . $message );
        }
    }
    
    /**
     * Log DEBUG
     */
    public function debug( string $message, array $context = [] ): void {
        if ( $this->should_log( 'DEBUG' ) ) {
            $this->log_raw( 'DEBUG', $message, $context );
        }
    }
    
    /**
     * Log INFO
     */
    public function info( string $message, array $context = [] ): void {
        if ( $this->should_log( 'INFO' ) ) {
            $this->log_raw( 'INFO', $message, $context );
        }
    }
    
    /**
     * Log WARNING
     */
    public function warning( string $message, array $context = [] ): void {
        if ( $this->should_log( 'WARNING' ) ) {
            $this->log_raw( 'WARNING', $message, $context );
        }
    }
    
    /**
     * Log ERROR
     */
    public function error( string $message, array $context = [] ): void {
        $this->log_raw( 'ERROR', $message, $context );
    }
    
    /**
     * Log CRITICAL
     */
    public function critical( string $message, array $context = [] ): void {
        $this->log_raw( 'CRITICAL', $message, $context );
    }
    
    /**
     * Vérifier si on doit logger ce niveau
     */
    private function should_log( string $level ): bool {
        $levels = [
            'DEBUG' => 0,
            'INFO' => 1,
            'WARNING' => 2,
            'ERROR' => 3,
            'CRITICAL' => 4
        ];
        
        $current_level = get_option( 'wcqs_log_level', 'DEBUG' );
        
        return ( $levels[$level] ?? 0 ) >= ( $levels[$current_level] ?? 0 );
    }
    
    /**
     * Obtenir le chemin du fichier de log
     */
    public function get_log_file(): string {
        return $this->log_file;
    }
    
    /**
     * Lire les logs
     */
    public function read_logs( int $lines = 100, ?int $since_timestamp = null ): array {
        if ( ! file_exists( $this->log_file ) ) {
            return [];
        }
        
        $logs = [];
        $file_lines = file( $this->log_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES );
        
        if ( false === $file_lines ) {
            return [];
        }
        
        // Prendre les dernières lignes
        $file_lines = array_slice( $file_lines, -$lines );
        
        foreach ( $file_lines as $line ) {
            // Parser la ligne
            if ( preg_match( '/^\[([^\]]+)\]\s+(\S+)\s+\[USER:(\d+)\]\s+\[([^\]]+)\]\s+(.*)$/', $line, $matches ) ) {
                $entry = [
                    'timestamp' => strtotime( $matches[1] ),
                    'datetime' => $matches[1],
                    'level' => trim( $matches[2] ),
                    'user_id' => (int) $matches[3],
                    'uri' => $matches[4],
                    'message' => $matches[5]
                ];
                
                // Filtrer par timestamp si nécessaire
                if ( $since_timestamp && $entry['timestamp'] < $since_timestamp ) {
                    continue;
                }
                
                $logs[] = $entry;
            }
        }
        
        return $logs;
    }
    
    /**
     * Vider les logs
     */
    public function clear_logs(): bool {
        if ( file_exists( $this->log_file ) ) {
            return file_put_contents( $this->log_file, '' ) !== false;
        }
        return true;
    }
    
    /**
     * Logger une trace de pile
     */
    public function trace( string $message ): void {
        $backtrace = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 5 );
        $trace_info = [];
        
        foreach ( $backtrace as $i => $trace ) {
            if ( $i === 0 ) continue; // Skip this function
            
            $trace_info[] = sprintf(
                '%s%s%s() at %s:%d',
                $trace['class'] ?? '',
                $trace['type'] ?? '',
                $trace['function'] ?? 'unknown',
                basename( $trace['file'] ?? 'unknown' ),
                $trace['line'] ?? 0
            );
        }
        
        $this->debug( $message, [ 'trace' => $trace_info ] );
    }
}

/**
 * Helper function pour logger rapidement
 */
function wcqs_log( string $level, string $message, array $context = [] ): void {
    $logger = WCQS_Logger::get_instance();
    
    switch ( strtoupper( $level ) ) {
        case 'DEBUG':
            $logger->debug( $message, $context );
            break;
        case 'INFO':
            $logger->info( $message, $context );
            break;
        case 'WARNING':
            $logger->warning( $message, $context );
            break;
        case 'ERROR':
            $logger->error( $message, $context );
            break;
        case 'CRITICAL':
            $logger->critical( $message, $context );
            break;
        default:
            $logger->info( $message, $context );
    }
}
