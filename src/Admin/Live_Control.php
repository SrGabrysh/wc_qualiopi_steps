<?php
namespace WcQualiopiSteps\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Contrôle live pour surveillance temps réel
 *
 * @package WcQualiopiSteps
 */
class Live_Control {

	/**
	 * Initialise les hooks de contrôle live
	 */
	public static function init(): void {
		add_action( 'wp_ajax_wcqs_live_status', array( __CLASS__, 'get_live_status' ) );
		add_action( 'wp_ajax_wcqs_quick_search', array( __CLASS__, 'quick_search' ) );
	}

	/**
	 * Retourne le statut live du système
	 */
	public static function get_live_status(): void {
		// Vérification nonce
		if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'wcqs_ajax_nonce' ) ) {
			wp_die( 'Security check failed' );
		}

		$mapping = \WcQualiopiSteps\Core\Plugin::get_mapping();
		$stats = array(
			'total_mappings' => 0,
			'active_mappings' => 0,
			'inactive_mappings' => 0,
			'products_with_issues' => 0,
			'pages_with_issues' => 0,
			'last_check' => current_time( 'Y-m-d H:i:s' )
		);

		$issues = array();

		foreach ( $mapping as $key => $data ) {
			if ( strpos( $key, 'product_' ) !== 0 ) continue;

			$stats['total_mappings']++;
			
			if ( $data['active'] ) {
				$stats['active_mappings']++;
			} else {
				$stats['inactive_mappings']++;
			}

			$product_id = (int) str_replace( 'product_', '', $key );
			
			// Vérification produit
			if ( function_exists( 'wc_get_product' ) ) {
				$product = wc_get_product( $product_id );
				if ( ! $product || $product->get_status() !== 'publish' ) {
					$stats['products_with_issues']++;
					$issues[] = array(
						'type' => 'product',
						'id' => $product_id,
						'issue' => $product ? 'Non publié' : 'Introuvable'
					);
				}
			}

			// Vérification page
			$page_id = $data['page_id'];
			$page = get_post( $page_id );
			if ( ! $page || $page->post_status !== 'publish' || $page->post_type !== 'page' ) {
				$stats['pages_with_issues']++;
				$issues[] = array(
					'type' => 'page',
					'id' => $page_id,
					'issue' => ! $page ? 'Introuvable' : 'Non publiée ou mauvais type'
				);
			}
		}

		wp_send_json_success( array(
			'stats' => $stats,
			'issues' => array_slice( $issues, 0, 20 ), // Limite à 20 issues
			'server_time' => current_time( 'c' ),
			'wp_version' => get_bloginfo( 'version' ),
			'wc_active' => function_exists( 'wc_get_product' ),
			'gf_active' => class_exists( 'GFForms' )
		) );
	}

	/**
	 * Recherche rapide de produits/pages
	 */
	public static function quick_search(): void {
		// Vérification nonce
		if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'wcqs_ajax_nonce' ) ) {
			wp_die( 'Security check failed' );
		}

		$search_term = sanitize_text_field( $_POST['search'] ?? '' );
		$search_type = sanitize_text_field( $_POST['type'] ?? 'product' );

		if ( strlen( $search_term ) < 2 ) {
			wp_send_json_error( array(
				'message' => __( 'Terme de recherche trop court (min 2 caractères)', 'wc_qualiopi_steps' )
			) );
		}

		$results = array();

		if ( $search_type === 'product' && function_exists( 'wc_get_products' ) ) {
			$products = wc_get_products( array(
				'limit' => 10,
				'status' => 'publish',
				's' => $search_term
			) );

			foreach ( $products as $product ) {
				$results[] = array(
					'id' => $product->get_id(),
					'title' => $product->get_name(),
					'subtitle' => $product->get_price_html(),
					'url' => get_edit_post_link( $product->get_id() )
				);
			}
		} elseif ( $search_type === 'page' ) {
			$pages = get_posts( array(
				'post_type' => 'page',
				'post_status' => 'publish',
				'posts_per_page' => 10,
				's' => $search_term
			) );

			foreach ( $pages as $page ) {
				$results[] = array(
					'id' => $page->ID,
					'title' => $page->post_title,
					'subtitle' => get_permalink( $page->ID ),
					'url' => get_edit_post_link( $page->ID )
				);
			}
		}

		wp_send_json_success( array(
			'results' => $results,
			'search_term' => $search_term,
			'search_type' => $search_type
		) );
	}
}
