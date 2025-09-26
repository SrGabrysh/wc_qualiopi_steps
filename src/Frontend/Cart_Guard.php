<?php
/**
 * Cart Guard - UX panier pour WC Qualiopi Steps
 * 
 * Masque le bouton "Commander" si test non validé et affiche le CTA "Passer le test"
 * 
 * IMPORTANT - URLS FRANÇAISES :
 * Ce site utilise des URLs françaises au lieu des URLs anglaises standard :
 * - Panier : /panier/ (au lieu de /cart/)
 * - Checkout : /commander/ (au lieu de /checkout/)
 * 
 * Le plugin prend en charge les deux formats pour une compatibilité maximale.
 * 
 * @package WcQualiopiSteps\Frontend
 * @since 0.6.0
 */

namespace WcQualiopiSteps\Frontend;

use WcQualiopiSteps\Core\Plugin;
use WcQualiopiSteps\Utils\WCQS_Mapping;
use WcQualiopiSteps\Utils\WCQS_Session;

/**
 * Classe Cart_Guard
 * 
 * Gère l'affichage conditionnel du bouton "Commander" dans le panier
 * selon l'état de validation du test de positionnement.
 * 
 * Support des URLs françaises (/panier/, /commander/) et anglaises (/cart/, /checkout/)
 */
class Cart_Guard {
    
    /**
     * Instance unique (singleton)
     * 
     * @var Cart_Guard|null
     */
    private static $instance = null;
    
    /**
     * Cache des produits nécessitant un test
     * 
     * @var array
     */
    private $products_requiring_test = [];
    
    /**
     * Cache des validations utilisateur
     * 
     * @var array
     */
    private $user_validations = [];
    
    /**
     * Obtenir l'instance unique
     * 
     * @return Cart_Guard
     */
    public static function get_instance(): Cart_Guard {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructeur privé (singleton)
     */
    private function __construct() {
        $this->init_hooks();
    }
    
    /**
     * Initialiser les hooks WooCommerce
     * CORRECTION EXPERTS: Hooks multiples + garde serveur universelle
     */
    private function init_hooks(): void {
        error_log( '[WCQS] Cart_Guard: Initializing hooks (Expert Fix)...' );
        
        // CORRECTION: Toujours enregistrer les hooks, vérifier conditions dans callbacks
        
        // 1) Garde universelle par redirection (classique + Blocks)
        \add_action( 'template_redirect', [ $this, 'guard_template_redirect' ], 0 );
        
        // 2) Hooks WooCommerce classiques (fallback)
        \add_action( 'woocommerce_proceed_to_checkout', [ $this, 'maybe_replace_checkout_button' ], 5 );
        \add_action( 'woocommerce_proceed_to_checkout', [ $this, 'maybe_add_test_notice' ], 10 );
        
        // 3) Hooks alternatifs plus fiables
        \add_action( 'woocommerce_cart_actions', [ $this, 'maybe_add_test_notice' ], 10 );
        \add_action( 'woocommerce_before_cart_totals', [ $this, 'maybe_add_test_notice_before_totals' ], 5 );
        
        // 4) Intercepter Store API (Checkout Block)
        \add_filter( 'rest_request_before_callbacks', [ $this, 'intercept_store_api_checkout' ], 5, 3 );
        
        // 5) Notices serveur (compatibles Blocks)
        \add_action( 'wp', [ $this, 'maybe_add_server_notice' ] );
        
        // 6) Filtrer URL checkout (classique)
        \add_filter( 'woocommerce_get_checkout_url', [ $this, 'filter_checkout_url_when_blocked' ], 10, 1 );
        
        // Styles et scripts
        \add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );
        
        // Debug hook pour vérifier l'exécution
        \add_action( 'wp_footer', [ $this, 'debug_cart_state' ] );
        
        error_log( '[WCQS] Cart_Guard: All hooks initialized (Expert Fix)' );
    }
    
    /**
     * Vérifier si l'enforcement du panier est activé
     * 
     * @return bool
     */
    private function is_cart_enforcement_enabled(): bool {
        $flags = Plugin::get_flags();
        $enabled = ! empty( $flags['enforce_cart'] );
        \error_log( 'WCQS Cart_Guard: Enforcement enabled: ' . ( $enabled ? 'YES' : 'NO' ) . ' (flags: ' . json_encode( $flags ) . ')' );
        return $enabled;
    }
    
    /**
     * Vérifier si nous sommes sur la page panier
     * Support de l'URL française /panier/ et anglaise /cart/
     * 
     * NOTE: Ce site utilise des URLs françaises :
     * - Panier : /panier/ (au lieu de /cart/)
     * - Checkout : /commander/ (au lieu de /checkout/)
     * 
     * @return bool
     */
    private function is_cart_page(): bool {
        // Test WordPress standard
        if ( \is_cart() ) {
            return true;
        }
        
        // Test alternatif pour URL française /panier/
        if ( \function_exists( 'wc_get_page_id' ) ) {
            $cart_page_id = \wc_get_page_id( 'cart' );
            if ( $cart_page_id && \is_page( $cart_page_id ) ) {
                return true;
            }
        }
        
        // Test par URL pour /panier/ ou /cart/
        $request_uri = $_SERVER['REQUEST_URI'] ?? '';
        if ( \strpos( $request_uri, '/panier/' ) !== false || \strpos( $request_uri, '/cart/' ) !== false ) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Vérifier si nous sommes sur la page checkout
     * Support de l'URL française /commander/ et anglaise /checkout/
     * 
     * NOTE: Ce site utilise /commander/ au lieu de /checkout/
     * 
     * @return bool
     */
    private function is_checkout_page(): bool {
        // Test WordPress standard
        if ( \is_checkout() ) {
            return true;
        }
        
        // Test alternatif pour URL française /commander/
        if ( \function_exists( 'wc_get_page_id' ) ) {
            $checkout_page_id = \wc_get_page_id( 'checkout' );
            if ( $checkout_page_id && \is_page( $checkout_page_id ) ) {
                return true;
            }
        }
        
        // Test par URL pour /commander/ ou /checkout/
        $request_uri = $_SERVER['REQUEST_URI'] ?? '';
        if ( \strpos( $request_uri, '/commander/' ) !== false || \strpos( $request_uri, '/checkout/' ) !== false ) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Remplacer le bouton checkout si nécessaire
     * CORRECTION EXPERTS: Vérifier conditions dans callback + Test d'isolation Expert #5
     */
    public function maybe_replace_checkout_button(): void {
        $this->log_trace( "Hook 'woocommerce_proceed_to_checkout' FIRED (Priority 5) - BUTTON REPLACE" );
        error_log( '[WCQS] Cart_Guard: maybe_replace_checkout_button triggered' );
        
        // CORRECTION: Vérifier les conditions ici, pas à l'init
        if ( ! $this->is_cart_enforcement_enabled() ) {
            error_log( '[WCQS] Cart_Guard: Enforcement disabled, skipping' );
            return;
        }
        
        if ( ! $this->should_block_checkout() ) {
            error_log( '[WCQS] Cart_Guard: Should not block checkout, skipping' );
            return;
        }
        
        error_log( '[WCQS] Cart_Guard: Blocking checkout button' );
        
        // Supprimer le bouton checkout par défaut
        \remove_action( 'woocommerce_proceed_to_checkout', 'woocommerce_button_proceed_to_checkout', 20 );
        
        // Ajouter notre message personnalisé à la place
        \add_action( 'woocommerce_proceed_to_checkout', [ $this, 'render_blocked_checkout_message' ], 20 );
    }
    
    /**
     * Ajouter la notice de test requis
     * CORRECTION EXPERTS: Vérifier conditions dans callback + Test d'isolation Expert #5
     */
    public function maybe_add_test_notice(): void {
        $this->log_trace( "Hook 'woocommerce_proceed_to_checkout' FIRED (Priority 10) - ADD NOTICE" );
        error_log( '[WCQS] Cart_Guard: maybe_add_test_notice triggered' );
        
        // CORRECTION: Vérifier les conditions ici
        if ( ! $this->is_cart_enforcement_enabled() ) {
            error_log( '[WCQS] Cart_Guard: Enforcement disabled, no notice needed' );
            return;
        }
        
        $pending_tests = $this->get_pending_tests_info();
        
        if ( empty( $pending_tests ) ) {
            error_log( '[WCQS] Cart_Guard: No pending tests, no notice needed' );
            return;
        }
        
        error_log( '[WCQS] Cart_Guard: Adding test notices for ' . count( $pending_tests ) . ' tests' );
        
        // Afficher la notice pour chaque test requis
        foreach ( $pending_tests as $test_info ) {
            $this->render_test_notice( $test_info );
        }
    }
    
    /**
     * Déterminer si le checkout doit être bloqué
     * CORRECTION: Test d'isolation Expert #5 - Force TRUE pour diagnostic
     * 
     * @return bool
     */
    private function should_block_checkout(): bool {
        // --- DEBUG: FORCE TRUE (Expert #5 Isolation Test) ---
        if ( function_exists( 'WC' ) && \WC() && \WC()->cart && ! \WC()->cart->is_empty() ) {
            $this->log_trace( "DEBUG MODE: Forcing should_block_checkout() to TRUE (Expert #5 Test)" );
            error_log( '[WCQS] Cart_Guard: DEBUG MODE - Forcing should_block_checkout() to TRUE' );
            return true;
        }
        // -------------------------
        
        // Code original commenté pour le test
        /*
        // Vérifier que WooCommerce est disponible
        if ( ! function_exists( 'WC' ) || ! \WC() || ! \WC()->cart ) {
            return false;
        }
        
        if ( \WC()->cart->is_empty() ) {
            return false;
        }
        
        $pending_tests = $this->get_pending_tests_info();
        return ! empty( $pending_tests );
        */
        
        return false;
    }
    
    /**
     * Obtenir les informations sur les tests en attente
     * 
     * @return array Tableau des tests requis non validés
     */
    private function get_pending_tests_info(): array {
        if ( ! function_exists( 'WC' ) || ! \WC() || ! \WC()->cart ) {
            \error_log( 'WCQS Cart_Guard: WooCommerce not available' );
            return [];
        }
        
        $pending_tests = [];
        $current_user_id = \get_current_user_id();
        $cart_items = \WC()->cart->get_cart();
        
        \error_log( 'WCQS Cart_Guard: Checking ' . count( $cart_items ) . ' cart items for user ' . $current_user_id );
        
        foreach ( $cart_items as $cart_item_key => $cart_item ) {
            $product_id = $cart_item['product_id'];
            \error_log( 'WCQS Cart_Guard: Checking product ' . $product_id );
            
            // Vérifier si ce produit nécessite un test
            $mapping = WCQS_Mapping::get_for_product( $product_id );
            \error_log( 'WCQS Cart_Guard: Mapping for product ' . $product_id . ': ' . ( $mapping ? 'found' : 'not found' ) );
            
            if ( ! $mapping || empty( $mapping['active'] ) ) {
                \error_log( 'WCQS Cart_Guard: Product ' . $product_id . ' - no mapping or inactive' );
                continue;
            }
            
            // Vérifier si le test est déjà validé
            if ( $this->is_test_validated( $current_user_id, $product_id ) ) {
                \error_log( 'WCQS Cart_Guard: Product ' . $product_id . ' - test already validated' );
                continue;
            }
            
            \error_log( 'WCQS Cart_Guard: Product ' . $product_id . ' - adding to pending tests' );
            
            // Ajouter aux tests en attente
            $pending_tests[] = [
                'product_id' => $product_id,
                'product_name' => $cart_item['data']->get_name(),
                'test_url' => $this->get_test_url( $product_id, $cart_item_key ),
                'cart_item_key' => $cart_item_key,
                'mapping' => $mapping
            ];
        }
        
        \error_log( 'WCQS Cart_Guard: Found ' . count( $pending_tests ) . ' pending tests' );
        return $pending_tests;
    }
    
    /**
     * Vérifier si un test est validé pour un utilisateur/produit
     * 
     * @param int $user_id
     * @param int $product_id
     * @return bool
     */
    private function is_test_validated( int $user_id, int $product_id ): bool {
        // Cache pour éviter les répétitions
        $cache_key = "{$user_id}_{$product_id}";
        if ( isset( $this->user_validations[ $cache_key ] ) ) {
            return $this->user_validations[ $cache_key ];
        }
        
        $is_validated = false;
        
        // 1. Vérifier la session WooCommerce (priorité)
        if ( WCQS_Session::is_solved( $product_id ) ) {
            $is_validated = true;
        }
        
        // 2. Vérifier les user meta (fallback)
        if ( ! $is_validated && $user_id > 0 ) {
            $meta_key = "_wcqs_testpos_ok_{$product_id}";
            $meta_value = \get_user_meta( $user_id, $meta_key, true );
            
            if ( ! empty( $meta_value ) ) {
                // Vérifier que la validation n'est pas trop ancienne (24h)
                $validation_time = strtotime( $meta_value );
                $is_validated = ( $validation_time && ( \time() - $validation_time ) < \DAY_IN_SECONDS );
            }
        }
        
        // Mettre en cache
        $this->user_validations[ $cache_key ] = $is_validated;
        
        return $is_validated;
    }
    
    /**
     * Obtenir l'URL du test pour un produit
     * 
     * @param int $product_id
     * @param string $cart_item_key
     * @return string
     */
    private function get_test_url( int $product_id, string $cart_item_key ): string {
        $mapping = WCQS_Mapping::get_for_product( $product_id );
        
        if ( ! $mapping || empty( $mapping['page_id'] ) ) {
            return '';
        }
        
        $test_page_url = \get_permalink( $mapping['page_id'] );
        if ( ! $test_page_url ) {
            return '';
        }
        
        // Ajouter les paramètres nécessaires
        $params = [
            'wcqs_product_id' => $product_id,
            'wcqs_cart_key' => $cart_item_key,
            'wcqs_return' => \urlencode( \wc_get_cart_url() )
        ];
        
        return \add_query_arg( $params, $test_page_url );
    }
    
    /**
     * Afficher le message de checkout bloqué
     */
    public function render_blocked_checkout_message(): void {
        ?>
        <div class="wcqs-checkout-blocked" role="alert" aria-live="assertive">
            <div class="woocommerce-info">
                <strong><?php \esc_html_e( 'Test de positionnement requis', 'wc_qualiopi_steps' ); ?></strong>
                <p><?php \esc_html_e( 'Vous devez d\'abord valider le test de positionnement pour poursuivre votre commande.', 'wc_qualiopi_steps' ); ?></p>
            </div>
        </div>
        <?php
    }
    
    /**
     * Afficher la notice pour un test requis
     * 
     * @param array $test_info
     */
    private function render_test_notice( array $test_info ): void {
        $product_name = \esc_html( $test_info['product_name'] );
        $test_url = \esc_url( $test_info['test_url'] );
        
        if ( empty( $test_url ) ) {
            // Fallback si URL du test non disponible
            $this->render_test_unavailable_notice( $product_name );
            return;
        }
        
        ?>
        <div class="wcqs-test-notice" role="alert" aria-live="polite">
            <div class="woocommerce-message">
                <strong><?php echo \esc_html( \sprintf( \__( 'Test requis : %s', 'wc_qualiopi_steps' ), $product_name ) ); ?></strong>
                <p><?php \esc_html_e( 'Pour poursuivre, réalisez d\'abord le test de positionnement lié à cette formation.', 'wc_qualiopi_steps' ); ?></p>
                <p>
                    <a href="<?php echo $test_url; ?>" class="button wc-forward wcqs-test-cta">
                        <?php \esc_html_e( 'Passer le test', 'wc_qualiopi_steps' ); ?>
                    </a>
                </p>
            </div>
        </div>
        <?php
    }
    
    /**
     * Afficher une notice si le test n'est pas disponible
     * 
     * @param string $product_name
     */
    private function render_test_unavailable_notice( string $product_name ): void {
        ?>
        <div class="wcqs-test-unavailable" role="alert" aria-live="assertive">
            <div class="woocommerce-error">
                <strong><?php echo \esc_html( \sprintf( \__( 'Test temporairement indisponible : %s', 'wc_qualiopi_steps' ), $product_name ) ); ?></strong>
                <p><?php \esc_html_e( 'Le test de positionnement est temporairement indisponible. Contactez le support ou réessayez plus tard.', 'wc_qualiopi_steps' ); ?></p>
            </div>
        </div>
        <?php
    }
    
    /**
     * Charger les assets CSS/JS
     */
    public function enqueue_assets(): void {
        // Seulement sur la page panier (support URL française /panier/)
        if ( ! $this->is_cart_page() ) {
            return;
        }
        
        // CSS pour l'UX
        $css_version = WC_QUALIOPI_STEPS_VERSION;
        \wp_enqueue_style(
            'wcqs-cart-guard',
            \WC_QUALIOPI_STEPS_PLUGIN_URL . 'assets/css/cart-guard.css',
            [],
            $css_version
        );
    }
    
    /**
     * Méthode pour les tests : forcer la validation d'un test
     * 
     * @param int $user_id
     * @param int $product_id
     * @param bool $validated
     */
    public function force_test_validation( int $user_id, int $product_id, bool $validated = true ): void {
        $cache_key = "{$user_id}_{$product_id}";
        $this->user_validations[ $cache_key ] = $validated;
        
        if ( $validated ) {
            // Marquer en session aussi si WooCommerce est disponible
            if ( function_exists( 'WC' ) && \WC() ) {
                WCQS_Session::set_solved( $product_id, 30 );
            }
        } else {
            // Supprimer de la session si WooCommerce est disponible
            if ( function_exists( 'WC' ) && \WC() ) {
                WCQS_Session::unset_solved( $product_id );
            }
        }
    }
    
    /**
     * Méthode pour les tests : réinitialiser le cache
     */
    public function clear_cache(): void {
        $this->user_validations = [];
        $this->products_requiring_test = [];
    }
    
    /**
     * NOUVELLES MÉTHODES RECOMMANDÉES PAR LES 5 EXPERTS
     */
    
    /**
     * Log de trace pour diagnostic (Expert #5)
     */
    private function log_trace( string $message ): void {
        if ( function_exists( 'wc_get_logger' ) ) {
            wc_get_logger()->info( $message, [ 'source' => 'WCQS_Cart_Guard_Trace' ] );
        } else {
            error_log( "WCQS_Cart_Guard_Trace: " . $message );
        }
    }
    
    /**
     * Garde universelle par redirection (Expert #1)
     */
    public function guard_template_redirect(): void {
        if ( is_admin() || wp_doing_cron() || wp_doing_ajax() ) {
            return;
        }

        if ( ! $this->should_block_checkout() ) {
            return;
        }

        // Si on est sur le checkout, empêcher l'accès direct
        if ( $this->is_checkout_page() ) {
            $test_url = $this->get_test_page_url_for_cart();
            if ( $test_url ) {
                wp_safe_redirect( $test_url );
                exit;
            }
        }
    }
    
    /**
     * Hook alternatif avant les totaux (Expert #2)
     */
    public function maybe_add_test_notice_before_totals(): void {
        $this->log_trace( "Hook 'woocommerce_before_cart_totals' FIRED - ALTERNATIVE HOOK" );
        error_log( '[WCQS] Cart_Guard: maybe_add_test_notice_before_totals triggered' );
        $this->maybe_add_test_notice();
    }
    
    /**
     * Interception Store API pour WooCommerce Blocks (Expert #1)
     */
    public function intercept_store_api_checkout( $response, $handler, \WP_REST_Request $request ) {
        $route = $request->get_route();
        $method = $request->get_method();

        // Routes Checkout Store API
        if ( $method === 'POST' && preg_match( '#^/wc/store(/v[0-9]+)?/checkout$#', $route ) ) {
            if ( $this->should_block_checkout() ) {
                return new \WP_Error(
                    'wcqs_checkout_blocked',
                    __( 'Le test de positionnement doit être validé avant le paiement.', 'wc-qualiopi-steps' ),
                    [ 'status' => 403 ]
                );
            }
        }

        return $response;
    }
    
    /**
     * Notices serveur compatibles Blocks (Expert #1)
     */
    public function maybe_add_server_notice(): void {
        if ( $this->should_block_checkout() && ( is_cart() || $this->is_checkout_page() ) ) {
            wc_add_notice(
                __( 'Pour poursuivre, vous devez d\'abord réaliser le test de positionnement lié à cette formation.', 'wc-qualiopi-steps' ),
                'notice'
            );
        }
    }
    
    /**
     * Filtrer URL checkout (Expert #1)
     */
    public function filter_checkout_url_when_blocked( string $url ): string {
        if ( $this->should_block_checkout() ) {
            $test_url = $this->get_test_page_url_for_cart();
            if ( $test_url ) {
                return $test_url;
            }
        }
        return $url;
    }
    
    /**
     * Debug de l'état du panier (Expert #2)
     */
    public function debug_cart_state(): void {
        if ( ! $this->is_cart_page() ) {
            return;
        }
        
        $enforcement_enabled = $this->is_cart_enforcement_enabled();
        $should_block = $this->should_block_checkout();
        $cart_count = function_exists( 'WC' ) && WC() && WC()->cart ? WC()->cart->get_cart_contents_count() : 0;
        $pending_tests = $this->get_pending_tests_info();
        
        error_log( "[WCQS] Cart_Guard DEBUG: cart_page=true, enforcement={$enforcement_enabled}, should_block={$should_block}, cart_items={$cart_count}, pending_tests=" . count( $pending_tests ) );
        
        // Ajouter debug JavaScript
        ?>
        <script type="text/javascript">
        console.log('=== WCQS Cart_Guard Debug (Expert Fix) ===');
        console.log('Enforcement enabled:', <?php echo $enforcement_enabled ? 'true' : 'false'; ?>);
        console.log('Should block checkout:', <?php echo $should_block ? 'true' : 'false'; ?>);
        console.log('Cart items count:', <?php echo $cart_count; ?>);
        console.log('Pending tests count:', <?php echo count( $pending_tests ); ?>);
        console.log('Pending tests:', <?php echo json_encode( $pending_tests ); ?>);
        </script>
        <?php
    }
    
    /**
     * Helper pour obtenir l'URL de test pour le panier (Expert #1)
     */
    private function get_test_page_url_for_cart(): ?string {
        if ( ! function_exists( 'WC' ) || ! WC() || ! WC()->cart || WC()->cart->is_empty() ) {
            return null;
        }
        
        $item = reset( WC()->cart->get_cart() );
        if ( ! $item || empty( $item['product_id'] ) ) {
            return null;
        }
        
        $product_id = (int) $item['product_id'];
        $mapping = WCQS_Mapping::get_for_product( $product_id );
        
        if ( ! $mapping || empty( $mapping['active'] ) || empty( $mapping['page_id'] ) ) {
            return null;
        }
        
        $url = get_permalink( (int) $mapping['page_id'] );
        return $url ?: null;
    }
}
