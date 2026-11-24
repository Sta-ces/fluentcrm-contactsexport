<?php
/**
 * Plugin Name:       Export Contacts FluentCRM (gratuit)
 * Plugin URI:        https://github.com/Sta-ces/fluentcrm-contactsexport
 * Description:       Exportez en un clic tous vos contacts FluentCRM avec toutes leurs informations, listes, étiquettes, statuts et champs personnalisés : téléchargement CSV compatible Excel ou copie du tableau dans le presse-papiers. Un seul fichier, aucune dépendance, aucun réglage.
 * Version:           0.1.0
 * Requires at least: 5.2
 * Requires PHP:      7.0
 * Author:            Sta-ces
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       fluentcrm-contactsexport
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class FCE_Contact_Exporter {

	const VERSION = '0.1.0';
	const SLUG    = 'fce-contact-exporter';
	const CAP     = 'manage_options';
	const NONCE   = 'fce_export_nonce';
	const CHUNK   = 500;

	private static $instance = null;

	public static function init() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_post_fce_export_csv', array( $this, 'handle_csv_export' ) );
		add_action( 'wp_ajax_fce_export_rows', array( $this, 'handle_ajax_rows' ) );
	}

	private function table( $name ) {
		global $wpdb;
		return $wpdb->prefix . $name;
	}

	public function register_menu() {
		add_submenu_page(
			'fluentcrm-admin',
			__( 'Export des contacts', 'fluentcrm-contactsexport' ),
			__( 'Export Contacts', 'fluentcrm-contactsexport' ),
			self::CAP,
			self::SLUG,
			array( $this, 'render_page' )
		);
	}

	/**
	 * Vérifie que FluentCRM est actif et que ses tables existent.
	 *
	 * @return true|WP_Error
	 */
	private function is_ready() {
		if ( ! defined( 'FLUENTCRM' ) && ! function_exists( 'fluentcrm_get_custom_contact_fields' ) ) {
			return new WP_Error( 'fce_missing', __( 'FluentCRM doit être installé et activé pour utiliser cet export.', 'fluentcrm-contactsexport' ) );
		}
		global $wpdb;
		$table = $this->table( 'fc_subscribers' );
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
			return new WP_Error( 'fce_tables', __( 'Les tables de FluentCRM sont introuvables. Ouvrez FluentCRM une première fois pour terminer son installation, puis revenez ici.', 'fluentcrm-contactsexport' ) );
		}
		return true;
	}

	public function render_page() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'Accès refusé.', 'fluentcrm-contactsexport' ) );
		}

		$ready = $this->is_ready();
		if ( is_wp_error( $ready ) ) {
			echo '<div class="wrap"><div class="notice notice-error"><p>' . esc_html( $ready->get_error_message() ) . '</p></div></div>';
			return;
		}

		$count = $this->count_contacts( '' );

		$statuses = array(
			'subscribed'   => __( 'Inscrits (subscribed)', 'fluentcrm-contactsexport' ),
			'unsubscribed' => __( 'Désabonnés (unsubscribed)', 'fluentcrm-contactsexport' ),
			'pending'      => __( 'En attente (pending)', 'fluentcrm-contactsexport' ),
			'bounced'      => __( 'Rebondis (bounced)', 'fluentcrm-contactsexport' ),
			'unconfirmed'  => __( 'Non confirmés (unconfirmed)', 'fluentcrm-contactsexport' ),
		);

		$sep_options = array(
			';' => '; (Excel FR, DE…)',
			',' => ', (Excel EN)',
		);
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Export des contacts FluentCRM', 'fluentcrm-contactsexport' ); ?></h1>
			<p>
				<?php
				printf(
					esc_html(
						/* translators: %s: nombre de contacts */
						_n(
							'%s contact est prêt à être exporté (avec listes, étiquettes, statut et champs personnalisés).',
							'%s contacts sont prêts à être exportés (avec listes, étiquettes, statut et champs personnalisés).',
							(int) $count,
							'fluentcrm-contactsexport'
						)
					),
					'<strong>' . esc_html( number_format_i18n( $count ) ) . '</strong>'
				);
				?>
			</p>

			<div style="max-width:560px;background:#fff;border:1px solid #ccd0d4;box-shadow:0 1px 1px rgba(0,0,0,.04);padding:8px 16px 16px;">
				<p>
					<label for="fce_status" style="display:block;font-weight:600;margin:8px 0 4px;"><?php esc_html_e( 'Filtre de statut (facultatif)', 'fluentcrm-contactsexport' ); ?></label>
					<select id="fce_status" name="fce_status">
						<option value=""><?php esc_html_e( 'Tous les contacts', 'fluentcrm-contactsexport' ); ?></option>
						<?php foreach ( $statuses as $value => $label ) : ?>
							<option value="<?php echo esc_attr( $value ); ?>"><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>

					<label for="fce_sep" style="display:block;font-weight:600;margin:12px 0 4px;"><?php esc_html_e( 'Séparateur CSV', 'fluentcrm-contactsexport' ); ?></label>
					<select id="fce_sep" name="fce_sep">
						<?php foreach ( $sep_options as $value => $label ) : ?>
							<option value="<?php echo esc_attr( $value ); ?>"><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
				</p>

				<p>
					<button type="button" class="button button-primary button-hero" id="fce-btn-csv">
						<?php esc_html_e( '⬇ Télécharger en Excel (CSV)', 'fluentcrm-contactsexport' ); ?>
					</button>
					<button type="button" class="button button-secondary button-hero" id="fce-btn-copy">
						<?php esc_html_e( '📋 Copier le tableau (coller dans Excel)', 'fluentcrm-contactsexport' ); ?>
					</button>
				</p>

				<p id="fce-msg" style="display:none;"></p>
				<p>
					<em><?php esc_html_e( 'Le fichier CSV s’ouvre directement dans Excel (double-clic). Pour la copie, cliquez sur la première cellule vide d’Excel puis Collez (Ctrl+V).', 'fluentcrm-contactsexport' ); ?></em>
				</p>
			</div>
		</div>

		<script type="text/javascript">
		(function () {
			var __ = {
				busy: <?php echo wp_json_encode( __( 'Récupération en cours…', 'fluentcrm-contactsexport' ) ); ?>,
				prepared: <?php echo wp_json_encode( __( 'Contacts préparés :', 'fluentcrm-contactsexport' ) ); ?>,
				copied: <?php echo wp_json_encode( __( 'Contacts copiés :', 'fluentcrm-contactsexport' ) ); ?>,
				paste: <?php echo wp_json_encode( __( 'Collez maintenant avec Ctrl+V dans Excel (une cellule par colonne).', 'fluentcrm-contactsexport' ) ); ?>,
				unknown: <?php echo wp_json_encode( __( 'Erreur inconnue.', 'fluentcrm-contactsexport' ) ); ?>
			};

			var FCE_AJAX_URL = <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;
			var FCE_NONCE = <?php echo wp_json_encode( wp_create_nonce( self::NONCE ) ); ?>;
			var msg = document.getElementById('fce-msg');
			var btnCopy = document.getElementById('fce-btn-copy');
			var btnCsv = document.getElementById('fce-btn-csv');

			function showMsg(text, isError) {
				msg.style.display = 'block';
				msg.innerHTML = text;
				msg.style.color = isError ? '#b32d2e' : '';
			}

			function showBusy(button, busyText) {
				button.dataset.label = button.textContent;
				button.disabled = true;
				button.textContent = busyText;
			}

			function restore(button) {
				button.disabled = false;
				button.textContent = button.dataset.label;
			}

			btnCsv.addEventListener('click', function () {
				var form = document.createElement('form');
				form.method = 'POST';
				form.action = <?php echo wp_json_encode( admin_url( 'admin-post.php' ) ); ?>;
				[['action', 'fce_export_csv'], ['_wpnonce', FCE_NONCE], ['fce_status', document.getElementById('fce_status').value], ['fce_sep', document.getElementById('fce_sep').value]].forEach(function (pair) {
					var input = document.createElement('input');
					input.type = 'hidden';
					input.name = pair[0];
					input.value = pair[1];
					form.appendChild(input);
				});
				document.body.appendChild(form);
				form.submit();
				form.remove();
			});

			btnCopy.addEventListener('click', function () {
				var status = document.getElementById('fce_status').value;
				showBusy(btnCopy, __.busy);
				showMsg('');

				var columns = null;
				var tsv = [];
				var lastId = 0;
				var total = 0;

				function cell(v) {
					if (v === null || v === undefined) { return ''; }
					return String(v).replace(/[\t\r\n]+/g, ' ').trim();
				}

				function rowToTsv(row) {
					return row.map(cell).join('\t');
				}

				function nextChunk() {
					var body = new FormData();
					body.append('action', 'fce_export_rows');
					body.append('nonce', FCE_NONCE);
					body.append('last_id', String(lastId));
					body.append('limit', String(<?php echo (int) self::CHUNK; ?>));
					body.append('status', status);
					return fetch(FCE_AJAX_URL, { method: 'POST', credentials: 'same-origin', body: body })
						.then(function (r) { return r.json(); })
						.then(function (json) {
							if (!json || !json.success) {
								throw new Error((json && json.data && json.data.message) ? json.data.message : __.unknown);
							}
							if (!columns && json.data.columns) {
								columns = json.data.columns;
								tsv.push(rowToTsv(columns));
							}
							json.data.rows.forEach(function (row) {
								tsv.push(rowToTsv(row));
							});
							total += json.data.rows.length;
							lastId = json.data.last_id;
							if (json.data.rows.length > 0) {
								showMsg(__.prepared + ' <strong>' + total + '</strong>');
							}
							return json.data.has_more ? nextChunk() : undefined;
						});
				}

				nextChunk()
					.then(function () {
						var text = tsv.join('\r\n');
						if (navigator.clipboard && navigator.clipboard.writeText) {
							return navigator.clipboard.writeText(text);
						}
						var ta = document.createElement('textarea');
						ta.value = text;
						ta.style.position = 'fixed';
						ta.style.opacity = '0';
						document.body.appendChild(ta);
						ta.select();
						document.execCommand('copy');
						ta.remove();
					})
					.then(function () {
						restore(btnCopy);
						showMsg('✅ ' + __.copied + ' <strong>' + total + '</strong>. ' + __.paste);
					})
					.catch(function (err) {
						restore(btnCopy);
						showMsg('⚠️ ' + (err && err.message ? err.message : __.unknown), true);
					});
			});
		})();
		</script>
		<?php
	}

	/* ----------------------------------------------------------------- */
	/* Données                                                           */
	/* ----------------------------------------------------------------- */

	/**
	 * Colonnes de base de la table fc_subscribers et leur libellé d'export.
	 */
	private function get_columns() {
		return array(
			array( 'key' => 'id',              'label' => __( 'ID', 'fluentcrm-contactsexport' ) ),
			array( 'key' => 'user_id',         'label' => __( 'ID utilisateur WordPress', 'fluentcrm-contactsexport' ) ),
			array( 'key' => 'first_name',      'label' => __( 'Prénom', 'fluentcrm-contactsexport' ) ),
			array( 'key' => 'last_name',       'label' => __( 'Nom', 'fluentcrm-contactsexport' ) ),
			array( 'key' => 'email',           'label' => __( 'E-mail', 'fluentcrm-contactsexport' ) ),
			array( 'key' => 'status',          'label' => __( 'Statut', 'fluentcrm-contactsexport' ) ),
			array( 'key' => 'contact_type',    'label' => __( 'Type de contact', 'fluentcrm-contactsexport' ) ),
			array( 'key' => 'phone',           'label' => __( 'Téléphone', 'fluentcrm-contactsexport' ) ),
			array( 'key' => 'prefix',          'label' => __( 'Civilité', 'fluentcrm-contactsexport' ) ),
			array( 'key' => 'address_line_1',  'label' => __( 'Adresse 1', 'fluentcrm-contactsexport' ) ),
			array( 'key' => 'address_line_2',  'label' => __( 'Adresse 2', 'fluentcrm-contactsexport' ) ),
			array( 'key' => 'postal_code',     'label' => __( 'Code postal', 'fluentcrm-contactsexport' ) ),
			array( 'key' => 'city',            'label' => __( 'Ville', 'fluentcrm-contactsexport' ) ),
			array( 'key' => 'state',           'label' => __( 'Région/État', 'fluentcrm-contactsexport' ) ),
			array( 'key' => 'country',         'label' => __( 'Pays', 'fluentcrm-contactsexport' ) ),
			array( 'key' => 'timezone',        'label' => __( 'Fuseau horaire', 'fluentcrm-contactsexport' ) ),
			array( 'key' => 'date_of_birth',   'label' => __( 'Date de naissance', 'fluentcrm-contactsexport' ) ),
			array( 'key' => 'total_points',    'label' => __( 'Points', 'fluentcrm-contactsexport' ) ),
			array( 'key' => 'life_time_value', 'label' => __( 'Valeur vie client', 'fluentcrm-contactsexport' ) ),
			array( 'key' => 'source',          'label' => __( 'Source', 'fluentcrm-contactsexport' ) ),
			array( 'key' => 'ip',              'label' => __( 'IP', 'fluentcrm-contactsexport' ) ),
			array( 'key' => 'company_id',      'label' => __( 'ID entreprise', 'fluentcrm-contactsexport' ) ),
			array( 'key' => 'created_at',      'label' => __( 'Créé le', 'fluentcrm-contactsexport' ) ),
			array( 'key' => 'updated_at',      'label' => __( 'Modifié le', 'fluentcrm-contactsexport' ) ),
			array( 'key' => 'last_activity',   'label' => __( 'Dernière activité', 'fluentcrm-contactsexport' ) ),
			array( 'key' => '__lists',         'label' => __( 'Listes', 'fluentcrm-contactsexport' ) ),
			array( 'key' => '__tags',          'label' => __( 'Étiquettes', 'fluentcrm-contactsexport' ) ),
		);
	}

	private function status_where( $status ) {
		global $wpdb;
		$allowed = array( 'subscribed', 'unsubscribed', 'pending', 'bounced', 'unconfirmed' );
		if ( $status && in_array( $status, $allowed, true ) ) {
			return $wpdb->prepare( ' AND s.status = %s ', $status );
		}
		return '';
	}

	private function count_contacts( $status ) {
		global $wpdb;
		return (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . $this->table( 'fc_subscribers' ) . ' s WHERE 1=1 ' . $this->status_where( $status ) );
	}

	/**
	 * Clés des champs personnalisés, avec leur libellé si disponible.
	 */
	private function get_custom_field_keys() {
		global $wpdb;

		$keys = array();

		if ( function_exists( 'fluentcrm_get_custom_contact_fields' ) ) {
			$fields = fluentcrm_get_custom_contact_fields();
			if ( $fields && is_array( $fields ) ) {
				foreach ( $fields as $field ) {
					if ( ! empty( $field['slug'] ) ) {
						$keys[ $field['slug'] ] = ! empty( $field['label'] ) ? $field['label'] : $field['slug'];
					}
				}
			}
		}

		$existing_types = $wpdb->get_col( 'SELECT DISTINCT `key` FROM ' . $this->table( 'fc_subscriber_meta' ) . " WHERE object_type = 'custom_field' LIMIT 500" );
		foreach ( (array) $existing_types as $key ) {
			if ( $key && ! isset( $keys[ $key ] ) ) {
				$keys[ $key ] = $key;
			}
		}

		return $keys;
	}

	/**
	 * Extrait un lot de contacts avec tags, listes et champs personnalisés.
	 *
	 * @return array {columns: string[], rows: array[], last_id: int, has_more: bool}
	 */
	private function get_chunk( $status, $limit, $last_id ) {
		global $wpdb;

		$limit   = max( 1, min( 2000, (int) $limit ) );
		$last_id = (int) $last_id;

		$columns_src = $this->get_columns();
		$sub         = $this->table( 'fc_subscribers' );

		$select = array();
		foreach ( $columns_src as $col ) {
			if ( 0 === strpos( $col['key'], '__' ) ) {
				continue;
			}
			$select[] = 's.' . $col['key'];
		}

		$subs = $wpdb->get_results( 'SELECT ' . implode( ', ', $select ) . ' FROM ' . $sub . ' s WHERE s.id > ' . $last_id . $this->status_where( $status ) . ' ORDER BY s.id ASC LIMIT ' . $limit, ARRAY_A );

		$columns = array();
		foreach ( $columns_src as $col ) {
			$columns[] = $col['label'];
		}

		$custom = $this->get_custom_field_keys();
		foreach ( $custom as $label ) {
			$columns[] = $label;
		}

		$ids = array();
		foreach ( $subs as $s ) {
			$ids[] = (int) $s['id'];
		}

		$lists = array();
		$tags  = array();
		$metas = array();

		if ( $ids ) {
			$in  = '(' . implode( ',', $ids ) . ')';
			$pivot = $this->table( 'fc_subscriber_pivot' );

			$tag_rows = $wpdb->get_results( 'SELECT p.subscriber_id AS sid, t.title AS title FROM ' . $pivot . ' p JOIN ' . $this->table( 'fc_tags' ) . " t ON t.id = p.object_id WHERE p.object_type = 'FluentCrm\\App\\Models\\Tag' AND p.subscriber_id IN " . $in, ARRAY_A );
			$list_rows = $wpdb->get_results( 'SELECT p.subscriber_id AS sid, l.title AS title FROM ' . $pivot . ' p JOIN ' . $this->table( 'fc_lists' ) . " l ON l.id = p.object_id WHERE p.object_type = 'FluentCrm\\App\\Models\\Lists' AND p.subscriber_id IN " . $in, ARRAY_A );
			foreach ( (array) $tag_rows as $row ) {
				$tags[ $row['sid'] ][] = $row['title'];
			}
			foreach ( (array) $list_rows as $row ) {
				$lists[ $row['sid'] ][] = $row['title'];
			}
		}

		if ( $ids && $custom ) {
			$in       = '(' . implode( ',', $ids ) . ')';
			$key_args = array();
			$keys_sql = array();
			foreach ( array_keys( $custom ) as $key ) {
				$keys_sql[] = '%s';
				$key_args[] = $key;
			}
			$meta_sql = 'SELECT subscriber_id, `key`, value FROM ' . $this->table( 'fc_subscriber_meta' ) . ' WHERE object_type = \'custom_field\' AND `key` IN (' . implode( ',', $keys_sql ) . ") AND subscriber_id IN $in";
			$meta_rows = $wpdb->get_results( $wpdb->prepare( $meta_sql, $key_args ), ARRAY_A );
			foreach ( (array) $meta_rows as $row ) {
				$metas[ $row['subscriber_id'] ][ $row['key'] ] = maybe_unserialize( $row['value'] );
			}
		}

		$rows = array();
		foreach ( $subs as $s ) {
			$row = array();
			foreach ( $columns_src as $col ) {
				$key = $col['key'];
				if ( '__lists' === $key ) {
					$row[] = isset( $lists[ $s['id'] ] ) ? implode( ', ', $lists[ $s['id'] ] ) : '';
				} elseif ( '__tags' === $key ) {
					$row[] = isset( $tags[ $s['id'] ] ) ? implode( ', ', $tags[ $s['id'] ] ) : '';
				} else {
					$row[] = isset( $s[ $key ] ) ? (string) $s[ $key ] : '';
				}
			}

			$custom_vals = isset( $metas[ $s['id'] ] ) ? $metas[ $s['id'] ] : array();
			foreach ( array_keys( $custom ) as $key ) {
				$val = isset( $custom_vals[ $key ] ) ? $custom_vals[ $key ] : '';
				if ( is_array( $val ) ) {
					$val = implode( ', ', $val );
				}
				$row[] = (string) $val;
			}
			$rows[] = $row;
		}

		$has_more = count( $subs ) === $limit;
		$next_id  = $subs ? (int) end( $subs )['id'] : $last_id;

		return array(
			'columns'  => $columns,
			'rows'     => $rows,
			'last_id'  => $next_id,
			'has_more' => $has_more,
		);
	}

	public function handle_ajax_rows() {
		check_ajax_referer( self::NONCE, 'nonce' );

		if ( ! current_user_can( self::CAP ) ) {
			wp_send_json_error( array( 'message' => __( 'Accès refusé.', 'fluentcrm-contactsexport' ) ), 403 );
		}

		$ready = $this->is_ready();
		if ( is_wp_error( $ready ) ) {
			wp_send_json_error( array( 'message' => $ready->get_error_message() ) );
		}

		$last_id = isset( $_POST['last_id'] ) ? (int) $_POST['last_id'] : 0;
		$limit   = isset( $_POST['limit'] ) ? (int) $_POST['limit'] : self::CHUNK;
		$status  = isset( $_POST['status'] ) ? sanitize_key( wp_unslash( $_POST['status'] ) ) : '';

		wp_send_json_success( $this->get_chunk( $status, $limit, $last_id ) );
	}

	/**
	 * Export CSV diffusé en continu (lot par lot) pour éviter tout dépassement mémoire.
	 */
	public function handle_csv_export() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'Accès refusé.', 'fluentcrm-contactsexport' ), 403 );
		}
		check_admin_referer( self::NONCE );

		$ready = $this->is_ready();
		if ( is_wp_error( $ready ) ) {
			wp_die( esc_html( $ready->get_error_message() ) );
		}

		$status = isset( $_POST['fce_status'] ) ? sanitize_key( wp_unslash( $_POST['fce_status'] ) ) : '';
		$sep    = ( isset( $_POST['fce_sep'] ) && ',' === $_POST['fce_sep'] ) ? ',' : ';';

		$filename = 'fluentcrm-contacts-' . gmdate( 'Ymd-His' ) . '.csv';

		nocache_headers();
		header( 'Content-Type: text/csv; charset=UTF-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'X-Content-Type-Options: nosniff' );

		$out = fopen( 'php://output', 'w' );
		fwrite( $out, "\xEF\xBB\xBF" );

		$last_id = 0;
		$count   = 0;
		$header_written = false;
		do {
			$chunk = $this->get_chunk( $status, self::CHUNK, $last_id );
			if ( ! $header_written ) {
				fputcsv( $out, $chunk['columns'], $sep );
				$header_written = true;
			}
			foreach ( $chunk['rows'] as $row ) {
				fputcsv( $out, $row, $sep );
				$count++;
			}
			$last_id = $chunk['last_id'];
			flush();
		} while ( $chunk['has_more'] );

		fclose( $out );
		exit;
	}
}

add_action( 'plugins_loaded', array( 'FCE_Contact_Exporter', 'init' ), 20 );
