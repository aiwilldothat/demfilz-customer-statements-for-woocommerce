<?php
/**
 * Pro feature gate. The free plugin defines the extension points; the separate
 * Pro add-on plugin hooks into them only when the licence is active.
 *
 * @package DemfilzCustomerStatements
 */

defined( 'ABSPATH' ) || exit;

/**
 * Central authority for "is a Pro feature allowed right now".
 */
class DCSF_Pro {

	/**
	 * Whether the Pro add-on plugin is installed and active.
	 *
	 * @return bool
	 */
	public static function addon_installed() {
		return defined( 'DCSF_PRO_VERSION' );
	}

	/**
	 * Whether a Pro feature is available (add-on present AND licence active).
	 *
	 * @param string $feature Feature key (informational; reserved for per-plan gating).
	 * @return bool
	 */
	public static function can( $feature = '' ) {
		if ( ! self::addon_installed() ) {
			return false;
		}
		$allowed = DCSF_Licence::is_pro_active();

		/**
		 * Filter the final Pro gate decision.
		 *
		 * @param bool   $allowed Whether the feature is allowed.
		 * @param string $feature Feature key.
		 */
		return (bool) apply_filters( 'dcsf_pro_can', $allowed, $feature );
	}
}
