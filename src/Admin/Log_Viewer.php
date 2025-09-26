<?php
/**
 * ⚠️ WARNING CRITIQUE: TB-Formation utilise WooCommerce BLOCKS
 * Interface d'administration pour consultation et gestion des logs.
 * 
 * Log Viewer - Interface admin pour WC Qualiopi Steps
 * 
 * Permet de consulter, filtrer, télécharger et gérer les logs directement
 * depuis l'interface WordPress d'administration.
 * 
 * @package WcQualiopiSteps\Admin
 * @since 0.6.15
 */

namespace WcQualiopiSteps\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Classe Log_Viewer pour la gestion des logs en admin
 */
class Log_Viewer {
    
    /**
     * Instance singleton
     */
    private static ?Log_Viewer $instance = null;
    
    /**
     * Chemins des logs
     */
    private array $log_paths;
    
    /**
     * Constructeur
     */
    private function __construct() {
        $this->init_log_paths();
        $this->init_hooks();
    }
    
    /**
     * Récupérer l'instance singleton
     */
    public static function get_instance(): Log_Viewer {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Initialiser les chemins des logs
     */
    private function init_log_paths(): void {
        $upload_dir = wp_upload_dir();
        
        $this->log_paths = [
            'wc_logs' => $upload_dir['basedir'] . '/wc-logs/',
            'debug_log' => $upload_dir['basedir'] . '/debug-log-manager/',
            'wcqs_specific' => $upload_dir['basedir'] . '/wc-logs/wcqs_cart_guard_trace-'
        ];
    }
    
    /**
     * Initialiser les hooks
     */
    private function init_hooks(): void {
        // Scripts et styles
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );

        // Enregistrer les hooks AJAX directement ici
        add_action( 'wp_ajax_wcqs_get_logs', [ $this, 'ajax_get_logs' ] );
        add_action( 'wp_ajax_wcqs_clear_logs', [ $this, 'ajax_clear_logs' ] );
        add_action( 'wp_ajax_wcqs_download_logs', [ $this, 'ajax_download_logs' ] );
        add_action( 'wp_ajax_wcqs_test_hooks', [ $this, 'ajax_test_hooks' ] );
        
        // Hook de test de connexion
        add_action( 'wp_ajax_wcqs_test_connection', function() {
            wp_send_json_success( [ 'message' => 'Connection OK', 'timestamp' => current_time( 'Y-m-d H:i:s' ) ] );
        } );
        
        error_log( '[WCQS] Log_Viewer: AJAX hooks registered in init_hooks()' );
    }
    
    /**
     * Charger les scripts et styles
     */
    public function enqueue_scripts( string $hook ): void {
        if ( 'settings_page_wcqs-settings' !== $hook ) {
            return;
        }
        
        $plugin_url = plugin_dir_url( dirname( dirname( __FILE__ ) ) );
        
        wp_enqueue_script(
            'wcqs-log-viewer',
            $plugin_url . 'assets/js/log-viewer.js',
            [ 'jquery' ],
            '0.6.18',
            true
        );
        
        wp_enqueue_style(
            'wcqs-log-viewer',
            $plugin_url . 'assets/css/log-viewer.css',
            [],
            '0.6.18'
        );
        
        $localize_data = [
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce' => wp_create_nonce( 'wcqs_log_viewer' ),
            'strings' => [
                'loading' => __( 'Chargement des logs...', 'wc_qualiopi_steps' ),
                'error' => __( 'Erreur lors du chargement', 'wc_qualiopi_steps' ),
                'no_logs' => __( 'Aucun log trouvé', 'wc_qualiopi_steps' ),
                'cleared' => __( 'Logs vidés avec succès', 'wc_qualiopi_steps' ),
                'confirm_clear' => __( 'Êtes-vous sûr de vouloir vider tous les logs ?', 'wc_qualiopi_steps' )
            ]
        ];
        
        wp_localize_script(
            'wcqs-log-viewer',
            'wcqsLogViewer',
            $localize_data
        );
    }
    
    /**
     * Rendu de l'interface Log Viewer
     */
    public function render_log_viewer(): void {
        ?>
        <div class="wcqs-log-viewer-section">
            <h2><?php _e( '📊 Console de Logs & Debug', 'wc_qualiopi_steps' ); ?></h2>
            
            <!-- Contrôles de filtrage -->
            <div class="wcqs-log-controls">
                <div class="wcqs-filter-group">
                    <label for="wcqs-time-filter"><?php _e( 'Période :', 'wc_qualiopi_steps' ); ?></label>
                    <select id="wcqs-time-filter">
                        <option value="1"><?php _e( '1 minute', 'wc_qualiopi_steps' ); ?></option>
                        <option value="5"><?php _e( '5 minutes', 'wc_qualiopi_steps' ); ?></option>
                        <option value="15"><?php _e( '15 minutes', 'wc_qualiopi_steps' ); ?></option>
                        <option value="60" selected><?php _e( '1 heure', 'wc_qualiopi_steps' ); ?></option>
                        <option value="360"><?php _e( '6 heures', 'wc_qualiopi_steps' ); ?></option>
                        <option value="1440"><?php _e( '24 heures', 'wc_qualiopi_steps' ); ?></option>
                        <option value="all"><?php _e( 'Tout', 'wc_qualiopi_steps' ); ?></option>
                    </select>
                </div>
                
                <div class="wcqs-filter-group">
                    <label for="wcqs-level-filter"><?php _e( 'Niveau :', 'wc_qualiopi_steps' ); ?></label>
                    <select id="wcqs-level-filter">
                        <option value="all"><?php _e( 'Tous', 'wc_qualiopi_steps' ); ?></option>
                        <option value="error"><?php _e( 'Erreurs', 'wc_qualiopi_steps' ); ?></option>
                        <option value="warning"><?php _e( 'Avertissements', 'wc_qualiopi_steps' ); ?></option>
                        <option value="info"><?php _e( 'Informations', 'wc_qualiopi_steps' ); ?></option>
                        <option value="debug"><?php _e( 'Debug', 'wc_qualiopi_steps' ); ?></option>
                        <option value="cart_guard"><?php _e( 'Cart Guard', 'wc_qualiopi_steps' ); ?></option>
                    </select>
                </div>
                
                <div class="wcqs-filter-group">
                    <label for="wcqs-source-filter"><?php _e( 'Source :', 'wc_qualiopi_steps' ); ?></label>
                    <select id="wcqs-source-filter">
                        <option value="all"><?php _e( 'Toutes', 'wc_qualiopi_steps' ); ?></option>
                        <option value="wc_logs"><?php _e( 'WooCommerce', 'wc_qualiopi_steps' ); ?></option>
                        <option value="debug_log"><?php _e( 'Debug WordPress', 'wc_qualiopi_steps' ); ?></option>
                        <option value="wcqs_only"><?php _e( 'WCQS uniquement', 'wc_qualiopi_steps' ); ?></option>
                    </select>
                </div>
            </div>
            
            <!-- Boutons d'action -->
            <div class="wcqs-log-actions">
                <button id="wcqs-test-connection" class="button button-secondary">
                    <span class="dashicons dashicons-admin-tools"></span>
                    <?php _e( 'Test Connexion', 'wc_qualiopi_steps' ); ?>
                </button>
                
                <button id="wcqs-refresh-logs" class="button button-primary">
                    <span class="dashicons dashicons-update"></span>
                    <?php _e( 'Actualiser', 'wc_qualiopi_steps' ); ?>
                </button>
                
                <button id="wcqs-download-logs" class="button button-secondary">
                    <span class="dashicons dashicons-download"></span>
                    <?php _e( 'Télécharger JSON', 'wc_qualiopi_steps' ); ?>
                </button>
                
                <button id="wcqs-clear-logs" class="button button-secondary">
                    <span class="dashicons dashicons-trash"></span>
                    <?php _e( 'Vider les logs', 'wc_qualiopi_steps' ); ?>
                </button>
                
                <button id="wcqs-test-hooks" class="button button-secondary">
                    <span class="dashicons dashicons-admin-tools"></span>
                    <?php _e( 'Test Hooks', 'wc_qualiopi_steps' ); ?>
                </button>
                
                <label class="wcqs-auto-refresh">
                    <input type="checkbox" id="wcqs-auto-refresh" />
                    <?php _e( 'Auto-refresh (30s)', 'wc_qualiopi_steps' ); ?>
                </label>
            </div>
            
            <!-- Statistiques rapides -->
            <div class="wcqs-log-stats">
                <div class="wcqs-stat-item">
                    <span class="wcqs-stat-label"><?php _e( 'Total logs :', 'wc_qualiopi_steps' ); ?></span>
                    <span class="wcqs-stat-value" id="wcqs-total-logs">-</span>
                </div>
                <div class="wcqs-stat-item wcqs-stat-error">
                    <span class="wcqs-stat-label"><?php _e( 'Erreurs :', 'wc_qualiopi_steps' ); ?></span>
                    <span class="wcqs-stat-value" id="wcqs-error-count">-</span>
                </div>
                <div class="wcqs-stat-item wcqs-stat-warning">
                    <span class="wcqs-stat-label"><?php _e( 'Warnings :', 'wc_qualiopi_steps' ); ?></span>
                    <span class="wcqs-stat-value" id="wcqs-warning-count">-</span>
                </div>
                <div class="wcqs-stat-item wcqs-stat-info">
                    <span class="wcqs-stat-label"><?php _e( 'Dernière activité :', 'wc_qualiopi_steps' ); ?></span>
                    <span class="wcqs-stat-value" id="wcqs-last-activity">-</span>
                </div>
            </div>
            
            <!-- Zone d'affichage des logs -->
            <div class="wcqs-log-container">
                <div class="wcqs-log-header">
                    <span><?php _e( 'Horodatage', 'wc_qualiopi_steps' ); ?></span>
                    <span><?php _e( 'Niveau', 'wc_qualiopi_steps' ); ?></span>
                    <span><?php _e( 'Source', 'wc_qualiopi_steps' ); ?></span>
                    <span><?php _e( 'Message', 'wc_qualiopi_steps' ); ?></span>
                </div>
                <div id="wcqs-log-content" class="wcqs-log-content">
                    <div class="wcqs-log-loading">
                        <span class="spinner is-active"></span>
                        <?php _e( 'Chargement des logs...', 'wc_qualiopi_steps' ); ?>
                    </div>
                </div>
            </div>
            
            <!-- Outils de debug avancés -->
            <div class="wcqs-debug-tools">
                <h3><?php _e( '🔧 Outils de Debug Avancés', 'wc_qualiopi_steps' ); ?></h3>
                
                <div class="wcqs-debug-grid">
                    <div class="wcqs-debug-card">
                        <h4><?php _e( 'Test Cart Guard', 'wc_qualiopi_steps' ); ?></h4>
                        <p><?php _e( 'Simuler les conditions de blocage du panier', 'wc_qualiopi_steps' ); ?></p>
                        <button id="wcqs-test-cart-guard" class="button">
                            <?php _e( 'Tester', 'wc_qualiopi_steps' ); ?>
                        </button>
                    </div>
                    
                    <div class="wcqs-debug-card">
                        <h4><?php _e( 'Vérifier Hooks', 'wc_qualiopi_steps' ); ?></h4>
                        <p><?php _e( 'Tester si les hooks WooCommerce se déclenchent', 'wc_qualiopi_steps' ); ?></p>
                        <button id="wcqs-verify-hooks" class="button">
                            <?php _e( 'Vérifier', 'wc_qualiopi_steps' ); ?>
                        </button>
                    </div>
                    
                    <div class="wcqs-debug-card">
                        <h4><?php _e( 'Infos Système', 'wc_qualiopi_steps' ); ?></h4>
                        <p><?php _e( 'WooCommerce Blocks vs Classique détection', 'wc_qualiopi_steps' ); ?></p>
                        <button id="wcqs-system-info" class="button">
                            <?php _e( 'Analyser', 'wc_qualiopi_steps' ); ?>
                        </button>
                    </div>
                    
                    <div class="wcqs-debug-card">
                        <h4><?php _e( 'Simuler Panier', 'wc_qualiopi_steps' ); ?></h4>
                        <p><?php _e( 'Ajouter/supprimer produits pour tests', 'wc_qualiopi_steps' ); ?></p>
                        <button id="wcqs-simulate-cart" class="button">
                            <?php _e( 'Simuler', 'wc_qualiopi_steps' ); ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        
        <style>
        .wcqs-log-viewer-section {
            margin: 20px 0;
            background: #fff;
            border: 1px solid #ccd0d4;
            border-radius: 4px;
            padding: 20px;
        }
        
        .wcqs-log-controls {
            display: flex;
            gap: 15px;
            margin: 15px 0;
            flex-wrap: wrap;
        }
        
        .wcqs-filter-group {
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .wcqs-filter-group label {
            font-weight: 600;
            min-width: 60px;
        }
        
        .wcqs-log-actions {
            display: flex;
            gap: 10px;
            margin: 15px 0;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .wcqs-auto-refresh {
            margin-left: auto;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .wcqs-log-stats {
            display: flex;
            gap: 20px;
            margin: 15px 0;
            padding: 10px;
            background: #f6f7f7;
            border-radius: 4px;
            flex-wrap: wrap;
        }
        
        .wcqs-stat-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            min-width: 100px;
        }
        
        .wcqs-stat-label {
            font-size: 12px;
            color: #666;
            margin-bottom: 2px;
        }
        
        .wcqs-stat-value {
            font-weight: bold;
            font-size: 16px;
        }
        
        .wcqs-stat-error .wcqs-stat-value { color: #dc3232; }
        .wcqs-stat-warning .wcqs-stat-value { color: #ffb900; }
        .wcqs-stat-info .wcqs-stat-value { color: #00a32a; }
        
        .wcqs-log-container {
            border: 1px solid #ddd;
            border-radius: 4px;
            margin: 15px 0;
            max-height: 600px;
            overflow: hidden;
        }
        
        .wcqs-log-header {
            display: grid;
            grid-template-columns: 150px 80px 120px 1fr;
            gap: 10px;
            padding: 10px;
            background: #f1f1f1;
            font-weight: bold;
            border-bottom: 1px solid #ddd;
        }
        
        .wcqs-log-content {
            max-height: 550px;
            overflow-y: auto;
        }
        
        .wcqs-log-loading {
            padding: 40px;
            text-align: center;
            color: #666;
        }
        
        .wcqs-debug-tools {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
        }
        
        .wcqs-debug-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }
        
        .wcqs-debug-card {
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 15px;
            background: #fafafa;
        }
        
        .wcqs-debug-card h4 {
            margin: 0 0 8px 0;
            color: #23282d;
        }
        
        .wcqs-debug-card p {
            margin: 0 0 12px 0;
            color: #666;
            font-size: 13px;
        }
        </style>
        <?php
    }
    
    /**
     * AJAX: Récupérer les logs filtrés
     */
    public function ajax_get_logs(): void {
        // Test d'écriture de log direct pour vérifier si la fonction est appelée
        $log_test_file = WP_CONTENT_DIR . '/wcqs_test_log.txt';
        file_put_contents( $log_test_file, '[WCQS] ajax_get_logs called at ' . current_time( 'Y-m-d H:i:s' ) . PHP_EOL, FILE_APPEND | LOCK_EX );
        
        error_log( '[WCQS] Log_Viewer: ajax_get_logs called' );
        error_log( '[WCQS] Log_Viewer: POST data: ' . json_encode( $_POST ) );
        
        // Vérifier que c'est bien une requête POST
        if ( empty( $_POST ) ) {
            error_log( '[WCQS] Log_Viewer: Empty POST data' );
            wp_send_json_error( [ 'message' => 'Données POST manquantes' ] );
            return;
        }
        
        // Vérifier que l'action est correcte
        if ( ( $_POST['action'] ?? '' ) !== 'wcqs_get_logs' ) {
            error_log( '[WCQS] Log_Viewer: Wrong action. Expected: wcqs_get_logs, Received: ' . ( $_POST['action'] ?? 'none' ) );
            wp_send_json_error( [ 'message' => 'Action incorrecte' ] );
            return;
        }
        
        if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'wcqs_log_viewer' ) ) {
            error_log( '[WCQS] Log_Viewer: Invalid nonce for get_logs. Received: ' . ( $_POST['nonce'] ?? 'none' ) );
            wp_send_json_error( [ 'message' => 'Nonce invalide' ] );
            return;
        }
        
        if ( ! current_user_can( 'manage_options' ) ) {
            error_log( '[WCQS] Log_Viewer: Insufficient permissions for get_logs' );
            wp_send_json_error( [ 'message' => 'Permissions insuffisantes' ] );
            return;
        }
        
        $time_filter = sanitize_text_field( $_POST['time_filter'] ?? '60' );
        $level_filter = sanitize_text_field( $_POST['level_filter'] ?? 'all' );
        $source_filter = sanitize_text_field( $_POST['source_filter'] ?? 'all' );
        
        error_log( '[WCQS] Log_Viewer: Getting logs with filters - time:' . $time_filter . ', level:' . $level_filter . ', source:' . $source_filter );
        
        $logs = $this->get_filtered_logs( $time_filter, $level_filter, $source_filter );
        
        error_log( '[WCQS] Log_Viewer: Found ' . count( $logs['entries'] ) . ' log entries' );
        
        // S'assurer que toutes les données sont des strings
        $cleaned_logs = array_map( function( $log ) {
            return [
                'timestamp' => (int) $log['timestamp'],
                'datetime' => (string) $log['datetime'],
                'level' => (string) $log['level'],
                'source' => (string) $log['source'],
                'message' => (string) $log['message'],
                'raw_line' => (string) $log['raw_line']
            ];
        }, $logs['entries'] );
        
        wp_send_json_success( [
            'logs' => $cleaned_logs,
            'stats' => $logs['stats']
        ] );
    }
    
    /**
     * AJAX: Vider les logs
     */
    public function ajax_clear_logs(): void {
        check_ajax_referer( 'wcqs_log_viewer', 'nonce' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'Permissions insuffisantes', 'wc_qualiopi_steps' ) );
        }
        
        $cleared = $this->clear_all_logs();
        
        if ( $cleared ) {
            wp_send_json_success( [ 'message' => __( 'Logs vidés avec succès', 'wc_qualiopi_steps' ) ] );
        } else {
            wp_send_json_error( [ 'message' => __( 'Erreur lors de la suppression', 'wc_qualiopi_steps' ) ] );
        }
    }
    
    /**
     * AJAX: Télécharger les logs en JSON
     */
    public function ajax_download_logs(): void {
        error_log( '[WCQS] Log_Viewer: ajax_download_logs called' );
        
        // Vérifier le nonce
        if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'wcqs_log_viewer' ) ) {
            error_log( '[WCQS] Log_Viewer: Invalid nonce for download' );
            wp_die( __( 'Nonce invalide', 'wc_qualiopi_steps' ) );
        }
        
        if ( ! current_user_can( 'manage_options' ) ) {
            error_log( '[WCQS] Log_Viewer: Insufficient permissions for download' );
            wp_die( __( 'Permissions insuffisantes', 'wc_qualiopi_steps' ) );
        }
        
        $time_filter = sanitize_text_field( $_POST['time_filter'] ?? 'all' );
        $level_filter = sanitize_text_field( $_POST['level_filter'] ?? 'all' );
        $source_filter = sanitize_text_field( $_POST['source_filter'] ?? 'all' );
        
        error_log( '[WCQS] Log_Viewer: Filters - time:' . $time_filter . ', level:' . $level_filter . ', source:' . $source_filter );
        
        $logs = $this->get_filtered_logs( $time_filter, $level_filter, $source_filter );
        
        $export_data = [
            'export_info' => [
                'timestamp' => current_time( 'Y-m-d H:i:s' ),
                'site_url' => get_site_url(),
                'plugin_version' => '0.6.18',
                'filters' => [
                    'time' => $time_filter,
                    'level' => $level_filter,
                    'source' => $source_filter
                ]
            ],
            'system_info' => [
                'wordpress_version' => get_bloginfo( 'version' ),
                'woocommerce_version' => defined( 'WC_VERSION' ) ? WC_VERSION : 'N/A',
                'php_version' => PHP_VERSION,
                'theme' => get_template(),
                'plugins' => get_option( 'active_plugins', [] )
            ],
            'statistics' => $logs['stats'],
            'logs' => $logs['entries']
        ];
        
        $filename = 'wcqs-logs-' . date( 'Y-m-d-H-i-s' ) . '.json';
        
        // S'assurer qu'il n'y a pas de sortie avant les headers
        if ( ob_get_level() ) {
            ob_end_clean();
        }
        
        header( 'Content-Type: application/json; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
        header( 'Cache-Control: no-cache, must-revalidate' );
        header( 'Expires: Sat, 26 Jul 1997 05:00:00 GMT' );
        header( 'Pragma: no-cache' );
        
        echo json_encode( $export_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
        
        error_log( '[WCQS] Log_Viewer: JSON download completed' );
        wp_die(); // Utiliser wp_die() au lieu de exit pour WordPress
    }
    
    /**
     * AJAX: Tester les hooks
     */
    public function ajax_test_hooks(): void {
        check_ajax_referer( 'wcqs_log_viewer', 'nonce' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'Permissions insuffisantes', 'wc_qualiopi_steps' ) );
        }
        
        $test_type = sanitize_text_field( $_POST['test_type'] ?? 'hooks' );
        
        switch ( $test_type ) {
            case 'cart_guard':
                $result = $this->test_cart_guard();
                break;
            case 'hooks':
                $result = $this->test_woocommerce_hooks();
                break;
            case 'system':
                $result = $this->get_system_info();
                break;
            case 'simulate_cart':
                $result = $this->simulate_cart_scenario();
                break;
            default:
                $result = [ 'error' => 'Type de test inconnu' ];
        }
        
        wp_send_json_success( $result );
    }
    
    /**
     * Récupérer les logs filtrés
     */
    private function get_filtered_logs( string $time_filter, string $level_filter, string $source_filter ): array {
        error_log( '[WCQS] Log_Viewer: get_filtered_logs called with filters: time=' . $time_filter . ', level=' . $level_filter . ', source=' . $source_filter );
        
        $logs = [];
        $stats = [
            'total' => 0,
            'errors' => 0,
            'warnings' => 0,
            'info' => 0,
            'debug' => 0,
            'last_activity' => null
        ];
        
        // Calculer la date limite
        $time_limit = null;
        if ( 'all' !== $time_filter ) {
            $minutes = intval( $time_filter );
            $time_limit = time() - ( $minutes * 60 );
        }
        
        // Lire les logs WooCommerce
        if ( 'debug_log' !== $source_filter ) {
            $wc_logs = $this->read_woocommerce_logs( $time_limit );
            $logs = array_merge( $logs, $wc_logs );
        }
        
        // Lire les logs debug WordPress
        if ( 'wc_logs' !== $source_filter ) {
            $debug_logs = $this->read_debug_logs( $time_limit );
            $logs = array_merge( $logs, $debug_logs );
        }
        
        // Filtrer par niveau
        if ( 'all' !== $level_filter ) {
            $logs = array_filter( $logs, function( $log ) use ( $level_filter ) {
                return $log['level'] === $level_filter || 
                       ( 'cart_guard' === $level_filter && false !== strpos( $log['message'], 'Cart_Guard' ) );
            } );
        }
        
        // Filtrer pour WCQS uniquement si demandé
        if ( 'wcqs_only' === $source_filter ) {
            $logs = array_filter( $logs, function( $log ) {
                return false !== strpos( $log['message'], 'WCQS' ) || 
                       false !== strpos( $log['message'], 'Cart_Guard' ) ||
                       false !== strpos( $log['message'], 'WcQualiopiSteps' );
            } );
        }
        
        // Trier par timestamp décroissant
        usort( $logs, function( $a, $b ) {
            return $b['timestamp'] - $a['timestamp'];
        } );
        
        // Calculer les statistiques
        foreach ( $logs as $log ) {
            $stats['total']++;
            
            switch ( $log['level'] ) {
                case 'error':
                    $stats['errors']++;
                    break;
                case 'warning':
                    $stats['warnings']++;
                    break;
                case 'info':
                    $stats['info']++;
                    break;
                case 'debug':
                    $stats['debug']++;
                    break;
            }
            
            if ( null === $stats['last_activity'] || $log['timestamp'] > $stats['last_activity'] ) {
                $stats['last_activity'] = $log['timestamp'];
            }
        }
        
        // Formater la dernière activité
        if ( $stats['last_activity'] ) {
            $stats['last_activity'] = human_time_diff( $stats['last_activity'] ) . ' ago';
        } else {
            $stats['last_activity'] = 'Aucune';
        }
        
        // Limiter à 1000 entrées pour les performances
        $logs = array_slice( $logs, 0, 1000 );
        
        return [
            'entries' => $logs,
            'stats' => $stats
        ];
    }
    
    /**
     * Lire les logs WooCommerce
     */
    private function read_woocommerce_logs( ?int $time_limit ): array {
        $logs = [];
        $wc_log_dir = $this->log_paths['wc_logs'];
        
        if ( ! is_dir( $wc_log_dir ) ) {
            return $logs;
        }
        
        // Chercher tous les fichiers de logs WCQS
        $pattern = $wc_log_dir . 'wcqs_cart_guard_trace-*.log';
        $log_files = glob( $pattern );
        
        foreach ( $log_files as $log_file ) {
            if ( ! is_readable( $log_file ) ) {
                continue;
            }
            
            $file_logs = $this->parse_log_file( $log_file, $time_limit, 'wc_logs' );
            $logs = array_merge( $logs, $file_logs );
        }
        
        return $logs;
    }
    
    /**
     * Lire les logs debug WordPress
     */
    private function read_debug_logs( ?int $time_limit ): array {
        $logs = [];
        $debug_log_dir = $this->log_paths['debug_log'];
        
        if ( ! is_dir( $debug_log_dir ) ) {
            return $logs;
        }
        
        // Chercher les fichiers debug récents
        $pattern = $debug_log_dir . '*debug.log';
        $log_files = glob( $pattern );
        
        // Trier par date de modification
        usort( $log_files, function( $a, $b ) {
            return filemtime( $b ) - filemtime( $a );
        } );
        
        // Prendre seulement les 3 plus récents
        $log_files = array_slice( $log_files, 0, 3 );
        
        foreach ( $log_files as $log_file ) {
            if ( ! is_readable( $log_file ) ) {
                continue;
            }
            
            $file_logs = $this->parse_log_file( $log_file, $time_limit, 'debug_log' );
            $logs = array_merge( $logs, $file_logs );
        }
        
        return $logs;
    }
    
    /**
     * Parser un fichier de log
     */
    private function parse_log_file( string $log_file, ?int $time_limit, string $source ): array {
        $logs = [];
        
        $content = file_get_contents( $log_file );
        if ( false === $content ) {
            return $logs;
        }
        
        $lines = explode( "\n", $content );
        
        foreach ( $lines as $line ) {
            $line = trim( $line );
            if ( empty( $line ) ) {
                continue;
            }
            
            $parsed = $this->parse_log_line( $line, $source );
            if ( null === $parsed ) {
                continue;
            }
            
            // Filtrer par temps si nécessaire
            if ( $time_limit && $parsed['timestamp'] < $time_limit ) {
                continue;
            }
            
            $logs[] = $parsed;
        }
        
        return $logs;
    }
    
    /**
     * Parser une ligne de log
     */
    private function parse_log_line( string $line, string $source ): ?array {
        // Pattern pour logs WooCommerce : [timestamp] LEVEL message
        if ( preg_match( '/^\[([^\]]+)\]\s+(\w+):\s*(.+)$/', $line, $matches ) ) {
            $timestamp = strtotime( $matches[1] );
            $level = strtolower( $matches[2] );
            $message = $matches[3];
        }
        // Pattern pour logs WordPress : [timestamp] message
        elseif ( preg_match( '/^\[([^\]]+)\]\s+(.+)$/', $line, $matches ) ) {
            $timestamp = strtotime( $matches[1] );
            $message = $matches[2];
            
            // Déterminer le niveau basé sur le contenu
            if ( false !== strpos( $message, 'ERROR' ) || false !== strpos( $message, 'Fatal' ) ) {
                $level = 'error';
            } elseif ( false !== strpos( $message, 'WARNING' ) || false !== strpos( $message, 'Warning' ) ) {
                $level = 'warning';
            } elseif ( false !== strpos( $message, 'DEBUG' ) || false !== strpos( $message, 'Debug' ) ) {
                $level = 'debug';
            } else {
                $level = 'info';
            }
        }
        // Ligne sans format reconnu
        else {
            return null;
        }
        
        if ( false === $timestamp ) {
            $timestamp = time();
        }
        
        return [
            'timestamp' => $timestamp,
            'datetime' => date( 'Y-m-d H:i:s', $timestamp ),
            'level' => $level,
            'source' => $source,
            'message' => is_string( $message ) ? $message : print_r( $message, true ),
            'raw_line' => $line
        ];
    }
    
    /**
     * Vider tous les logs
     */
    private function clear_all_logs(): bool {
        $cleared = true;
        
        // Vider les logs WooCommerce
        $wc_pattern = $this->log_paths['wc_logs'] . 'wcqs_cart_guard_trace-*.log';
        $wc_files = glob( $wc_pattern );
        
        foreach ( $wc_files as $file ) {
            if ( is_writable( $file ) ) {
                file_put_contents( $file, '' );
            } else {
                $cleared = false;
            }
        }
        
        // Log de l'action
        error_log( '[WCQS] Log_Viewer: Logs cleared by admin user at ' . current_time( 'Y-m-d H:i:s' ) );
        
        return $cleared;
    }
    
    /**
     * Tester Cart Guard
     */
    private function test_cart_guard(): array {
        $cart_guard = \WcQualiopiSteps\Frontend\Cart_Guard::get_instance();
        
        $result = [
            'test_name' => 'Cart Guard Test',
            'timestamp' => current_time( 'Y-m-d H:i:s' ),
            'results' => []
        ];
        
        // Test 1: Vérifier si Cart Guard est actif
        $is_active = method_exists( $cart_guard, 'is_cart_enforcement_enabled' );
        $result['results'][] = [
            'test' => 'Cart Guard Class Available',
            'status' => $is_active ? 'PASS' : 'FAIL',
            'message' => $is_active ? 'Cart_Guard class is available' : 'Cart_Guard class not found'
        ];
        
        // Test 2: Vérifier WooCommerce
        $wc_active = function_exists( 'WC' ) && WC();
        $result['results'][] = [
            'test' => 'WooCommerce Available',
            'status' => $wc_active ? 'PASS' : 'FAIL',
            'message' => $wc_active ? 'WooCommerce is active' : 'WooCommerce not available'
        ];
        
        // Test 3: Vérifier le panier
        $cart_available = $wc_active && WC()->cart;
        $result['results'][] = [
            'test' => 'Cart Available',
            'status' => $cart_available ? 'PASS' : 'FAIL',
            'message' => $cart_available ? 'WooCommerce cart is available' : 'Cart not available'
        ];
        
        // Log du test
        error_log( '[WCQS] Log_Viewer: Cart Guard test executed - ' . count( array_filter( $result['results'], function( $r ) { return $r['status'] === 'PASS'; } ) ) . '/' . count( $result['results'] ) . ' tests passed' );
        
        return $result;
    }
    
    /**
     * Tester les hooks WooCommerce
     */
    private function test_woocommerce_hooks(): array {
        $result = [
            'test_name' => 'WooCommerce Hooks Test',
            'timestamp' => current_time( 'Y-m-d H:i:s' ),
            'results' => []
        ];
        
        // Détecter WooCommerce Blocks vs Classique
        $is_blocks = $this->detect_woocommerce_blocks();
        $result['results'][] = [
            'test' => 'WooCommerce Architecture Detection',
            'status' => 'INFO',
            'message' => $is_blocks ? 'WooCommerce BLOCKS detected' : 'WooCommerce CLASSIC detected'
        ];
        
        // Tester les hooks critiques
        $critical_hooks = [
            'woocommerce_proceed_to_checkout',
            'woocommerce_cart_actions',
            'template_redirect',
            'wp_footer'
        ];
        
        foreach ( $critical_hooks as $hook ) {
            $has_callbacks = has_action( $hook );
            $result['results'][] = [
                'test' => "Hook: {$hook}",
                'status' => $has_callbacks ? 'PASS' : 'WARNING',
                'message' => $has_callbacks ? "Hook has {$has_callbacks} callback(s)" : 'No callbacks registered'
            ];
        }
        
        return $result;
    }
    
    /**
     * Obtenir les informations système
     */
    private function get_system_info(): array {
        return [
            'test_name' => 'System Information',
            'timestamp' => current_time( 'Y-m-d H:i:s' ),
            'info' => [
                'wordpress_version' => get_bloginfo( 'version' ),
                'woocommerce_version' => defined( 'WC_VERSION' ) ? WC_VERSION : 'Not installed',
                'php_version' => PHP_VERSION,
                'theme' => get_template(),
                'is_wc_blocks' => $this->detect_woocommerce_blocks(),
                'active_plugins' => count( get_option( 'active_plugins', [] ) ),
                'memory_limit' => ini_get( 'memory_limit' ),
                'max_execution_time' => ini_get( 'max_execution_time' ),
                'upload_max_filesize' => ini_get( 'upload_max_filesize' )
            ]
        ];
    }
    
    /**
     * Simuler un scénario de panier
     */
    private function simulate_cart_scenario(): array {
        return [
            'test_name' => 'Cart Simulation',
            'timestamp' => current_time( 'Y-m-d H:i:s' ),
            'message' => 'Cart simulation feature - To be implemented based on specific needs'
        ];
    }
    
    /**
     * Détecter WooCommerce Blocks vs Classique
     */
    private function detect_woocommerce_blocks(): bool {
        // Vérifier si les blocs WooCommerce sont utilisés
        if ( function_exists( 'wc_get_page_id' ) ) {
            $cart_page_id = wc_get_page_id( 'cart' );
            if ( $cart_page_id ) {
                $cart_content = get_post_field( 'post_content', $cart_page_id );
                return false !== strpos( $cart_content, 'wp:woocommerce/cart' );
            }
        }
        
        return false;
    }
}
