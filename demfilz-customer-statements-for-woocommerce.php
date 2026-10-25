<?php
/**
 * Plugin Name:       Demfilz Customer Statements for WooCommerce
 * Plugin URI:        https://demfilz.com/customer-statements-for-woocommerce
 * Description:       Generate, display and export a financial-year, quarterly or monthly statement of paid orders for selected WooCommerce customers. Print, save as PDF or download CSV. Counts actual paid invoices only.
 * Version:           1.2.1
 * Requires at least: 6.2
 * Requires PHP:      7.4
 * Requires Plugins:  woocommerce
 * Author:            Demfilz
 * Author URI:        https://demfilz.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       demfilz-customer-statements-for-woocommerce
 * WC requires at least: 6.0
 * WC tested up to:   9.5
 *
 * @package DemfilzCustomerStatements
 */

defined( 'ABSPATH' ) || exit;

define( 'DCSF_VERSION', '1.2.1' );
define( 'DCSF_FILE', __FILE__ );
define( 'DCSF_PATH', plugin_dir_path( __FILE__ ) );
define( 'DCSF_URL', plugin_dir_url( __FILE__ ) );
define( 'DCSF_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Declare compatibility with WooCommerce High-Performance Order Storage (HPOS).
 */
add_action(
	'before_woocommerce_init',
	function () {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', DCSF_FILE, true );
		}
	}
);

/**
 * Boot the plugin once all plugins are loaded, guarding for WooCommerce.
 *
 * @return void
 */
add_action(
	'plugins_loaded',
	function () {
		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action(
				'admin_notices',
				function () {
					echo '<div class="notice notice-error"><p>';
					echo esc_html__( 'Demfilz Customer Statements for WooCommerce requires WooCommerce to be installed and active.', 'demfilz-customer-statements-for-woocommerce' );
					echo '</p></div>';
				}
			);
			return;
		}

		require_once DCSF_PATH . 'includes/class-dcsf-settings.php';
		require_once DCSF_PATH . 'includes/class-dcsf-periods.php';
		require_once DCSF_PATH . 'includes/class-dcsf-report.php';
		require_once DCSF_PATH . 'includes/class-dcsf-licence.php';
		require_once DCSF_PATH . 'includes/class-dcsf-pro.php';
		require_once DCSF_PATH . 'includes/class-dcsf-admin.php';
		require_once DCSF_PATH . 'includes/class-dcsf-plugin.php';

		DCSF_Plugin::instance();
	}
);

/**
 * Activation: seed default options.
 *
 * @return void
 */
register_activation_hook(
	__FILE__,
	function () {
		require_once DCSF_PATH . 'includes/class-dcsf-settings.php';
		if ( false === get_option( 'dcsf_settings', false ) ) {
			add_option( 'dcsf_settings', DCSF_Settings::defaults() );
		}
	}
);
