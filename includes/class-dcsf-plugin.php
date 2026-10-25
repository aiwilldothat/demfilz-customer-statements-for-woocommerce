<?php
/**
 * Plugin bootstrap singleton.
 *
 * @package DemfilzCustomerStatements
 */

defined( 'ABSPATH' ) || exit;

/**
 * Wires up the plugin components.
 */
class DCSF_Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var DCSF_Plugin|null
	 */
	private static $instance = null;

	/**
	 * Admin controller.
	 *
	 * @var DCSF_Admin
	 */
	public $admin;

	/**
	 * Get the singleton instance.
	 *
	 * @return DCSF_Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor — boot components.
	 */
	private function __construct() {
		// Translations for plugins hosted on WordPress.org load automatically.
		if ( is_admin() ) {
			$this->admin = new DCSF_Admin();
			$this->admin->init();
		}
	}
}
