<?php
/**
 * Cart Guard - UX panier pour WC Qualiopi Steps
 * 
 * Masque le bouton "Commander" si test non validé et affiche le CTA "Passer le test"
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
     */
    private function init_hooks(): void {
        // Seulement si le flag enforce_cart est activé
        if ( ! $this->is_cart_enforcement_enabled() ) {
            return;
        }
        
        // Hook principal : modifier l'affichage du bouton checkout
        \add_action( 'woocommerce_proceed_to_checkout', [ $this, 'maybe_replace_checkout_button' ], 5 );
        
        // Hook pour ajouter les notices et CTA
        \add_action( 'woocommerce_proceed_to_checkout', [ $this, 'maybe_add_test_notice' ], 10 );
        
        // Styles et scripts
        \add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );
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
     * Remplacer le bouton checkout si nécessaire
     * 
     * Supprime le bouton "Commander" du DOM si un test est requis et non validé
     */
    public function maybe_replace_checkout_button(): void {
        if ( ! $this->should_block_checkout() ) {
            return;
        }
        
        // Supprimer le bouton checkout par défaut
        \remove_action( 'woocommerce_proceed_to_checkout', 'woocommerce_button_proceed_to_checkout', 20 );
        
        // Ajouter notre message personnalisé à la place
        \add_action( 'woocommerce_proceed_to_checkout', [ $this, 'render_blocked_checkout_message' ], 20 );
    }
    
    /**
     * Ajouter la notice de test requis
     */
    public function maybe_add_test_notice(): void {
        $pending_tests = $this->get_pending_tests_info();
        
        if ( empty( $pending_tests ) ) {
            return;
        }
        
        // Afficher la notice pour chaque test requis
        foreach ( $pending_tests as $test_info ) {
            $this->render_test_notice( $test_info );
        }
    }
    
    /**
     * Déterminer si le checkout doit être bloqué
     * 
     * @return bool
     */
    private function should_block_checkout(): bool {
        // Vérifier que WooCommerce est disponible
        if ( ! function_exists( 'WC' ) || ! \WC() || ! \WC()->cart ) {
            return false;
        }
        
        if ( \WC()->cart->is_empty() ) {
            return false;
        }
        
        $pending_tests = $this->get_pending_tests_info();
        return ! empty( $pending_tests );
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
        // Seulement sur la page panier
        if ( ! \is_cart() ) {
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
}
