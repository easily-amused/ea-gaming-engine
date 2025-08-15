<?php
/**
 * Admin functionality
 *
 * @package EAGamingEngine
 */

namespace EAGamingEngine\Admin;

use EAGamingEngine\Core\ThemeManager;
use EAGamingEngine\Core\PolicyEngine;
use EAGamingEngine\Core\GameEngine;

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
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_filter( 'plugin_action_links_' . EA_GAMING_ENGINE_BASENAME, array( $this, 'add_action_links' ) );
		add_action( 'admin_notices', array( $this, 'admin_notices' ) );

		// AJAX handlers.
		add_action( 'wp_ajax_ea_gaming_save_settings', array( $this, 'ajax_save_settings' ) );
		add_action( 'wp_ajax_ea_gaming_get_settings', array( $this, 'ajax_get_settings' ) );
		add_action( 'wp_ajax_ea_gaming_reset_settings', array( $this, 'ajax_reset_settings' ) );
		add_action( 'wp_ajax_ea_gaming_get_analytics', array( $this, 'ajax_get_analytics' ) );
		add_action( 'wp_ajax_ea_gaming_export_data', array( $this, 'ajax_export_data' ) );

		// Policy AJAX handlers.
		add_action( 'wp_ajax_ea_gaming_get_policy', array( $this, 'ajax_get_policy' ) );
		add_action( 'wp_ajax_ea_gaming_save_policy', array( $this, 'ajax_save_policy' ) );
		add_action( 'wp_ajax_ea_gaming_toggle_policy', array( $this, 'ajax_toggle_policy' ) );
		add_action( 'wp_ajax_ea_gaming_delete_policy', array( $this, 'ajax_delete_policy' ) );
	}

	/**
	 * Add admin menu
	 *
	 * @return void
	 */
	public function add_admin_menu() {
		// Main menu.
		add_menu_page(
			__( 'EA Gaming Engine', 'ea-gaming-engine' ),
			__( 'Gaming Engine', 'ea-gaming-engine' ),
			'manage_options',
			'ea-gaming-engine',
			array( $this, 'render_dashboard_page' ),
			'dashicons-games',
			30
		);

		// Dashboard submenu.
		add_submenu_page(
			'ea-gaming-engine',
			__( 'Dashboard', 'ea-gaming-engine' ),
			__( 'Dashboard', 'ea-gaming-engine' ),
			'manage_options',
			'ea-gaming-engine',
			array( $this, 'render_dashboard_page' )
		);

		// Settings submenu.
		add_submenu_page(
			'ea-gaming-engine',
			__( 'Settings', 'ea-gaming-engine' ),
			__( 'Settings', 'ea-gaming-engine' ),
			'manage_options',
			'ea-gaming-settings',
			array( $this, 'render_settings_page' )
		);

		// Policies submenu.
		add_submenu_page(
			'ea-gaming-engine',
			__( 'Policies', 'ea-gaming-engine' ),
			__( 'Policies', 'ea-gaming-engine' ),
			'manage_options',
			'ea-gaming-policies',
			array( $this, 'render_policies_page' )
		);

		// Analytics submenu.
		add_submenu_page(
			'ea-gaming-engine',
			__( 'Analytics', 'ea-gaming-engine' ),
			__( 'Analytics', 'ea-gaming-engine' ),
			'manage_options',
			'ea-gaming-analytics',
			array( $this, 'render_analytics_page' )
		);

		// Games submenu.
		add_submenu_page(
			'ea-gaming-engine',
			__( 'Games', 'ea-gaming-engine' ),
			__( 'Games', 'ea-gaming-engine' ),
			'manage_options',
			'ea-gaming-games',
			array( $this, 'render_games_page' )
		);

		// Parent Controls submenu (only if Student-Parent Access plugin is active).
		// This hook allows the StudentParentAccess integration to add its menu item.
		do_action( 'ea_gaming_engine_add_admin_menus' );
	}

	/**
	 * Enqueue admin assets
	 *
	 * @param string $hook Hook suffix.
	 * @return void
	 */
	public function enqueue_admin_assets( $hook ) {
		// Only load on our admin pages.
		if ( false === strpos( $hook, 'ea-gaming' ) && 'toplevel_page_ea-gaming-engine' !== $hook ) {
			return;
		}

		// Enqueue WordPress dependencies.
		wp_enqueue_script( 'wp-api' );
		wp_enqueue_script( 'wp-i18n' );
		wp_enqueue_script( 'wp-components' );
		wp_enqueue_script( 'wp-element' );
		wp_enqueue_script( 'wp-api-fetch' );
		wp_enqueue_script( 'wp-data' );
		wp_enqueue_script( 'wp-notices' );

		// Enqueue Phaser and games bundle on games page.
		if ( strpos( $hook, 'page_ea-gaming-games' ) !== false ) {
			// Load UMD build of Phaser.
			wp_enqueue_script(
				'ea-gaming-phaser',
				EA_GAMING_ENGINE_URL . 'assets/vendor/phaser.min.js',
				array(),
				'3.80.0',
				true
			);
			
			// Games bundle depends on Phaser.
			wp_enqueue_script(
				'ea-gaming-games',
				EA_GAMING_ENGINE_URL . 'assets/dist/js/games.min.js',
				array( 'ea-gaming-phaser' ),
				EA_GAMING_ENGINE_VERSION,
				true
			);

			// Add nonce for games REST API calls.
			wp_add_inline_script(
				'ea-gaming-games',
				'window.eaGamingNonce = "' . wp_create_nonce( 'wp_rest' ) . '";',
				'before'
			);
		}

		// Enqueue WordPress styles.
		wp_enqueue_style( 'wp-components' );
		wp_enqueue_style( 'wp-notices' );

		// Enqueue Chart.js for analytics.
		wp_enqueue_script(
			'chartjs',
			'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js',
			array(),
			'4.4.0',
			true
		);

		// Enqueue our admin script.
		wp_enqueue_script(
			'ea-gaming-admin',
			EA_GAMING_ENGINE_URL . 'assets/dist/js/admin.min.js',
			array( 'wp-api', 'wp-i18n', 'wp-components', 'wp-element', 'wp-api-fetch', 'wp-data', 'wp-notices', 'chartjs' ),
			EA_GAMING_ENGINE_VERSION,
			true
		);

		// Enqueue admin styles.
		wp_enqueue_style(
			'ea-gaming-admin',
			EA_GAMING_ENGINE_URL . 'assets/dist/css/admin.min.css',
			array( 'wp-components' ),
			EA_GAMING_ENGINE_VERSION
		);

		// Localize script.
		wp_localize_script(
			'ea-gaming-admin',
			'eaGamingAdmin',
			array(
				'apiUrl'      => home_url( '/wp-json/ea-gaming/v1/' ),
				'nonce'       => wp_create_nonce( 'ea-gaming-engine-admin' ),
				'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
				'currentPage' => $hook,
				'settings'    => $this->get_all_settings(),
				'i18n'        => array(
					'save'            => __( 'Save Settings', 'ea-gaming-engine' ),
					'saving'          => __( 'Saving...', 'ea-gaming-engine' ),
					'saved'           => __( 'Settings Saved', 'ea-gaming-engine' ),
					'error'           => __( 'Error saving settings', 'ea-gaming-engine' ),
					'reset'           => __( 'Reset to Defaults', 'ea-gaming-engine' ),
					'confirmReset'    => __( 'Are you sure you want to reset all settings to defaults?', 'ea-gaming-engine' ),
					'noData'          => __( 'No data available', 'ea-gaming-engine' ),
					'loading'         => __( 'Loading...', 'ea-gaming-engine' ),
					'exportData'      => __( 'Export Data', 'ea-gaming-engine' ),
					'exporting'       => __( 'Exporting...', 'ea-gaming-engine' ),
					'exported'        => __( 'Data Exported', 'ea-gaming-engine' ),
					'confirmExport'   => __( 'This will create a backup file with all plugin data. Continue?', 'ea-gaming-engine' ),
					'keepDataLabel'   => __( 'Keep data when uninstalling plugin', 'ea-gaming-engine' ),
					'keepDataHelp'    => __( 'If enabled, plugin data will be preserved when the plugin is deleted.', 'ea-gaming-engine' ),
					'exportDataLabel' => __( 'Export data before uninstalling', 'ea-gaming-engine' ),
					'exportDataHelp'  => __( 'If enabled, a backup file will be created before deleting plugin data.', 'ea-gaming-engine' ),
				),
			)
		);

		// Add inline script for React mount point.
		wp_add_inline_script(
			'ea-gaming-admin',
			'window.eaGamingAdminReady = true;',
			'after'
		);

		// Add game catalog data on games page.
		if ( strpos( $hook, 'page_ea-gaming-games' ) !== false ) {
			$game_engine = GameEngine::get_instance();
			$theme_manager = ThemeManager::get_instance();
			
			// Get available LearnDash courses.
			$courses = array();
			if ( function_exists( 'learndash_get_courses' ) ) {
				$ld_courses = learndash_get_courses( array( 'posts_per_page' => -1 ) );
				foreach ( $ld_courses as $course ) {
					$courses[] = array(
						'id'   => $course->ID,
						'name' => $course->post_title,
					);
				}
			}
			
			// Add demo course option.
			array_unshift( $courses, array(
				'id'   => 0,
				'name' => __( 'Demo Course', 'ea-gaming-engine' ),
			) );
			
			wp_localize_script(
				'ea-gaming-games',
				'eaGamingCatalog',
				array(
					'games'   => $game_engine->get_game_types(),
					'courses' => $courses,
					'themes'  => $theme_manager->get_all_themes(),
					'presets' => $theme_manager->get_all_presets(),
				)
			);
		}
	}

	/**
	 * Register settings
	 *
	 * @return void
	 */
	public function register_settings() {
		// General settings.
		register_setting( 'ea_gaming_general', 'ea_gaming_engine_enabled' );
		register_setting( 'ea_gaming_general', 'ea_gaming_engine_default_theme' );
		register_setting( 'ea_gaming_general', 'ea_gaming_engine_default_preset' );

		// Policy settings.
		register_setting( 'ea_gaming_policies', 'ea_gaming_engine_policies' );

		// Game settings.
		register_setting( 'ea_gaming_games', 'ea_gaming_engine_games' );

		// Theme settings.
		register_setting( 'ea_gaming_themes', 'ea_gaming_engine_themes' );

		// Hint system settings.
		register_setting( 'ea_gaming_hints', 'ea_gaming_engine_hint_settings' );

		// Advanced settings.
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
		global $wpdb;

		// Get stats.
		$sessions_table = $wpdb->prefix . 'ea_game_sessions';
		$stats_table    = $wpdb->prefix . 'ea_player_stats';

		// Get cached stats or fetch from database.
		$cache_key = 'ea_gaming_dashboard_stats';
		$stats     = wp_cache_get( $cache_key, 'ea_gaming_engine' );

		if ( false === $stats ) {
			$total_sessions = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$sessions_table}" ) );
			$active_players = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(DISTINCT user_id) FROM {$sessions_table}" ) );
			$total_score    = $wpdb->get_var( $wpdb->prepare( "SELECT SUM(score) FROM {$sessions_table}" ) );
			$avg_score      = $wpdb->get_var( $wpdb->prepare( "SELECT AVG(score) FROM {$sessions_table}" ) );

			$stats = compact( 'total_sessions', 'active_players', 'total_score', 'avg_score' );
			wp_cache_set( $cache_key, $stats, 'ea_gaming_engine', 300 ); // Cache for 5 minutes.
		} else {
			extract( $stats );
		}

		// Get recent sessions.
		$cache_key_sessions = 'ea_gaming_recent_sessions';
		$recent_sessions    = wp_cache_get( $cache_key_sessions, 'ea_gaming_engine' );

		if ( false === $recent_sessions ) {
			$recent_sessions = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT s.*, u.display_name 
					FROM {$sessions_table} s
					LEFT JOIN {$wpdb->users} u ON s.user_id = u.ID
					ORDER BY s.started_at DESC
					LIMIT %d",
					5
				)
			);
			wp_cache_set( $cache_key_sessions, $recent_sessions, 'ea_gaming_engine', 300 );
		}

		?>
		<div class="wrap ea-gaming-admin-wrap">
			<div class="ea-gaming-admin-header">
				<h1>
					<span class="dashicons dashicons-games"></span>
					<?php esc_html_e( 'EA Gaming Engine Dashboard', 'ea-gaming-engine' ); ?>
				</h1>
			</div>
			
			<!-- Quick Stats -->
			<div class="ea-gaming-analytics-grid">
				<div class="ea-gaming-analytics-card">
					<h3><?php esc_html_e( 'Total Sessions', 'ea-gaming-engine' ); ?></h3>
					<div class="ea-gaming-stat-value"><?php echo number_format( $total_sessions ); ?></div>
					<div class="ea-gaming-stat-label"><?php esc_html_e( 'Games Played', 'ea-gaming-engine' ); ?></div>
				</div>
				
				<div class="ea-gaming-analytics-card">
					<h3><?php esc_html_e( 'Active Players', 'ea-gaming-engine' ); ?></h3>
					<div class="ea-gaming-stat-value"><?php echo number_format( $active_players ); ?></div>
					<div class="ea-gaming-stat-label"><?php esc_html_e( 'Unique Users', 'ea-gaming-engine' ); ?></div>
				</div>
				
				<div class="ea-gaming-analytics-card">
					<h3><?php esc_html_e( 'Total Score', 'ea-gaming-engine' ); ?></h3>
					<div class="ea-gaming-stat-value"><?php echo number_format( $total_score ); ?></div>
					<div class="ea-gaming-stat-label"><?php esc_html_e( 'Points Earned', 'ea-gaming-engine' ); ?></div>
				</div>
				
				<div class="ea-gaming-analytics-card">
					<h3><?php esc_html_e( 'Average Score', 'ea-gaming-engine' ); ?></h3>
					<div class="ea-gaming-stat-value"><?php echo number_format( $avg_score, 1 ); ?></div>
					<div class="ea-gaming-stat-label"><?php esc_html_e( 'Per Session', 'ea-gaming-engine' ); ?></div>
				</div>
			</div>
			
			<!-- Quick Actions -->
			<div class="ea-gaming-settings-section">
				<h2><?php esc_html_e( 'Quick Actions', 'ea-gaming-engine' ); ?></h2>
				<div style="display: flex; gap: 10px; flex-wrap: wrap;">
					<a href="<?php echo admin_url( 'admin.php?page=ea-gaming-settings' ); ?>" class="ea-gaming-button">
						<?php esc_html_e( 'Configure Settings', 'ea-gaming-engine' ); ?>
					</a>
					<a href="<?php echo admin_url( 'admin.php?page=ea-gaming-policies' ); ?>" class="ea-gaming-button secondary">
						<?php esc_html_e( 'Manage Policies', 'ea-gaming-engine' ); ?>
					</a>
					<a href="<?php echo admin_url( 'admin.php?page=ea-gaming-games' ); ?>" class="ea-gaming-button secondary">
						<?php esc_html_e( 'View Games', 'ea-gaming-engine' ); ?>
					</a>
					<a href="<?php echo admin_url( 'admin.php?page=ea-gaming-analytics' ); ?>" class="ea-gaming-button secondary">
						<?php esc_html_e( 'View Analytics', 'ea-gaming-engine' ); ?>
					</a>
				</div>
			</div>
			
			<!-- Recent Sessions -->
			<?php if ( ! empty( $recent_sessions ) ) : ?>
			<div class="ea-gaming-settings-section">
				<h2><?php esc_html_e( 'Recent Game Sessions', 'ea-gaming-engine' ); ?></h2>
				<table class="ea-gaming-policies-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Player', 'ea-gaming-engine' ); ?></th>
							<th><?php esc_html_e( 'Game Type', 'ea-gaming-engine' ); ?></th>
							<th><?php esc_html_e( 'Score', 'ea-gaming-engine' ); ?></th>
							<th><?php esc_html_e( 'Status', 'ea-gaming-engine' ); ?></th>
							<th><?php esc_html_e( 'Date', 'ea-gaming-engine' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $recent_sessions as $session ) : ?>
						<tr>
							<td><?php echo esc_html( $session->display_name ); ?></td>
							<td><?php echo esc_html( ucwords( str_replace( '_', ' ', $session->game_type ) ) ); ?></td>
							<td><?php echo number_format( $session->score ); ?></td>
							<td>
								<span class="ea-gaming-policy-status <?php echo 'completed' === $session->status ? 'active' : 'inactive'; ?>">
									<?php echo esc_html( ucfirst( $session->status ) ); ?>
								</span>
							</td>
							<td><?php echo esc_html( human_time_diff( strtotime( $session->started_at ), current_time( 'timestamp' ) ) . ' ago' ); ?></td>
						</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
			<?php else : ?>
			<div class="ea-gaming-settings-section">
				<h2><?php esc_html_e( 'Getting Started', 'ea-gaming-engine' ); ?></h2>
				<div class="ea-gaming-notice info">
					<p><?php esc_html_e( 'No game sessions yet. Create a LearnDash course with quizzes to get started!', 'ea-gaming-engine' ); ?></p>
					<p><?php esc_html_e( 'Once you have courses set up, use the [ea_gaming_arcade] shortcode to display games on your site.', 'ea-gaming-engine' ); ?></p>
				</div>
			</div>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render settings page
	 *
	 * @return void
	 */
	public function render_settings_page() {
		$theme_manager  = ThemeManager::get_instance();
		$current_theme  = $theme_manager->get_current_theme();
		$current_preset = $theme_manager->get_current_preset();
		$themes         = $theme_manager->get_all_themes();
		$presets        = $theme_manager->get_all_presets();

		// Get current settings.
		$enabled       = get_option( 'ea_gaming_engine_enabled', true );
		$cache_enabled = get_option( 'ea_gaming_engine_cache_enabled', true );
		$debug_mode    = get_option( 'ea_gaming_engine_debug_mode', false );
		$hint_settings = get_option(
			'ea_gaming_engine_hint_settings',
			array(
				'enabled'         => true,
				'cooldown'        => 30,
				'max_per_session' => 3,
			)
		);
		?>
		<div class="wrap ea-gaming-admin-wrap">
			<div class="ea-gaming-admin-header">
				<h1>
					<span class="dashicons dashicons-games"></span>
					<?php esc_html_e( 'EA Gaming Engine Settings', 'ea-gaming-engine' ); ?>
				</h1>
			</div>
			
			<form method="post" action="options.php" id="ea-gaming-settings-form" class="ea-gaming-settings-form">
				<?php settings_fields( 'ea_gaming_general' ); ?>
				
				<!-- General Settings -->
				<div class="ea-gaming-settings-section">
					<h2><?php esc_html_e( 'General Settings', 'ea-gaming-engine' ); ?></h2>
					
					<div class="ea-gaming-settings-row">
						<label for="ea_gaming_engine_enabled">
							<?php esc_html_e( 'Enable Gaming Engine', 'ea-gaming-engine' ); ?>
						</label>
						<label class="ea-gaming-toggle">
							<input type="checkbox" name="ea_gaming_engine_enabled" id="ea_gaming_engine_enabled" value="1" <?php checked( $enabled ); ?>>
							<span class="ea-gaming-toggle-slider"></span>
						</label>
						<span class="description"><?php esc_html_e( 'Enable or disable the gaming features globally', 'ea-gaming-engine' ); ?></span>
					</div>
				</div>
				
				<!-- Theme Selection -->
				<div class="ea-gaming-settings-section">
					<h2><?php esc_html_e( 'Theme Selection', 'ea-gaming-engine' ); ?></h2>
					<p class="description"><?php esc_html_e( 'Choose the visual theme for your games', 'ea-gaming-engine' ); ?></p>
					
					<div class="ea-gaming-theme-selector">
						<?php foreach ( $themes as $theme_id => $theme ) : ?>
							<div class="ea-gaming-theme-card <?php echo $theme_id === $current_theme ? 'selected' : ''; ?>" data-theme="<?php echo esc_attr( $theme_id ); ?>">
								<h3><?php echo esc_html( $theme['name'] ); ?></h3>
								<p><?php echo esc_html( $theme['description'] ); ?></p>
								<div class="theme-preview">
									<?php foreach ( array_slice( $theme['colors'], 0, 4 ) as $color ) : ?>
										<span class="color-swatch" style="background-color: <?php echo esc_attr( $color ); ?>"></span>
									<?php endforeach; ?>
								</div>
							</div>
						<?php endforeach; ?>
					</div>
					<input type="hidden" name="ea_gaming_engine_default_theme" id="selected-theme" value="<?php echo esc_attr( $current_theme ); ?>">
				</div>
				
				<!-- Difficulty Preset -->
				<div class="ea-gaming-settings-section">
					<h2><?php esc_html_e( 'Difficulty Preset', 'ea-gaming-engine' ); ?></h2>
					<p class="description"><?php esc_html_e( 'Select a difficulty preset for your games', 'ea-gaming-engine' ); ?></p>
					
					<div class="ea-gaming-preset-selector">
						<?php foreach ( $presets as $preset_id => $preset ) : ?>
							<div class="ea-gaming-preset-card <?php echo $preset_id === $current_preset ? 'selected' : ''; ?>" data-preset="<?php echo esc_attr( $preset_id ); ?>">
								<h4><?php echo esc_html( $preset['name'] ?? '' ); ?></h4>
								<p><?php echo esc_html( $preset['description'] ?? '' ); ?></p>
								<div class="preset-stats">
									<span class="preset-stat">
										<span class="dashicons dashicons-dashboard"></span>
										<?php echo esc_html( $preset['settings']['speed_multiplier'] ?? '1.0' ); ?>x speed
									</span>
									<span class="preset-stat">
										<span class="dashicons dashicons-awards"></span>
										<?php echo esc_html( ucfirst( $preset['settings']['ai_difficulty'] ?? 'medium' ) ); ?>
									</span>
								</div>
							</div>
						<?php endforeach; ?>
					</div>
					<input type="hidden" name="ea_gaming_engine_default_preset" id="selected-preset" value="<?php echo esc_attr( $current_preset ); ?>">
				</div>
				
				<!-- Hint System Settings -->
				<div class="ea-gaming-settings-section">
					<h2><?php esc_html_e( 'Hint System', 'ea-gaming-engine' ); ?></h2>
					
					<div class="ea-gaming-settings-row">
						<label for="hint_enabled">
							<?php esc_html_e( 'Enable Hints', 'ea-gaming-engine' ); ?>
						</label>
						<label class="ea-gaming-toggle">
							<input type="checkbox" name="ea_gaming_engine_hint_settings[enabled]" id="hint_enabled" value="1" <?php checked( $hint_settings['enabled'] ?? true ); ?>>
							<span class="ea-gaming-toggle-slider"></span>
						</label>
					</div>
					
					<div class="ea-gaming-settings-row">
						<label for="hint_cooldown">
							<?php esc_html_e( 'Hint Cooldown (seconds)', 'ea-gaming-engine' ); ?>
						</label>
						<input type="number" name="ea_gaming_engine_hint_settings[cooldown]" id="hint_cooldown" value="<?php echo esc_attr( $hint_settings['cooldown'] ?? 30 ); ?>" min="0" max="300">
						<span class="description"><?php esc_html_e( 'Time between hint requests', 'ea-gaming-engine' ); ?></span>
					</div>
					
					<div class="ea-gaming-settings-row">
						<label for="hint_max">
							<?php esc_html_e( 'Max Hints per Session', 'ea-gaming-engine' ); ?>
						</label>
						<input type="number" name="ea_gaming_engine_hint_settings[max_per_session]" id="hint_max" value="<?php echo esc_attr( $hint_settings['max_per_session'] ?? 3 ); ?>" min="0" max="10">
						<span class="description"><?php esc_html_e( 'Maximum hints allowed per game session', 'ea-gaming-engine' ); ?></span>
					</div>
				</div>
				
				<!-- Integration Settings -->
				<div class="ea-gaming-settings-section">
					<h2><?php esc_html_e( 'Integrations', 'ea-gaming-engine' ); ?></h2>
					
					<div class="ea-gaming-settings-row">
						<label for="learndash_enabled">
							<?php esc_html_e( 'LearnDash Integration', 'ea-gaming-engine' ); ?>
						</label>
						<label class="ea-gaming-toggle">
							<input type="checkbox" name="ea_gaming_engine_integrations[learndash]" id="learndash_enabled" value="1" <?php checked( class_exists( 'SFWD_LMS' ) ); ?> <?php echo ! class_exists( 'SFWD_LMS' ) ? 'disabled' : ''; ?>>
							<span class="ea-gaming-toggle-slider"></span>
						</label>
						<span class="description"><?php echo class_exists( 'SFWD_LMS' ) ? esc_html__( 'Pull questions from LearnDash courses and quizzes', 'ea-gaming-engine' ) : esc_html__( 'LearnDash not detected', 'ea-gaming-engine' ); ?></span>
					</div>
					
					<div class="ea-gaming-settings-row">
						<label for="parent_controls_enabled">
							<?php esc_html_e( 'Parent Controls', 'ea-gaming-engine' ); ?>
						</label>
						<label class="ea-gaming-toggle">
							<input type="checkbox" name="ea_gaming_engine_integrations[parent_controls]" id="parent_controls_enabled" value="1" <?php checked( class_exists( 'EA_Student_Parent_Access' ) ); ?> <?php echo ! class_exists( 'EA_Student_Parent_Access' ) ? 'disabled' : ''; ?>>
							<span class="ea-gaming-toggle-slider"></span>
						</label>
						<span class="description"><?php echo class_exists( 'EA_Student_Parent_Access' ) ? esc_html__( 'Enable parent-managed gaming restrictions', 'ea-gaming-engine' ) : esc_html__( 'Student-Parent Access plugin not detected', 'ea-gaming-engine' ); ?></span>
					</div>
					
					<div class="ea-gaming-settings-row">
						<label for="flashcards_enabled">
							<?php esc_html_e( 'Flashcards Integration', 'ea-gaming-engine' ); ?>
						</label>
						<label class="ea-gaming-toggle">
							<input type="checkbox" name="ea_gaming_engine_integrations[flashcards]" id="flashcards_enabled" value="1" <?php checked( class_exists( 'EA_Flashcards' ) ); ?> <?php echo ! class_exists( 'EA_Flashcards' ) ? 'disabled' : ''; ?>>
							<span class="ea-gaming-toggle-slider"></span>
						</label>
						<span class="description"><?php echo class_exists( 'EA_Flashcards' ) ? esc_html__( 'Use flashcards as remediation questions', 'ea-gaming-engine' ) : esc_html__( 'Flashcards plugin not detected', 'ea-gaming-engine' ); ?></span>
					</div>
				</div>
				
				<!-- Advanced Settings -->
				<div class="ea-gaming-settings-section">
					<h2><?php esc_html_e( 'Advanced Settings', 'ea-gaming-engine' ); ?></h2>
					
					<div class="ea-gaming-settings-row">
						<label for="ea_gaming_cache">
							<?php esc_html_e( 'Enable Caching', 'ea-gaming-engine' ); ?>
						</label>
						<label class="ea-gaming-toggle">
							<input type="checkbox" name="ea_gaming_engine_cache_enabled" id="ea_gaming_cache" value="1" <?php checked( $cache_enabled ); ?>>
							<span class="ea-gaming-toggle-slider"></span>
						</label>
						<span class="description"><?php esc_html_e( 'Cache questions and game data for better performance', 'ea-gaming-engine' ); ?></span>
					</div>
					
					<div class="ea-gaming-settings-row">
						<label for="ea_gaming_debug">
							<?php esc_html_e( 'Debug Mode', 'ea-gaming-engine' ); ?>
						</label>
						<label class="ea-gaming-toggle">
							<input type="checkbox" name="ea_gaming_engine_debug_mode" id="ea_gaming_debug" value="1" <?php checked( $debug_mode ); ?>>
							<span class="ea-gaming-toggle-slider"></span>
						</label>
						<span class="description"><?php esc_html_e( 'Enable debug logging for troubleshooting', 'ea-gaming-engine' ); ?></span>
					</div>
				</div>
				
				<?php submit_button( __( 'Save Settings', 'ea-gaming-engine' ), 'primary', 'ea-gaming-save-settings' ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Render policies page
	 *
	 * @return void
	 */
	public function render_policies_page() {
		global $wpdb;
		$policies_table = $wpdb->prefix . 'ea_game_policies';

		// Get all policies.
		$cache_key_policies = 'ea_gaming_all_policies';
		$policies           = wp_cache_get( $cache_key_policies, 'ea_gaming_engine' );

		if ( false === $policies ) {
			$policies = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$policies_table} ORDER BY priority ASC, id DESC" ) );
			wp_cache_set( $cache_key_policies, $policies, 'ea_gaming_engine', 300 );
		}

		$policy_types = PolicyEngine::get_policy_types();
		?>
		<div class="wrap ea-gaming-admin-wrap">
			<div class="ea-gaming-admin-header">
				<h1>
					<span class="dashicons dashicons-shield"></span>
					<?php esc_html_e( 'Gaming Policies', 'ea-gaming-engine' ); ?>
				</h1>
			</div>
			
			<div class="ea-gaming-settings-section">
				<h2><?php esc_html_e( 'Policy Management', 'ea-gaming-engine' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Configure access rules and restrictions for gaming features', 'ea-gaming-engine' ); ?></p>
				
				<?php if ( ! empty( $policies ) ) : ?>
				<table class="ea-gaming-policies-table" id="ea-gaming-policies-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Policy Name', 'ea-gaming-engine' ); ?></th>
							<th><?php esc_html_e( 'Type', 'ea-gaming-engine' ); ?></th>
							<th><?php esc_html_e( 'Priority', 'ea-gaming-engine' ); ?></th>
							<th><?php esc_html_e( 'Status', 'ea-gaming-engine' ); ?></th>
							<th><?php esc_html_e( 'Actions', 'ea-gaming-engine' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $policies as $policy ) : ?>
						<tr id="policy-<?php echo esc_attr( $policy->id ); ?>" class="<?php echo $policy->active ? '' : 'policy-inactive'; ?>">
							<td><strong><?php echo esc_html( $policy->name ); ?></strong></td>
							<td><?php echo esc_html( $policy_types[ $policy->rule_type ] ?? ucwords( str_replace( '_', ' ', $policy->rule_type ) ) ); ?></td>
							<td><?php echo esc_html( $policy->priority ); ?></td>
							<td>
								<span class="ea-gaming-policy-status <?php echo $policy->active ? 'active' : 'inactive'; ?>">
									<?php echo $policy->active ? esc_html__( 'Active', 'ea-gaming-engine' ) : esc_html__( 'Inactive', 'ea-gaming-engine' ); ?>
								</span>
							</td>
							<td>
								<button class="button button-small edit-policy" data-policy-id="<?php echo esc_attr( $policy->id ); ?>">
									<?php esc_html_e( 'Edit', 'ea-gaming-engine' ); ?>
								</button>
								<button class="button button-small toggle-policy-status" data-policy-id="<?php echo esc_attr( $policy->id ); ?>" data-status="<?php echo esc_attr( $policy->active ); ?>">
									<?php echo $policy->active ? esc_html__( 'Disable', 'ea-gaming-engine' ) : esc_html__( 'Enable', 'ea-gaming-engine' ); ?>
								</button>
								<button class="button button-small delete-policy" data-policy-id="<?php echo esc_attr( $policy->id ); ?>">
									<?php esc_html_e( 'Delete', 'ea-gaming-engine' ); ?>
								</button>
							</td>
						</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
				<?php else : ?>
				<div class="ea-gaming-notice info">
					<p><?php esc_html_e( 'No policies configured yet.', 'ea-gaming-engine' ); ?></p>
				</div>
				<?php endif; ?>
			</div>
			
			<!-- Add New Policy Section -->
			<div class="ea-gaming-settings-section">
				<h2><?php esc_html_e( 'Add New Policy', 'ea-gaming-engine' ); ?></h2>
				<p><?php esc_html_e( 'Create a new policy or use a template:', 'ea-gaming-engine' ); ?></p>
				<div style="display: flex; gap: 10px; flex-wrap: wrap; margin-top: 15px;">
					<button class="button button-primary" id="add-new-policy">
						<span class="dashicons dashicons-plus-alt"></span>
						<?php esc_html_e( 'Create Custom Policy', 'ea-gaming-engine' ); ?>
					</button>
					<button class="button add-template-policy" data-template="free_play">
						<?php esc_html_e( 'Free Play Template', 'ea-gaming-engine' ); ?>
					</button>
					<button class="button add-template-policy" data-template="quiet_hours">
						<?php esc_html_e( 'Quiet Hours Template', 'ea-gaming-engine' ); ?>
					</button>
					<button class="button add-template-policy" data-template="daily_limit">
						<?php esc_html_e( 'Daily Limit Template', 'ea-gaming-engine' ); ?>
					</button>
					<button class="button add-template-policy" data-template="study_first">
						<?php esc_html_e( 'Study First Template', 'ea-gaming-engine' ); ?>
					</button>
				</div>
			</div>
		</div>
		
		<!-- Policy Edit Modal -->
		<div id="policy-modal" class="ea-gaming-modal" style="display: none;">
			<div class="ea-gaming-modal-content">
				<span class="ea-gaming-modal-close">&times;</span>
				<h2 id="policy-modal-title"><?php esc_html_e( 'Edit Policy', 'ea-gaming-engine' ); ?></h2>
				
				<form id="policy-form">
					<input type="hidden" id="policy-id" name="policy_id" value="">
					
					<div class="ea-gaming-settings-row">
						<label for="policy-name"><?php esc_html_e( 'Policy Name', 'ea-gaming-engine' ); ?></label>
						<input type="text" id="policy-name" name="name" required style="width: 100%;">
					</div>
					
					<div class="ea-gaming-settings-row">
						<label for="policy-type"><?php esc_html_e( 'Policy Type', 'ea-gaming-engine' ); ?></label>
						<select id="policy-type" name="rule_type" required style="width: 100%;">
							<?php foreach ( $policy_types as $type => $label ) : ?>
								<option value="<?php echo esc_attr( $type ); ?>"><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
					
					<div class="ea-gaming-settings-row">
						<label for="policy-priority"><?php esc_html_e( 'Priority', 'ea-gaming-engine' ); ?></label>
						<input type="number" id="policy-priority" name="priority" min="1" max="100" value="10" required>
						<span class="description"><?php esc_html_e( 'Lower numbers = higher priority', 'ea-gaming-engine' ); ?></span>
					</div>
					
					<div class="ea-gaming-settings-row">
						<label for="policy-active"><?php esc_html_e( 'Status', 'ea-gaming-engine' ); ?></label>
						<label class="ea-gaming-toggle">
							<input type="checkbox" id="policy-active" name="active" value="1" checked>
							<span class="ea-gaming-toggle-slider"></span>
						</label>
						<span class="description"><?php esc_html_e( 'Enable this policy immediately', 'ea-gaming-engine' ); ?></span>
					</div>
					
					<div class="ea-gaming-settings-row" id="policy-conditions-wrapper">
						<label><?php esc_html_e( 'Conditions (JSON)', 'ea-gaming-engine' ); ?></label>
						<textarea id="policy-conditions" name="conditions" rows="5" style="width: 100%; font-family: monospace;">{}</textarea>
						<span class="description"><?php esc_html_e( 'Define when this policy applies', 'ea-gaming-engine' ); ?></span>
					</div>
					
					<div class="ea-gaming-settings-row" id="policy-actions-wrapper">
						<label><?php esc_html_e( 'Actions (JSON)', 'ea-gaming-engine' ); ?></label>
						<textarea id="policy-actions" name="actions" rows="5" style="width: 100%; font-family: monospace;">{}</textarea>
						<span class="description"><?php esc_html_e( 'Define what happens when conditions are met', 'ea-gaming-engine' ); ?></span>
					</div>
					
					<div class="ea-gaming-modal-footer" style="margin-top: 20px;">
						<button type="submit" class="button button-primary"><?php esc_html_e( 'Save Policy', 'ea-gaming-engine' ); ?></button>
						<button type="button" class="button cancel-policy"><?php esc_html_e( 'Cancel', 'ea-gaming-engine' ); ?></button>
					</div>
				</form>
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
		global $wpdb;
		$sessions_table = $wpdb->prefix . 'ea_game_sessions';
		$stats_table    = $wpdb->prefix . 'ea_player_stats';

		// Get analytics data.
		$cache_key_analytics = 'ea_gaming_analytics_data';
		$analytics_data      = wp_cache_get( $cache_key_analytics, 'ea_gaming_engine' );

		if ( false === $analytics_data ) {
			$total_sessions     = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$sessions_table}" ) );
			$completed_sessions = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$sessions_table} WHERE status = 'completed'" ) );
			$avg_duration       = $wpdb->get_var( $wpdb->prepare( "SELECT AVG(TIMESTAMPDIFF(SECOND, started_at, ended_at)) FROM {$sessions_table} WHERE ended_at IS NOT NULL" ) );

			$analytics_data = compact( 'total_sessions', 'completed_sessions', 'avg_duration' );
			wp_cache_set( $cache_key_analytics, $analytics_data, 'ea_gaming_engine', 300 );
		} else {
			extract( $analytics_data );
		}
		$cache_key_top_players = 'ea_gaming_top_players';
		$top_players           = wp_cache_get( $cache_key_top_players, 'ea_gaming_engine' );

		if ( false === $top_players ) {
			$top_players = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT u.display_name, ps.total_score, ps.games_played, ps.avg_score
					FROM {$stats_table} ps
					LEFT JOIN {$wpdb->users} u ON ps.user_id = u.ID
					ORDER BY ps.total_score DESC
					LIMIT %d",
					10
				)
			);
			wp_cache_set( $cache_key_top_players, $top_players, 'ea_gaming_engine', 300 );
		}

		?>
		<div class="wrap ea-gaming-admin-wrap">
			<div class="ea-gaming-admin-header">
				<h1>
					<span class="dashicons dashicons-chart-area"></span>
					<?php esc_html_e( 'Gaming Analytics', 'ea-gaming-engine' ); ?>
				</h1>
			</div>
			
			<!-- Overview Stats -->
			<div class="ea-gaming-analytics-grid">
				<div class="ea-gaming-analytics-card">
					<h3><?php esc_html_e( 'Total Sessions', 'ea-gaming-engine' ); ?></h3>
					<div class="ea-gaming-stat-value"><?php echo number_format( $total_sessions ); ?></div>
				</div>
				
				<div class="ea-gaming-analytics-card">
					<h3><?php esc_html_e( 'Completion Rate', 'ea-gaming-engine' ); ?></h3>
					<div class="ea-gaming-stat-value">
						<?php echo 0 < $total_sessions ? number_format( ( $completed_sessions / $total_sessions ) * 100, 1 ) : 0; ?>%
					</div>
				</div>
				
				<div class="ea-gaming-analytics-card">
					<h3><?php esc_html_e( 'Avg Duration', 'ea-gaming-engine' ); ?></h3>
					<div class="ea-gaming-stat-value">
						<?php echo $avg_duration ? gmdate( 'i:s', $avg_duration ) : '00:00'; ?>
					</div>
				</div>
			</div>
			
			<!-- Top Players Leaderboard -->
			<div class="ea-gaming-settings-section">
				<h2><?php esc_html_e( 'Top Players', 'ea-gaming-engine' ); ?></h2>
				<?php if ( ! empty( $top_players ) ) : ?>
				<table class="ea-gaming-policies-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Rank', 'ea-gaming-engine' ); ?></th>
							<th><?php esc_html_e( 'Player', 'ea-gaming-engine' ); ?></th>
							<th><?php esc_html_e( 'Total Score', 'ea-gaming-engine' ); ?></th>
							<th><?php esc_html_e( 'Games Played', 'ea-gaming-engine' ); ?></th>
							<th><?php esc_html_e( 'Average Score', 'ea-gaming-engine' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $top_players as $index => $player ) : ?>
						<tr>
							<td><strong>#<?php echo $index + 1; ?></strong></td>
							<td><?php echo esc_html( $player->display_name ); ?></td>
							<td><?php echo number_format( $player->total_score ); ?></td>
							<td><?php echo number_format( $player->games_played ); ?></td>
							<td><?php echo number_format( $player->avg_score, 1 ); ?></td>
						</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
				<?php else : ?>
				<div class="ea-gaming-notice info">
					<p><?php esc_html_e( 'No player statistics available yet.', 'ea-gaming-engine' ); ?></p>
				</div>
				<?php endif; ?>
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
		$game_engine = GameEngine::get_instance();
		$game_types  = $game_engine->get_game_types();

		?>
		<div class="wrap ea-gaming-admin-wrap">
			<div class="ea-gaming-admin-header">
				<h1>
					<span class="dashicons dashicons-games"></span>
					<?php esc_html_e( 'Game Configuration', 'ea-gaming-engine' ); ?>
				</h1>
			</div>
			
			<div class="ea-gaming-settings-section">
				<h2><?php esc_html_e( 'Available Games', 'ea-gaming-engine' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Configure and manage your educational games', 'ea-gaming-engine' ); ?></p>
				
				<div class="ea-gaming-games-grid">
					<?php foreach ( $game_types as $game_id => $game ) : ?>
					<div class="ea-gaming-game-card" data-game-id="<?php echo esc_attr( $game_id ); ?>">
						<div class="ea-gaming-game-card-header">
							<h3><?php echo esc_html( $game['name'] ); ?></h3>
						</div>
						<div class="ea-gaming-game-card-body">
							<p><?php echo esc_html( $game['description'] ); ?></p>
							<div class="ea-gaming-game-card-stats">
								<div class="ea-gaming-game-card-stat">
									<div class="ea-gaming-game-card-stat-value">
										<span class="dashicons dashicons-clock"></span>
									</div>
									<div class="ea-gaming-game-card-stat-label">
										<?php echo esc_html( $game['duration'] ?? '5-10 min' ); ?>
									</div>
								</div>
								<div class="ea-gaming-game-card-stat">
									<div class="ea-gaming-game-card-stat-value">
										<span class="dashicons dashicons-groups"></span>
									</div>
									<div class="ea-gaming-game-card-stat-label">
										<?php echo esc_html( $game['players'] ?? 'Single' ); ?>
									</div>
								</div>
							</div>
						</div>
					</div>
					<?php endforeach; ?>
				</div>
			</div>
			
			<!-- Shortcode Examples -->
			<div class="ea-gaming-settings-section">
				<h2><?php esc_html_e( 'Usage Examples', 'ea-gaming-engine' ); ?></h2>
				<p><?php esc_html_e( 'Use these shortcodes to display games on your site:', 'ea-gaming-engine' ); ?></p>
				
				<div style="background: #f1f1f1; padding: 15px; border-radius: 4px; margin-top: 15px;">
					<h4><?php esc_html_e( 'Display Game Arcade', 'ea-gaming-engine' ); ?></h4>
					<code>[ea_gaming_arcade]</code>
					<p class="description"><?php esc_html_e( 'Shows all available games for enrolled courses', 'ea-gaming-engine' ); ?></p>
				</div>
				
				<div style="background: #f1f1f1; padding: 15px; border-radius: 4px; margin-top: 15px;">
					<h4><?php esc_html_e( 'Display Specific Game', 'ea-gaming-engine' ); ?></h4>
					<code>[ea_gaming_launcher course_id="123" game_type="whack_a_question"]</code>
					<p class="description"><?php esc_html_e( 'Shows a specific game for a specific course', 'ea-gaming-engine' ); ?></p>
				</div>
				
				<div style="background: #f1f1f1; padding: 15px; border-radius: 4px; margin-top: 15px;">
					<h4><?php esc_html_e( 'Display Leaderboard', 'ea-gaming-engine' ); ?></h4>
					<code>[ea_gaming_leaderboard limit="10"]</code>
					<p class="description"><?php esc_html_e( 'Shows top players across all games', 'ea-gaming-engine' ); ?></p>
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
		$action_links = array(
			'settings' => '<a href="' . admin_url( 'admin.php?page=ea-gaming-settings' ) . '">' . __( 'Settings', 'ea-gaming-engine' ) . '</a>',
			'docs'     => '<a href="https://honorswp.com/docs/ea-gaming-engine" target="_blank">' . __( 'Docs', 'ea-gaming-engine' ) . '</a>',
		);

		return array_merge( $action_links, $links );
	}

	/**
	 * Display admin notices
	 *
	 * @return void
	 */
	public function admin_notices() {
		// Check if LearnDash is active.
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

		// Check for first activation.
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
		return array(
			'general'  => array(
				'enabled'        => get_option( 'ea_gaming_engine_enabled', true ),
				'default_theme'  => get_option( 'ea_gaming_engine_default_theme', 'playful' ),
				'default_preset' => get_option( 'ea_gaming_engine_default_preset', 'classic' ),
			),
			'policies' => get_option( 'ea_gaming_engine_policies', $this->get_default_policies() ),
			'games'    => get_option( 'ea_gaming_engine_games', $this->get_default_games() ),
			'themes'   => get_option( 'ea_gaming_engine_themes', array() ),
			'hints'    => get_option( 'ea_gaming_engine_hint_settings', $this->get_default_hint_settings() ),
			'advanced' => array(
				'cache_enabled'            => get_option( 'ea_gaming_engine_cache_enabled', true ),
				'debug_mode'               => get_option( 'ea_gaming_engine_debug_mode', false ),
				'api_rate_limit'           => get_option( 'ea_gaming_engine_api_rate_limit', 100 ),
				'keep_data_on_uninstall'   => get_option( 'ea_gaming_engine_keep_data_on_uninstall', false ),
				'export_data_on_uninstall' => get_option( 'ea_gaming_engine_export_data_on_uninstall', false ),
			),
		);
	}

	/**
	 * Get default policies
	 *
	 * @return array
	 */
	private function get_default_policies() {
		return array(
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
		);
	}

	/**
	 * Get default games
	 *
	 * @return array
	 */
	private function get_default_games() {
		return array(
			'whack_a_question' => array(
				'enabled'    => true,
				'difficulty' => 'medium',
				'time_limit' => 60,
			),
			'tic_tac_tactics'  => array(
				'enabled'    => true,
				'difficulty' => 'medium',
			),
			'target_trainer'   => array(
				'enabled'    => true,
				'difficulty' => 'medium',
				'targets'    => 10,
			),
		);
	}

	/**
	 * Get default hint settings
	 *
	 * @return array
	 */
	private function get_default_hint_settings() {
		return array(
			'enabled'            => true,
			'cooldown'           => 30,
			'max_hints'          => 3,
			'ai_integration'     => false,
			'context_analysis'   => true,
			'lesson_integration' => true,
		);
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

		// Sanitize and validate POST data.
		$settings = isset( $_POST['settings'] ) ? wp_unslash( $_POST['settings'] ) : array();

		if ( empty( $settings ) ) {
			wp_send_json_error( __( 'Invalid settings data', 'ea-gaming-engine' ) );
		}

		// Save general settings.
		if ( isset( $settings['enabled'] ) ) {
			update_option( 'ea_gaming_engine_enabled', filter_var( $settings['enabled'], FILTER_VALIDATE_BOOLEAN ) );
		}

		if ( isset( $settings['cache_enabled'] ) ) {
			update_option( 'ea_gaming_engine_cache_enabled', filter_var( $settings['cache_enabled'], FILTER_VALIDATE_BOOLEAN ) );
		}

		if ( isset( $settings['debug_mode'] ) ) {
			update_option( 'ea_gaming_engine_debug_mode', filter_var( $settings['debug_mode'], FILTER_VALIDATE_BOOLEAN ) );
		}

		if ( isset( $settings['default_theme'] ) ) {
			update_option( 'ea_gaming_engine_default_theme', sanitize_text_field( $settings['default_theme'] ) );
		}

		if ( isset( $settings['default_preset'] ) ) {
			update_option( 'ea_gaming_engine_default_preset', sanitize_text_field( $settings['default_preset'] ) );
		}

		// Save hint settings.
		if ( isset( $settings['hint_settings'] ) ) {
			$hint_settings = array(
				'enabled'         => filter_var( $settings['hint_settings']['enabled'] ?? true, FILTER_VALIDATE_BOOLEAN ),
				'cooldown'        => intval( $settings['hint_settings']['cooldown'] ?? 30 ),
				'max_per_session' => intval( $settings['hint_settings']['max_per_session'] ?? 3 ),
			);
			update_option( 'ea_gaming_engine_hint_settings', $hint_settings );
		}

		// Save integration settings.
		if ( isset( $settings['integration_settings'] ) ) {
			$integration_settings = array(
				'learndash_enabled'       => filter_var( $settings['integration_settings']['learndash_enabled'] ?? true, FILTER_VALIDATE_BOOLEAN ),
				'parent_controls_enabled' => filter_var( $settings['integration_settings']['parent_controls_enabled'] ?? false, FILTER_VALIDATE_BOOLEAN ),
				'flashcards_enabled'      => filter_var( $settings['integration_settings']['flashcards_enabled'] ?? false, FILTER_VALIDATE_BOOLEAN ),
			);
			update_option( 'ea_gaming_engine_integration_settings', $integration_settings );
		}

		wp_send_json_success(
			array(
				'message'  => __( 'Settings saved successfully', 'ea-gaming-engine' ),
				'settings' => $this->get_all_settings(),
			)
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

		// Reset to defaults.
		update_option( 'ea_gaming_engine_enabled', true );
		update_option( 'ea_gaming_engine_default_theme', 'playful' );
		update_option( 'ea_gaming_engine_default_preset', 'classic' );
		update_option( 'ea_gaming_engine_policies', $this->get_default_policies() );
		update_option( 'ea_gaming_engine_games', $this->get_default_games() );
		update_option( 'ea_gaming_engine_themes', array() );
		update_option( 'ea_gaming_engine_hint_settings', $this->get_default_hint_settings() );
		update_option( 'ea_gaming_engine_cache_enabled', true );
		update_option( 'ea_gaming_engine_debug_mode', false );
		update_option( 'ea_gaming_engine_api_rate_limit', 100 );
		update_option( 'ea_gaming_engine_keep_data_on_uninstall', false );
		update_option( 'ea_gaming_engine_export_data_on_uninstall', false );

		wp_send_json_success(
			array(
				'message'  => __( 'Settings reset to defaults', 'ea-gaming-engine' ),
				'settings' => $this->get_all_settings(),
			)
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

		// Get analytics data.
		$period    = isset( $_POST['period'] ) ? sanitize_text_field( wp_unslash( $_POST['period'] ) ) : '7days';
		$course_id = isset( $_POST['course_id'] ) ? intval( $_POST['course_id'] ) : 0;

		// Calculate date range.
		$end_date = gmdate( 'Y-m-d 23:59:59' );
		switch ( $period ) {
			case '24hours':
				$start_date = gmdate( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS );
				break;
			case '7days':
				$start_date = gmdate( 'Y-m-d 00:00:00', time() - ( 7 * DAY_IN_SECONDS ) );
				break;
			case '30days':
				$start_date = gmdate( 'Y-m-d 00:00:00', time() - ( 30 * DAY_IN_SECONDS ) );
				break;
			default:
				$start_date = gmdate( 'Y-m-d 00:00:00', time() - ( 7 * DAY_IN_SECONDS ) );
		}

		// Build query.
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

		$params = array( $start_date, $end_date );

		if ( $course_id ) {
			$query   .= ' AND course_id = %d';
			$params[] = $course_id;
		}

		$query .= ' GROUP BY DATE(created_at), game_type ORDER BY date ASC';

		$results = $wpdb->get_results( $wpdb->prepare( $query, $params ) );

		// Get top players.
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
		$top_players        = $wpdb->get_results( $top_players_query );

		// Get game type distribution.
		$game_distribution_query = "SELECT 
			game_type,
			COUNT(*) as count
		FROM {$wpdb->prefix}ea_game_sessions
		WHERE created_at BETWEEN %s AND %s";

		$dist_params = array( $start_date, $end_date );

		if ( $course_id ) {
			$game_distribution_query .= ' AND course_id = %d';
			$dist_params[]            = $course_id;
		}

		$game_distribution_query .= ' GROUP BY game_type';
		$game_distribution        = $wpdb->get_results( $wpdb->prepare( $game_distribution_query, $dist_params ) );

		wp_send_json_success(
			array(
				'sessions'          => $results,
				'top_players'       => $top_players,
				'game_distribution' => $game_distribution,
				'period'            => $period,
				'start_date'        => $start_date,
				'end_date'          => $end_date,
			)
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

		$export_data = array(
			'export_date'    => gmdate( 'Y-m-d H:i:s' ),
			'plugin_version' => EA_GAMING_ENGINE_VERSION,
			'site_url'       => get_site_url(),
			'wp_version'     => get_bloginfo( 'version' ),
			'options'        => array(),
			'tables'         => array(),
		);

		// Export all plugin options.
		$options = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE %s",
				'ea_gaming_engine_%'
			),
			ARRAY_A
		);

		foreach ( $options as $option ) {
			$export_data['options'][ $option['option_name'] ] = maybe_unserialize( $option['option_value'] );
		}

		// Export table data.
		$tables = array(
			'ea_game_sessions',
			'ea_game_policies',
			'ea_question_attempts',
			'ea_player_stats',
			'ea_hint_usage',
		);

		foreach ( $tables as $table ) {
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

		// Create export file.
		$upload_dir = wp_upload_dir();
		$filename   = 'ea-gaming-export-' . gmdate( 'Y-m-d-H-i-s' ) . '.json';
		$file_path  = $upload_dir['basedir'] . '/' . $filename;

		$json_data = wp_json_encode( $export_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );

		if ( ! $json_data ) {
			wp_send_json_error( __( 'Failed to encode export data', 'ea-gaming-engine' ) );
		}

		// Use WP_Filesystem.
		global $wp_filesystem;
		if ( empty( $wp_filesystem ) ) {
			require_once ABSPATH . '/wp-admin/includes/file.php';
			WP_Filesystem();
		}

		if ( ! $wp_filesystem->put_contents( $file_path, $json_data, FS_CHMOD_FILE ) ) {
			wp_send_json_error( __( 'Failed to create export file', 'ea-gaming-engine' ) );
		}

		// Calculate file size.
		$file_size = size_format( filesize( $file_path ) );

		wp_send_json_success(
			array(
				'message'      => __( 'Data exported successfully', 'ea-gaming-engine' ),
				'filename'     => $filename,
				'file_size'    => $file_size,
				'download_url' => $upload_dir['baseurl'] . '/' . $filename,
				'records'      => array(
					'options' => count( $export_data['options'] ),
					'tables'  => array_sum( array_map( 'count', $export_data['tables'] ) ),
				),
			)
		);
	}

	/**
	 * AJAX handler for getting a single policy
	 *
	 * @return void
	 */
	public function ajax_get_policy() {
		check_ajax_referer( 'ea-gaming-engine-admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Permission denied', 'ea-gaming-engine' ) );
		}

		$policy_id = isset( $_POST['policy_id'] ) ? intval( $_POST['policy_id'] ) : 0;

		if ( ! $policy_id ) {
			wp_send_json_error( __( 'Invalid policy ID', 'ea-gaming-engine' ) );
		}

		global $wpdb;
		$table = $wpdb->prefix . 'ea_game_policies';

		$policy = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM $table WHERE id = %d",
				$policy_id
			)
		);

		if ( ! $policy ) {
			wp_send_json_error( __( 'Policy not found', 'ea-gaming-engine' ) );
		}

		wp_send_json_success( $policy );
	}

	/**
	 * AJAX handler for saving a policy
	 *
	 * @return void
	 */
	public function ajax_save_policy() {
		check_ajax_referer( 'ea-gaming-engine-admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Permission denied', 'ea-gaming-engine' ) );
		}

		$policy = isset( $_POST['policy'] ) ? wp_unslash( $_POST['policy'] ) : array();

		if ( empty( $policy ) ) {
			wp_send_json_error( __( 'Invalid policy data', 'ea-gaming-engine' ) );
		}

		global $wpdb;
		$table = $wpdb->prefix . 'ea_game_policies';

		$data = array(
			'name'       => sanitize_text_field( $policy['name'] ?? '' ),
			'rule_type'  => sanitize_text_field( $policy['rule_type'] ?? '' ),
			'priority'   => intval( $policy['priority'] ?? 10 ),
			'conditions' => wp_unslash( $policy['conditions'] ?? '{}' ),
			'actions'    => wp_unslash( $policy['actions'] ?? '{}' ),
			'active'     => intval( $policy['active'] ?? 0 ),
		);

		// Validate JSON.
		if ( json_decode( $data['conditions'] ) === null || json_decode( $data['actions'] ) === null ) {
			wp_send_json_error( __( 'Invalid JSON in conditions or actions', 'ea-gaming-engine' ) );
		}

		if ( ! empty( $policy['policy_id'] ) ) {
			// Update existing policy.
			$result = $wpdb->update(
				$table,
				$data,
				array( 'id' => intval( $policy['policy_id'] ) )
			);
		} else {
			// Insert new policy.
			$result = $wpdb->insert( $table, $data );
		}

		if ( false === $result ) {
			wp_send_json_error( __( 'Failed to save policy', 'ea-gaming-engine' ) );
		}

		wp_send_json_success(
			array(
				'message'   => __( 'Policy saved successfully', 'ea-gaming-engine' ),
				'policy_id' => ! empty( $policy['policy_id'] ) ? intval( $policy['policy_id'] ) : $wpdb->insert_id,
			)
		);
	}

	/**
	 * AJAX handler for toggling policy status
	 *
	 * @return void
	 */
	public function ajax_toggle_policy() {
		check_ajax_referer( 'ea-gaming-engine-admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Permission denied', 'ea-gaming-engine' ) );
		}

		$policy_id = isset( $_POST['policy_id'] ) ? intval( $_POST['policy_id'] ) : 0;
		$active    = isset( $_POST['active'] ) ? intval( $_POST['active'] ) : 0;

		if ( ! $policy_id ) {
			wp_send_json_error( __( 'Invalid policy ID', 'ea-gaming-engine' ) );
		}

		global $wpdb;
		$table = $wpdb->prefix . 'ea_game_policies';

		$result = $wpdb->update(
			$table,
			array( 'active' => $active ),
			array( 'id' => $policy_id )
		);

		if ( false === $result ) {
			wp_send_json_error( __( 'Failed to update policy status', 'ea-gaming-engine' ) );
		}

		wp_send_json_success(
			array(
				'message' => __( 'Policy status updated', 'ea-gaming-engine' ),
			)
		);
	}

	/**
	 * AJAX handler for deleting a policy
	 *
	 * @return void
	 */
	public function ajax_delete_policy() {
		check_ajax_referer( 'ea-gaming-engine-admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Permission denied', 'ea-gaming-engine' ) );
		}

		$policy_id = isset( $_POST['policy_id'] ) ? intval( $_POST['policy_id'] ) : 0;

		if ( ! $policy_id ) {
			wp_send_json_error( __( 'Invalid policy ID', 'ea-gaming-engine' ) );
		}

		global $wpdb;
		$table = $wpdb->prefix . 'ea_game_policies';

		$result = $wpdb->delete(
			$table,
			array( 'id' => $policy_id )
		);

		if ( false === $result ) {
			wp_send_json_error( __( 'Failed to delete policy', 'ea-gaming-engine' ) );
		}

		wp_send_json_success(
			array(
				'message' => __( 'Policy deleted successfully', 'ea-gaming-engine' ),
			)
		);
	}
}