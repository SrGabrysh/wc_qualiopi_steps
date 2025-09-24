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
		// Exemple : déplanifier la tâche CRON si elle existe.
		$ts = wp_next_scheduled( 'wc_qualiopi_steps_daily_task' );
		if ( $ts ) {
			wp_unschedule_event( $ts, 'wc_qualiopi_steps_daily_task' );
		}

		// Pas de suppression d'options à la désactivation.
		flush_rewrite_rules();
	}
}