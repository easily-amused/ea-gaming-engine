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
		// Clear scheduled events.
		self::clear_scheduled_events();

		// Clean up transients and caches.
		self::clean_transients();

		// Clear object cache.
		self::clear_object_cache();

		// Clean up user sessions.
		self::cleanup_user_sessions();

		// Remove temporary files.
		self::cleanup_temporary_files();

		// Flush rewrite rules.
		flush_rewrite_rules();
	}

	/**
	 * Clear scheduled events
	 *
	 * @return void
	 */
	private static function clear_scheduled_events() {
		$cron_events = array(
			'ea_gaming_engine_daily_cleanup',
			'ea_gaming_engine_policy_check',
			'ea_gaming_engine_stats_update',
			'ea_gaming_engine_cache_cleanup',
		);

		foreach ( $cron_events as $event ) {
			wp_clear_scheduled_hook( $event );
		}
	}

	/**
	 * Clean up transients
	 *
	 * @return void
	 */
	private static function clean_transients() {
		global $wpdb;

		// Delete all plugin transients.
		$wpdb->query(
			"DELETE FROM {$wpdb->options} 
			WHERE option_name LIKE '_transient_ea_gaming_%' 
			OR option_name LIKE '_transient_timeout_ea_gaming_%'"
		);

		// Delete all plugin site transients.
		$wpdb->query(
			"DELETE FROM {$wpdb->options} 
			WHERE option_name LIKE '_site_transient_ea_gaming_%' 
			OR option_name LIKE '_site_transient_timeout_ea_gaming_%'"
		);
	}

	/**
	 * Clear object cache
	 *
	 * @return void
	 */
	private static function clear_object_cache() {
		// Clear WordPress object cache if available.
		if ( function_exists( 'wp_cache_flush' ) ) {
			wp_cache_flush();
		}

		// Clear plugin-specific cache groups.
		if ( function_exists( 'wp_cache_flush_group' ) ) {
			wp_cache_flush_group( 'ea_gaming_engine' );
			wp_cache_flush_group( 'ea_gaming_sessions' );
			wp_cache_flush_group( 'ea_gaming_policies' );
		}
	}

	/**
	 * Clean up user sessions
	 *
	 * @return void
	 */
	private static function cleanup_user_sessions() {
		global $wpdb;

		// Clear any active game sessions that weren't properly closed.
		$table_name   = $wpdb->prefix . 'ea_game_sessions';
		$table_exists = $wpdb->get_var(
			$wpdb->prepare(
				'SHOW TABLES LIKE %s',
				$table_name
			)
		);

		if ( $table_exists ) {
			// Mark incomplete sessions as completed.
			$wpdb->update(
				$table_name,
				array(
					'completed'  => 0,
					'updated_at' => current_time( 'mysql' ),
				),
				array( 'completed' => 0 )
			);
		}

		// Clear any session-related user meta.
		$wpdb->delete(
			$wpdb->usermeta,
			array( 'meta_key' => 'ea_gaming_active_session' )
		);
	}

	/**
	 * Clean up temporary files
	 *
	 * @return void
	 */
	private static function cleanup_temporary_files() {
		// Clean up any temporary cache files.
		$upload_dir = wp_upload_dir();
		$temp_dir   = $upload_dir['basedir'] . '/ea-gaming-temp';

		if ( is_dir( $temp_dir ) ) {
			self::recursive_rmdir( $temp_dir );
		}

		// Clean up old export files (older than 24 hours).
		$export_files = glob( $upload_dir['basedir'] . '/ea-gaming-export-*.json' );
		$cutoff_time  = time() - DAY_IN_SECONDS;

		foreach ( $export_files as $file ) {
			if ( file_exists( $file ) && filemtime( $file ) < $cutoff_time ) {
				unlink( $file );
			}
		}
	}

	/**
	 * Recursively remove directory
	 *
	 * @param string $dir Directory path.
	 * @return bool
	 */
	private static function recursive_rmdir( $dir ) {
		if ( ! is_dir( $dir ) ) {
			return false;
		}

		$files = array_diff( scandir( $dir ), array( '.', '..' ) );

		foreach ( $files as $file ) {
			$path = $dir . '/' . $file;
			if ( is_dir( $path ) ) {
				self::recursive_rmdir( $path );
			} else {
				unlink( $path );
			}
		}

		return rmdir( $dir );
	}
}
