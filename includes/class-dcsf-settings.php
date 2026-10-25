<?php
/**
 * Settings storage and helpers.
 *
 * @package DemfilzCustomerStatements
 */

defined( 'ABSPATH' ) || exit;

/**
 * Reads, writes and resolves the plugin options.
 */
class DCSF_Settings {

	const OPTION = 'dcsf_settings';

	/**
	 * Default option values.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'fy_start_month'      => 7,
			'date_basis'          => 'paid',
			'statuses'            => array(),
			'tax_label'           => 'GST',
			'company_name'        => '',
			'company_abn'         => '',
			'company_address'     => '',
			'company_logo'        => '',
			'statement_notes'     => '',
			'delete_on_uninstall' => 0,
		);
	}

	/**
	 * Get all settings merged with defaults.
	 *
	 * @return array
	 */
	public static function all() {
		$saved = get_option( self::OPTION, array() );
		if ( ! is_array( $saved ) ) {
			$saved = array();
		}
		return wp_parse_args( $saved, self::defaults() );
	}

	/**
	 * Get a single setting.
	 *
	 * @param string $key     Setting key.
	 * @param mixed  $default_value Fallback value.
	 * @return mixed
	 */
	public static function get( $key, $default_value = '' ) {
		$all = self::all();
		return isset( $all[ $key ] ) ? $all[ $key ] : $default_value;
	}

	/**
	 * Sanitise and persist a raw settings payload.
	 *
	 * @param array $raw Unslashed $_POST payload.
	 * @return void
	 */
	public static function save( $raw ) {
		$raw = is_array( $raw ) ? $raw : array();

		$statuses = array();
		if ( isset( $raw['statuses'] ) && is_array( $raw['statuses'] ) ) {
			foreach ( $raw['statuses'] as $status ) {
				$status = preg_replace( '/^wc-/', '', sanitize_key( $status ) );
				if ( '' !== $status ) {
					$statuses[] = $status;
				}
			}
		}

		$clean = array(
			'fy_start_month'      => max( 1, min( 12, absint( $raw['fy_start_month'] ?? 7 ) ) ),
			'date_basis'          => ( isset( $raw['date_basis'] ) && 'created' === $raw['date_basis'] ) ? 'created' : 'paid',
			'statuses'            => array_values( array_unique( $statuses ) ),
			'tax_label'           => sanitize_text_field( $raw['tax_label'] ?? 'GST' ),
			'company_name'        => sanitize_text_field( $raw['company_name'] ?? '' ),
			'company_abn'         => sanitize_text_field( $raw['company_abn'] ?? '' ),
			'company_address'     => sanitize_textarea_field( $raw['company_address'] ?? '' ),
			'company_logo'        => esc_url_raw( $raw['company_logo'] ?? '' ),
			'statement_notes'     => sanitize_textarea_field( $raw['statement_notes'] ?? '' ),
			'delete_on_uninstall' => empty( $raw['delete_on_uninstall'] ) ? 0 : 1,
		);

		if ( '' === $clean['tax_label'] ) {
			$clean['tax_label'] = 'GST';
		}

		update_option( self::OPTION, $clean );
	}

	/**
	 * The order statuses that count as a paid invoice.
	 *
	 * Falls back to WooCommerce's "paid" statuses (processing + completed)
	 * when the user has not chosen any explicitly.
	 *
	 * @return array
	 */
	public static function effective_statuses() {
		$selected = array_filter( (array) self::get( 'statuses', array() ) );
		if ( empty( $selected ) ) {
			return wc_get_is_paid_statuses();
		}
		return array_map(
			function ( $status ) {
				return preg_replace( '/^wc-/', '', $status );
			},
			$selected
		);
	}

	/**
	 * Resolve the business header used on statements, falling back to the store.
	 *
	 * @return array
	 */
	public static function company_display() {
		$all = self::all();

		$name = $all['company_name'];
		if ( '' === $name ) {
			$name = get_bloginfo( 'name' );
		}

		$address = $all['company_address'];
		if ( '' === $address ) {
			$lines   = array(
				get_option( 'woocommerce_store_address' ),
				get_option( 'woocommerce_store_address_2' ),
				trim( get_option( 'woocommerce_store_city' ) . ' ' . get_option( 'woocommerce_store_postcode' ) ),
			);
			$address = implode( "\n", array_filter( array_map( 'trim', $lines ) ) );
		}

		return array(
			'name'    => $name,
			'abn'     => $all['company_abn'],
			'address' => $address,
			'logo'    => $all['company_logo'],
			'notes'   => $all['statement_notes'],
		);
	}
}
