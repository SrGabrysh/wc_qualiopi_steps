<?php
/**
 * Plugin Name: WC Qualiopi Steps
 * Plugin URI: https://github.com/SrGabrysh/wc_qualiopi_steps
 * Description: WC Qualiopi Steps est un plugin WooCommerce qui impose un test de positionnement (Qualiopi) avant paiement. Mapping produit→page de test via page d’options, jeton HMAC + session, garde checkout, logs d’audit et page fallback. Développement step-by-step, SRP, UX accessible.
 * Version: 1.0.2
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

// Constantes du plugin.
define( 'WC_QUALIOPI_STEPS_VERSION', '1.0.2' );
define( 'WC_QUALIOPI_STEPS_PLUGIN_FILE', __FILE__ );
define( 'WC_QUALIOPI_STEPS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WC_QUALIOPI_STEPS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Chargement de Composer.
if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
	require_once __DIR__ . '/vendor/autoload.php';
}

// Initialisation du plugin.
add_action(
	'plugins_loaded',
	function() {
		if ( class_exists( '\\WcQualiopiSteps\\Core\\Plugin' ) ) {
			\\WcQualiopiSteps\\Core\\Plugin::get_instance();
		}
	}
);

// Hook d'activation.
register_activation_hook( __FILE__, array( 'WcQualiopiSteps\\Core\\Activator', 'run' ) );

// Hook de désactivation.
register_deactivation_hook( __FILE__, array( 'WcQualiopiSteps\\Core\\Deactivator', 'run' ) );
