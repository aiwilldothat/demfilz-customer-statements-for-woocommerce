<?php
/**
 * Pro licence client. Talks to the shared Demfilz licence endpoint to
 * activate/validate/deactivate a key for this product.
 *
 * External service: https://api.demfilz.com/auto-order/licence (declared in readme.txt).
 *
 * @package DemfilzCustomerStatements
 */

defined( 'ABSPATH' ) || exit;

/**
 * Validates and caches the Pro licence status.
 */
class DCSF_Licence {

	const OPTION       = 'dcsf_licence';
	const ENDPOINT     = 'https://api.demfilz.com/auto-order/licence';
	const PRODUCT      = 'demfilz-customer-statements';
	const CACHE_TTL    = 43200; // 12 hours.
	const GRACE_PERIOD = 604800; // 7 days.

	/**
	 * Stored licence state.
	 *
	 * @return array
	 */
	public static function state() {
		$s = get_option( self::OPTION, array() );
		return is_array( $s ) ? $s : array();
	}

	/**
	 * Whether Pro is currently active (valid, or within grace after a failed check).
	 *
	 * @return bool
	 */
	public static function is_pro_active() {
		$s = self::state();
		if ( empty( $s['key'] ) ) {
			return false;
		}

		if ( empty( $s['checked_at'] ) || ( time() - (int) $s['checked_at'] ) > self::CACHE_TTL ) {
			self::validate();
			$s = self::state();
		}

		if ( ! empty( $s['active'] ) ) {
			return true;
		}

		if ( ! empty( $s['grace_until'] ) && time() < (int) $s['grace_until'] ) {
			return true;
		}

		return false;
	}

	/**
	 * Activate a licence key against this site URL.
	 *
	 * @param string $key Licence key.
	 * @return true|WP_Error
	 */
	public static function activate( $key ) {
		$key = sanitize_text_field( $key );
		return self::call( 'activate', $key );
	}

	/**
	 * Validate the stored key.
	 *
	 * @return true|WP_Error
	 */
	public static function validate() {
		$s = self::state();
		if ( empty( $s['key'] ) ) {
			return new WP_Error( 'dcsf_no_key', __( 'No licence key stored.', 'demfilz-customer-statements-for-woocommerce' ) );
		}
		return self::call( 'validate', $s['key'] );
	}

	/**
	 * Deactivate this site.
	 *
	 * @return true
	 */
	public static function deactivate() {
		$s = self::state();
		if ( ! empty( $s['key'] ) ) {
			self::call( 'deactivate', $s['key'] );
		}
		delete_option( self::OPTION );
		return true;
	}

	/**
	 * Perform a licence API call and persist the result.
	 *
	 * @param string $action activate|validate|deactivate.
	 * @param string $key    Licence key.
	 * @return true|WP_Error
	 */
	private static function call( $action, $key ) {
		$response = wp_remote_post(
			self::ENDPOINT,
			array(
				'timeout' => 20,
				'body'    => array(
					'action'  => $action,
					'key'     => $key,
					'site'    => home_url(),
					'product' => self::PRODUCT,
					'version' => DCSF_VERSION,
				),
			)
		);

		$prev = self::state();

		if ( is_wp_error( $response ) ) {
			$prev['grace_until'] = isset( $prev['grace_until'] ) ? $prev['grace_until'] : ( time() + self::GRACE_PERIOD );
			update_option( self::OPTION, $prev, false );
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		$ok   = ! empty( $body['valid'] );

		update_option(
			self::OPTION,
			array(
				'key'         => $key,
				'active'      => $ok,
				'plan'        => isset( $body['plan'] ) ? sanitize_text_field( $body['plan'] ) : '',
				'expires'     => isset( $body['expires'] ) ? sanitize_text_field( $body['expires'] ) : '',
				'checked_at'  => time(),
				'grace_until' => $ok ? 0 : ( time() + self::GRACE_PERIOD ),
				'message'     => isset( $body['message'] ) ? sanitize_text_field( $body['message'] ) : '',
			),
			false
		);

		if ( ! $ok && 'activate' === $action ) {
			$msg = isset( $body['message'] ) ? $body['message'] : __( 'Licence key was not accepted.', 'demfilz-customer-statements-for-woocommerce' );
			return new WP_Error( 'dcsf_licence_invalid', $msg );
		}

		return true;
	}

	/**
	 * Hosted Stripe checkout URL for this product's subscription.
	 *
	 * @return string
	 */
	public static function checkout_url() {
		return add_query_arg(
			array(
				'product' => rawurlencode( self::PRODUCT ),
				'site'    => rawurlencode( home_url() ),
			),
			'https://api.demfilz.com/auto-order/subscribe'
		);
	}

	/**
	 * Customer account / billing portal URL.
	 *
	 * @return string
	 */
	public static function account_url() {
		return 'https://api.demfilz.com/account/';
	}
}
