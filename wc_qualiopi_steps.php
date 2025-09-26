<?php
/**
 * Plugin Name: WC Qualiopi Steps
 * Plugin URI: https://github.com/SrGabrysh/wc_qualiopi_steps
 * Description: WC Qualiopi Steps est un plugin WooCommerce qui impose un test de positionnement (Qualiopi) avant paiement. Mapping produit→page de test via page d’options, jeton HMAC + session, garde checkout, logs d’audit et page fallback. Développement step-by-step, SRP, UX accessible.
 * Version: 0.6.12
 * Author: TB-Web
 * Author URI: https://tb-web.fr
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wc_qualiopi_steps
 * Domain Path: /languages
 * Requires at least: 6.6
 * Tested up to: 6.7
 * Requires PHP: 8.1
 * Network: false
 */

// Sécurité : Empêcher l'accès direct.
defined( 'ABSPATH' ) || exit;

// Constantes du plugin avec vérification pour éviter les warnings "already defined"
if ( ! defined( 'WC_QUALIOPI_STEPS_VERSION' ) ) {
	define( 'WC_QUALIOPI_STEPS_VERSION', '0.6.12' );
}
if ( ! defined( 'WC_QUALIOPI_STEPS_PLUGIN_FILE' ) ) {
	define( 'WC_QUALIOPI_STEPS_PLUGIN_FILE', __FILE__ );
}
if ( ! defined( 'WC_QUALIOPI_STEPS_PLUGIN_DIR' ) ) {
	define( 'WC_QUALIOPI_STEPS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
}
if ( ! defined( 'WC_QUALIOPI_STEPS_PLUGIN_URL' ) ) {
	define( 'WC_QUALIOPI_STEPS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
}

// Vérifications préalables propres
function wc_qualiopi_steps_check_requirements() {
	$errors = array();
	
	// Vérifier PHP 8.1+
	if ( version_compare( PHP_VERSION, '8.1', '<' ) ) {
		$errors[] = sprintf(
			__( 'WC Qualiopi Steps requires PHP 8.1 or higher. Current version: %s', 'wc_qualiopi_steps' ),
			PHP_VERSION
		);
	}
	
	// Vérifier autoload Composer
	if ( ! file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
		$errors[] = __( 'WC Qualiopi Steps: Composer autoload not found. Run "composer install" in the plugin directory.', 'wc_qualiopi_steps' );
	}
	
	// Afficher les erreurs via admin notices
	if ( ! empty( $errors ) ) {
		add_action( 'admin_notices', function() use ( $errors ) {
			foreach ( $errors as $error ) {
				echo '<div class="notice notice-error"><p>' . esc_html( $error ) . '</p></div>';
			}
		});
		return false;
	}
	
	return true;
}

// Chargement de Composer avec vérification
if ( wc_qualiopi_steps_check_requirements() && file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
	require_once __DIR__ . '/vendor/autoload.php';
} else {
	// Arrêter le chargement du plugin si les requirements ne sont pas remplis
	return;
}

// Initialisation du plugin.
add_action(
	'plugins_loaded',
	function() {
		if ( class_exists( '\\WcQualiopiSteps\\Core\\Plugin' ) ) {
			\WcQualiopiSteps\Core\Plugin::get_instance();
		}
	}
);

// Hook d'activation.
register_activation_hook( __FILE__, array( 'WcQualiopiSteps\\Core\\Activator', 'run' ) );

// Hook de désactivation.
register_deactivation_hook( __FILE__, array( 'WcQualiopiSteps\\Core\\Deactivator', 'run' ) );
