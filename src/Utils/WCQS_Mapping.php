<?php
namespace WcQualiopiSteps\Utils;

defined( 'ABSPATH' ) || exit;

/**
 * Gestion du mapping produit → page de test pour WC Qualiopi Steps
 * 
 * Accès optimisé à l'option wcqs_testpos_mapping avec cache statique
 * Helpers pour récupérer les configurations de test par produit
 * 
 * @package WcQualiopiSteps\Utils
 */
class WCQS_Mapping {

	/**
	 * Nom de l'option contenant le mapping
	 */
	const MAPPING_OPTION = 'wcqs_testpos_mapping';

	/**
	 * Cache statique pour éviter multiples get_option
	 */
	private static $mapping_cache = null;

	/**
	 * Obtient le mapping complet avec cache
	 *
	 * @return array Mapping complet
	 */
	public static function get_mapping(): array {
		if ( null !== self::$mapping_cache ) {
			return self::$mapping_cache;
		}

		$raw = get_option( self::MAPPING_OPTION, array() );
		
		// Utiliser la méthode de normalisation du Plugin principal
		if ( class_exists( '\\WcQualiopiSteps\\Core\\Plugin' ) ) {
			$mapping = \WcQualiopiSteps\Core\Plugin::get_mapping();
		} else {
			// Fallback si Plugin non disponible
			$mapping = is_array( $raw ) ? $raw : array( '_version' => 1 );
		}

		self::$mapping_cache = $mapping;
		return self::$mapping_cache;
	}

	/**
	 * Obtient la configuration de test pour un produit
	 *
	 * @param int $product_id ID du produit
	 * @return array|null Configuration du test ou null si non trouvé
	 */
	public static function get_for_product( int $product_id ): ?array {
		$mapping = self::get_mapping();
		$key = 'product_' . $product_id;

		if ( ! isset( $mapping[ $key ] ) ) {
			return null;
		}

		$config = $mapping[ $key ];
		
		// Validation de base
		if ( ! is_array( $config ) ) {
			return null;
		}

		// Ajouter product_id pour commodité
		$config['product_id'] = $product_id;

		// Valeurs par défaut
		$config = wp_parse_args( $config, array(
			'page_id'    => 0,
			'gf_form_id' => 0,
			'active'     => false,
			'notes'      => '',
		) );

		return $config;
	}

	/**
	 * Vérifie si un produit a un test configuré et actif
	 *
	 * @param int $product_id ID du produit
	 * @return bool True si test configuré et actif
	 */
	public static function has_active_test( int $product_id ): bool {
		$config = self::get_for_product( $product_id );
		
		if ( ! $config || ! $config['active'] ) {
			return false;
		}

		// Vérifier que page_id existe et est publiée
		$page_id = (int) $config['page_id'];
		if ( $page_id <= 0 ) {
			return false;
		}

		$page = \get_post( $page_id );
		return $page && $page->post_status === 'publish';
	}

	/**
	 * Obtient l'URL de la page de test pour un produit
	 *
	 * @param int   $product_id    ID du produit
	 * @param array $query_params  Paramètres de query string additionnels
	 * @return string|null URL de la page de test ou null si non configurée
	 */
	public static function get_test_url( int $product_id, array $query_params = array() ): ?string {
		$config = self::get_for_product( $product_id );
		
		if ( ! $config || ! $config['active'] ) {
			return null;
		}

		$page_id = (int) $config['page_id'];
		if ( $page_id <= 0 ) {
			return null;
		}

		$url = get_permalink( $page_id );
		if ( ! $url ) {
			return null;
		}

		// Ajouter paramètres par défaut
		$default_params = array(
			'product_id' => $product_id,
		);

		$query_params = wp_parse_args( $query_params, $default_params );

		// Construire URL avec paramètres
		if ( ! empty( $query_params ) ) {
			$url = add_query_arg( $query_params, $url );
		}

		return $url;
	}

	/**
	 * Obtient tous les produits ayant un test configuré
	 *
	 * @param bool $active_only Retourner seulement les tests actifs
	 * @return array Produits avec leur configuration de test
	 */
	public static function get_all_products( bool $active_only = false ): array {
		$mapping = self::get_mapping();
		$products = array();

		foreach ( $mapping as $key => $config ) {
			if ( strpos( $key, 'product_' ) !== 0 ) {
				continue;
			}

			$product_id = (int) str_replace( 'product_', '', $key );
			if ( $product_id <= 0 ) {
				continue;
			}

			if ( $active_only && ( ! is_array( $config ) || empty( $config['active'] ) ) ) {
				continue;
			}

			$config['product_id'] = $product_id;
			$products[ $product_id ] = $config;
		}

		return $products;
	}

	/**
	 * Valide une configuration de mapping
	 *
	 * @param array $config Configuration à valider
	 * @return array Array avec 'valid' (bool) et 'errors' (array)
	 */
	public static function validate_config( array $config ): array {
		$errors = array();

		// Vérifier product_id
		$product_id = isset( $config['product_id'] ) ? (int) $config['product_id'] : 0;
		if ( $product_id <= 0 ) {
			$errors[] = __( 'Product ID is required', 'wc_qualiopi_steps' );
		} else {
			$product = wc_get_product( $product_id );
			if ( ! $product || $product->get_status() !== 'publish' ) {
				$errors[] = __( 'Product must exist and be published', 'wc_qualiopi_steps' );
			}
		}

		// Vérifier page_id
		$page_id = isset( $config['page_id'] ) ? (int) $config['page_id'] : 0;
		if ( $page_id <= 0 ) {
			$errors[] = __( 'Test page ID is required', 'wc_qualiopi_steps' );
		} else {
			$page = get_post( $page_id );
			if ( ! $page || $page->post_status !== 'publish' ) {
				$errors[] = __( 'Test page must exist and be published', 'wc_qualiopi_steps' );
			}
		}

		// Vérifier gf_form_id si présent
		if ( isset( $config['gf_form_id'] ) && ! empty( $config['gf_form_id'] ) ) {
			$gf_form_id = (int) $config['gf_form_id'];
			if ( $gf_form_id > 0 && class_exists( 'GFAPI' ) ) {
				$form = \GFAPI::get_form( $gf_form_id );
				if ( ! $form || is_wp_error( $form ) ) {
					$errors[] = __( 'Gravity Forms form not found', 'wc_qualiopi_steps' );
				}
			}
		}

		return array(
			'valid'  => empty( $errors ),
			'errors' => $errors,
		);
	}

	/**
	 * Obtient des statistiques sur le mapping
	 *
	 * @return array Statistiques du mapping
	 */
	public static function get_stats(): array {
		$mapping = self::get_mapping();
		$total = 0;
		$active = 0;
		$inactive = 0;
		$problematic = 0;

		foreach ( $mapping as $key => $config ) {
			if ( strpos( $key, 'product_' ) !== 0 ) {
				continue;
			}

			$total++;

			if ( ! is_array( $config ) ) {
				$problematic++;
				continue;
			}

			if ( ! empty( $config['active'] ) ) {
				$active++;
				
				// Vérifier santé
				$product_id = (int) str_replace( 'product_', '', $key );
				if ( ! self::has_active_test( $product_id ) ) {
					$problematic++;
				}
			} else {
				$inactive++;
			}
		}

		return array(
			'total'        => $total,
			'active'       => $active,
			'inactive'     => $inactive,
			'problematic'  => $problematic,
			'health_score' => $total > 0 ? round( ( ( $active - $problematic ) / $total ) * 100, 1 ) : 100,
		);
	}

	/**
	 * Recherche dans le mapping
	 *
	 * @param string $search_term Terme de recherche
	 * @return array Résultats de recherche
	 */
	public static function search( string $search_term ): array {
		if ( empty( $search_term ) ) {
			return array();
		}

		$products = self::get_all_products();
		$results = array();

		foreach ( $products as $product_id => $config ) {
			$product = wc_get_product( $product_id );
			if ( ! $product ) {
				continue;
			}

			$product_name = $product->get_name();
			$page_title = '';
			
			if ( ! empty( $config['page_id'] ) ) {
				$page = get_post( $config['page_id'] );
				$page_title = $page ? $page->post_title : '';
			}

			$notes = isset( $config['notes'] ) ? $config['notes'] : '';

			// Recherche dans nom produit, titre page, notes
			$search_in = strtolower( $product_name . ' ' . $page_title . ' ' . $notes );
			if ( strpos( $search_in, strtolower( $search_term ) ) !== false ) {
				$config['product_name'] = $product_name;
				$config['page_title'] = $page_title;
				$results[ $product_id ] = $config;
			}
		}

		return $results;
	}

	/**
	 * Vide le cache statique
	 */
	public static function clear_cache(): void {
		self::$mapping_cache = null;
	}

	/**
	 * Recharge le cache depuis la base
	 *
	 * @return array Nouveau mapping
	 */
	public static function refresh_cache(): array {
		self::clear_cache();
		return self::get_mapping();
	}
}
