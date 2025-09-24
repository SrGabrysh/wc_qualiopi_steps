<?php
/**
 * Classe principale du plugin WC Qualiopi Steps
 *
 * @package WcQualiopiSteps
 */

defined( 'ABSPATH' ) || exit;

namespace WcQualiopiSteps\Core;

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
	const VERSION = '1.0.0';

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
