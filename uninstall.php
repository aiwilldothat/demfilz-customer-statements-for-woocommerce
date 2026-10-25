<?php
/**
 * Uninstall — remove plugin settings when the user opted in.
 *
 * Order data, customers and WooCommerce records are never touched.
 *
 * @package DemfilzCustomerStatements
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$dcsf_settings = get_option( 'dcsf_settings' );

if ( is_array( $dcsf_settings ) && ! empty( $dcsf_settings['delete_on_uninstall'] ) ) {
	delete_option( 'dcsf_settings' );
}
