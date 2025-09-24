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
	const VERSION = '0.2.0';

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
		// Hooks WordPress.
		add_action( 'init', array( $this, 'on_init' ) );
		add_action( 'admin_init', array( $this, 'on_admin_init' ) );

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
		// Charger les modules selon les besoins.
	}

	/**
	 * Récupère la version du plugin
	 *
	 * @return string
	 */
	public function get_version() {
		return self::VERSION;
	}
}
