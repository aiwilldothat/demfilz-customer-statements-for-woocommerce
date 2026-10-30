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
	 * Whether Pro is currently active.
	 *
	 * An authoritative "invalid" from the licence server turns Pro off
	 * immediately. Grace applies ONLY to transport failures (server
	 * unreachable), is anchored to the FIRST failure, and re-checks are
	 * rate-limited so an outage never blocks page renders repeatedly.
	 *
	 * @return bool
	 */
	public static function is_pro_active() {
		$s = self::state();
		if ( empty( $s['key'] ) ) {
			return false;
		}

		$stale     = empty( $s['checked_at'] ) || ( time() - (int) $s['checked_at'] ) > self::CACHE_TTL;
		$may_retry = empty( $s['retry_at'] ) || time() >= (int) $s['retry_at'];

		if ( $stale && $may_retry && ! get_transient( 'dcsf_lic_lock' ) ) {
			set_transient( 'dcsf_lic_lock', 1, 2 * MINUTE_IN_SECONDS );
			self::validate();
			delete_transient( 'dcsf_lic_lock' );
			$s = self::state();
		}

		if ( empty( $s['active'] ) ) {
			return false;
		}

		// Active from the last successful check; expire it when the server
		// has been unreachable past the grace window.
		if ( ! empty( $s['grace_until'] ) && time() >= (int) $s['grace_until'] ) {
			return false;
		}

		return true;
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
				'timeout' => 10,
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
			// Transport failure: keep the previous verdict, back off retries,
			// and anchor a one-off grace window to the FIRST failure so an
			// extended outage eventually expires Pro.
			if ( empty( $prev['grace_until'] ) ) {
				$prev['grace_until'] = time() + self::GRACE_PERIOD;
			}
			$prev['retry_at'] = time() + 15 * MINUTE_IN_SECONDS;
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
				// Authoritative answer: no grace either way.
				'grace_until' => 0,
				'retry_at'    => 0,
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
