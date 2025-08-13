<?php
/**
 * Plugin activation
 *
 * @package EAGamingEngine
 */

namespace EAGamingEngine\Core;

/**
 * Activator class
 */
class Activator {

	/**
	 * Activate the plugin
	 *
	 * @return void
	 */
	public static function activate() {
		// Create database tables.
		self::create_tables();

		// Set default options.
		self::set_default_options();

		// Schedule cron events.
		self::schedule_events();

		// Set flag to flush rewrite rules.
		update_option( 'ea_gaming_engine_flush_rules', true );

		// Set activation timestamp.
		update_option( 'ea_gaming_engine_activated', time() );
	}

	/**
	 * Create database tables
	 *
	 * @return void
	 */
	private static function create_tables() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		// Game sessions table.
		$table_sessions = $wpdb->prefix . 'ea_game_sessions';
		$sql_sessions   = "CREATE TABLE IF NOT EXISTS $table_sessions (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id bigint(20) UNSIGNED NOT NULL,
			course_id bigint(20) UNSIGNED NOT NULL,
			game_type varchar(50) NOT NULL,
			game_mode varchar(50) DEFAULT 'arcade',
			score int(11) DEFAULT 0,
			questions_correct int(11) DEFAULT 0,
			questions_total int(11) DEFAULT 0,
			duration int(11) DEFAULT 0,
			theme_used varchar(50) DEFAULT 'playful',
			profile_preset varchar(50) DEFAULT 'classic',
			completed tinyint(1) DEFAULT 0,
			metadata longtext,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY user_id (user_id),
			KEY course_id (course_id),
			KEY game_type (game_type),
			KEY created_at (created_at)
		) $charset_collate;";

		// Policy rules table.
		$table_policies = $wpdb->prefix . 'ea_game_policies';
		$sql_policies   = "CREATE TABLE IF NOT EXISTS $table_policies (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			name varchar(100) NOT NULL,
			rule_type varchar(50) NOT NULL,
			conditions longtext,
			actions longtext,
			priority int(11) DEFAULT 10,
			active tinyint(1) DEFAULT 1,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY rule_type (rule_type),
			KEY active (active),
			KEY priority (priority)
		) $charset_collate;";

		// Question attempts table.
		$table_attempts = $wpdb->prefix . 'ea_question_attempts';
		$sql_attempts   = "CREATE TABLE IF NOT EXISTS $table_attempts (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			session_id bigint(20) UNSIGNED NOT NULL,
			question_id bigint(20) UNSIGNED NOT NULL,
			quiz_id bigint(20) UNSIGNED NOT NULL,
			user_answer longtext,
			is_correct tinyint(1) DEFAULT 0,
			points_earned int(11) DEFAULT 0,
			time_taken int(11) DEFAULT 0,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY session_id (session_id),
			KEY question_id (question_id),
			KEY quiz_id (quiz_id)
		) $charset_collate;";

		// Player stats table.
		$table_stats = $wpdb->prefix . 'ea_player_stats';
		$sql_stats   = "CREATE TABLE IF NOT EXISTS $table_stats (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id bigint(20) UNSIGNED NOT NULL,
			course_id bigint(20) UNSIGNED DEFAULT NULL,
			total_games_played int(11) DEFAULT 0,
			total_score int(11) DEFAULT 0,
			total_questions_answered int(11) DEFAULT 0,
			total_questions_correct int(11) DEFAULT 0,
			total_time_played int(11) DEFAULT 0,
			streak_current int(11) DEFAULT 0,
			streak_best int(11) DEFAULT 0,
			achievements longtext,
			last_played datetime DEFAULT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY user_course (user_id, course_id),
			KEY user_id (user_id),
			KEY course_id (course_id)
		) $charset_collate;";

		// Hint usage table.
		$table_hints = $wpdb->prefix . 'ea_hint_usage';
		$sql_hints   = "CREATE TABLE IF NOT EXISTS $table_hints (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			question_id bigint(20) UNSIGNED NOT NULL,
			user_id bigint(20) UNSIGNED NOT NULL,
			session_id bigint(20) UNSIGNED DEFAULT NULL,
			hint_level tinyint(1) NOT NULL DEFAULT 1,
			hint_text longtext NOT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY question_id (question_id),
			KEY user_id (user_id),
			KEY session_id (session_id),
			KEY created_at (created_at)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql_sessions );
		dbDelta( $sql_policies );
		dbDelta( $sql_attempts );
		dbDelta( $sql_stats );
		dbDelta( $sql_hints );

		// Store database version.
		update_option( 'ea_gaming_engine_db_version', '1.1.0' );
	}

	/**
	 * Set default options
	 *
	 * @return void
	 */
	private static function set_default_options() {
		// General settings.
		add_option( 'ea_gaming_engine_enabled', true );
		add_option( 'ea_gaming_engine_default_theme', 'playful' );
		add_option( 'ea_gaming_engine_default_preset', 'classic' );

		// Policy settings.
		add_option(
			'ea_gaming_engine_policies',
			array(
				'free_play_enabled'   => false,
				'free_play_start'     => '15:00',
				'free_play_end'       => '17:00',
				'quiet_hours_enabled' => false,
				'quiet_hours_start'   => '22:00',
				'quiet_hours_end'     => '07:00',
				'study_first_enabled' => true,
				'study_first_minutes' => 10,
			)
		);

		// Game settings.
		add_option(
			'ea_gaming_engine_games',
			array(
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
			)
		);

		// Theme settings.
		add_option(
			'ea_gaming_engine_themes',
			array(
				'playful'     => array(
					'name'   => __( 'Playful', 'ea-gaming-engine' ),
					'colors' => array(
						'primary'   => '#7C3AED',
						'secondary' => '#EC4899',
						'success'   => '#10B981',
						'danger'    => '#EF4444',
						'warning'   => '#F59E0B',
					),
				),
				'minimal_pro' => array(
					'name'   => __( 'Minimal Pro', 'ea-gaming-engine' ),
					'colors' => array(
						'primary'   => '#1F2937',
						'secondary' => '#6B7280',
						'success'   => '#059669',
						'danger'    => '#DC2626',
						'warning'   => '#D97706',
					),
				),
			)
		);

		// Profile presets.
		add_option(
			'ea_gaming_engine_presets',
			array(
				'chill'      => array(
					'name'          => __( 'Chill', 'ea-gaming-engine' ),
					'speed'         => 0.8,
					'ai_difficulty' => 'easy',
					'effects'       => true,
					'hints'         => true,
				),
				'classic'    => array(
					'name'          => __( 'Classic', 'ea-gaming-engine' ),
					'speed'         => 1.0,
					'ai_difficulty' => 'medium',
					'effects'       => true,
					'hints'         => false,
				),
				'pro'        => array(
					'name'          => __( 'Pro', 'ea-gaming-engine' ),
					'speed'         => 1.5,
					'ai_difficulty' => 'hard',
					'effects'       => false,
					'hints'         => false,
				),
				'accessible' => array(
					'name'          => __( 'Accessible', 'ea-gaming-engine' ),
					'speed'         => 0.6,
					'ai_difficulty' => 'easy',
					'effects'       => false,
					'hints'         => true,
					'high_contrast' => true,
					'large_text'    => true,
				),
			)
		);

		// Hint system settings.
		add_option(
			'ea_gaming_engine_hint_settings',
			array(
				'enabled'            => true,
				'cooldown'           => 30,
				'max_hints'          => 3,
				'ai_integration'     => false,
				'context_analysis'   => true,
				'lesson_integration' => true,
			)
		);
	}

	/**
	 * Schedule cron events
	 *
	 * @return void
	 */
	private static function schedule_events() {
		// Schedule daily stats cleanup.
		if ( ! wp_next_scheduled( 'ea_gaming_engine_daily_cleanup' ) ) {
			wp_schedule_event( time(), 'daily', 'ea_gaming_engine_daily_cleanup' );
		}

		// Schedule hourly policy check.
		if ( ! wp_next_scheduled( 'ea_gaming_engine_policy_check' ) ) {
			wp_schedule_event( time(), 'hourly', 'ea_gaming_engine_policy_check' );
		}
	}
}
