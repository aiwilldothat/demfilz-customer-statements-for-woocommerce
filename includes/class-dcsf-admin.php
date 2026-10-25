<?php
/**
 * Admin menu, pages, settings handling and export endpoints.
 *
 * @package DemfilzCustomerStatements
 */

defined( 'ABSPATH' ) || exit;

/**
 * Builds the Customer Statements admin screens.
 */
class DCSF_Admin {

	const CAP  = 'manage_woocommerce';
	const SLUG = 'dcsf';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_init', array( $this, 'handle_settings_post' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
		add_action( 'admin_post_dcsf_export_csv', array( $this, 'export_csv' ) );
		add_action( 'admin_post_dcsf_print', array( $this, 'print_document' ) );
		add_filter( 'plugin_action_links_' . DCSF_BASENAME, array( $this, 'action_links' ) );
	}

	/**
	 * Register the menu and sub-pages.
	 *
	 * @return void
	 */
	public function menu() {
		add_menu_page(
			__( 'Customer Statements', 'demfilz-customer-statements-for-woocommerce' ),
			__( 'Statements', 'demfilz-customer-statements-for-woocommerce' ),
			self::CAP,
			self::SLUG,
			array( $this, 'page_statement' ),
			'dashicons-media-spreadsheet',
			56
		);
		add_submenu_page( self::SLUG, __( 'Statement', 'demfilz-customer-statements-for-woocommerce' ), __( 'Statement', 'demfilz-customer-statements-for-woocommerce' ), self::CAP, self::SLUG, array( $this, 'page_statement' ) );
		add_submenu_page( self::SLUG, __( 'Settings', 'demfilz-customer-statements-for-woocommerce' ), __( 'Settings', 'demfilz-customer-statements-for-woocommerce' ), self::CAP, self::SLUG . '-settings', array( $this, 'page_settings' ) );
		add_submenu_page( self::SLUG, __( 'Licence / Pro', 'demfilz-customer-statements-for-woocommerce' ), __( 'Licence / Pro', 'demfilz-customer-statements-for-woocommerce' ), self::CAP, self::SLUG . '-licence', array( $this, 'page_licence' ) );
	}

	/**
	 * Settings link on the plugins screen.
	 *
	 * @param array $links Existing links.
	 * @return array
	 */
	public function action_links( $links ) {
		$link = '<a href="' . esc_url( admin_url( 'admin.php?page=' . self::SLUG ) ) . '">' . esc_html__( 'Statements', 'demfilz-customer-statements-for-woocommerce' ) . '</a>';
		array_unshift( $links, $link );
		return $links;
	}

	/**
	 * Enqueue CSS/JS on our pages only.
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public function assets( $hook ) {
		if ( false === strpos( (string) $hook, self::SLUG ) ) {
			return;
		}

		wp_enqueue_style( 'dcsf-admin', DCSF_URL . 'admin/css/admin.css', array(), (string) filemtime( DCSF_PATH . 'admin/css/admin.css' ) );

		// WooCommerce-powered customer search box + media picker for the logo.
		wp_enqueue_style( 'woocommerce_admin_styles' );
		wp_enqueue_script( 'wc-enhanced-select' );
		wp_enqueue_media();

		wp_enqueue_script( 'dcsf-admin', DCSF_URL . 'admin/js/admin.js', array( 'jquery', 'wc-enhanced-select', 'media-editor' ), (string) filemtime( DCSF_PATH . 'admin/js/admin.js' ), true );
	}

	// ----- Settings / Licence POST -----

	/**
	 * Handle settings and licence form submissions.
	 *
	 * @return void
	 */
	public function handle_settings_post() {
		if ( ! isset( $_POST['dcsf_action'] ) || ! current_user_can( self::CAP ) ) {
			return;
		}
		$action = sanitize_key( wp_unslash( $_POST['dcsf_action'] ) );

		if ( 'save_settings' === $action ) {
			check_admin_referer( 'dcsf_save_settings' );
			// Nonce verified above; each field sanitised in DCSF_Settings::save().
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash
			$raw = isset( $_POST['dcsf'] ) ? wp_unslash( $_POST['dcsf'] ) : array();
			DCSF_Settings::save( $raw );
			wp_safe_redirect( admin_url( 'admin.php?page=' . self::SLUG . '-settings&dcsf_msg=saved' ) );
			exit;
		}

		if ( 'activate_licence' === $action ) {
			check_admin_referer( 'dcsf_licence' );
			$key    = isset( $_POST['dcsf_licence_key'] ) ? sanitize_text_field( wp_unslash( $_POST['dcsf_licence_key'] ) ) : '';
			$result = DCSF_Licence::activate( $key );
			if ( is_wp_error( $result ) ) {
				set_transient( 'dcsf_lic_err', $result->get_error_message(), 120 );
				$msg = 'lic_err';
			} else {
				delete_transient( 'dcsf_lic_err' );
				$msg = 'lic_ok';
			}
			wp_safe_redirect( admin_url( 'admin.php?page=' . self::SLUG . '-licence&dcsf_msg=' . $msg ) );
			exit;
		}

		if ( 'deactivate_licence' === $action ) {
			check_admin_referer( 'dcsf_licence' );
			DCSF_Licence::deactivate();
			wp_safe_redirect( admin_url( 'admin.php?page=' . self::SLUG . '-licence&dcsf_msg=lic_cleared' ) );
			exit;
		}
	}

	// ----- Pages -----

	/**
	 * The statement builder + results page.
	 *
	 * @return void
	 */
	public function page_statement() {
		echo '<div class="wrap dcsf-wrap">';
		echo '<h1>' . esc_html__( 'Customer Statements', 'demfilz-customer-statements-for-woocommerce' ) . '</h1>';

		$filters   = DCSF_Report::parse_request( $this->get_request() );
		$submitted = $this->is_generate_request();

		require DCSF_PATH . 'admin/views/statement.php';

		if ( $submitted && DCSF_Report::has_targets( $filters ) ) {
			$data       = DCSF_Report::build( $filters );
			$export_url = admin_url( 'admin-post.php' ) . '?' . http_build_query( $this->export_query( $filters ) );
			DCSF_Report::render_results( $data, $export_url );
		} elseif ( $submitted ) {
			echo '<div class="notice notice-warning"><p>' . esc_html__( 'Select at least one customer (or enter a billing email) to generate a statement.', 'demfilz-customer-statements-for-woocommerce' ) . '</p></div>';
		}

		echo '</div>';
	}

	/**
	 * The settings page.
	 *
	 * @return void
	 */
	public function page_settings() {
		echo '<div class="wrap dcsf-wrap">';
		echo '<h1>' . esc_html__( 'Customer Statements — Settings', 'demfilz-customer-statements-for-woocommerce' ) . '</h1>';

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['dcsf_msg'] ) && 'saved' === sanitize_key( wp_unslash( $_GET['dcsf_msg'] ) ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Settings saved.', 'demfilz-customer-statements-for-woocommerce' ) . '</p></div>';
		}

		$s = DCSF_Settings::all();
		require DCSF_PATH . 'admin/views/settings.php';
		echo '</div>';
	}

	/**
	 * The Licence / Pro page.
	 *
	 * @return void
	 */
	public function page_licence() {
		echo '<div class="wrap dcsf-wrap">';
		echo '<h1>' . esc_html__( 'Customer Statements — Licence / Pro', 'demfilz-customer-statements-for-woocommerce' ) . '</h1>';

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$msg = isset( $_GET['dcsf_msg'] ) ? sanitize_key( wp_unslash( $_GET['dcsf_msg'] ) ) : '';
		if ( 'lic_ok' === $msg ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Licence activated — Pro features are unlocked.', 'demfilz-customer-statements-for-woocommerce' ) . '</p></div>';
		} elseif ( 'lic_cleared' === $msg ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Licence removed from this site.', 'demfilz-customer-statements-for-woocommerce' ) . '</p></div>';
		} elseif ( 'lic_err' === $msg ) {
			$detail = get_transient( 'dcsf_lic_err' );
			delete_transient( 'dcsf_lic_err' );
			echo '<div class="notice notice-error is-dismissible"><p>';
			echo esc_html__( 'That licence key was not accepted.', 'demfilz-customer-statements-for-woocommerce' );
			if ( $detail ) {
				echo ' <strong>' . esc_html__( 'Reason:', 'demfilz-customer-statements-for-woocommerce' ) . '</strong> ' . esc_html( $detail );
			} else {
				echo ' ' . esc_html__( 'Please check it and try again.', 'demfilz-customer-statements-for-woocommerce' );
			}
			echo '</p>';
			if ( $detail && preg_match( '/could not|resolve|timed out|timeout|curl|ssl|connect|refused|http_request_failed/i', $detail ) ) {
				echo '<p>' . esc_html__( 'Your site could not reach the Demfilz licence server (api.demfilz.com). This is almost always an outbound-connection block on your hosting, not a problem with the key. Ask your host to allow outbound HTTPS to api.demfilz.com, temporarily pause any firewall or security plugin (such as Wordfence), then try activating again.', 'demfilz-customer-statements-for-woocommerce' ) . '</p>';
			}
			echo '</div>';
		}

		$lic     = DCSF_Licence::state();
		$active  = DCSF_Licence::is_pro_active();
		$has_pro = DCSF_Pro::addon_installed();
		require DCSF_PATH . 'admin/views/licence.php';
		echo '</div>';
	}

	// ----- Export endpoints (admin-post) -----

	/**
	 * Stream the CSV export.
	 *
	 * @return void
	 */
	public function export_csv() {
		$filters = $this->verify_export();
		$data    = DCSF_Report::build( $filters );
		DCSF_Report::stream_csv( $data );
		exit;
	}

	/**
	 * Output the print/PDF document.
	 *
	 * @return void
	 */
	public function print_document() {
		$filters = $this->verify_export();
		$data    = DCSF_Report::build( $filters );
		DCSF_Report::render_document( $data );
		exit;
	}

	/**
	 * Verify capability + nonce for an export request and return filters.
	 *
	 * @return array
	 */
	private function verify_export() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'You do not have permission to do that.', 'demfilz-customer-statements-for-woocommerce' ) );
		}
		check_admin_referer( 'dcsf_export' );
		return DCSF_Report::parse_request( $this->get_request() );
	}

	// ----- Request helpers -----

	/**
	 * Read the current request payload, verifying the generate nonce for GET
	 * submissions (read-only display) where present.
	 *
	 * @return array
	 */
	private function get_request() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only filters; nonce checked in is_generate_request()/verify_export() before any action.
		return wp_unslash( $_GET );
	}

	/**
	 * Whether this is a valid "generate statement" request.
	 *
	 * @return bool
	 */
	private function is_generate_request() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $_GET['dcsf_generate'] ) ) {
			return false;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$nonce = isset( $_GET['dcsf_nonce'] ) ? sanitize_text_field( wp_unslash( $_GET['dcsf_nonce'] ) ) : '';
		return (bool) wp_verify_nonce( $nonce, 'dcsf_generate' );
	}

	/**
	 * Build the query-arg set used for export/print URLs.
	 *
	 * @param array $filters Parsed filters.
	 * @return array
	 */
	private function export_query( $filters ) {
		return array(
			'customer_ids' => $filters['customer_ids'],
			'emails'       => implode( ',', $filters['emails'] ),
			'period'       => $filters['period'],
			'fy'           => $filters['fy'],
			'quarter'      => $filters['quarter'],
			'month'        => $filters['month'],
			'cal_year'     => $filters['cal_year'],
			'from'         => $filters['from'],
			'to'           => $filters['to'],
		);
	}
}
