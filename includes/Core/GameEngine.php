<?php
/**
 * Game Engine core
 *
 * @package EAGamingEngine
 */

namespace EAGamingEngine\Core;

/**
 * GameEngine class
 */
class GameEngine {

	/**
	 * Instance
	 *
	 * @var GameEngine
	 */
	private static $instance = null;

	/**
	 * Available game types
	 *
	 * @var array
	 */
	private $game_types = array(
		'whack_a_question' => array(
			'name'        => 'Whack-a-Question',
			'description' => 'Test your reflexes by whacking questions as they pop up!',
			'duration'    => '3-5 min',
			'players'     => 'Single',
		),
		'tic_tac_tactics'  => array(
			'name'        => 'Tic-Tac-Tactics',
			'description' => 'Strategic tic-tac-toe where correct answers earn your moves.',
			'duration'    => '5-10 min',
			'players'     => 'Single',
		),
		'target_trainer'   => array(
			'name'        => 'Target Trainer',
			'description' => 'Sharpen your accuracy by hitting the right answer targets.',
			'duration'    => '5-7 min',
			'players'     => 'Single',
		),
	);

	/**
	 * Get instance
	 *
	 * @return GameEngine
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Get game types
	 *
	 * @return array
	 */
	public function get_game_types() {
		return $this->game_types;
	}

	/**
	 * Constructor
	 */
	private function __construct() {
		$this->init_hooks();
	}

	/**
	 * Initialize hooks
	 *
	 * @return void
	 */
	private function init_hooks() {
		add_action( 'wp_ajax_ea_gaming_start_session', array( $this, 'ajax_start_session' ) );
		add_action( 'wp_ajax_ea_gaming_end_session', array( $this, 'ajax_end_session' ) );
		add_action( 'wp_ajax_ea_gaming_update_session', array( $this, 'ajax_update_session' ) );
	}

	/**
	 * Start a new game session
	 *
	 * @param int    $user_id User ID.
	 * @param int    $course_id Course ID.
	 * @param string $game_type Game type.
	 * @param array  $options Session options.
	 * @return int|false Session ID or false on failure.
	 */
	public function start_session( $user_id, $course_id, $game_type, $options = array() ) {
		global $wpdb;

		if ( ! array_key_exists( $game_type, $this->game_types ) ) {
			return false;
		}

		// Check if user can play.
		$policy_engine = PolicyEngine::get_instance();
		if ( ! $policy_engine->can_user_play( $user_id, $course_id ) ) {
			return false;
		}

		$table = $wpdb->prefix . 'ea_game_sessions';

		$data = array(
			'user_id'        => $user_id,
			'course_id'      => $course_id,
			'game_type'      => $game_type,
			'game_mode'      => isset( $options['game_mode'] ) ? $options['game_mode'] : 'arcade',
			'theme_used'     => isset( $options['theme'] ) ? $options['theme'] : get_option( 'ea_gaming_engine_default_theme', 'playful' ),
			'profile_preset' => isset( $options['preset'] ) ? $options['preset'] : get_option( 'ea_gaming_engine_default_preset', 'classic' ),
			'metadata'       => wp_json_encode( isset( $options['metadata'] ) ? $options['metadata'] : array() ),
		);

		$wpdb->insert( $table, $data );

		$session_id = $wpdb->insert_id;

		// Trigger action.
		do_action( 'ea_gaming_engine_session_started', $session_id, $user_id, $course_id, $game_type );

		return $session_id;
	}

	/**
	 * End a game session
	 *
	 * @param int   $session_id Session ID.
	 * @param array $stats Final session stats.
	 * @return bool
	 */
	public function end_session( $session_id, $stats = array() ) {
		global $wpdb;

		$table = $wpdb->prefix . 'ea_game_sessions';

		// Get session data.
		$cache_key = 'ea_gaming_session_' . $session_id;
		$session   = wp_cache_get( $cache_key, 'ea_gaming_engine' );

		if ( false === $session ) {
			$session = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM {$table} WHERE id = %d",
					$session_id
				)
			);
			wp_cache_set( $cache_key, $session, 'ea_gaming_engine', 300 );
		}

		if ( ! $session ) {
			return false;
		}

		// Calculate duration.
		$duration = time() - strtotime( $session->created_at );

		// Update session.
		$data = array(
			'score'             => isset( $stats['score'] ) ? $stats['score'] : 0,
			'questions_correct' => isset( $stats['questions_correct'] ) ? $stats['questions_correct'] : 0,
			'questions_total'   => isset( $stats['questions_total'] ) ? $stats['questions_total'] : 0,
			'duration'          => $duration,
			'completed'         => 1,
		);

		$wpdb->update(
			$table,
			$data,
			array( 'id' => $session_id )
		);

		// Update player stats.
		$this->update_player_stats( $session->user_id, $session->course_id, $stats );

		// Trigger action.
		do_action( 'ea_gaming_engine_session_ended', $session_id, $session->user_id, $session->course_id, $stats );

		return true;
	}

	/**
	 * Update player statistics
	 *
	 * @param int   $user_id User ID.
	 * @param int   $course_id Course ID.
	 * @param array $stats Session stats.
	 * @return void
	 */
	private function update_player_stats( $user_id, $course_id, $stats ) {
		global $wpdb;

		$table = $wpdb->prefix . 'ea_player_stats';

		// Get existing stats.
		$cache_key    = 'ea_gaming_player_stats_' . $user_id . '_' . $course_id;
		$player_stats = wp_cache_get( $cache_key, 'ea_gaming_engine' );

		if ( false === $player_stats ) {
			$player_stats = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM {$table} WHERE user_id = %d AND course_id = %d",
					$user_id,
					$course_id
				)
			);
			wp_cache_set( $cache_key, $player_stats, 'ea_gaming_engine', 600 );
		}

		if ( $player_stats ) {
			// Update existing stats.
			$data = array(
				'total_games_played'       => $player_stats->total_games_played + 1,
				'total_score'              => $player_stats->total_score + ( isset( $stats['score'] ) ? $stats['score'] : 0 ),
				'total_questions_answered' => $player_stats->total_questions_answered + ( isset( $stats['questions_total'] ) ? $stats['questions_total'] : 0 ),
				'total_questions_correct'  => $player_stats->total_questions_correct + ( isset( $stats['questions_correct'] ) ? $stats['questions_correct'] : 0 ),
				'total_time_played'        => $player_stats->total_time_played + ( isset( $stats['duration'] ) ? $stats['duration'] : 0 ),
				'last_played'              => gmdate( 'Y-m-d H:i:s' ),
			);

			// Update streak.
			if ( isset( $stats['perfect'] ) && $stats['perfect'] ) {
				$data['streak_current'] = $player_stats->streak_current + 1;
				if ( $data['streak_current'] > $player_stats->streak_best ) {
					$data['streak_best'] = $data['streak_current'];
				}
			} else {
				$data['streak_current'] = 0;
			}

			$wpdb->update(
				$table,
				$data,
				array(
					'user_id'   => $user_id,
					'course_id' => $course_id,
				)
			);
		} else {
			// Create new stats.
			$data = array(
				'user_id'                  => $user_id,
				'course_id'                => $course_id,
				'total_games_played'       => 1,
				'total_score'              => isset( $stats['score'] ) ? $stats['score'] : 0,
				'total_questions_answered' => isset( $stats['questions_total'] ) ? $stats['questions_total'] : 0,
				'total_questions_correct'  => isset( $stats['questions_correct'] ) ? $stats['questions_correct'] : 0,
				'total_time_played'        => isset( $stats['duration'] ) ? $stats['duration'] : 0,
				'streak_current'           => ( isset( $stats['perfect'] ) && $stats['perfect'] ) ? 1 : 0,
				'streak_best'              => ( isset( $stats['perfect'] ) && $stats['perfect'] ) ? 1 : 0,
				'last_played'              => gmdate( 'Y-m-d H:i:s' ),
			);

			$wpdb->insert( $table, $data );
		}
	}

	/**
	 * Get player statistics
	 *
	 * @param int $user_id User ID.
	 * @param int $course_id Course ID (optional).
	 * @return object|null
	 */
	public function get_player_stats( $user_id, $course_id = null ) {
		global $wpdb;

		$table = $wpdb->prefix . 'ea_player_stats';

		if ( $course_id ) {
			$cache_key = 'ea_gaming_player_stats_' . $user_id . '_' . $course_id;
			$stats     = wp_cache_get( $cache_key, 'ea_gaming_engine' );

			if ( false === $stats ) {
				$stats = $wpdb->get_row(
					$wpdb->prepare(
						"SELECT * FROM {$table} WHERE user_id = %d AND course_id = %d",
						$user_id,
						$course_id
					)
				);
				wp_cache_set( $cache_key, $stats, 'ea_gaming_engine', 600 );
			}
			return $stats;
		}

		// Get overall stats.
		$cache_key     = 'ea_gaming_overall_stats_' . $user_id;
		$overall_stats = wp_cache_get( $cache_key, 'ea_gaming_engine' );

		if ( false === $overall_stats ) {
			$overall_stats = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT 
						SUM(total_games_played) as total_games_played,
						SUM(total_score) as total_score,
						SUM(total_questions_answered) as total_questions_answered,
						SUM(total_questions_correct) as total_questions_correct,
						SUM(total_time_played) as total_time_played,
						MAX(streak_best) as streak_best,
						MAX(last_played) as last_played
					FROM {$table} 
					WHERE user_id = %d",
					$user_id
				)
			);
			wp_cache_set( $cache_key, $overall_stats, 'ea_gaming_engine', 600 );
		}
		return $overall_stats;
	}

	/**
	 * AJAX handler for starting session
	 *
	 * @return void
	 */
	public function ajax_start_session() {
		check_ajax_referer( 'ea-gaming-engine', 'nonce' );

		$user_id   = get_current_user_id();
		$course_id = isset( $_POST['course_id'] ) ? intval( $_POST['course_id'] ) : 0;
		$game_type = isset( $_POST['game_type'] ) ? sanitize_text_field( wp_unslash( $_POST['game_type'] ) ) : '';

		if ( ! $user_id || ! $course_id || ! $game_type ) {
			wp_send_json_error( __( 'Invalid parameters', 'ea-gaming-engine' ) );
		}

		$options = array(
			'theme'     => isset( $_POST['theme'] ) ? sanitize_text_field( wp_unslash( $_POST['theme'] ) ) : '',
			'preset'    => isset( $_POST['preset'] ) ? sanitize_text_field( wp_unslash( $_POST['preset'] ) ) : '',
			'game_mode' => isset( $_POST['game_mode'] ) ? sanitize_text_field( wp_unslash( $_POST['game_mode'] ) ) : 'arcade',
		);

		$session_id = $this->start_session( $user_id, $course_id, $game_type, $options );

		if ( $session_id ) {
			wp_send_json_success(
				array(
					'session_id' => $session_id,
					'message'    => __( 'Game session started', 'ea-gaming-engine' ),
				)
			);
		} else {
			wp_send_json_error( __( 'Failed to start game session', 'ea-gaming-engine' ) );
		}
	}

	/**
	 * AJAX handler for ending session
	 *
	 * @return void
	 */
	public function ajax_end_session() {
		check_ajax_referer( 'ea-gaming-engine', 'nonce' );

		$session_id = isset( $_POST['session_id'] ) ? intval( $_POST['session_id'] ) : 0;

		if ( ! $session_id ) {
			wp_send_json_error( __( 'Invalid session ID', 'ea-gaming-engine' ) );
		}

		$stats = array(
			'score'             => isset( $_POST['score'] ) ? intval( $_POST['score'] ) : 0,
			'questions_correct' => isset( $_POST['questions_correct'] ) ? intval( $_POST['questions_correct'] ) : 0,
			'questions_total'   => isset( $_POST['questions_total'] ) ? intval( $_POST['questions_total'] ) : 0,
			'perfect'           => isset( $_POST['perfect'] ) ? (bool) wp_unslash( $_POST['perfect'] ) : false,
		);

		if ( $this->end_session( $session_id, $stats ) ) {
			wp_send_json_success(
				array(
					'message' => __( 'Game session ended', 'ea-gaming-engine' ),
				)
			);
		} else {
			wp_send_json_error( __( 'Failed to end game session', 'ea-gaming-engine' ) );
		}
	}

	/**
	 * AJAX handler for updating session
	 *
	 * @return void
	 */
	public function ajax_update_session() {
		check_ajax_referer( 'ea-gaming-engine', 'nonce' );

		$session_id = isset( $_POST['session_id'] ) ? intval( $_POST['session_id'] ) : 0;

		if ( ! $session_id ) {
			wp_send_json_error( __( 'Invalid session ID', 'ea-gaming-engine' ) );
		}

		// Update session progress.
		global $wpdb;
		$table = $wpdb->prefix . 'ea_game_sessions';

		$data = array(
			'score'             => isset( $_POST['score'] ) ? intval( $_POST['score'] ) : 0,
			'questions_correct' => isset( $_POST['questions_correct'] ) ? intval( $_POST['questions_correct'] ) : 0,
			'questions_total'   => isset( $_POST['questions_total'] ) ? intval( $_POST['questions_total'] ) : 0,
		);

		$wpdb->update(
			$table,
			$data,
			array( 'id' => $session_id )
		);

		wp_send_json_success(
			array(
				'message' => __( 'Session updated', 'ea-gaming-engine' ),
			)
		);
	}
}
