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
		// Initialisation d'options par défaut si besoin (exemple).
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
		if ( false === get_option( 'wcqs_testpos_mapping', false ) ) {
			add_option( 'wcqs_testpos_mapping', array( '_version' => 1 ), '', 'no' );
		}

		// Planifier une tâche CRON si nécessaire (facultatif à l'étape 0).
		if ( ! wp_next_scheduled( 'wc_qualiopi_steps_daily_task' ) ) {
			wp_schedule_event( time(), 'daily', 'wc_qualiopi_steps_daily_task' );
		}

		// Flush des règles de réécriture.
		flush_rewrite_rules();

		// Log d'activation (dev).
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( '[wc_qualiopi_steps] Plugin activé avec succès.' );
		}
	}
}