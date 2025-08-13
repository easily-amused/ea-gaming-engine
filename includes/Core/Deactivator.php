<?php
/**
 * Plugin deactivation
 *
 * @package EAGamingEngine
 */

namespace EAGamingEngine\Core;

/**
 * Deactivator class
 */
class Deactivator {

	/**
	 * Deactivate the plugin
	 *
	 * @return void
	 */
	public static function deactivate() {
		// Clear scheduled events
		self::clear_scheduled_events();

		// Clean up transients
		self::clean_transients();

		// Flush rewrite rules
		flush_rewrite_rules();
	}

	/**
	 * Clear scheduled events
	 *
	 * @return void
	 */
	private static function clear_scheduled_events() {
		wp_clear_scheduled_hook( 'ea_gaming_engine_daily_cleanup' );
		wp_clear_scheduled_hook( 'ea_gaming_engine_policy_check' );
	}

	/**
	 * Clean up transients
	 *
	 * @return void
	 */
	private static function clean_transients() {
		global $wpdb;

		// Delete all plugin transients
		$wpdb->query(
			"DELETE FROM {$wpdb->options} 
			WHERE option_name LIKE '_transient_ea_gaming_%' 
			OR option_name LIKE '_transient_timeout_ea_gaming_%'"
		);
	}
}