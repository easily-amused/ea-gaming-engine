<?php
/**
 * EA Gaming Engine Uninstall
 *
 * Handles complete cleanup when the plugin is deleted.
 *
 * @package EAGamingEngine
 */

// If uninstall not called from WordPress, then exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Main uninstall class
 */
class EAGamingEngineUninstaller {

	/**
	 * Plugin option prefix
	 */
	const OPTION_PREFIX = 'ea_gaming_engine_';

	/**
	 * Database table names
	 */
	private static $tables = array(
		'ea_game_sessions',
		'ea_game_policies',
		'ea_question_attempts',
		'ea_player_stats',
		'ea_hint_usage',
	);

	/**
	 * Run the uninstall process
	 *
	 * @return void
	 */
	public static function uninstall() {
		global $wpdb;

		// Check if we should keep data
		$keep_data = get_option( self::OPTION_PREFIX . 'keep_data_on_uninstall', false );

		if ( $keep_data ) {
			// Only clean up temporary data, keep user data
			self::cleanup_temporary_data();
			return;
		}

		// Export data if requested
		$export_data = get_option( self::OPTION_PREFIX . 'export_data_on_uninstall', false );
		if ( $export_data ) {
			self::export_plugin_data();
		}

		// Remove database tables
		self::remove_database_tables();

		// Remove all plugin options
		self::remove_plugin_options();

		// Clean up transients and caches
		self::cleanup_temporary_data();

		// Remove custom capabilities
		self::remove_custom_capabilities();

		// Clean up user meta
		self::cleanup_user_meta();

		// Remove cron events
		self::remove_cron_events();

		// Clean up uploads
		self::cleanup_uploads();

		// Remove custom database indexes (if any)
		self::cleanup_database_indexes();

		// Log uninstall
		error_log( 'EA Gaming Engine: Plugin uninstalled and all data removed.' );
	}

	/**
	 * Remove all database tables
	 *
	 * @return void
	 */
	private static function remove_database_tables() {
		global $wpdb;

		foreach ( self::$tables as $table ) {
			$table_name = $wpdb->prefix . $table;

			// Check if table exists before dropping
			$table_exists = $wpdb->get_var(
				$wpdb->prepare(
					'SHOW TABLES LIKE %s',
					$table_name
				)
			);

			if ( $table_exists ) {
				// Drop the table
				$wpdb->query( "DROP TABLE IF EXISTS `{$table_name}`" );

				// Log table removal
				error_log( "EA Gaming Engine: Removed table {$table_name}" );
			}
		}

		// Remove database version option
		delete_option( self::OPTION_PREFIX . 'db_version' );
	}

	/**
	 * Remove all plugin options
	 *
	 * @return void
	 */
	private static function remove_plugin_options() {
		global $wpdb;

		// Get all options with our prefix
		$options = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
				self::OPTION_PREFIX . '%'
			)
		);

		// Remove each option
		foreach ( $options as $option ) {
			delete_option( $option );
		}

		// Remove specific known options that might not follow the prefix pattern
		$specific_options = array(
			'ea_gaming_arcade_settings',
			'ea_gaming_launcher_settings',
			'ea_gaming_stats_settings',
			'ea_gaming_leaderboard_settings',
		);

		foreach ( $specific_options as $option ) {
			delete_option( $option );
		}

		error_log( 'EA Gaming Engine: Removed ' . count( $options ) . ' plugin options.' );
	}

	/**
	 * Clean up temporary data (transients, caches, etc.)
	 *
	 * @return void
	 */
	private static function cleanup_temporary_data() {
		global $wpdb;

		// Remove all plugin transients
		$wpdb->query(
			"DELETE FROM {$wpdb->options} 
			WHERE option_name LIKE '_transient_ea_gaming_%' 
			OR option_name LIKE '_transient_timeout_ea_gaming_%'"
		);

		// Remove all plugin site transients
		$wpdb->query(
			"DELETE FROM {$wpdb->options} 
			WHERE option_name LIKE '_site_transient_ea_gaming_%' 
			OR option_name LIKE '_site_transient_timeout_ea_gaming_%'"
		);

		// Clear object cache if available
		if ( function_exists( 'wp_cache_flush' ) ) {
			wp_cache_flush();
		}

		// Clear any plugin-specific cache groups
		if ( function_exists( 'wp_cache_flush_group' ) ) {
			wp_cache_flush_group( 'ea_gaming_engine' );
		}

		error_log( 'EA Gaming Engine: Cleaned up temporary data and caches.' );
	}

	/**
	 * Remove custom capabilities and roles
	 *
	 * @return void
	 */
	private static function remove_custom_capabilities() {
		// Get all roles
		$roles = wp_roles();

		if ( ! $roles || ! property_exists( $roles, 'roles' ) ) {
			return;
		}

		$custom_capabilities = array(
			'ea_gaming_manage_games',
			'ea_gaming_view_analytics',
			'ea_gaming_manage_policies',
			'ea_gaming_play_games',
			'ea_gaming_access_advanced',
		);

		// Remove capabilities from all roles
		foreach ( $roles->roles as $role_name => $role_info ) {
			$role = get_role( $role_name );
			if ( $role ) {
				foreach ( $custom_capabilities as $cap ) {
					$role->remove_cap( $cap );
				}
			}
		}

		// Remove any custom roles we might have created
		$custom_roles = array(
			'ea_gaming_instructor',
			'ea_gaming_student',
		);

		foreach ( $custom_roles as $role ) {
			remove_role( $role );
		}

		error_log( 'EA Gaming Engine: Removed custom capabilities and roles.' );
	}

	/**
	 * Clean up user meta
	 *
	 * @return void
	 */
	private static function cleanup_user_meta() {
		global $wpdb;

		// Remove all plugin-related user meta
		$user_meta_keys = array(
			'ea_gaming_theme_preference',
			'ea_gaming_profile_preset',
			'ea_gaming_game_settings',
			'ea_gaming_achievements',
			'ea_gaming_stats_cache',
			'ea_gaming_last_activity',
			'ea_gaming_preferences',
		);

		foreach ( $user_meta_keys as $meta_key ) {
			$wpdb->delete( $wpdb->usermeta, array( 'meta_key' => $meta_key ) );
		}

		// Remove meta keys with our prefix pattern
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE %s",
				self::OPTION_PREFIX . '%'
			)
		);

		error_log( 'EA Gaming Engine: Cleaned up user meta data.' );
	}

	/**
	 * Remove scheduled cron events
	 *
	 * @return void
	 */
	private static function remove_cron_events() {
		// Clear all plugin cron events
		$cron_events = array(
			'ea_gaming_engine_daily_cleanup',
			'ea_gaming_engine_policy_check',
			'ea_gaming_engine_stats_update',
			'ea_gaming_engine_cache_cleanup',
		);

		foreach ( $cron_events as $event ) {
			wp_clear_scheduled_hook( $event );
		}

		error_log( 'EA Gaming Engine: Removed scheduled cron events.' );
	}

	/**
	 * Clean up uploaded files
	 *
	 * @return void
	 */
	private static function cleanup_uploads() {
		$upload_dir        = wp_upload_dir();
		$plugin_upload_dir = $upload_dir['basedir'] . '/ea-gaming-engine';

		if ( is_dir( $plugin_upload_dir ) ) {
			self::recursive_rmdir( $plugin_upload_dir );
			error_log( 'EA Gaming Engine: Removed upload directory.' );
		}

		// Remove any exported data files
		$export_files = glob( $upload_dir['basedir'] . '/ea-gaming-export-*.json' );
		foreach ( $export_files as $file ) {
			if ( file_exists( $file ) ) {
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

	/**
	 * Clean up database indexes
	 *
	 * @return void
	 */
	private static function cleanup_database_indexes() {
		global $wpdb;

		// Remove any custom indexes we might have added to existing tables
		$custom_indexes = array(
			array(
				'table' => $wpdb->posts,
				'index' => 'ea_gaming_post_type',
			),
			array(
				'table' => $wpdb->postmeta,
				'index' => 'ea_gaming_meta_key',
			),
		);

		foreach ( $custom_indexes as $index_info ) {
			$index_exists = $wpdb->get_var(
				$wpdb->prepare(
					"SHOW INDEX FROM {$index_info['table']} WHERE Key_name = %s",
					$index_info['index']
				)
			);

			if ( $index_exists ) {
				$wpdb->query( "DROP INDEX {$index_info['index']} ON {$index_info['table']}" );
			}
		}
	}

	/**
	 * Export plugin data before removal
	 *
	 * @return void
	 */
	private static function export_plugin_data() {
		global $wpdb;

		$export_data = array(
			'export_date'    => current_time( 'mysql' ),
			'plugin_version' => get_option( self::OPTION_PREFIX . 'version', '1.0.0' ),
			'options'        => array(),
			'tables'         => array(),
		);

		// Export all plugin options
		$options = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE %s",
				self::OPTION_PREFIX . '%'
			),
			ARRAY_A
		);

		foreach ( $options as $option ) {
			$export_data['options'][ $option['option_name'] ] = maybe_unserialize( $option['option_value'] );
		}

		// Export table data
		foreach ( self::$tables as $table ) {
			$table_name = $wpdb->prefix . $table;

			$table_exists = $wpdb->get_var(
				$wpdb->prepare(
					'SHOW TABLES LIKE %s',
					$table_name
				)
			);

			if ( $table_exists ) {
				$export_data['tables'][ $table ] = $wpdb->get_results(
					"SELECT * FROM {$table_name}",
					ARRAY_A
				);
			}
		}

		// Save export file
		$upload_dir  = wp_upload_dir();
		$export_file = $upload_dir['basedir'] . '/ea-gaming-export-' . date( 'Y-m-d-H-i-s' ) . '.json';

		file_put_contents(
			$export_file,
			json_encode( $export_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE )
		);

		error_log( "EA Gaming Engine: Data exported to {$export_file}" );
	}
}

// Run the uninstall process
EAGamingEngineUninstaller::uninstall();
