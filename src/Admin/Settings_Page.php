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

		// DEBUG désactivé en production
		// Réactiver si besoin en décommentant les lignes ci-dessous :
		/*
		if ( defined('WP_DEBUG') && WP_DEBUG && isset($_POST['wcqs']) ) {
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
			}
		}
		*/

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
			$product_id  = isset( $line['product_id'] ) ? (int) $line['product_id'] : 0;
			$page_id     = isset( $line['page_id'] ) ? (int) $line['page_id'] : 0;
			$form_source = isset( $line['form_source'] ) ? sanitize_text_field( (string) $line['form_source'] ) : 'learndash';
			$form_ref    = isset( $line['form_ref'] ) ? sanitize_text_field( (string) $line['form_ref'] ) : '';

			// Migration de l'ancienne structure
			if ( isset( $line['gf_form_id'] ) && ! isset( $line['form_source'] ) ) {
				$form_source = 'gravityforms';
				$form_ref = (string) $line['gf_form_id'];
			}

			$active = ! empty( $line['active'] );
			$notes  = isset( $line['notes'] ) ? sanitize_text_field( (string) $line['notes'] ) : '';

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
			$err = self::validate_row( $product_id, $page_id, $form_source, $form_ref );
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
				'page_id'     => $page_id,
				'form_source' => $form_source,
				'form_ref'    => $form_ref,
				'active'      => (bool) $active,
				'notes'       => $notes,
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
			// error_log('[WCQS] SUCCESS: ' . $complete_count . ' lignes sauvegardées'); // Debug désactivé
		} else {
			self::admin_error( __( 'Aucune ligne complète détectée. Vérifiez que chaque ligne a bien un Product ID ET un Page ID.', 'wc_qualiopi_steps' ) );
			// error_log('[WCQS] WARNING: Aucune ligne valide trouvée'); // Debug désactivé
		}
	}

	/**
	 * Valide une ligne (existence produit/page publiés).
	 */
	private static function validate_row( int $product_id, int $page_id, string $form_source = 'learndash', string $form_ref = '' ): string {
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

		// Valider form_source et form_ref
		if ( ! empty( $form_ref ) ) {
			if ( $form_source === 'gravityforms' ) {
				$gf_form_id = (int) $form_ref;
				if ( $gf_form_id <= 0 ) {
					return __( 'ID Gravity Forms invalide.', 'wc_qualiopi_steps' );
				}
				if ( class_exists( 'GFAPI' ) ) {
					$form = \GFAPI::get_form( $gf_form_id );
					if ( ! $form || is_wp_error( $form ) ) {
						return __( 'Formulaire Gravity Forms introuvable.', 'wc_qualiopi_steps' );
					}
				}
			} elseif ( $form_source === 'learndash' ) {
				if ( ! is_numeric( $form_ref ) ) {
					return __( 'Référence LearnDash doit être numérique (ID quiz, leçon, etc.).', 'wc_qualiopi_steps' );
				}
			}
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
		$desc  = esc_html__( 'Définissez pour chaque formation la page de test de positionnement et sa source (LearnDash ou Gravity Forms). Un seul mapping par produit.', 'wc_qualiopi_steps' );

		?>
		<div class="wrap wcqs-wrap">
			<h1><?php echo $title; ?></h1>
			<p class="description"><?php echo $desc; ?></p>
			
			<?php
			// Intégrer le Log Viewer
			$log_viewer = \WcQualiopiSteps\Admin\Log_Viewer::get_instance();
			$log_viewer->render_log_viewer();
			?>

			<form method="post">
				<?php wp_nonce_field( 'wcqs_save_mapping', 'wcqs_nonce' ); ?>

				<table class="widefat wcqs-table" id="wcqs-table">
					<thead>
						<tr>
							<th style="width:140px;"><?php esc_html_e( 'ID produit', 'wc_qualiopi_steps' ); ?></th>
							<th><?php esc_html_e( 'Page de test (ID)', 'wc_qualiopi_steps' ); ?></th>
							<th style="width:120px;"><?php esc_html_e( 'Source', 'wc_qualiopi_steps' ); ?></th>
							<th style="width:120px;"><?php esc_html_e( 'Référence', 'wc_qualiopi_steps' ); ?></th>
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

			<!-- Section Import/Export CSV -->
			<!-- Section Contrôle Live -->
			<div class="wcqs-live-section" style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd;">
				<h2><?php esc_html_e( 'Contrôle Live', 'wc_qualiopi_steps' ); ?></h2>
				<p class="description">
					<?php esc_html_e( 'Surveillance en temps réel de l\'état des mappings et recherche rapide.', 'wc_qualiopi_steps' ); ?>
				</p>

				<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin: 15px 0;">
					<!-- Statistiques live -->
					<div class="wcqs-live-stats" style="background: #f9f9f9; padding: 15px; border-radius: 4px;">
						<h3 style="margin-top: 0;">📊 Statistiques Live</h3>
						<div id="wcqs-stats-content">
							<p>🔄 Chargement...</p>
						</div>
						<button type="button" class="button button-small" id="wcqs-refresh-stats">
							🔄 Actualiser
						</button>
					</div>

					<!-- Recherche rapide -->
					<div class="wcqs-quick-search" style="background: #f9f9f9; padding: 15px; border-radius: 4px;">
						<h3 style="margin-top: 0;">🔍 Recherche Rapide</h3>
						<div style="margin-bottom: 10px;">
							<select id="wcqs-search-type" style="margin-right: 10px;">
								<option value="product">Produits</option>
								<option value="page">Pages</option>
							</select>
							<input type="text" id="wcqs-search-input" placeholder="Rechercher..." style="width: 200px;">
						</div>
						<div id="wcqs-search-results" style="max-height: 200px; overflow-y: auto; border: 1px solid #ddd; background: white; border-radius: 3px; display: none;">
						</div>
					</div>
				</div>
			</div>

			<div class="wcqs-csv-section" style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd;">
				<h2><?php esc_html_e( 'Import/Export CSV', 'wc_qualiopi_steps' ); ?></h2>
				<p class="description">
					<?php esc_html_e( 'Gérez vos mappings en lot via fichier CSV (Excel compatible).', 'wc_qualiopi_steps' ); ?>
				</p>

				<div style="display: flex; gap: 15px; flex-wrap: wrap; margin: 15px 0;">
					<button type="button" class="button" id="wcqs-export-csv">
						📥 <?php esc_html_e( 'Exporter CSV', 'wc_qualiopi_steps' ); ?>
					</button>
					
					<button type="button" class="button" id="wcqs-download-template">
						📋 <?php esc_html_e( 'Télécharger Template', 'wc_qualiopi_steps' ); ?>
					</button>
					
					<div style="display: inline-block;">
						<input type="file" id="wcqs-csv-file" accept=".csv" style="display: none;">
						<button type="button" class="button" id="wcqs-import-csv">
							📤 <?php esc_html_e( 'Importer CSV', 'wc_qualiopi_steps' ); ?>
						</button>
					</div>
				</div>

				<div id="wcqs-csv-progress" style="display: none; margin: 10px 0;">
					<div style="background: #f1f1f1; border-radius: 3px; padding: 3px;">
						<div style="background: #0073aa; height: 20px; border-radius: 3px; width: 0%; transition: width 0.3s;"></div>
					</div>
					<p style="margin: 5px 0; font-size: 13px;"></p>
				</div>
			</div>

			<form method="post" style="display: none;">
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
		self::render_row( 0, array( 'page_id' => 0, 'form_source' => 'learndash', 'form_ref' => '', 'active' => true, 'notes' => '' ), $template );
	}

	private static function render_row( int $product_id, array $data, bool $template = false ): void {
		$attr = $template ? ' data-template="1"' : '';

		// Migration automatique pour l'affichage
		$form_source = $data['form_source'] ?? 'learndash';
		$form_ref = $data['form_ref'] ?? '';
		if ( isset( $data['gf_form_id'] ) && ! isset( $data['form_source'] ) ) {
			$form_source = 'gravityforms';
			$form_ref = (string) $data['gf_form_id'];
		}
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
				<select class="small-text" name="wcqs[lines][<?php echo $template ? '{INDEX}' : uniqid(); ?>][form_source]">
					<option value="learndash" <?php selected( $form_source, 'learndash' ); ?>>LearnDash</option>
					<option value="gravityforms" <?php selected( $form_source, 'gravityforms' ); ?>>Gravity Forms</option>
				</select>
				<p class="description"><?php esc_html_e( 'Source du formulaire', 'wc_qualiopi_steps' ); ?></p>
			</td>
			<td>
				<input type="text" class="small-text"
					name="wcqs[lines][<?php echo $template ? '{INDEX}' : uniqid(); ?>][form_ref]" value="<?php echo esc_attr( $form_ref ); ?>" />
				<p class="description"><?php esc_html_e( 'ID/Référence', 'wc_qualiopi_steps' ); ?></p>
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