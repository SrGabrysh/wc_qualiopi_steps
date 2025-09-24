<?php
namespace WcQualiopiSteps\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Classe d'activation du plugin WC Qualiopi Steps
 *
 * @package WcQualiopiSteps
 */
class Activator {

	/**
	 * Méthode exécutée lors de l'activation du plugin.
	 *
	 * @return void
	 */
	public static function run(): void {
		// Initialisation des options par défaut (step 0)
		if ( false === get_option( 'wcqs_flags', false ) ) {
			add_option(
				'wcqs_flags',
				array(
					'enforce_cart'     => false,
					'enforce_checkout' => false,
					'logging'          => true,
				),
				'',
				'no'
			);
		}
		
		// Réserver l'option mapping pour l'étape 1
		if ( false === get_option( 'wcqs_testpos_mapping', false ) ) {
			add_option( 'wcqs_testpos_mapping', array( '_version' => 1 ), '', 'no' );
		}

		// Log d'activation (dev uniquement)
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( '[wc_qualiopi_steps] Plugin activé avec succès - Step 0.' );
		}
	}
}