<?php
/**
 * Admin functionality
 *
 * @package EAGamingEngine
 */

namespace EAGamingEngine\Admin;

/**
 * Admin class
 */
class Admin {

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->init_hooks();
	}

	/**
	 * Initialize hooks
	 *
	 * @return void
	 */
	private function init_hooks() {
		add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );
		add_action( 'admin_init', [ $this, 'register_settings' ] );
		add_filter( 'plugin_action_links_' . EA_GAMING_ENGINE_BASENAME, [ $this, 'add_action_links' ] );
		add_action( 'admin_notices', [ $this, 'admin_notices' ] );
		
		// AJAX handlers
		add_action( 'wp_ajax_ea_gaming_save_settings', [ $this, 'ajax_save_settings' ] );
		add_action( 'wp_ajax_ea_gaming_get_settings', [ $this, 'ajax_get_settings' ] );
		add_action( 'wp_ajax_ea_gaming_reset_settings', [ $this, 'ajax_reset_settings' ] );
		add_action( 'wp_ajax_ea_gaming_get_analytics', [ $this, 'ajax_get_analytics' ] );
		add_action( 'wp_ajax_ea_gaming_export_data', [ $this, 'ajax_export_data' ] );
	}

	/**
	 * Add admin menu
	 *
	 * @return void
	 */
	public function add_admin_menu() {
		// Main menu
		add_menu_page(
			__( 'EA Gaming Engine', 'ea-gaming-engine' ),
			__( 'Gaming Engine', 'ea-gaming-engine' ),
			'manage_options',
			'ea-gaming-engine',
			[ $this, 'render_dashboard_page' ],
			'dashicons-games',
			30
		);

		// Dashboard submenu
		add_submenu_page(
			'ea-gaming-engine',
			__( 'Dashboard', 'ea-gaming-engine' ),
			__( 'Dashboard', 'ea-gaming-engine' ),
			'manage_options',
			'ea-gaming-engine',
			[ $this, 'render_dashboard_page' ]
		);

		// Settings submenu
		add_submenu_page(
			'ea-gaming-engine',
			__( 'Settings', 'ea-gaming-engine' ),
			__( 'Settings', 'ea-gaming-engine' ),
			'manage_options',
			'ea-gaming-settings',
			[ $this, 'render_settings_page' ]
		);

		// Policies submenu
		add_submenu_page(
			'ea-gaming-engine',
			__( 'Policies', 'ea-gaming-engine' ),
			__( 'Policies', 'ea-gaming-engine' ),
			'manage_options',
			'ea-gaming-policies',
			[ $this, 'render_policies_page' ]
		);

		// Analytics submenu
		add_submenu_page(
			'ea-gaming-engine',
			__( 'Analytics', 'ea-gaming-engine' ),
			__( 'Analytics', 'ea-gaming-engine' ),
			'manage_options',
			'ea-gaming-analytics',
			[ $this, 'render_analytics_page' ]
		);

		// Games submenu
		add_submenu_page(
			'ea-gaming-engine',
			__( 'Games', 'ea-gaming-engine' ),
			__( 'Games', 'ea-gaming-engine' ),
			'manage_options',
			'ea-gaming-games',
			[ $this, 'render_games_page' ]
		);
	}

	/**
	 * Enqueue admin assets
	 *
	 * @param string $hook Hook suffix.
	 * @return void
	 */
	public function enqueue_admin_assets( $hook ) {
		// Only load on our admin pages
		if ( strpos( $hook, 'ea-gaming' ) === false && $hook !== 'toplevel_page_ea-gaming-engine' ) {
			return;
		}

		// Enqueue WordPress dependencies
		wp_enqueue_script( 'wp-api' );
		wp_enqueue_script( 'wp-i18n' );
		wp_enqueue_script( 'wp-components' );
		wp_enqueue_script( 'wp-element' );
		wp_enqueue_script( 'wp-api-fetch' );
		wp_enqueue_script( 'wp-data' );
		wp_enqueue_script( 'wp-notices' );
		
		// Enqueue WordPress styles
		wp_enqueue_style( 'wp-components' );
		wp_enqueue_style( 'wp-notices' );

		// Enqueue Chart.js for analytics
		wp_enqueue_script(
			'chartjs',
			'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js',
			[],
			'4.4.0',
			true
		);

		// Enqueue our admin script
		wp_enqueue_script(
			'ea-gaming-admin',
			EA_GAMING_ENGINE_URL . 'assets/js/admin.js',
			[ 'wp-api', 'wp-i18n', 'wp-components', 'wp-element', 'wp-api-fetch', 'wp-data', 'wp-notices', 'chartjs' ],
			EA_GAMING_ENGINE_VERSION,
			true
		);

		// Enqueue admin styles
		wp_enqueue_style(
			'ea-gaming-admin',
			EA_GAMING_ENGINE_URL . 'assets/css/admin.css',
			[ 'wp-components' ],
			EA_GAMING_ENGINE_VERSION
		);

		// Localize script
		wp_localize_script(
			'ea-gaming-admin',
			'eaGamingAdmin',
			[
				'apiUrl'     => home_url( '/wp-json/ea-gaming/v1/' ),
				'nonce'      => wp_create_nonce( 'ea-gaming-engine-admin' ),
				'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
				'currentPage' => $hook,
				'settings'   => $this->get_all_settings(),
				'i18n'       => [
					'save'             => __( 'Save Settings', 'ea-gaming-engine' ),
					'saving'           => __( 'Saving...', 'ea-gaming-engine' ),
					'saved'            => __( 'Settings Saved', 'ea-gaming-engine' ),
					'error'            => __( 'Error saving settings', 'ea-gaming-engine' ),
					'reset'            => __( 'Reset to Defaults', 'ea-gaming-engine' ),
					'confirmReset'     => __( 'Are you sure you want to reset all settings to defaults?', 'ea-gaming-engine' ),
					'noData'           => __( 'No data available', 'ea-gaming-engine' ),
					'loading'          => __( 'Loading...', 'ea-gaming-engine' ),
					'exportData'       => __( 'Export Data', 'ea-gaming-engine' ),
					'exporting'        => __( 'Exporting...', 'ea-gaming-engine' ),
					'exported'         => __( 'Data Exported', 'ea-gaming-engine' ),
					'confirmExport'    => __( 'This will create a backup file with all plugin data. Continue?', 'ea-gaming-engine' ),
					'keepDataLabel'    => __( 'Keep data when uninstalling plugin', 'ea-gaming-engine' ),
					'keepDataHelp'     => __( 'If enabled, plugin data will be preserved when the plugin is deleted.', 'ea-gaming-engine' ),
					'exportDataLabel'  => __( 'Export data before uninstalling', 'ea-gaming-engine' ),
					'exportDataHelp'   => __( 'If enabled, a backup file will be created before deleting plugin data.', 'ea-gaming-engine' ),
				],
			]
		);

		// Add inline script for React mount point
		wp_add_inline_script(
			'ea-gaming-admin',
			'window.eaGamingAdminReady = true;',
			'after'
		);
	}

	/**
	 * Register settings
	 *
	 * @return void
	 */
	public function register_settings() {
		// General settings
		register_setting( 'ea_gaming_general', 'ea_gaming_engine_enabled' );
		register_setting( 'ea_gaming_general', 'ea_gaming_engine_default_theme' );
		register_setting( 'ea_gaming_general', 'ea_gaming_engine_default_preset' );

		// Policy settings
		register_setting( 'ea_gaming_policies', 'ea_gaming_engine_policies' );

		// Game settings
		register_setting( 'ea_gaming_games', 'ea_gaming_engine_games' );

		// Theme settings
		register_setting( 'ea_gaming_themes', 'ea_gaming_engine_themes' );

		// Hint system settings
		register_setting( 'ea_gaming_hints', 'ea_gaming_engine_hint_settings' );

		// Advanced settings
		register_setting( 'ea_gaming_advanced', 'ea_gaming_engine_cache_enabled' );
		register_setting( 'ea_gaming_advanced', 'ea_gaming_engine_debug_mode' );
		register_setting( 'ea_gaming_advanced', 'ea_gaming_engine_api_rate_limit' );
		register_setting( 'ea_gaming_advanced', 'ea_gaming_engine_keep_data_on_uninstall' );
		register_setting( 'ea_gaming_advanced', 'ea_gaming_engine_export_data_on_uninstall' );
	}

	/**
	 * Render dashboard page
	 *
	 * @return void
	 */
	public function render_dashboard_page() {
		?>
		<div class="wrap ea-gaming-admin-wrap">
			<h1><?php esc_html_e( 'EA Gaming Engine Dashboard', 'ea-gaming-engine' ); ?></h1>
			<div id="ea-gaming-dashboard" class="ea-gaming-admin-container">
				<!-- React app will mount here -->
				<div class="ea-gaming-loading">
					<span class="spinner is-active"></span>
					<p><?php esc_html_e( 'Loading dashboard...', 'ea-gaming-engine' ); ?></p>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render settings page
	 *
	 * @return void
	 */
	public function render_settings_page() {
		?>
		<div class="wrap ea-gaming-admin-wrap">
			<h1><?php esc_html_e( 'EA Gaming Engine Settings', 'ea-gaming-engine' ); ?></h1>
			<div id="ea-gaming-settings" class="ea-gaming-admin-container">
				<!-- React app will mount here -->
				<div class="ea-gaming-loading">
					<span class="spinner is-active"></span>
					<p><?php esc_html_e( 'Loading settings...', 'ea-gaming-engine' ); ?></p>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render policies page
	 *
	 * @return void
	 */
	public function render_policies_page() {
		?>
		<div class="wrap ea-gaming-admin-wrap">
			<h1><?php esc_html_e( 'Gaming Policies', 'ea-gaming-engine' ); ?></h1>
			<div id="ea-gaming-policies" class="ea-gaming-admin-container">
				<!-- React app will mount here -->
				<div class="ea-gaming-loading">
					<span class="spinner is-active"></span>
					<p><?php esc_html_e( 'Loading policies...', 'ea-gaming-engine' ); ?></p>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render analytics page
	 *
	 * @return void
	 */
	public function render_analytics_page() {
		?>
		<div class="wrap ea-gaming-admin-wrap">
			<h1><?php esc_html_e( 'Gaming Analytics', 'ea-gaming-engine' ); ?></h1>
			<div id="ea-gaming-analytics" class="ea-gaming-admin-container">
				<!-- React app will mount here -->
				<div class="ea-gaming-loading">
					<span class="spinner is-active"></span>
					<p><?php esc_html_e( 'Loading analytics...', 'ea-gaming-engine' ); ?></p>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render games page
	 *
	 * @return void
	 */
	public function render_games_page() {
		?>
		<div class="wrap ea-gaming-admin-wrap">
			<h1><?php esc_html_e( 'Game Configuration', 'ea-gaming-engine' ); ?></h1>
			<div id="ea-gaming-games" class="ea-gaming-admin-container">
				<!-- React app will mount here -->
				<div class="ea-gaming-loading">
					<span class="spinner is-active"></span>
					<p><?php esc_html_e( 'Loading games...', 'ea-gaming-engine' ); ?></p>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Add action links
	 *
	 * @param array $links Links array.
	 * @return array
	 */
	public function add_action_links( $links ) {
		$action_links = [
			'settings' => '<a href="' . admin_url( 'admin.php?page=ea-gaming-settings' ) . '">' . __( 'Settings', 'ea-gaming-engine' ) . '</a>',
			'docs'     => '<a href="https://honorswp.com/docs/ea-gaming-engine" target="_blank">' . __( 'Docs', 'ea-gaming-engine' ) . '</a>',
		];

		return array_merge( $action_links, $links );
	}

	/**
	 * Display admin notices
	 *
	 * @return void
	 */
	public function admin_notices() {
		// Check if LearnDash is active
		if ( ! class_exists( 'SFWD_LMS' ) ) {
			?>
			<div class="notice notice-warning">
				<p>
					<?php
					printf(
						/* translators: %s: LearnDash link */
						esc_html__( 'EA Gaming Engine requires %s to be installed and activated.', 'ea-gaming-engine' ),
						'<a href="https://www.learndash.com" target="_blank">LearnDash</a>'
					);
					?>
				</p>
			</div>
			<?php
		}

		// Check for first activation
		if ( get_transient( 'ea_gaming_engine_activation_notice' ) ) {
			?>
			<div class="notice notice-success is-dismissible">
				<p>
					<?php
					printf(
						/* translators: %s: Settings page link */
						esc_html__( 'EA Gaming Engine is ready! %s to get started.', 'ea-gaming-engine' ),
						'<a href="' . admin_url( 'admin.php?page=ea-gaming-settings' ) . '">' . __( 'Configure your settings', 'ea-gaming-engine' ) . '</a>'
					);
					?>
				</p>
			</div>
			<?php
			delete_transient( 'ea_gaming_engine_activation_notice' );
		}
	}

	/**
	 * Get all settings
	 *
	 * @return array
	 */
	private function get_all_settings() {
		return [
			'general'  => [
				'enabled'        => get_option( 'ea_gaming_engine_enabled', true ),
				'default_theme'  => get_option( 'ea_gaming_engine_default_theme', 'playful' ),
				'default_preset' => get_option( 'ea_gaming_engine_default_preset', 'classic' ),
			],
			'policies' => get_option( 'ea_gaming_engine_policies', $this->get_default_policies() ),
			'games'    => get_option( 'ea_gaming_engine_games', $this->get_default_games() ),
			'themes'   => get_option( 'ea_gaming_engine_themes', [] ),
			'hints'    => get_option( 'ea_gaming_engine_hint_settings', $this->get_default_hint_settings() ),
			'advanced' => [
				'cache_enabled'           => get_option( 'ea_gaming_engine_cache_enabled', true ),
				'debug_mode'              => get_option( 'ea_gaming_engine_debug_mode', false ),
				'api_rate_limit'          => get_option( 'ea_gaming_engine_api_rate_limit', 100 ),
				'keep_data_on_uninstall'  => get_option( 'ea_gaming_engine_keep_data_on_uninstall', false ),
				'export_data_on_uninstall' => get_option( 'ea_gaming_engine_export_data_on_uninstall', false ),
			],
		];
	}

	/**
	 * Get default policies
	 *
	 * @return array
	 */
	private function get_default_policies() {
		return [
			'free_play_enabled'   => false,
			'free_play_start'     => '15:00',
			'free_play_end'       => '17:00',
			'quiet_hours_enabled' => false,
			'quiet_hours_start'   => '22:00',
			'quiet_hours_end'     => '07:00',
			'study_first_enabled' => true,
			'study_first_minutes' => 10,
			'daily_limit_enabled' => false,
			'daily_limit_games'   => 10,
			'daily_limit_time'    => 60,
		];
	}

	/**
	 * Get default games
	 *
	 * @return array
	 */
	private function get_default_games() {
		return [
			'whack_a_question' => [
				'enabled'    => true,
				'difficulty' => 'medium',
				'time_limit' => 60,
			],
			'tic_tac_tactics'  => [
				'enabled'    => true,
				'difficulty' => 'medium',
			],
			'target_trainer'   => [
				'enabled'    => true,
				'difficulty' => 'medium',
				'targets'    => 10,
			],
		];
	}

	/**
	 * Get default hint settings
	 *
	 * @return array
	 */
	private function get_default_hint_settings() {
		return [
			'enabled' => true,
			'cooldown' => 30,
			'max_hints' => 3,
			'ai_integration' => false,
			'context_analysis' => true,
			'lesson_integration' => true,
		];
	}

	/**
	 * AJAX handler for saving settings
	 *
	 * @return void
	 */
	public function ajax_save_settings() {
		check_ajax_referer( 'ea-gaming-engine-admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Permission denied', 'ea-gaming-engine' ) );
		}

		$settings = json_decode( stripslashes( $_POST['settings'] ?? '{}' ), true );

		if ( empty( $settings ) ) {
			wp_send_json_error( __( 'Invalid settings data', 'ea-gaming-engine' ) );
		}

		// Save each settings group
		if ( isset( $settings['general'] ) ) {
			update_option( 'ea_gaming_engine_enabled', $settings['general']['enabled'] ?? true );
			update_option( 'ea_gaming_engine_default_theme', sanitize_text_field( $settings['general']['default_theme'] ?? 'playful' ) );
			update_option( 'ea_gaming_engine_default_preset', sanitize_text_field( $settings['general']['default_preset'] ?? 'classic' ) );
		}

		if ( isset( $settings['policies'] ) ) {
			update_option( 'ea_gaming_engine_policies', $settings['policies'] );
		}

		if ( isset( $settings['games'] ) ) {
			update_option( 'ea_gaming_engine_games', $settings['games'] );
		}

		if ( isset( $settings['themes'] ) ) {
			update_option( 'ea_gaming_engine_themes', $settings['themes'] );
		}

		if ( isset( $settings['advanced'] ) ) {
			update_option( 'ea_gaming_engine_cache_enabled', $settings['advanced']['cache_enabled'] ?? true );
			update_option( 'ea_gaming_engine_debug_mode', $settings['advanced']['debug_mode'] ?? false );
			update_option( 'ea_gaming_engine_api_rate_limit', intval( $settings['advanced']['api_rate_limit'] ?? 100 ) );
			update_option( 'ea_gaming_engine_keep_data_on_uninstall', $settings['advanced']['keep_data_on_uninstall'] ?? false );
			update_option( 'ea_gaming_engine_export_data_on_uninstall', $settings['advanced']['export_data_on_uninstall'] ?? false );
		}

		wp_send_json_success(
			[
				'message'  => __( 'Settings saved successfully', 'ea-gaming-engine' ),
				'settings' => $this->get_all_settings(),
			]
		);
	}

	/**
	 * AJAX handler for getting settings
	 *
	 * @return void
	 */
	public function ajax_get_settings() {
		check_ajax_referer( 'ea-gaming-engine-admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Permission denied', 'ea-gaming-engine' ) );
		}

		wp_send_json_success( $this->get_all_settings() );
	}

	/**
	 * AJAX handler for resetting settings
	 *
	 * @return void
	 */
	public function ajax_reset_settings() {
		check_ajax_referer( 'ea-gaming-engine-admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Permission denied', 'ea-gaming-engine' ) );
		}

		// Reset to defaults
		update_option( 'ea_gaming_engine_enabled', true );
		update_option( 'ea_gaming_engine_default_theme', 'playful' );
		update_option( 'ea_gaming_engine_default_preset', 'classic' );
		update_option( 'ea_gaming_engine_policies', $this->get_default_policies() );
		update_option( 'ea_gaming_engine_games', $this->get_default_games() );
		update_option( 'ea_gaming_engine_themes', [] );
		update_option( 'ea_gaming_engine_hint_settings', $this->get_default_hint_settings() );
		update_option( 'ea_gaming_engine_cache_enabled', true );
		update_option( 'ea_gaming_engine_debug_mode', false );
		update_option( 'ea_gaming_engine_api_rate_limit', 100 );
		update_option( 'ea_gaming_engine_keep_data_on_uninstall', false );
		update_option( 'ea_gaming_engine_export_data_on_uninstall', false );

		wp_send_json_success(
			[
				'message'  => __( 'Settings reset to defaults', 'ea-gaming-engine' ),
				'settings' => $this->get_all_settings(),
			]
		);
	}

	/**
	 * AJAX handler for getting analytics
	 *
	 * @return void
	 */
	public function ajax_get_analytics() {
		check_ajax_referer( 'ea-gaming-engine-admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Permission denied', 'ea-gaming-engine' ) );
		}

		global $wpdb;

		// Get analytics data
		$period = sanitize_text_field( $_POST['period'] ?? '7days' );
		$course_id = intval( $_POST['course_id'] ?? 0 );

		// Calculate date range
		$end_date = current_time( 'Y-m-d 23:59:59' );
		switch ( $period ) {
			case '24hours':
				$start_date = current_time( 'Y-m-d H:i:s', strtotime( '-24 hours' ) );
				break;
			case '7days':
				$start_date = current_time( 'Y-m-d 00:00:00', strtotime( '-7 days' ) );
				break;
			case '30days':
				$start_date = current_time( 'Y-m-d 00:00:00', strtotime( '-30 days' ) );
				break;
			default:
				$start_date = current_time( 'Y-m-d 00:00:00', strtotime( '-7 days' ) );
		}

		// Build query
		$query = "SELECT 
			COUNT(*) as total_sessions,
			COUNT(DISTINCT user_id) as unique_players,
			AVG(score) as avg_score,
			AVG(duration) as avg_duration,
			SUM(questions_correct) as total_correct,
			SUM(questions_total) as total_questions,
			game_type,
			DATE(created_at) as date
		FROM {$wpdb->prefix}ea_game_sessions
		WHERE created_at BETWEEN %s AND %s";

		$params = [ $start_date, $end_date ];

		if ( $course_id ) {
			$query .= ' AND course_id = %d';
			$params[] = $course_id;
		}

		$query .= ' GROUP BY DATE(created_at), game_type ORDER BY date ASC';

		$results = $wpdb->get_results( $wpdb->prepare( $query, $params ) );

		// Get top players
		$top_players_query = "SELECT 
			u.display_name,
			ps.total_score,
			ps.total_games_played,
			ps.streak_best
		FROM {$wpdb->prefix}ea_player_stats ps
		JOIN {$wpdb->users} u ON ps.user_id = u.ID";

		if ( $course_id ) {
			$top_players_query .= $wpdb->prepare( ' WHERE ps.course_id = %d', $course_id );
		}

		$top_players_query .= ' ORDER BY ps.total_score DESC LIMIT 10';
		$top_players = $wpdb->get_results( $top_players_query );

		// Get game type distribution
		$game_distribution_query = "SELECT 
			game_type,
			COUNT(*) as count
		FROM {$wpdb->prefix}ea_game_sessions
		WHERE created_at BETWEEN %s AND %s";

		$dist_params = [ $start_date, $end_date ];

		if ( $course_id ) {
			$game_distribution_query .= ' AND course_id = %d';
			$dist_params[] = $course_id;
		}

		$game_distribution_query .= ' GROUP BY game_type';
		$game_distribution = $wpdb->get_results( $wpdb->prepare( $game_distribution_query, $dist_params ) );

		wp_send_json_success(
			[
				'sessions'          => $results,
				'top_players'       => $top_players,
				'game_distribution' => $game_distribution,
				'period'            => $period,
				'start_date'        => $start_date,
				'end_date'          => $end_date,
			]
		);
	}

	/**
	 * AJAX handler for exporting plugin data
	 *
	 * @return void
	 */
	public function ajax_export_data() {
		check_ajax_referer( 'ea-gaming-engine-admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Permission denied', 'ea-gaming-engine' ) );
		}

		global $wpdb;

		$export_data = [
			'export_date'    => current_time( 'mysql' ),
			'plugin_version' => EA_GAMING_ENGINE_VERSION,
			'site_url'       => get_site_url(),
			'wp_version'     => get_bloginfo( 'version' ),
			'options'        => [],
			'tables'         => [],
		];

		// Export all plugin options
		$options = $wpdb->get_results( $wpdb->prepare(
			"SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE %s",
			'ea_gaming_engine_%'
		), ARRAY_A );

		foreach ( $options as $option ) {
			$export_data['options'][ $option['option_name'] ] = maybe_unserialize( $option['option_value'] );
		}

		// Export table data
		$tables = [
			'ea_game_sessions',
			'ea_game_policies', 
			'ea_question_attempts',
			'ea_player_stats',
			'ea_hint_usage',
		];

		foreach ( $tables as $table ) {
			$table_name = $wpdb->prefix . $table;
			
			$table_exists = $wpdb->get_var( $wpdb->prepare( 
				"SHOW TABLES LIKE %s", 
				$table_name 
			) );

			if ( $table_exists ) {
				$export_data['tables'][ $table ] = $wpdb->get_results( 
					"SELECT * FROM {$table_name}", 
					ARRAY_A 
				);
			}
		}

		// Create export file
		$upload_dir = wp_upload_dir();
		$filename = 'ea-gaming-export-' . date( 'Y-m-d-H-i-s' ) . '.json';
		$file_path = $upload_dir['basedir'] . '/' . $filename;
		
		$json_data = json_encode( $export_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
		
		if ( false === file_put_contents( $file_path, $json_data ) ) {
			wp_send_json_error( __( 'Failed to create export file', 'ea-gaming-engine' ) );
		}

		// Calculate file size
		$file_size = size_format( filesize( $file_path ) );

		wp_send_json_success(
			[
				'message'     => __( 'Data exported successfully', 'ea-gaming-engine' ),
				'filename'    => $filename,
				'file_size'   => $file_size,
				'download_url' => $upload_dir['baseurl'] . '/' . $filename,
				'records'     => [
					'options' => count( $export_data['options'] ),
					'tables'  => array_sum( array_map( 'count', $export_data['tables'] ) ),
				],
			]
		);
	}
}