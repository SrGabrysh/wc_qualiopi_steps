<?php
namespace WcQualiopiSteps\Utils;

defined( 'ABSPATH' ) || exit;

/**
 * Gestion des sessions WooCommerce pour WC Qualiopi Steps
 * 
 * Utilise les sessions natives WC()->session comme backup des jetons HMAC
 * TTL logique : 30 minutes (timestamp + vérification côté code)
 * Clés session : wcqs_testpos_solved_<product_id>
 * 
 * @package WcQualiopiSteps\Utils
 */
class WCQS_Session {

	/**
	 * TTL de session en secondes (30 minutes)
	 */
	const SESSION_TTL = 1800;

	/**
	 * Préfixe pour les clés de session
	 */
	const SESSION_PREFIX = 'wcqs_testpos_solved_';

	/**
	 * Marque un produit comme validé en session
	 *
	 * @param int $product_id ID du produit
	 * @param int $ttl        TTL en secondes (optionnel)
	 * @return bool Succès de l'opération
	 */
	public static function set_solved( int $product_id, int $ttl = null ): bool {
		if ( null === $ttl ) {
			$ttl = self::SESSION_TTL;
		}

		if ( ! self::is_wc_session_available() ) {
			return false;
		}

		$key = self::SESSION_PREFIX . $product_id;
		$value = array(
			'solved'    => true,
			'timestamp' => time(),
			'expires'   => time() + $ttl,
			'product_id' => $product_id,
		);

		WC()->session->set( $key, $value );
		return true;
	}

	/**
	 * Vérifie si un produit est marqué comme validé
	 *
	 * @param int $product_id ID du produit
	 * @return bool True si validé et non expiré
	 */
	public static function is_solved( int $product_id ): bool {
		if ( ! self::is_wc_session_available() ) {
			return false;
		}

		$key = self::SESSION_PREFIX . $product_id;
		$value = WC()->session->get( $key );

		if ( ! is_array( $value ) || empty( $value['solved'] ) ) {
			return false;
		}

		// Vérifier expiration
		$expires = isset( $value['expires'] ) ? (int) $value['expires'] : 0;
		if ( time() > $expires ) {
			// Session expirée, nettoyer
			self::unset_solved( $product_id );
			return false;
		}

		return true;
	}

	/**
	 * Supprime la marque de validation pour un produit
	 *
	 * @param int $product_id ID du produit
	 * @return bool Succès de l'opération
	 */
	public static function unset_solved( int $product_id ): bool {
		if ( ! self::is_wc_session_available() ) {
			return false;
		}

		$key = self::SESSION_PREFIX . $product_id;
		WC()->session->__unset( $key );
		return true;
	}

	/**
	 * Obtient les détails de session pour un produit
	 *
	 * @param int $product_id ID du produit
	 * @return array|null Détails de session ou null si non trouvé
	 */
	public static function get_session_details( int $product_id ): ?array {
		if ( ! self::is_wc_session_available() ) {
			return null;
		}

		$key = self::SESSION_PREFIX . $product_id;
		$value = WC()->session->get( $key );

		if ( ! is_array( $value ) ) {
			return null;
		}

		// Ajouter informations calculées
		$value['is_expired'] = isset( $value['expires'] ) && time() > $value['expires'];
		$value['age'] = isset( $value['timestamp'] ) ? time() - $value['timestamp'] : 0;
		$value['remaining_ttl'] = isset( $value['expires'] ) ? max( 0, $value['expires'] - time() ) : 0;

		return $value;
	}

	/**
	 * Nettoie toutes les sessions expirées
	 *
	 * @return int Nombre de sessions nettoyées
	 */
	public static function cleanup_expired(): int {
		if ( ! self::is_wc_session_available() ) {
			return 0;
		}

		$session_data = WC()->session->get_session_data();
		$cleaned = 0;

		foreach ( $session_data as $key => $value ) {
			if ( strpos( $key, self::SESSION_PREFIX ) !== 0 ) {
				continue;
			}

			if ( ! is_array( $value ) ) {
				continue;
			}

			$expires = isset( $value['expires'] ) ? (int) $value['expires'] : 0;
			if ( time() > $expires ) {
				WC()->session->__unset( $key );
				$cleaned++;
			}
		}

		return $cleaned;
	}

	/**
	 * Obtient toutes les sessions actives de test
	 *
	 * @return array Sessions actives indexées par product_id
	 */
	public static function get_all_active_sessions(): array {
		if ( ! self::is_wc_session_available() ) {
			return array();
		}

		$session_data = WC()->session->get_session_data();
		$active_sessions = array();

		foreach ( $session_data as $key => $value ) {
			if ( strpos( $key, self::SESSION_PREFIX ) !== 0 ) {
				continue;
			}

			if ( ! is_array( $value ) || empty( $value['solved'] ) ) {
				continue;
			}

			// Extraire product_id de la clé
			$product_id = (int) str_replace( self::SESSION_PREFIX, '', $key );
			
			// Vérifier expiration
			$expires = isset( $value['expires'] ) ? (int) $value['expires'] : 0;
			if ( time() <= $expires ) {
				$value['product_id'] = $product_id;
				$value['is_expired'] = false;
				$value['age'] = isset( $value['timestamp'] ) ? time() - $value['timestamp'] : 0;
				$value['remaining_ttl'] = max( 0, $expires - time() );
				
				$active_sessions[ $product_id ] = $value;
			}
		}

		return $active_sessions;
	}

	/**
	 * Prolonge la durée de vie d'une session
	 *
	 * @param int $product_id ID du produit
	 * @param int $additional_ttl TTL supplémentaire en secondes
	 * @return bool Succès de l'opération
	 */
	public static function extend_session( int $product_id, int $additional_ttl = null ): bool {
		if ( null === $additional_ttl ) {
			$additional_ttl = self::SESSION_TTL;
		}

		if ( ! self::is_wc_session_available() ) {
			return false;
		}

		$key = self::SESSION_PREFIX . $product_id;
		$value = WC()->session->get( $key );

		if ( ! is_array( $value ) || empty( $value['solved'] ) ) {
			return false;
		}

		// Prolonger l'expiration
		$value['expires'] = time() + $additional_ttl;
		WC()->session->set( $key, $value );

		return true;
	}

	/**
	 * Nettoie toutes les sessions pour un produit spécifique
	 *
	 * @param int $product_id ID du produit
	 * @return bool Succès de l'opération
	 */
	public static function force_clear_product( int $product_id ): bool {
		if ( ! self::is_wc_session_available() ) {
			return false;
		}

		// Nettoyer TOUTES les clés possibles pour ce produit
		$keys_to_clear = [
			self::SESSION_PREFIX . $product_id,
			'wcqs_solved_tests',
			'wcqs_testpos_solved_' . $product_id,
			'wcqs_test_' . $product_id,
			'testpos_solved_' . $product_id
		];

		foreach ( $keys_to_clear as $key ) {
			WC()->session->__unset( $key );
		}

		// Nettoyer aussi le tableau général si présent
		$solved_tests = WC()->session->get( 'wcqs_solved_tests' );
		if ( is_array( $solved_tests ) ) {
			unset( $solved_tests[ $product_id ] );
			WC()->session->set( 'wcqs_solved_tests', $solved_tests );
		}

		// Forcer la sauvegarde immédiate
		if ( WC()->session && method_exists( WC()->session, 'save_data' ) ) {
			WC()->session->save_data();
		}

		return true;
	}

	/**
	 * Vérifie si les sessions WooCommerce sont disponibles
	 *
	 * @return bool True si WC()->session est disponible
	 */
	private static function is_wc_session_available(): bool {
		return function_exists( 'WC' ) && WC()->session !== null;
	}

	/**
	 * Obtient des statistiques sur les sessions
	 *
	 * @return array Statistiques des sessions
	 */
	public static function get_session_stats(): array {
		if ( ! self::is_wc_session_available() ) {
			return array(
				'total' => 0,
				'active' => 0,
				'expired' => 0,
			);
		}

		$session_data = WC()->session->get_session_data();
		$total = 0;
		$active = 0;
		$expired = 0;

		foreach ( $session_data as $key => $value ) {
			if ( strpos( $key, self::SESSION_PREFIX ) !== 0 ) {
				continue;
			}

			$total++;

			if ( ! is_array( $value ) || empty( $value['solved'] ) ) {
				continue;
			}

			$expires = isset( $value['expires'] ) ? (int) $value['expires'] : 0;
			if ( time() <= $expires ) {
				$active++;
			} else {
				$expired++;
			}
		}

		return array(
			'total' => $total,
			'active' => $active,
			'expired' => $expired,
		);
	}
}
