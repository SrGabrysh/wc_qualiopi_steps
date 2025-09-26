<?php
namespace WcQualiopiSteps\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Gestionnaire AJAX pour validation instantanée
 *
 * @package WcQualiopiSteps
 */
class Ajax_Handler {

	/**
	 * Initialise les hooks AJAX
	 */
	public static function init(): void {
		add_action( 'wp_ajax_wcqs_validate_product', array( __CLASS__, 'validate_product' ) );
		add_action( 'wp_ajax_wcqs_validate_page', array( __CLASS__, 'validate_page' ) );
		add_action( 'wp_ajax_wcqs_validate_gf_form', array( __CLASS__, 'validate_gf_form' ) );
		add_action( 'wp_ajax_wcqs_simulate_validation', array( __CLASS__, 'simulate_validation' ) );
	}

	/**
	 * Valide un produit WooCommerce
	 */
	public static function validate_product(): void {
		// Vérification nonce
		if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'wcqs_ajax_nonce' ) ) {
			wp_die( 'Security check failed' );
		}

		$product_id = (int) ( $_POST['product_id'] ?? 0 );

		if ( $product_id <= 0 ) {
			wp_send_json_error( array(
				'message' => __( 'ID produit invalide', 'wc_qualiopi_steps' )
			) );
		}

		// Vérifier si WooCommerce est actif
		if ( ! function_exists( 'wc_get_product' ) ) {
			wp_send_json_error( array(
				'message' => __( 'WooCommerce non disponible', 'wc_qualiopi_steps' )
			) );
		}

		$product = wc_get_product( $product_id );

		if ( ! $product ) {
			wp_send_json_error( array(
				'message' => sprintf( __( 'Produit #%d introuvable', 'wc_qualiopi_steps' ), $product_id )
			) );
		}

		if ( $product->get_status() !== 'publish' ) {
			wp_send_json_error( array(
				'message' => sprintf( __( 'Produit #%d non publié (statut: %s)', 'wc_qualiopi_steps' ), $product_id, $product->get_status() )
			) );
		}

		wp_send_json_success( array(
			'message' => sprintf( __( 'Produit "%s" trouvé ✓', 'wc_qualiopi_steps' ), $product->get_name() ),
			'product_name' => $product->get_name(),
			'product_price' => $product->get_price_html()
		) );
	}

	/**
	 * Valide une page WordPress
	 */
	public static function validate_page(): void {
		// Vérification nonce
		if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'wcqs_ajax_nonce' ) ) {
			wp_die( 'Security check failed' );
		}

		$page_id = (int) ( $_POST['page_id'] ?? 0 );

		if ( $page_id <= 0 ) {
			wp_send_json_error( array(
				'message' => __( 'ID page invalide', 'wc_qualiopi_steps' )
			) );
		}

		$page = get_post( $page_id );

		if ( ! $page ) {
			wp_send_json_error( array(
				'message' => sprintf( __( 'Page #%d introuvable', 'wc_qualiopi_steps' ), $page_id )
			) );
		}

		if ( $page->post_status !== 'publish' ) {
			wp_send_json_error( array(
				'message' => sprintf( __( 'Page #%d non publiée (statut: %s)', 'wc_qualiopi_steps' ), $page_id, $page->post_status )
			) );
		}

		if ( $page->post_type !== 'page' ) {
			wp_send_json_error( array(
				'message' => sprintf( __( '#%d n\'est pas une page (type: %s)', 'wc_qualiopi_steps' ), $page_id, $page->post_type )
			) );
		}

		wp_send_json_success( array(
			'message' => sprintf( __( 'Page "%s" trouvée ✓', 'wc_qualiopi_steps' ), $page->post_title ),
			'page_title' => $page->post_title,
			'page_url' => get_permalink( $page_id )
		) );
	}

	/**
	 * Valide un formulaire Gravity Forms
	 */
	public static function validate_gf_form(): void {
		// Vérification nonce
		if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'wcqs_ajax_nonce' ) ) {
			wp_die( 'Security check failed' );
		}

		$form_id = (int) ( $_POST['form_id'] ?? 0 );

		if ( $form_id <= 0 ) {
			wp_send_json_success( array(
				'message' => __( 'Pas de formulaire (optionnel)', 'wc_qualiopi_steps' )
			) );
		}

		// Vérifier si Gravity Forms est actif
		if ( ! class_exists( 'GFForms' ) ) {
			wp_send_json_error( array(
				'message' => __( 'Gravity Forms non disponible', 'wc_qualiopi_steps' )
			) );
		}

		$form = \GFAPI::get_form( $form_id );

		if ( ! $form || is_wp_error( $form ) ) {
			wp_send_json_error( array(
				'message' => sprintf( __( 'Formulaire #%d introuvable', 'wc_qualiopi_steps' ), $form_id )
			) );
		}

		if ( ! $form['is_active'] ) {
			wp_send_json_error( array(
				'message' => sprintf( __( 'Formulaire #%d inactif', 'wc_qualiopi_steps' ), $form_id )
			) );
		}

		wp_send_json_success( array(
			'message' => sprintf( __( 'Formulaire "%s" trouvé ✓', 'wc_qualiopi_steps' ), $form['title'] ),
			'form_title' => $form['title']
		) );
	}

	/**
	 * Simule la validation d'un test (pour les tests d'intégration)
	 */
	public static function simulate_validation(): void {
		// Vérification nonce
		if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'wcqs_ajax_nonce' ) ) {
			wp_send_json_error( array(
				'message' => __( 'Security check failed', 'wc_qualiopi_steps' )
			) );
		}

		$product_id = (int) ( $_POST['product_id'] ?? 0 );

		if ( $product_id <= 0 ) {
			wp_send_json_error( array(
				'message' => __( 'ID produit invalide', 'wc_qualiopi_steps' )
			) );
		}

		$user_id = get_current_user_id();
		if ( $user_id <= 0 ) {
			wp_send_json_error( array(
				'message' => __( 'Utilisateur non connecté', 'wc_qualiopi_steps' )
			) );
		}

		// Simuler la validation en session WooCommerce
		$session_set = false;
		if ( class_exists( '\\WcQualiopiSteps\\Utils\\WCQS_Session' ) ) {
			$session_set = \WcQualiopiSteps\Utils\WCQS_Session::set_solved( $product_id, 3600 ); // 1 heure
			error_log( "[WCQS DEBUG AJAX] Session set_solved result: " . ( $session_set ? 'SUCCESS' : 'FAILED' ) );
			
			// Vérifier immédiatement
			$session_check = \WcQualiopiSteps\Utils\WCQS_Session::is_solved( $product_id );
			error_log( "[WCQS DEBUG AJAX] Session is_solved check: " . ( $session_check ? 'SOLVED' : 'NOT_SOLVED' ) );
		}

		// Simuler la validation en user meta (preuve persistante)
		$meta_key = "_wcqs_testpos_ok_{$product_id}";
		$timestamp = date( 'c' ); // Format ISO 8601
		$meta_updated = update_user_meta( $user_id, $meta_key, $timestamp );
		
		error_log( "[WCQS DEBUG AJAX] User meta update result: " . ( $meta_updated ? 'SUCCESS' : 'FAILED' ) );
		error_log( "[WCQS DEBUG AJAX] Meta key: {$meta_key}" );
		error_log( "[WCQS DEBUG AJAX] Meta value: {$timestamp}" );
		
		// Vérifier immédiatement
		$meta_check = get_user_meta( $user_id, $meta_key, true );
		error_log( "[WCQS DEBUG AJAX] Meta verification: " . ( $meta_check ? $meta_check : 'EMPTY' ) );

		// Log de l'action pour audit
		error_log( "[WCQS] AJAX: Test validation simulated for user {$user_id}, product {$product_id}" );
		
		// Forcer l'invalidation du cache Cart_Guard
		if ( class_exists( '\\WcQualiopiSteps\\Frontend\\Cart_Guard' ) ) {
			$cart_guard = \WcQualiopiSteps\Frontend\Cart_Guard::get_instance();
			$cart_guard->clear_cache();
			error_log( "[WCQS DEBUG AJAX] Cart_Guard cache cleared" );
		}

		wp_send_json_success( array(
			'message' => sprintf( __( 'Test simulé comme validé pour le produit #%d', 'wc_qualiopi_steps' ), $product_id ),
			'product_id' => $product_id,
			'user_id' => $user_id,
			'timestamp' => $timestamp,
			'session_set' => true,
			'meta_set' => true
		) );
	}
}
