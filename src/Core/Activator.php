<?php
/**
 * Classe d'activation du plugin WC Qualiopi Steps
 *
 * @package WcQualiopiSteps
 */

defined( 'ABSPATH' ) || exit;

namespace WcQualiopiSteps\Core;

/**
 * Classe d'activation du plugin
 */
class Activator {

	/**
	 * Actions à effectuer lors de l'activation du plugin
	 */
	public static function run() {
		// Créer les options par défaut.
		add_option( 'wc_qualiopi_steps_version', '1.0.0' );
		add_option( 'wc_qualiopi_steps_settings', array() );

		// Planifier les tâches CRON si nécessaire.
		if ( ! wp_next_scheduled( 'wc_qualiopi_steps_daily_task' ) ) {
			wp_schedule_event( time(), 'daily', 'wc_qualiopi_steps_daily_task' );
		}

		// Flush des règles de réécriture.
		flush_rewrite_rules();

		// Log d'activation.
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( '[wc_qualiopi_steps] Plugin activé avec succès.' );
		}
	}
}
