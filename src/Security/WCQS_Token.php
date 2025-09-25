<?php
namespace WcQualiopiSteps\Security;

defined( 'ABSPATH' ) || exit;

/**
 * Gestion des jetons HMAC pour WC Qualiopi Steps
 * 
 * Jeton format : base64url(user_id:product_id:ts:nonce).hmac_sha256(payload, key)
 * TTL : 2 heures
 * Support rotation de clé avec acceptance temporaire N-1 pendant TTL
 * 
 * @package WcQualiopiSteps\Security
 */
class WCQS_Token {

	/**
	 * TTL du token en secondes (2 heures)
	 */
	const TOKEN_TTL = 7200;

	/**
	 * Nom de l'option pour la clé secrète
	 */
	const SECRET_OPTION = 'wcqs_hmac_secret';

	/**
	 * Nom de l'option pour la version de clé
	 */
	const KEY_VERSION_OPTION = 'wcqs_hmac_key_version';

	/**
	 * Cache statique pour éviter multiples get_option
	 */
	private static $secret_cache = null;
	private static $key_version_cache = null;
	private static $previous_secret_cache = null;

	/**
	 * Génère un jeton HMAC
	 *
	 * @param int    $user_id    ID utilisateur
	 * @param int    $product_id ID produit
	 * @param int    $timestamp  Timestamp (optionnel, current time par défaut)
	 * @param string $nonce      Nonce (optionnel, généré automatiquement)
	 * @return string Jeton HMAC complet
	 */
	public static function create( int $user_id, int $product_id, int $timestamp = null, string $nonce = '' ): string {
		if ( null === $timestamp ) {
			$timestamp = time();
		}
		
		if ( empty( $nonce ) ) {
			$nonce = wp_generate_password( 8, false );
		}

		// Payload : user_id:product_id:ts:nonce
		$payload = sprintf( '%d:%d:%d:%s', $user_id, $product_id, $timestamp, $nonce );
		
		// Encoder en base64url
		$encoded_payload = self::base64url_encode( $payload );
		
		// Générer signature HMAC
		$secret = self::get_secret();
		$signature = hash_hmac( 'sha256', $encoded_payload, $secret );
		
		return $encoded_payload . '.' . $signature;
	}

	/**
	 * Vérifie et décode un jeton HMAC
	 *
	 * @param string $token              Jeton à vérifier
	 * @param int    $expected_user_id   ID utilisateur attendu
	 * @param int    $expected_product_id ID produit attendu
	 * @param int    $max_age            Âge maximum en secondes (défaut: TOKEN_TTL)
	 * @return array|false Array avec payload décodé si valide, false sinon
	 */
	public static function verify( string $token, int $expected_user_id, int $expected_product_id, int $max_age = null ): array|false {
		if ( null === $max_age ) {
			$max_age = self::TOKEN_TTL;
		}

		// Séparer payload et signature
		$parts = explode( '.', $token );
		if ( count( $parts ) !== 2 ) {
			return false;
		}

		list( $encoded_payload, $signature ) = $parts;

		// Vérifier signature avec clé actuelle
		$secret = self::get_secret();
		$expected_signature = hash_hmac( 'sha256', $encoded_payload, $secret );
		
		$is_valid = hash_equals( $expected_signature, $signature );
		
		// Si échec, essayer avec clé précédente (rotation)
		if ( ! $is_valid ) {
			$previous_secret = self::get_previous_secret();
			if ( $previous_secret ) {
				$expected_signature_prev = hash_hmac( 'sha256', $encoded_payload, $previous_secret );
				$is_valid = hash_equals( $expected_signature_prev, $signature );
			}
		}

		if ( ! $is_valid ) {
			return false;
		}

		// Décoder payload
		$payload = self::base64url_decode( $encoded_payload );
		if ( false === $payload ) {
			return false;
		}

		$parts = explode( ':', $payload );
		if ( count( $parts ) !== 4 ) {
			return false;
		}

		list( $user_id, $product_id, $timestamp, $nonce ) = $parts;

		// Validation des types
		$user_id = (int) $user_id;
		$product_id = (int) $product_id;
		$timestamp = (int) $timestamp;

		// Vérifier correspondance user_id et product_id
		if ( $user_id !== $expected_user_id || $product_id !== $expected_product_id ) {
			return false;
		}

		// Vérifier TTL
		if ( ( time() - $timestamp ) > $max_age ) {
			return false;
		}

		return array(
			'user_id'    => $user_id,
			'product_id' => $product_id,
			'timestamp'  => $timestamp,
			'nonce'      => $nonce,
			'age'        => time() - $timestamp,
		);
	}

	/**
	 * Obtient la clé secrète (constante ou option)
	 *
	 * @return string
	 */
	private static function get_secret(): string {
		if ( null !== self::$secret_cache ) {
			return self::$secret_cache;
		}

		// 1. Priorité : constante wp-config.php
		if ( defined( 'WCQS_HMAC_KEY' ) && ! empty( constant( 'WCQS_HMAC_KEY' ) ) ) {
			self::$secret_cache = constant( 'WCQS_HMAC_KEY' );
			return self::$secret_cache;
		}

		// 2. Fallback : option générée
		$secret = get_option( self::SECRET_OPTION );
		if ( empty( $secret ) ) {
			$secret = self::generate_secret();
			update_option( self::SECRET_OPTION, $secret, false ); // autoload = no
			
			// Initialiser version de clé
			update_option( self::KEY_VERSION_OPTION, 1, false );
		}

		self::$secret_cache = $secret;
		return self::$secret_cache;
	}

	/**
	 * Obtient la clé précédente pour rotation
	 *
	 * @return string|null
	 */
	private static function get_previous_secret(): ?string {
		if ( null !== self::$previous_secret_cache ) {
			return self::$previous_secret_cache;
		}

		$previous_secret = get_option( 'wcqs_hmac_secret_prev', '' );
		self::$previous_secret_cache = $previous_secret ?: null;
		
		return self::$previous_secret_cache;
	}

	/**
	 * Génère une nouvelle clé secrète
	 *
	 * @return string
	 */
	private static function generate_secret(): string {
		return wp_generate_password( 64, true, true );
	}

	/**
	 * Effectue une rotation de clé
	 *
	 * @return bool Succès de la rotation
	 */
	public static function rotate_key(): bool {
		$current_secret = self::get_secret();
		$new_secret = self::generate_secret();
		
		// Sauvegarder ancienne clé
		update_option( 'wcqs_hmac_secret_prev', $current_secret, false );
		
		// Mettre à jour clé actuelle
		update_option( self::SECRET_OPTION, $new_secret, false );
		
		// Incrémenter version
		$current_version = (int) get_option( self::KEY_VERSION_OPTION, 1 );
		update_option( self::KEY_VERSION_OPTION, $current_version + 1, false );
		
		// Vider cache
		self::$secret_cache = null;
		self::$previous_secret_cache = null;
		
		return true;
	}

	/**
	 * Obtient la version actuelle de la clé
	 *
	 * @return int
	 */
	public static function get_key_version(): int {
		if ( null !== self::$key_version_cache ) {
			return self::$key_version_cache;
		}

		self::$key_version_cache = (int) get_option( self::KEY_VERSION_OPTION, 1 );
		return self::$key_version_cache;
	}

	/**
	 * Encode en base64url (URL-safe)
	 *
	 * @param string $data
	 * @return string
	 */
	private static function base64url_encode( string $data ): string {
		return rtrim( strtr( base64_encode( $data ), '+/', '-_' ), '=' );
	}

	/**
	 * Décode depuis base64url
	 *
	 * @param string $data
	 * @return string|false
	 */
	private static function base64url_decode( string $data ): string|false {
		return base64_decode( strtr( $data, '-_', '+/' ) );
	}

	/**
	 * Nettoie le cache statique (pour tests)
	 */
	public static function clear_cache(): void {
		self::$secret_cache = null;
		self::$key_version_cache = null;
		self::$previous_secret_cache = null;
	}
}
