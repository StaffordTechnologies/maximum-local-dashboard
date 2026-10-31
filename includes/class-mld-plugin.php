<?php
/**
 * Plugin bootstrap singleton.
 *
 * @package MaximumLocalDashboard
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class MLD_Plugin
 */
class MLD_Plugin {

	/**
	 * Singleton.
	 *
	 * @var MLD_Plugin|null
	 */
	private static $instance = null;

	/**
	 * Get instance.
	 *
	 * @return MLD_Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		MLD_Admin::init();
		MLD_Settings::init();
		MLD_Rest::init();
		MLD_Tracking::init();
	}
}
