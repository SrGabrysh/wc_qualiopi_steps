<?php
namespace WcQualiopiSteps\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Page d'options centrale : mapping produit -> page de test (+ id GF, actif, notes)
 */
class Settings_Page {

	/**
	 * Clé de l'option.
	 */
	private const OPTION_KEY = 'wcqs_testpos_mapping';

	/**
	 * Rendu et gestion du POST.
	 */
	public static function render_page(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Permissions insuffisantes.', 'wc_qualiopi_steps' ) );
		}

		// Hardening : aucune erreur PHP ne doit casser la page
		try {
			// Gestion POST (save).
			if ( 'POST' === $_SERVER['REQUEST_METHOD'] ) {
				self::handle_post();
			}

		// Utilise le helper sûr du Plugin principal
		$mapping = \WcQualiopiSteps\Core\Plugin::get_mapping();

			// Retire la clé interne _version de l'affichage.
			$rows = array_filter(
				$mapping,
				static function ( $k ) {
					return 0 !== strpos( (string) $k, '_' );
				},
				ARRAY_FILTER_USE_KEY
			);

			self::render_view( $rows );
		} catch ( \Throwable $e ) {
			error_log( '[wcqs] settings fatal: ' . $e->getMessage() );
			echo '<div class="notice notice-error"><p>'
			   . esc_html__( 'WCQS: une erreur est survenue sur la page de réglages. Consultez debug.log.', 'wc_qualiopi_steps' )
			   . '</p></div>';
		}
	}

	/**
	 * Traite la soumission du formulaire.
	 */
	private static function handle_post(): void {
		check_admin_referer( 'wcqs_save_mapping', 'wcqs_nonce' );

		// --- DEBUG : journalise un aperçu du POST
		if ( isset($_POST['wcqs']) ) {
			$snapshot = $_POST['wcqs'];
			error_log('[WCQS] POST keys: ' . implode(',', array_keys($snapshot)));
			if ( isset($snapshot['lines']) && is_array($snapshot['lines']) ) {
				$dbg = [];
				foreach ($snapshot['lines'] as $k => $v) {
					$dbg[] = sprintf(
						'%s => prod:%s page:%s gf:%s active:%s',
						is_int($k) ? $k : (string)$k,
						isset($v['product_id']) ? $v['product_id'] : '-',
						isset($v['page_id']) ? $v['page_id'] : '-',
						isset($v['gf_form_id']) ? $v['gf_form_id'] : '-',
						!empty($v['active']) ? '1' : '0'
					);
				}
				error_log('[WCQS] LINES: ' . implode(' | ', $dbg));
			} else {
				error_log('[WCQS] No lines array in POST.');
			}
		} else {
			error_log('[WCQS] No wcqs in POST.');
		}

		$raw = isset( $_POST['wcqs'] ) && is_array( $_POST['wcqs'] ) ? wp_unslash( $_POST['wcqs'] ) : array();

		// Normalise les lignes reçues.
		$lines = isset( $raw['lines'] ) && is_array( $raw['lines'] ) ? $raw['lines'] : array();

		// Sanitize et valide.
		$validated_rows = array();
		$seen_products  = array();
		$split_detected = false;

		// IMPORTANT: Utiliser array_values pour forcer l'indexation numérique (0, 1, 2...)
		// au lieu des clés string générées par JavaScript (68d52001dc28b, etc.)
		foreach ( array_values( $lines ) as $i => $line ) {
			$product_id = isset( $line['product_id'] ) ? (int) $line['product_id'] : 0;
			$page_id    = isset( $line['page_id'] ) ? (int) $line['page_id'] : 0;
			$gf_id      = isset( $line['gf_form_id'] ) ? (int) $line['gf_form_id'] : 0;
			$active     = ! empty( $line['active'] );
			$notes      = isset( $line['notes'] ) ? sanitize_text_field( (string) $line['notes'] ) : '';

			// Ignore les lignes vides.
			if ( $product_id <= 0 && $page_id <= 0 && empty( $notes ) ) {
				continue;
			}

			// GARDE-FOU: Détecte les lignes "explosées" par un mauvais rowId côté JS
			if ( ( $product_id > 0 ) xor ( $page_id > 0 ) ) {
				$split_detected = true;
				continue; // Ignore cette ligne incomplète
			}

			// Validations fortes.
			$err = self::validate_row( $product_id, $page_id );
			if ( $err ) {
				self::admin_error( sprintf( /* translators: %s error msg */
					__( 'Ligne %d: %s', 'wc_qualiopi_steps' ), $i + 1, $err
				) );
				continue; // n'insère pas cette ligne
			}

			// Unicité par produit.
			if ( isset( $seen_products[ $product_id ] ) ) {
				self::admin_error(
					sprintf(
						__( 'Le produit #%d est dupliqué. Un seul mapping par produit.', 'wc_qualiopi_steps' ),
						$product_id
					)
				);
				continue;
			}
			$seen_products[ $product_id ] = true;

			$key                   = 'product_' . $product_id;
			$validated_rows[ $key ] = array(
				'page_id'    => $page_id,
				'gf_form_id' => $gf_id > 0 ? $gf_id : null,
				'active'     => (bool) $active,
				'notes'      => $notes,
			);
		}

		// Affichage de la notice unique pour les champs séparés
		if ( $split_detected ) {
			self::admin_error( __( 'Champs séparés détectés (ancien bug JS). Rechargez la page puis réessayez — les nouvelles lignes seront correctes.', 'wc_qualiopi_steps' ) );
		}

		// Construit la valeur finale à stocker.
		$value = array( '_version' => 1 ) + $validated_rows;

		// IMPORTANT : forcer autoload=no via le 3e paramètre
		update_option( self::OPTION_KEY, $value, false );

		// Message informatif
		$complete_count = count( $validated_rows );
		if ( $complete_count > 0 ) {
			self::admin_success( sprintf(
				/* translators: %d number of saved lines */
				__( 'Mapping enregistré avec succès ! %d ligne(s) sauvegardée(s).', 'wc_qualiopi_steps' ),
				$complete_count
			) );
			error_log('[WCQS] SUCCESS: ' . $complete_count . ' lignes sauvegardées');
		} else {
			self::admin_error( __( 'Aucune ligne complète détectée. Vérifiez que chaque ligne a bien un Product ID ET un Page ID.', 'wc_qualiopi_steps' ) );
			error_log('[WCQS] WARNING: Aucune ligne valide trouvée');
		}
	}

	/**
	 * Valide une ligne (existence produit/page publiés).
	 */
	private static function validate_row( int $product_id, int $page_id ): string {
		if ( $product_id <= 0 ) {
			return __( 'Produit manquant.', 'wc_qualiopi_steps' );
		}
		if ( $page_id <= 0 ) {
			return __( 'Page de test manquante.', 'wc_qualiopi_steps' );
		}

		// Produit publié ?
		$product_post = get_post( $product_id );
		if ( ! $product_post || 'product' !== $product_post->post_type ) {
			return __( 'L\'ID produit ne correspond pas à un produit WooCommerce.', 'wc_qualiopi_steps' );
		}
		if ( 'publish' !== $product_post->post_status ) {
			return __( 'Le produit doit être publié.', 'wc_qualiopi_steps' );
		}

		// Page publiée ?
		$page_post = get_post( $page_id );
		if ( ! $page_post || 'page' !== $page_post->post_type ) {
			return __( 'L\'ID page ne correspond pas à une page WordPress.', 'wc_qualiopi_steps' );
		}
		if ( 'publish' !== $page_post->post_status ) {
			return __( 'La page de test doit être publiée.', 'wc_qualiopi_steps' );
		}

		return '';
	}

	/**
	 * Affiche la vue HTML.
	 *
	 * @param array<string,array> $rows
	 */
	private static function render_view( array $rows ): void {
		$title = esc_html__( 'Mapping produit → page de test', 'wc_qualiopi_steps' );
		$desc  = esc_html__( 'Définissez pour chaque formation la page de test de positionnement (et son éventuel ID Gravity Forms). Un seul mapping par produit.', 'wc_qualiopi_steps' );

		?>
		<div class="wrap wcqs-wrap">
			<h1><?php echo $title; ?></h1>
			<p class="description"><?php echo $desc; ?></p>

			<form method="post">
				<?php wp_nonce_field( 'wcqs_save_mapping', 'wcqs_nonce' ); ?>

				<table class="widefat wcqs-table" id="wcqs-table">
					<thead>
						<tr>
							<th style="width:140px;"><?php esc_html_e( 'ID produit', 'wc_qualiopi_steps' ); ?></th>
							<th><?php esc_html_e( 'Page de test (ID)', 'wc_qualiopi_steps' ); ?></th>
							<th style="width:120px;"><?php esc_html_e( 'ID Gravity Forms', 'wc_qualiopi_steps' ); ?></th>
							<th style="width:90px;"><?php esc_html_e( 'Actif', 'wc_qualiopi_steps' ); ?></th>
							<th><?php esc_html_e( 'Notes', 'wc_qualiopi_steps' ); ?></th>
							<th style="width:80px;"><?php esc_html_e( 'Actions', 'wc_qualiopi_steps' ); ?></th>
						</tr>
					</thead>
					<tbody id="wcqs-rows">
						<?php
						if ( empty( $rows ) ) :
							self::render_empty_row();
						else :
							foreach ( $rows as $key => $data ) :
								$product_id = (int) str_replace( 'product_', '', (string) $key );
								self::render_row( $product_id, $data );
							endforeach;
						endif;
						?>
					</tbody>
				</table>

				<p>
					<button type="button" class="button" id="wcqs-add-row"><?php esc_html_e( 'Ajouter une ligne', 'wc_qualiopi_steps' ); ?></button>
				</p>

				<p>
					<button type="submit" class="button button-primary"><?php esc_html_e( 'Enregistrer', 'wc_qualiopi_steps' ); ?></button>
				</p>
			</form>

			<!-- Template caché pour nouvelle ligne -->
			<table style="display:none;" id="wcqs-template-table">
				<tbody>
				<?php self::render_empty_row( true ); ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	/**
	 * Render une ligne vide ou préremplie.
	 *
	 * @param bool  $template  Si true, ajoute l'attribut data-template.
	 * @param int   $product_id
	 * @param array $data
	 */
	private static function render_empty_row( bool $template = false ): void {
		self::render_row( 0, array( 'page_id' => 0, 'gf_form_id' => null, 'active' => true, 'notes' => '' ), $template );
	}

	private static function render_row( int $product_id, array $data, bool $template = false ): void {
		$attr = $template ? ' data-template="1"' : '';
		?>
		<tr class="wcqs-row"<?php echo $attr; // phpcs:ignore ?>>
			<td>
				<input type="number" min="1" class="small-text"
					name="wcqs[lines][<?php echo $template ? '{INDEX}' : uniqid(); ?>][product_id]" value="<?php echo esc_attr( $product_id ); ?>" />
				<p class="description"><?php esc_html_e( 'ID du produit WooCommerce', 'wc_qualiopi_steps' ); ?></p>
			</td>
			<td>
				<input type="number" min="1" class="small-text"
					name="wcqs[lines][<?php echo $template ? '{INDEX}' : uniqid(); ?>][page_id]" value="<?php echo esc_attr( (int) ( $data['page_id'] ?? 0 ) ); ?>" />
				<p class="description"><?php esc_html_e( 'ID de la page WordPress publiée', 'wc_qualiopi_steps' ); ?></p>
			</td>
			<td>
				<input type="number" min="0" class="small-text"
					name="wcqs[lines][<?php echo $template ? '{INDEX}' : uniqid(); ?>][gf_form_id]" value="<?php echo esc_attr( (int) ( $data['gf_form_id'] ?? 0 ) ); ?>" />
			</td>
			<td style="text-align:center;">
				<label>
					<input type="checkbox"
						name="wcqs[lines][<?php echo $template ? '{INDEX}' : uniqid(); ?>][active]" <?php checked( ! empty( $data['active'] ) ); ?> />
				</label>
			</td>
			<td>
				<input type="text" class="regular-text"
					name="wcqs[lines][<?php echo $template ? '{INDEX}' : uniqid(); ?>][notes]" value="<?php echo esc_attr( (string) ( $data['notes'] ?? '' ) ); ?>" />
			</td>
			<td>
				<button type="button" class="button wcqs-remove-row"><?php esc_html_e( 'Supprimer', 'wc_qualiopi_steps' ); ?></button>
			</td>
		</tr>
		<?php
	}

	private static function admin_error( string $msg ): void {
		add_settings_error( 'wcqs_messages', 'wcqs_error_' . wp_generate_uuid4(), $msg, 'error' );
		settings_errors( 'wcqs_messages' );
	}

	private static function admin_success( string $msg ): void {
		add_settings_error( 'wcqs_messages', 'wcqs_success_' . wp_generate_uuid4(), $msg, 'updated' );
		settings_errors( 'wcqs_messages' );
	}
}