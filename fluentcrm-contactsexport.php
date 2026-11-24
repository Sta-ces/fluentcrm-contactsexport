<?php
/**
 * Plugin Name:       Export FluentCRM Contacts
 * Plugin URI:        https://github.com/Sta-ces/fluentcrm-contactsexport
 * Description:       Export all FluentCRM contacts in one click — with every field, lists, tags, statuses, and custom fields — to an Excel-ready CSV file, or copy them as a table to the clipboard for pasting into Excel. Single file, no dependencies, no settings.
 * Version:           0.5.2
 * Requires at least: 5.2
 * Requires PHP:      7.0
 * Author:            Cedric Staces
 * Author URI:        https://github.com/Sta-ces
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       fluentcrm-contactsexport
 * Domain Path:       /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'FCE_PLUGIN_FILE', __FILE__ );

final class FCE_Contact_Exporter {

	const VERSION = '0.5.1';
	const SLUG    = 'fce-contact-exporter';
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
		add_action( 'init', array( $this, 'load_textdomain' ) );
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_menu', array( $this, 'add_wp_submenu' ), 999 );
		add_action( 'admin_init', array( $this, 'maybe_deactivate_on_pro' ) );
		add_filter( 'fluent_crm/menu_items', array( $this, 'inject_menu_item' ) );
		add_action( 'admin_post_fce_export_csv', array( $this, 'handle_csv_export' ) );
		add_action( 'wp_ajax_fce_export_rows', array( $this, 'handle_ajax_rows' ) );
		add_action( 'admin_notices', array( $this, 'maybe_render_deactivated_notice' ) );
	}

	public function load_textdomain() {
		load_plugin_textdomain(
			'fluentcrm-contactsexport',
			false,
			dirname( plugin_basename( __FILE__ ) ) . '/languages'
		);
	}

	private function table( $name ) {
		global $wpdb;
		return $wpdb->prefix . $name;
	}

	/**
	 * FluentCRM Pro ships contact export natively, so this plugin deactivates itself.
	 */
	private function is_pro_active() {
		if ( defined( 'FLUENTCAMPAIGN' ) || defined( 'FLUENTCAMPAIGN_PLUGIN_PATH' ) ) {
			return true;
		}

		if ( ! function_exists( 'is_plugin_active' ) ) {
			include_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		return function_exists( 'is_plugin_active' ) && is_plugin_active( 'fluent-crm-pro/fluent-crm-pro.php' );
	}

	public function maybe_deactivate_on_pro() {
		if ( ! $this->is_pro_active() || ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		deactivate_plugins( plugin_basename( FCE_PLUGIN_FILE ) );
		set_transient( 'fce_deactivated_pro', 1, 5 * MINUTE_IN_SECONDS );

		if ( isset( $_GET['activate'] ) ) {
			unset( $_GET['activate'] );
		}
	}

	public function maybe_render_deactivated_notice() {
		if ( ! get_transient( 'fce_deactivated_pro' ) ) {
			return;
		}
		delete_transient( 'fce_deactivated_pro' );

		printf(
			'<div class="notice notice-info is-dismissible"><p>%s</p></div>',
			esc_html__( 'Export FluentCRM Contacts was deactivated because FluentCRM Pro already provides contact export.', 'fluentcrm-contactsexport' )
		);
	}

	/**
	 * Registers the export page as a hidden admin page, reachable by its classic
	 * WordPress URL (admin.php?page=fce-contact-exporter). The visible entry is
	 * injected into the FluentCRM sidebar under Contacts, right after Tags.
	 */
	public function register_menu() {
		if ( $this->is_pro_active() ) {
			return;
		}

		add_submenu_page(
			null,
			__( 'Export Contacts', 'fluentcrm-contactsexport' ),
			__( 'Export Contacts', 'fluentcrm-contactsexport' ),
			'manage_options',
			self::SLUG,
			array( $this, 'render_page' )
		);
	}

	/**
	 * Adds an "Export Contacts" entry to the WordPress admin sidebar, under the
	 * FluentCRM menu, right after Tags. The URL is given explicitly (the same
	 * way FluentCRM handles its own hash-based entries) so it always resolves
	 * to admin.php?page=fce-contact-exporter.
	 */
	public function add_wp_submenu() {
		global $submenu;

		if ( $this->is_pro_active() ) {
			return;
		}

		$parent = 'fluentcrm-admin';
		if ( empty( $submenu[ $parent ] ) || ! is_array( $submenu[ $parent ] ) ) {
			return;
		}

		$entries = array_values( (array) $submenu[ $parent ] );

		foreach ( $entries as $existing ) {
			if ( isset( $existing[2] ) && self::SLUG === $existing[2] ) {
				return;
			}
		}

		$title = __( 'Export Contacts', 'fluentcrm-contactsexport' );
		$entry = array( $title, 'manage_options', 'admin.php?page=' . self::SLUG, $title );

		$insert_at = count( $entries );
		foreach ( $entries as $position => $sub_item ) {
			if ( isset( $sub_item[2] ) && 'fluentcrm-admin#/contact-groups/tags' === $sub_item[2] ) {
				$insert_at = $position + 1;
				break;
			}
		}

		array_splice( $entries, $insert_at, 0, array( $entry ) );
		$submenu[ $parent ] = $entries;
	}

	/**
	 * Adds the export entry to FluentCRM's sidebar under Contacts, after Tags.
	 *
	 * @param array $menu_items FluentCRM sidebar menu items.
	 * @return array
	 */
	public function inject_menu_item( $menu_items ) {
		if ( ! is_array( $menu_items ) || ! $this->is_ready_check() || ! $this->user_can_export() ) {
			return $menu_items;
		}

		foreach ( $menu_items as $index => $item ) {
			if ( empty( $item['key'] ) || 'contacts' !== $item['key'] ) {
				continue;
			}

			$export_item = array(
				'key'         => 'fce_export_contacts',
				'label'       => __( 'Export Contacts', 'fluentcrm-contactsexport' ),
				'permalink'   => admin_url( 'admin.php?page=' . self::SLUG ),
				'description' => __( 'Export all your contacts with lists, tags, statuses, and custom fields to Excel.', 'fluentcrm-contactsexport' ),
				'icon'		  => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640"><path d="M128 64C92.7 64 64 92.7 64 128L64 512C64 547.3 92.7 576 128 576L208 576L208 464C208 428.7 236.7 400 272 400L448 400L448 234.5C448 217.5 441.3 201.2 429.3 189.2L322.7 82.7C310.7 70.7 294.5 64 277.5 64L128 64zM389.5 240L296 240C282.7 240 272 229.3 272 216L272 122.5L389.5 240zM296 444C271.7 444 252 463.7 252 488L252 568C252 592.3 271.7 612 296 612L312 612C336.3 612 356 592.3 356 568L356 560C356 549 347 540 336 540C325 540 316 549 316 560L316 568C316 570.2 314.2 572 312 572L296 572C293.8 572 292 570.2 292 568L292 488C292 485.8 293.8 484 296 484L312 484C314.2 484 316 485.8 316 488L316 496C316 507 325 516 336 516C347 516 356 507 356 496L356 488C356 463.7 336.3 444 312 444L296 444zM432 444C403.3 444 380 467.3 380 496C380 524.7 403.3 548 432 548C438.6 548 444 553.4 444 560C444 566.6 438.6 572 432 572L400 572C389 572 380 581 380 592C380 603 389 612 400 612L432 612C460.7 612 484 588.7 484 560C484 531.3 460.7 508 432 508C425.4 508 420 502.6 420 496C420 489.4 425.4 484 432 484L456 484C467 484 476 475 476 464C476 453 467 444 456 444L432 444zM528 444C517 444 508 453 508 464L508 495.6C508 531.1 518.5 565.9 538.2 595.4L543.3 603.1C547 608.7 553.3 612 559.9 612C566.5 612 572.8 608.7 576.5 603.1L581.6 595.4C601.3 565.8 611.8 531.1 611.8 495.6L611.8 464C611.8 453 602.8 444 591.8 444C580.8 444 571.8 453 571.8 464L571.8 495.6C571.8 515.2 567.7 534.5 559.8 552.3C551.9 534.5 547.8 515.2 547.8 495.6L547.8 464C547.8 453 538.8 444 527.8 444z"/></svg>'
			);

			$items = isset( $item['sub_items'] ) && is_array( $item['sub_items'] ) ? $item['sub_items'] : array();
			if ( empty( $items ) ) {
				continue;
			}
			$insert_at = count( $items );
			foreach ( $items as $position => $sub_item ) {
				if ( ! empty( $sub_item['key'] ) && 'dynamic-segments' === $sub_item['key'] ) {
					$insert_at = $position + 1;
					break;
				}
			}

			array_splice( $menu_items[ $index ]['sub_items'], $insert_at, 0, array( $export_item ) );
			break;
		}

		return $menu_items;
	}

	/**
	 * Lightweight FluentCRM presence check for the menu injection.
	 */
	private function is_ready_check() {
		return defined( 'FLUENTCRM' ) || function_exists( 'fluentcrm_get_custom_contact_fields' );
	}

	/**
	 * Administrators, or any user granted the FluentCRM export capability.
	 */
	private function user_can_export() {
		return current_user_can( 'manage_options' ) || current_user_can( 'fcrm_manage_contacts_export' );
	}

	/**
	 * Checks that FluentCRM is active and its tables exist.
	 *
	 * @return true|WP_Error
	 */
	private function is_ready() {
		if ( ! defined( 'FLUENTCRM' ) && ! function_exists( 'fluentcrm_get_custom_contact_fields' ) ) {
			return new WP_Error(
				'fce_missing',
				__( 'FluentCRM must be installed and activated to use this export.', 'fluentcrm-contactsexport' )
			);
		}
		global $wpdb;
		$table = $this->table( 'fc_subscribers' );
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
			return new WP_Error(
				'fce_tables',
				__( 'The FluentCRM tables could not be found. Open FluentCRM once to finish its setup, then come back here.', 'fluentcrm-contactsexport' )
			);
		}
		return true;
	}

	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Access denied.', 'fluentcrm-contactsexport' ) );
		}

		$ready = $this->is_ready();
		if ( is_wp_error( $ready ) ) {
			echo '<div class="wrap"><div class="notice notice-error"><p>' . esc_html( $ready->get_error_message() ) . '</p></div></div>';
			return;
		}

		$count = $this->count_contacts( '' );

		$statuses = array(
			'subscribed'   => __( 'Subscribed', 'fluentcrm-contactsexport' ),
			'unsubscribed' => __( 'Unsubscribed', 'fluentcrm-contactsexport' ),
			'pending'      => __( 'Pending', 'fluentcrm-contactsexport' ),
			'bounced'      => __( 'Bounced', 'fluentcrm-contactsexport' ),
			'unconfirmed'  => __( 'Unconfirmed', 'fluentcrm-contactsexport' ),
		);

		$sep_options = array(
			';' => '; (Excel FR, DE…)',
			',' => ', (Excel EN)',
		);
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Export FluentCRM Contacts', 'fluentcrm-contactsexport' ); ?></h1>
			<p>
				<?php
				printf(
					esc_html(
						/* translators: %s: number of contacts */
						_n(
							'%s contact is ready to be exported (with lists, tags, status, and custom fields).',
							'%s contacts are ready to be exported (with lists, tags, status, and custom fields).',
							(int) $count,
							'fluentcrm-contactsexport'
						)
					),
					'<strong>' . esc_html( number_format_i18n( $count ) ) . '</strong>'
				);
				?>
			</p>

			<div style="max-width:560px;background:#fff;border:1px solid #ccd0d4;box-shadow:0 1px 1px rgba(0,0,0,.04);padding:8px 16px 16px;">
				<div style="display:flex;flex-wrap:wrap;justify-content:space-around;margin-bottom:2rem;align-items:flex-end;">
					<div>
						<label for="fce_status" style="display:block;font-weight:600;margin:8px 0 4px;"><?php esc_html_e( 'Status filter (optional)', 'fluentcrm-contactsexport' ); ?></label>
						<select id="fce_status" name="fce_status">
							<option value=""><?php esc_html_e( 'All contacts', 'fluentcrm-contactsexport' ); ?></option>
							<?php foreach ( $statuses as $value => $label ) : ?>
								<option value="<?php echo esc_attr( $value ); ?>"><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
					<div>
						<label for="fce_sep" style="display:block;font-weight:600;margin:12px 0 4px;"><?php esc_html_e( 'CSV separator', 'fluentcrm-contactsexport' ); ?></label>
						<select id="fce_sep" name="fce_sep">
							<?php foreach ( $sep_options as $value => $label ) : ?>
								<option value="<?php echo esc_attr( $value ); ?>"><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
				</div>

				<div>
					<button type="button" class="button button-primary button-hero" id="fce-btn-csv">
						<?php esc_html_e( '⬇ Download for Excel (CSV)', 'fluentcrm-contactsexport' ); ?>
					</button>
					<button type="button" class="button button-secondary button-hero" id="fce-btn-copy">
						<?php esc_html_e( 'Copy the table (paste into Excel)', 'fluentcrm-contactsexport' ); ?>
					</button>
				</div>

				<p id="fce-msg" style="display:none;"></p>
				<p>
					<em><?php esc_html_e( 'The CSV file opens directly in Excel (double-click). For the clipboard copy, click the first empty cell in Excel, then paste (Ctrl+V).', 'fluentcrm-contactsexport' ); ?></em>
				</p>
			</div>
		</div>

		<script type="text/javascript">
		(function () {
			var __ = {
				busy: <?php echo wp_json_encode( __( 'Retrieving contacts…', 'fluentcrm-contactsexport' ) ); ?>,
				prepared: <?php echo wp_json_encode( __( 'Contacts prepared:', 'fluentcrm-contactsexport' ) ); ?>,
				copied: <?php echo wp_json_encode( __( 'Contacts copied:', 'fluentcrm-contactsexport' ) ); ?>,
				paste: <?php echo wp_json_encode( __( 'Now paste into Excel with Ctrl+V (one cell per column).', 'fluentcrm-contactsexport' ) ); ?>,
				unknown: <?php echo wp_json_encode( __( 'Something went wrong.', 'fluentcrm-contactsexport' ) ); ?>
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
	/* Data                                                              */
	/* ----------------------------------------------------------------- */

	/**
	 * Columns of the fc_subscribers table and their export labels.
	 * Virtual keys prefixed with "__" are filled from joined tables.
	 */
	private function get_columns() {
		return array(
			array( 'key' => 'id',              'label' => __( 'ID', 'fluentcrm-contactsexport' ) ),
			array( 'key' => 'user_id',         'label' => __( 'WordPress user ID', 'fluentcrm-contactsexport' ) ),
			array( 'key' => 'first_name',      'label' => __( 'First name', 'fluentcrm-contactsexport' ) ),
			array( 'key' => 'last_name',       'label' => __( 'Last name', 'fluentcrm-contactsexport' ) ),
			array( 'key' => 'email',           'label' => __( 'Email', 'fluentcrm-contactsexport' ) ),
			array( 'key' => 'status',          'label' => __( 'Status', 'fluentcrm-contactsexport' ) ),
			array( 'key' => 'contact_type',    'label' => __( 'Contact type', 'fluentcrm-contactsexport' ) ),
			array( 'key' => 'phone',           'label' => __( 'Phone', 'fluentcrm-contactsexport' ) ),
			array( 'key' => 'prefix',          'label' => __( 'Title', 'fluentcrm-contactsexport' ) ),
			array( 'key' => 'address_line_1',  'label' => __( 'Address line 1', 'fluentcrm-contactsexport' ) ),
			array( 'key' => 'address_line_2',  'label' => __( 'Address line 2', 'fluentcrm-contactsexport' ) ),
			array( 'key' => 'postal_code',     'label' => __( 'Postal code', 'fluentcrm-contactsexport' ) ),
			array( 'key' => 'city',            'label' => __( 'City', 'fluentcrm-contactsexport' ) ),
			array( 'key' => 'state',           'label' => __( 'State / Province', 'fluentcrm-contactsexport' ) ),
			array( 'key' => 'country',         'label' => __( 'Country', 'fluentcrm-contactsexport' ) ),
			array( 'key' => 'timezone',        'label' => __( 'Timezone', 'fluentcrm-contactsexport' ) ),
			array( 'key' => 'date_of_birth',   'label' => __( 'Date of birth', 'fluentcrm-contactsexport' ) ),
			array( 'key' => 'total_points',    'label' => __( 'Points', 'fluentcrm-contactsexport' ) ),
			array( 'key' => 'life_time_value', 'label' => __( 'Lifetime value', 'fluentcrm-contactsexport' ) ),
			array( 'key' => 'source',          'label' => __( 'Source', 'fluentcrm-contactsexport' ) ),
			array( 'key' => 'ip',              'label' => __( 'IP', 'fluentcrm-contactsexport' ) ),
			array( 'key' => 'company_id',      'label' => __( 'Company ID', 'fluentcrm-contactsexport' ) ),
			array( 'key' => 'created_at',      'label' => __( 'Created at', 'fluentcrm-contactsexport' ) ),
			array( 'key' => 'updated_at',      'label' => __( 'Updated at', 'fluentcrm-contactsexport' ) ),
			array( 'key' => 'last_activity',   'label' => __( 'Last activity', 'fluentcrm-contactsexport' ) ),
			array( 'key' => '__lists',         'label' => __( 'Lists', 'fluentcrm-contactsexport' ) ),
			array( 'key' => '__tags',          'label' => __( 'Tags', 'fluentcrm-contactsexport' ) ),
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
	 * Custom field keys with their label when available.
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

		$existing_keys = $wpdb->get_col( 'SELECT DISTINCT `key` FROM ' . $this->table( 'fc_subscriber_meta' ) . " WHERE object_type = 'custom_field' LIMIT 500" );
		foreach ( (array) $existing_keys as $key ) {
			if ( $key && ! isset( $keys[ $key ] ) ) {
				$keys[ $key ] = $key;
			}
		}

		return $keys;
	}

	/**
	 * Fetches a chunk of contacts with tags, lists, and custom fields.
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

		if ( ! $this->user_can_export() ) {
			wp_send_json_error( array( 'message' => __( 'Access denied.', 'fluentcrm-contactsexport' ) ), 403 );
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
	 * Streams the CSV export chunk by chunk to avoid memory exhaustion.
	 */
	public function handle_csv_export() {
		if ( ! $this->user_can_export() ) {
			wp_die( esc_html__( 'Access denied.', 'fluentcrm-contactsexport' ), 403 );
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

		$last_id        = 0;
		$count          = 0;
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
