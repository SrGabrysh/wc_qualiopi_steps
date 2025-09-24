<?php
namespace WcQualiopiSteps\Core;

/**
 * Classe de désactivation du plugin WC Qualiopi Steps.
 *
 * @package WcQualiopiSteps\Core
 */
class Deactivator {

	/**
	 * Méthode exécutée lors de la désactivation du plugin.
	 *
	 * @return void
	 */
	public static function run(): void {
		// Step 0 : Désactivation propre sans suppression d'options
		// Les options sont conservées pour réactivation
		
		// Log de désactivation (dev uniquement)
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( '[wc_qualiopi_steps] Plugin désactivé - Options conservées.' );
		}
	}
}