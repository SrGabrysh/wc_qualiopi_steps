<?php
/**
 * Classe de désactivation du plugin WC Qualiopi Steps.
 *
 * @package WcQualiopiSteps\Core
 */

namespace WcQualiopiSteps\Core;

/**
 * Classe Deactivator
 * 
 * Gère la désactivation du plugin.
 */
class Deactivator {

	/**
	 * Méthode exécutée lors de la désactivation du plugin.
	 *
	 * @return void
	 */
	public static function run(): void {
		// Actions de désactivation (pour l'instant vide)
		// Ici on pourrait :
		// - Nettoyer les tâches cron
		// - Désactiver les hooks temporaires
		// - Sauvegarder l'état avant désactivation
		
		// Pour l'étape 0 : rien de spécifique à faire
	}
}
