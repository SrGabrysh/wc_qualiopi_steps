<?php
namespace WcQualiopiSteps\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Classe principale du plugin WC Qualiopi Steps
 *
 * @package WcQualiopiSteps
 */

/**
 * Classe principale du plugin
 */
class Plugin {

	/**
	 * Instance unique (Singleton)
	 *
	 * @var Plugin|null
	 */
	private static $instance = null;

	/**
	 * Version du plugin
	 */
	const VERSION = '0.6.0';

	/**
	 * Flags par défaut du plugin
	 */
	const DEFAULT_FLAGS = array(
		'enforce_cart'     => false, // étape 3
		'enforce_checkout' => false, // étape 4
		'logging'          => true,  // étape 8
	);

	/**
	 * Helper central pour récupérer les flags avec merge des defaults
	 *
	 * @param string|null $flag_name Nom du flag spécifique ou null pour tous
	 * @return mixed Valeur du flag ou array de tous les flags
	 */
	public static function get_flags( $flag_name = null ) {
		$stored_flags = get_option( 'wcqs_flags', array() );
		$flags = wp_parse_args( $stored_flags, self::DEFAULT_FLAGS );

		if ( null !== $flag_name ) {
			return isset( $flags[ $flag_name ] ) ? $flags[ $flag_name ] : null;
		}

		return $flags;
	}

	/**
	 * Helper pour mettre à jour un flag spécifique
	 *
	 * @param string $flag_name Nom du flag
	 * @param mixed  $value Nouvelle valeur
	 * @return bool Succès de la mise à jour
	 */
	public static function set_flag( $flag_name, $value ) {
		$flags = self::get_flags();
		$flags[ $flag_name ] = $value;
		return update_option( 'wcqs_flags', $flags );
	}

	/**
	 * Constructeur privé (Singleton)
	 */
	private function __construct() {
		$this->init();
	}

	/**
	 * Récupère l'instance unique
	 *
	 * @return Plugin
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Initialisation du plugin
	 */
	private function init() {
		// Normalisation des options au chargement
		$this->normalize_options();

		// Hooks WordPress.
		add_action( 'init', array( $this, 'on_init' ) );
		add_action( 'admin_init', array( $this, 'on_admin_init' ) );

		// Hooks admin pour l'étape 1
		if ( is_admin() ) {
			add_action( 'admin_menu', array( $this, 'register_admin_pages' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );

		// Initialise AJAX handlers
		\WcQualiopiSteps\Admin\Ajax_Handler::init();
		\WcQualiopiSteps\Admin\Csv_Handler::init();
		\WcQualiopiSteps\Admin\Live_Control::init();
	}

		// Chargement des modules.
		$this->load_modules();
	}

	/**
	 * Hook init de WordPress
	 */
	public function on_init() {
		// Chargement des traductions.
		load_plugin_textdomain(
			'wc_qualiopi_steps',
			false,
			dirname( plugin_basename( WC_QUALIOPI_STEPS_PLUGIN_FILE ) ) . '/languages'
		);
	}

	/**
	 * Hook admin_init de WordPress
	 */
	public function on_admin_init() {
		// Code d'initialisation admin.
	}

	/**
	 * Chargement des modules du plugin
	 */
	private function load_modules() {
		// Initialiser les utilitaires de l'étape 2
		$this->init_step2_utilities();
		
		// Initialiser le garde du panier de l'étape 3
		$this->init_step3_cart_guard();
	}

	/**
	 * Initialise les utilitaires de l'étape 2 (Token, Session, Mapping)
	 */
	private function init_step2_utilities() {
		// Les classes sont chargées automatiquement via PSR-4
		// Elles sont disponibles statiquement, pas besoin d'instanciation
		
		// Vérifier la disponibilité des classes
		if ( ! class_exists( '\\WcQualiopiSteps\\Security\\WCQS_Token' ) ) {
			add_action( 'admin_notices', function() {
				echo '<div class="notice notice-error"><p>' 
				   . esc_html__( 'WCQS: Token class not found. Check autoload.', 'wc_qualiopi_steps' )
				   . '</p></div>';
			});
		}

		if ( ! class_exists( '\\WcQualiopiSteps\\Utils\\WCQS_Session' ) ) {
			add_action( 'admin_notices', function() {
				echo '<div class="notice notice-error"><p>' 
				   . esc_html__( 'WCQS: Session class not found. Check autoload.', 'wc_qualiopi_steps' )
				   . '</p></div>';
			});
		}

		if ( ! class_exists( '\\WcQualiopiSteps\\Utils\\WCQS_Mapping' ) ) {
			add_action( 'admin_notices', function() {
				echo '<div class="notice notice-error"><p>' 
				   . esc_html__( 'WCQS: Mapping class not found. Check autoload.', 'wc_qualiopi_steps' )
				   . '</p></div>';
			});
		}
	}

	/**
	 * Initialise le garde du panier de l'étape 3 (Cart Guard)
	 */
	private function init_step3_cart_guard() {
		// Vérifier la disponibilité de la classe
		if ( ! class_exists( '\\WcQualiopiSteps\\Frontend\\Cart_Guard' ) ) {
			add_action( 'admin_notices', function() {
				echo '<div class="notice notice-error"><p>' 
				   . esc_html__( 'WCQS: Cart_Guard class not found. Check autoload.', 'wc_qualiopi_steps' )
				   . '</p></div>';
			});
			return;
		}

		// Initialiser le garde du panier (singleton)
		// Seulement côté front-end et si WooCommerce est actif
		if ( ! is_admin() && class_exists( 'WooCommerce' ) ) {
			\WcQualiopiSteps\Frontend\Cart_Guard::get_instance();
		}
	}

	/**
	 * Récupère la version du plugin
	 *
	 * @return string
	 */
	public function get_version() {
		return self::VERSION;
	}

	/**
	 * Normalise les options au chargement pour corriger les formats incorrects
	 */
	private function normalize_options(): void {
		$raw = get_option( 'wcqs_testpos_mapping', null );
		if ( $raw !== null ) {
			$this->normalize_mapping_option( $raw );
		}
	}

	/**
	 * Normalise l'option wcqs_testpos_mapping
	 */
	private function normalize_mapping_option( $raw ): void {
		$normalized = null;

		if ( is_string( $raw ) ) {
			// Cas bug actuel : valeur stockée en JSON string → décoder
			$decoded = json_decode( $raw, true );
			if ( json_last_error() === JSON_ERROR_NONE && is_array( $decoded ) ) {
				$normalized = $decoded;
			} else {
				// Valeur illisible → repartir sur une base saine
				$normalized = array( '_version' => 1 );
			}
		} elseif ( is_array( $raw ) ) {
			$normalized = $raw;
		} else {
			// Types inattendus → valeur minimale
			$normalized = array( '_version' => 1 );
		}

		// IMPORTANT : forcer autoload=no via update_option(..., false)
		// Toujours forcer la mise à jour pour corriger l'autoload même si la valeur est identique
		update_option( 'wcqs_testpos_mapping', $normalized, false );
		
		// Double vérification : forcer explicitement l'autoload avec une requête SQL si nécessaire
		global $wpdb;
		$wpdb->update( 
			$wpdb->options, 
			array( 'autoload' => 'no' ), 
			array( 'option_name' => 'wcqs_testpos_mapping' ),
			array( '%s' ),
			array( '%s' )
		);
	}

	/**
	 * Helper sûr pour lire le mapping (toujours un array)
	 */
	public static function get_mapping(): array {
		$raw = get_option( 'wcqs_testpos_mapping', array() );
		if ( is_array( $raw ) ) {
			return $raw;
		}
		$decoded = json_decode( (string) $raw, true );
		if ( json_last_error() === JSON_ERROR_NONE && is_array( $decoded ) ) {
			// auto-réparation en base pour éviter de retomber dans le piège
			update_option( 'wcqs_testpos_mapping', $decoded, false );
			return $decoded;
		}
		// Valeur corrompue → base minimale
		update_option( 'wcqs_testpos_mapping', array( '_version' => 1 ), false );
		return array( '_version' => 1 );
	}

	/**
	 * Enregistre les pages admin
	 */
	public function register_admin_pages(): void {
		// Sécurise le chargement de la page (évite Class Not Found si autoload/chemin KO)
		$file = WC_QUALIOPI_STEPS_PLUGIN_DIR . 'src/Admin/Settings_Page.php';
		if ( file_exists( $file ) ) {
			require_once $file;
		} else {
			add_action( 'admin_notices', static function () {
				echo '<div class="notice notice-error"><p>'
				   . esc_html__( 'WCQS: fichier Settings_Page.php introuvable. Déployez src/Admin/Settings_Page.php.', 'wc_qualiopi_steps' )
				   . '</p></div>';
			} );
			return;
		}

		// Page sous Réglages
		add_options_page(
			__( 'Tests de positionnement', 'wc_qualiopi_steps' ),
			__( 'WC Qualiopi Steps', 'wc_qualiopi_steps' ),
			'manage_woocommerce',
			'wcqs-settings',
			array( '\\WcQualiopiSteps\\Admin\\Settings_Page', 'render_page' )
		);
	}

	/**
	 * Charge les assets admin
	 */
	public function enqueue_admin_assets( string $hook ): void {
		// Ne charger que sur notre page
		if ( $hook !== 'settings_page_wcqs-settings' ) {
			return;
		}

		// CORRECTIF: Charger jQuery UI Dialog pour éviter les erreurs LearnDash
		wp_enqueue_script( 'jquery-ui-dialog' );
		wp_enqueue_style( 'wp-jquery-ui-dialog' );

		wp_enqueue_style(
			'wcqs-admin',
			plugins_url( 'assets/admin/settings.css', WC_QUALIOPI_STEPS_PLUGIN_FILE ),
			array(),
			WC_QUALIOPI_STEPS_VERSION
		);

		wp_enqueue_script(
			'wcqs-admin',
			plugins_url( 'assets/admin/settings.js', WC_QUALIOPI_STEPS_PLUGIN_FILE ),
			array( 'jquery', 'jquery-ui-dialog' ),
			WC_QUALIOPI_STEPS_VERSION,
			true
		);

		// Variables AJAX pour JavaScript
		wp_localize_script( 'wcqs-admin', 'wcqsAjax', array(
			'ajaxurl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'wcqs_ajax_nonce' )
		) );
	}
}
