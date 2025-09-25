<?php
namespace WcQualiopiSteps\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Gestionnaire CSV pour import/export batch
 *
 * @package WcQualiopiSteps
 */
class Csv_Handler {

	/**
	 * Initialise les hooks CSV
	 */
	public static function init(): void {
		add_action( 'wp_ajax_wcqs_export_csv', array( __CLASS__, 'export_csv' ) );
		add_action( 'wp_ajax_wcqs_import_csv', array( __CLASS__, 'import_csv' ) );
	}

	/**
	 * Exporte le mapping en CSV
	 */
	public static function export_csv(): void {
		// Vérification nonce
		if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'wcqs_ajax_nonce' ) ) {
			wp_die( 'Security check failed' );
		}

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( 'Permissions insuffisantes' );
		}

		$mapping = \WcQualiopiSteps\Core\Plugin::get_mapping();
		
		// Prépare les données CSV
		$csv_data = array();
		$csv_data[] = array( 'Product ID', 'Page ID', 'GF Form ID', 'Active', 'Notes' );

		foreach ( $mapping as $key => $data ) {
			if ( strpos( $key, 'product_' ) === 0 ) {
				$product_id = (int) str_replace( 'product_', '', $key );
				$csv_data[] = array(
					$product_id,
					$data['page_id'] ?? 0,
					$data['gf_form_id'] ?? 0,
					$data['active'] ? 'Yes' : 'No',
					$data['notes'] ?? ''
				);
			}
		}

		// Headers pour téléchargement
		$filename = 'wcqs_mapping_' . date( 'Y-m-d_H-i-s' ) . '.csv';
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=' . $filename );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );

		// Génère le CSV
		$output = fopen( 'php://output', 'w' );
		
		// BOM UTF-8 pour Excel
		fwrite( $output, "\xEF\xBB\xBF" );
		
		foreach ( $csv_data as $row ) {
			fputcsv( $output, $row, ';' ); // Point-virgule pour compatibilité européenne
		}
		
		fclose( $output );
		exit;
	}

	/**
	 * Importe le mapping depuis CSV
	 */
	public static function import_csv(): void {
		// Vérification nonce
		if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'wcqs_ajax_nonce' ) ) {
			wp_die( 'Security check failed' );
		}

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( 'Permissions insuffisantes' );
		}

		if ( ! isset( $_FILES['csv_file'] ) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK ) {
			wp_send_json_error( array(
				'message' => __( 'Erreur lors de l\'upload du fichier', 'wc_qualiopi_steps' )
			) );
		}

		$file = $_FILES['csv_file'];
		
		// Vérifications de sécurité
		if ( $file['size'] > 1024 * 1024 ) { // 1MB max
			wp_send_json_error( array(
				'message' => __( 'Fichier trop volumineux (max 1MB)', 'wc_qualiopi_steps' )
			) );
		}

		$allowed_types = array( 'text/csv', 'application/csv', 'text/plain' );
		if ( ! in_array( $file['type'], $allowed_types ) ) {
			wp_send_json_error( array(
				'message' => __( 'Type de fichier non autorisé (CSV uniquement)', 'wc_qualiopi_steps' )
			) );
		}

		// Lecture du CSV
		$handle = fopen( $file['tmp_name'], 'r' );
		if ( ! $handle ) {
			wp_send_json_error( array(
				'message' => __( 'Impossible de lire le fichier', 'wc_qualiopi_steps' )
			) );
		}

		$imported_count = 0;
		$error_count = 0;
		$errors = array();
		$new_mapping = array( '_version' => 1 );

		// Ignore la première ligne (headers)
		$headers = fgetcsv( $handle, 1000, ';' );
		
		$line_number = 1;
		while ( ( $data = fgetcsv( $handle, 1000, ';' ) ) !== FALSE ) {
			$line_number++;
			
			if ( count( $data ) < 4 ) {
				$errors[] = sprintf( __( 'Ligne %d: Données insuffisantes', 'wc_qualiopi_steps' ), $line_number );
				$error_count++;
				continue;
			}

			$product_id = (int) trim( $data[0] );
			$page_id = (int) trim( $data[1] );
			$gf_form_id = (int) trim( $data[2] ?? 0 );
			$active = strtolower( trim( $data[3] ?? 'yes' ) ) === 'yes';
			$notes = trim( $data[4] ?? '' );

			// Validations
			if ( $product_id <= 0 ) {
				$errors[] = sprintf( __( 'Ligne %d: ID produit invalide', 'wc_qualiopi_steps' ), $line_number );
				$error_count++;
				continue;
			}

			if ( $page_id <= 0 ) {
				$errors[] = sprintf( __( 'Ligne %d: ID page invalide', 'wc_qualiopi_steps' ), $line_number );
				$error_count++;
				continue;
			}

			// Vérifications d'existence (optionnelles en import)
			if ( function_exists( 'wc_get_product' ) ) {
				$product = wc_get_product( $product_id );
				if ( ! $product ) {
					$errors[] = sprintf( __( 'Ligne %d: Produit #%d introuvable', 'wc_qualiopi_steps' ), $line_number, $product_id );
					$error_count++;
					continue;
				}
			}

			$page = get_post( $page_id );
			if ( ! $page || $page->post_type !== 'page' || $page->post_status !== 'publish' ) {
				$errors[] = sprintf( __( 'Ligne %d: Page #%d introuvable ou non publiée', 'wc_qualiopi_steps' ), $line_number, $page_id );
				$error_count++;
				continue;
			}

			// Ajoute au nouveau mapping
			$new_mapping["product_{$product_id}"] = array(
				'page_id' => $page_id,
				'gf_form_id' => $gf_form_id > 0 ? $gf_form_id : 0,
				'active' => $active,
				'notes' => $notes
			);

			$imported_count++;
		}

		fclose( $handle );

		// Sauvegarde le nouveau mapping
		if ( $imported_count > 0 ) {
			update_option( 'wcqs_testpos_mapping', $new_mapping, false );
		}

		// Réponse JSON
		$response = array(
			'imported' => $imported_count,
			'errors' => $error_count,
			'message' => sprintf(
				__( 'Import terminé : %d lignes importées, %d erreurs', 'wc_qualiopi_steps' ),
				$imported_count,
				$error_count
			)
		);

		if ( ! empty( $errors ) ) {
			$response['error_details'] = array_slice( $errors, 0, 10 ); // Limite à 10 erreurs
		}

		if ( $imported_count > 0 ) {
			wp_send_json_success( $response );
		} else {
			wp_send_json_error( $response );
		}
	}

	/**
	 * Génère un template CSV pour l'import
	 */
	public static function generate_template(): string {
		$template_data = array(
			array( 'Product ID', 'Page ID', 'GF Form ID', 'Active', 'Notes' ),
			array( '123', '456', '0', 'Yes', 'Exemple de mapping' ),
			array( '124', '457', '5', 'No', 'Mapping inactif avec GF' )
		);

		$output = '';
		foreach ( $template_data as $row ) {
			$output .= implode( ';', $row ) . "\n";
		}

		return $output;
	}
}
