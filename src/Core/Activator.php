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
		self::create_default_options();
		flush_rewrite_rules();

		// Log d'activation (dev uniquement)
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( '[wc_qualiopi_steps] Plugin activé avec succès - v' . WC_QUALIOPI_STEPS_VERSION );
		}
	}

	/**
	 * Crée les options par défaut avec le bon format et autoload
	 */
	private static function create_default_options(): void {
		// Flags par défaut (garde-fou)
		if ( get_option( 'wcqs_flags', null ) === null ) {
			add_option( 'wcqs_flags', array(
				'enforce_cart'     => false,
				'enforce_checkout' => false,
				'logging'          => true,
			), '', 'no' );
		}

		// Mapping central : DOIT être un tableau sérialisé et autoload = no
		$existing = get_option( 'wcqs_testpos_mapping', null );

		if ( $existing === null ) {
			// Valeur minimale conforme aux specs : versionnage + structure tableau
			add_option( 'wcqs_testpos_mapping', array( '_version' => 1 ), '', 'no' );
		} else {
			// Si une valeur existe déjà, on force autoload=no et on normalise le type au format array
			self::normalize_mapping_option( $existing );
		}
	}

	/**
	 * Normalise l'option wcqs_testpos_mapping :
	 * - si string JSON → json_decode puis update_option($array, autoload=false)
	 * - si array → re-save pour imposer autoload=no (si nécessaire)
	 */
	private static function normalize_mapping_option( $raw ): void {
		$normalized = null;

		if ( is_string( $raw ) ) {
			// Cas bug actuel : valeur stockée en JSON string → décoder
			$decoded = json_decode( $raw, true );
			if ( json_last_error() === JSON_ERROR_NONE && is_array( $decoded ) ) {
				$normalized = $decoded;
			} else {
				// Valeur illisible → repartir sur une base saine
				$normalized = array( '_version' => 1 );
			}
		} elseif ( is_array( $raw ) ) {
			$normalized = $raw;
		} else {
			// Types inattendus → valeur minimale
			$normalized = array( '_version' => 1 );
		}

		// IMPORTANT : forcer autoload=no via update_option(..., false)
		// Toujours forcer la mise à jour pour corriger l'autoload même si la valeur est identique
		update_option( 'wcqs_testpos_mapping', $normalized, false );
		
		// Double vérification : forcer explicitement l'autoload avec une requête SQL si nécessaire
		global $wpdb;
		$wpdb->update( 
			$wpdb->options, 
			array( 'autoload' => 'no' ), 
			array( 'option_name' => 'wcqs_testpos_mapping' ),
			array( '%s' ),
			array( '%s' )
		);
	}
}