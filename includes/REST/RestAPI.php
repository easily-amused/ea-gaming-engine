<?php
/**
 * REST API Controller
 *
 * @package EAGamingEngine
 */

namespace EAGamingEngine\REST;

use EAGamingEngine\Core\GameEngine;
use EAGamingEngine\Core\QuestionGate;
use EAGamingEngine\Core\PolicyEngine;
use EAGamingEngine\Core\ThemeManager;
use EAGamingEngine\Core\HintSystem;
use EAGamingEngine\Integrations\LearnDash;

/**
 * RestAPI class
 */
class RestAPI {

	/**
	 * API namespace
	 *
	 * @var string
	 */
	private $namespace = 'ea-gaming/v1';

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	/**
	 * Register REST API routes
	 *
	 * @return void
	 */
	public function register_routes() {
		// Session routes
		register_rest_route(
			$this->namespace,
			'/sessions',
			[
				[
					'methods'             => 'POST',
					'callback'            => [ $this, 'create_session' ],
					'permission_callback' => [ $this, 'check_logged_in' ],
					'args'                => $this->get_session_args(),
				],
				[
					'methods'             => 'GET',
					'callback'            => [ $this, 'get_sessions' ],
					'permission_callback' => [ $this, 'check_logged_in' ],
				],
			]
		);

		register_rest_route(
			$this->namespace,
			'/sessions/(?P<id>\d+)',
			[
				[
					'methods'             => 'GET',
					'callback'            => [ $this, 'get_session' ],
					'permission_callback' => [ $this, 'check_session_access' ],
				],
				[
					'methods'             => 'PUT',
					'callback'            => [ $this, 'update_session' ],
					'permission_callback' => [ $this, 'check_session_access' ],
					'args'                => $this->get_session_update_args(),
				],
				[
					'methods'             => 'DELETE',
					'callback'            => [ $this, 'end_session' ],
					'permission_callback' => [ $this, 'check_session_access' ],
				],
			]
		);

		// Question routes
		register_rest_route(
			$this->namespace,
			'/questions/(?P<quiz_id>\d+)',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'get_question' ],
				'permission_callback' => [ $this, 'check_logged_in' ],
				'args'                => [
					'quiz_id' => [
						'required'          => true,
						'validate_callback' => function ( $param ) {
							return is_numeric( $param );
						},
					],
				],
			]
		);

		register_rest_route(
			$this->namespace,
			'/validate-answer',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'validate_answer' ],
				'permission_callback' => [ $this, 'check_logged_in' ],
				'args'                => $this->get_validate_answer_args(),
			]
		);

		register_rest_route(
			$this->namespace,
			'/hints/(?P<question_id>\d+)',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'get_hint' ],
				'permission_callback' => [ $this, 'check_logged_in' ],
				'args'                => [
					'question_id' => [
						'required'          => true,
						'validate_callback' => function ( $param ) {
							return is_numeric( $param );
						},
					],
					'course_id' => [
						'required' => false,
						'type'     => 'integer',
					],
					'session_id' => [
						'required' => false,
						'type'     => 'integer',
					],
				],
			]
		);

		// Policy routes
		register_rest_route(
			$this->namespace,
			'/policies/active',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'get_active_policies' ],
				'permission_callback' => [ $this, 'check_logged_in' ],
			]
		);

		register_rest_route(
			$this->namespace,
			'/policies/check',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'check_policy' ],
				'permission_callback' => [ $this, 'check_logged_in' ],
				'args'                => [
					'course_id' => [
						'required' => false,
						'type'     => 'integer',
					],
				],
			]
		);

		// Stats routes
		register_rest_route(
			$this->namespace,
			'/stats/player',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'get_player_stats' ],
				'permission_callback' => [ $this, 'check_logged_in' ],
			]
		);

		register_rest_route(
			$this->namespace,
			'/stats/leaderboard',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'get_leaderboard' ],
				'permission_callback' => '__return_true',
				'args'                => [
					'course_id' => [
						'required' => false,
						'type'     => 'integer',
					],
					'limit'     => [
						'required' => false,
						'type'     => 'integer',
						'default'  => 10,
					],
				],
			]
		);

		// Theme routes
		register_rest_route(
			$this->namespace,
			'/themes',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'get_themes' ],
				'permission_callback' => '__return_true',
			]
		);

		register_rest_route(
			$this->namespace,
			'/themes/current',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'get_current_theme' ],
				'permission_callback' => '__return_true',
			]
		);

		register_rest_route(
			$this->namespace,
			'/themes/set',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'set_theme' ],
				'permission_callback' => [ $this, 'check_logged_in' ],
				'args'                => [
					'theme_id' => [
						'required' => true,
						'type'     => 'string',
					],
				],
			]
		);

		// Preset routes
		register_rest_route(
			$this->namespace,
			'/presets',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'get_presets' ],
				'permission_callback' => '__return_true',
			]
		);

		register_rest_route(
			$this->namespace,
			'/presets/current',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'get_current_preset' ],
				'permission_callback' => '__return_true',
			]
		);

		register_rest_route(
			$this->namespace,
			'/presets/set',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'set_preset' ],
				'permission_callback' => [ $this, 'check_logged_in' ],
				'args'                => [
					'preset_id' => [
						'required' => true,
						'type'     => 'string',
					],
				],
			]
		);

		// Course structure routes
		register_rest_route(
			$this->namespace,
			'/courses/(?P<id>\d+)/structure',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'get_course_structure' ],
				'permission_callback' => [ $this, 'check_course_access' ],
			]
		);

		// Game-specific routes
		register_rest_route(
			$this->namespace,
			'/games/available',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'get_available_games' ],
				'permission_callback' => '__return_true',
			]
		);

		register_rest_route(
			$this->namespace,
			'/games/launch',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'launch_game' ],
				'permission_callback' => [ $this, 'check_logged_in' ],
				'args'                => $this->get_launch_game_args(),
			]
		);
	}

	/**
	 * Check if user is logged in
	 *
	 * @return bool|\WP_Error
	 */
	public function check_logged_in() {
		if ( ! is_user_logged_in() ) {
			return new \WP_Error(
				'rest_not_logged_in',
				__( 'You must be logged in to access this endpoint.', 'ea-gaming-engine' ),
				[ 'status' => 401 ]
			);
		}
		return true;
	}

	/**
	 * Check session access
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return bool|\WP_Error
	 */
	public function check_session_access( $request ) {
		if ( ! is_user_logged_in() ) {
			return new \WP_Error(
				'rest_not_logged_in',
				__( 'You must be logged in to access this endpoint.', 'ea-gaming-engine' ),
				[ 'status' => 401 ]
			);
		}

		global $wpdb;
		$session_id = $request->get_param( 'id' );
		$user_id = get_current_user_id();

		$session = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT user_id FROM {$wpdb->prefix}ea_game_sessions WHERE id = %d",
				$session_id
			)
		);

		if ( ! $session || $session->user_id != $user_id ) {
			return new \WP_Error(
				'rest_forbidden',
				__( 'You do not have permission to access this session.', 'ea-gaming-engine' ),
				[ 'status' => 403 ]
			);
		}

		return true;
	}

	/**
	 * Check course access
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return bool|\WP_Error
	 */
	public function check_course_access( $request ) {
		if ( ! is_user_logged_in() ) {
			return new \WP_Error(
				'rest_not_logged_in',
				__( 'You must be logged in to access this endpoint.', 'ea-gaming-engine' ),
				[ 'status' => 401 ]
			);
		}

		$course_id = $request->get_param( 'id' );
		$user_id = get_current_user_id();

		if ( ! sfwd_lms_has_access( $course_id, $user_id ) ) {
			return new \WP_Error(
				'rest_forbidden',
				__( 'You do not have access to this course.', 'ea-gaming-engine' ),
				[ 'status' => 403 ]
			);
		}

		return true;
	}

	/**
	 * Create session endpoint
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function create_session( $request ) {
		$user_id = get_current_user_id();
		$course_id = $request->get_param( 'course_id' );
		$game_type = $request->get_param( 'game_type' );

		$options = [
			'game_mode' => $request->get_param( 'game_mode' ),
			'theme'     => $request->get_param( 'theme' ),
			'preset'    => $request->get_param( 'preset' ),
			'metadata'  => $request->get_param( 'metadata' ),
		];

		$game_engine = GameEngine::get_instance();
		$session_id = $game_engine->start_session( $user_id, $course_id, $game_type, $options );

		if ( ! $session_id ) {
			return new \WP_Error(
				'session_creation_failed',
				__( 'Failed to create game session.', 'ea-gaming-engine' ),
				[ 'status' => 500 ]
			);
		}

		return rest_ensure_response(
			[
				'session_id' => $session_id,
				'message'    => __( 'Session created successfully.', 'ea-gaming-engine' ),
			]
		);
	}

	/**
	 * Get sessions endpoint
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function get_sessions( $request ) {
		global $wpdb;
		$user_id = get_current_user_id();

		$sessions = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}ea_game_sessions 
				WHERE user_id = %d 
				ORDER BY created_at DESC 
				LIMIT 20",
				$user_id
			)
		);

		return rest_ensure_response( $sessions );
	}

	/**
	 * Get single session endpoint
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function get_session( $request ) {
		global $wpdb;
		$session_id = $request->get_param( 'id' );

		$session = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}ea_game_sessions WHERE id = %d",
				$session_id
			)
		);

		if ( ! $session ) {
			return new \WP_Error(
				'session_not_found',
				__( 'Session not found.', 'ea-gaming-engine' ),
				[ 'status' => 404 ]
			);
		}

		// Parse metadata
		$session->metadata = json_decode( $session->metadata, true );

		return rest_ensure_response( $session );
	}

	/**
	 * Update session endpoint
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function update_session( $request ) {
		global $wpdb;
		$session_id = $request->get_param( 'id' );

		$data = [];
		
		if ( $request->has_param( 'score' ) ) {
			$data['score'] = intval( $request->get_param( 'score' ) );
		}
		
		if ( $request->has_param( 'questions_correct' ) ) {
			$data['questions_correct'] = intval( $request->get_param( 'questions_correct' ) );
		}
		
		if ( $request->has_param( 'questions_total' ) ) {
			$data['questions_total'] = intval( $request->get_param( 'questions_total' ) );
		}

		if ( empty( $data ) ) {
			return new \WP_Error(
				'no_data',
				__( 'No data to update.', 'ea-gaming-engine' ),
				[ 'status' => 400 ]
			);
		}

		$updated = $wpdb->update(
			$wpdb->prefix . 'ea_game_sessions',
			$data,
			[ 'id' => $session_id ]
		);

		if ( $updated === false ) {
			return new \WP_Error(
				'update_failed',
				__( 'Failed to update session.', 'ea-gaming-engine' ),
				[ 'status' => 500 ]
			);
		}

		return rest_ensure_response(
			[
				'message' => __( 'Session updated successfully.', 'ea-gaming-engine' ),
			]
		);
	}

	/**
	 * End session endpoint
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function end_session( $request ) {
		$session_id = $request->get_param( 'id' );

		$stats = [
			'score'             => intval( $request->get_param( 'score' ) ?? 0 ),
			'questions_correct' => intval( $request->get_param( 'questions_correct' ) ?? 0 ),
			'questions_total'   => intval( $request->get_param( 'questions_total' ) ?? 0 ),
			'perfect'           => (bool) ( $request->get_param( 'perfect' ) ?? false ),
		];

		$game_engine = GameEngine::get_instance();
		$success = $game_engine->end_session( $session_id, $stats );

		if ( ! $success ) {
			return new \WP_Error(
				'end_session_failed',
				__( 'Failed to end session.', 'ea-gaming-engine' ),
				[ 'status' => 500 ]
			);
		}

		return rest_ensure_response(
			[
				'message' => __( 'Session ended successfully.', 'ea-gaming-engine' ),
			]
		);
	}

	/**
	 * Get question endpoint
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function get_question( $request ) {
		$quiz_id = $request->get_param( 'quiz_id' );
		$session_id = $request->get_param( 'session_id' );

		$options = [
			'difficulty' => $request->get_param( 'difficulty' ),
			'exclude'    => $request->get_param( 'exclude' ),
		];

		$question_gate = new QuestionGate( $session_id );
		$question = $question_gate->get_question( $quiz_id, $options );

		if ( ! $question ) {
			return new \WP_Error(
				'no_questions',
				__( 'No questions available.', 'ea-gaming-engine' ),
				[ 'status' => 404 ]
			);
		}

		return rest_ensure_response( $question );
	}

	/**
	 * Validate answer endpoint
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function validate_answer( $request ) {
		$question_id = $request->get_param( 'question_id' );
		$answer = $request->get_param( 'answer' );
		$session_id = $request->get_param( 'session_id' );

		$question_gate = new QuestionGate( $session_id );
		$result = $question_gate->validate_answer( $question_id, $answer );

		if ( ! $result['valid'] ) {
			return new \WP_Error(
				'validation_error',
				$result['message'],
				[ 'status' => 400 ]
			);
		}

		return rest_ensure_response( $result );
	}

	/**
	 * Get hint endpoint
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function get_hint( $request ) {
		$question_id = $request->get_param( 'question_id' );
		$course_id = $request->get_param( 'course_id' );
		$session_id = $request->get_param( 'session_id' );
		$user_id = get_current_user_id();

		$hint_system = new HintSystem();
		$result = $hint_system->get_hint( $question_id, $user_id, $course_id, $session_id );

		if ( ! $result['success'] ) {
			return new \WP_Error(
				'hint_error',
				$result['message'],
				[ 'status' => 400 ]
			);
		}

		return rest_ensure_response( $result );
	}

	/**
	 * Get active policies endpoint
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function get_active_policies( $request ) {
		$policy_engine = PolicyEngine::get_instance();
		$policies = $policy_engine->get_active_policies();

		// Filter out sensitive data
		$filtered = array_map(
			function ( $policy ) {
				return [
					'name'      => $policy['name'],
					'rule_type' => $policy['rule_type'],
					'active'    => true,
				];
			},
			$policies
		);

		return rest_ensure_response( $filtered );
	}

	/**
	 * Check policy endpoint
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function check_policy( $request ) {
		$user_id = get_current_user_id();
		$course_id = $request->get_param( 'course_id' );

		$policy_engine = PolicyEngine::get_instance();
		$result = $policy_engine->can_user_play( $user_id, $course_id );

		if ( is_array( $result ) && ! $result['can_play'] ) {
			return rest_ensure_response(
				[
					'can_play' => false,
					'reason'   => $result['reason'],
					'policy'   => $result['policy'],
				]
			);
		}

		return rest_ensure_response(
			[
				'can_play' => true,
			]
		);
	}

	/**
	 * Get player stats endpoint
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function get_player_stats( $request ) {
		$user_id = get_current_user_id();
		$course_id = $request->get_param( 'course_id' );

		$game_engine = GameEngine::get_instance();
		$stats = $game_engine->get_player_stats( $user_id, $course_id );

		if ( ! $stats ) {
			return rest_ensure_response(
				[
					'total_games_played'      => 0,
					'total_score'            => 0,
					'total_questions_answered' => 0,
					'total_questions_correct' => 0,
					'total_time_played'      => 0,
					'streak_best'            => 0,
				]
			);
		}

		return rest_ensure_response( $stats );
	}

	/**
	 * Get leaderboard endpoint
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function get_leaderboard( $request ) {
		global $wpdb;
		
		$course_id = $request->get_param( 'course_id' );
		$limit = $request->get_param( 'limit' );

		$query = "SELECT 
			ps.user_id,
			u.display_name,
			ps.total_score,
			ps.total_games_played,
			ps.streak_best
		FROM {$wpdb->prefix}ea_player_stats ps
		JOIN {$wpdb->users} u ON ps.user_id = u.ID";

		$params = [];
		
		if ( $course_id ) {
			$query .= ' WHERE ps.course_id = %d';
			$params[] = $course_id;
		}

		$query .= ' ORDER BY ps.total_score DESC LIMIT %d';
		$params[] = $limit;

		if ( ! empty( $params ) ) {
			$query = $wpdb->prepare( $query, $params );
		}

		$leaderboard = $wpdb->get_results( $query );

		return rest_ensure_response( $leaderboard );
	}

	/**
	 * Get themes endpoint
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function get_themes( $request ) {
		$theme_manager = ThemeManager::get_instance();
		$themes = $theme_manager->get_all_themes();

		return rest_ensure_response( $themes );
	}

	/**
	 * Get current theme endpoint
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function get_current_theme( $request ) {
		$theme_manager = ThemeManager::get_instance();
		$theme = $theme_manager->get_theme_data( null );

		return rest_ensure_response( $theme );
	}

	/**
	 * Set theme endpoint
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function set_theme( $request ) {
		$user_id = get_current_user_id();
		$theme_id = $request->get_param( 'theme_id' );

		$theme_manager = ThemeManager::get_instance();
		$success = $theme_manager->set_user_theme( $user_id, $theme_id );

		if ( ! $success ) {
			return new \WP_Error(
				'invalid_theme',
				__( 'Invalid theme ID.', 'ea-gaming-engine' ),
				[ 'status' => 400 ]
			);
		}

		return rest_ensure_response(
			[
				'message' => __( 'Theme updated successfully.', 'ea-gaming-engine' ),
				'theme'   => $theme_manager->get_theme_data( null, $theme_id ),
			]
		);
	}

	/**
	 * Get presets endpoint
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function get_presets( $request ) {
		$theme_manager = ThemeManager::get_instance();
		$presets = $theme_manager->get_all_presets();

		return rest_ensure_response( $presets );
	}

	/**
	 * Get current preset endpoint
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function get_current_preset( $request ) {
		$theme_manager = ThemeManager::get_instance();
		$preset = $theme_manager->get_preset_data( null );

		return rest_ensure_response( $preset );
	}

	/**
	 * Set preset endpoint
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function set_preset( $request ) {
		$user_id = get_current_user_id();
		$preset_id = $request->get_param( 'preset_id' );

		$theme_manager = ThemeManager::get_instance();
		$success = $theme_manager->set_user_preset( $user_id, $preset_id );

		if ( ! $success ) {
			return new \WP_Error(
				'invalid_preset',
				__( 'Invalid preset ID.', 'ea-gaming-engine' ),
				[ 'status' => 400 ]
			);
		}

		return rest_ensure_response(
			[
				'message' => __( 'Preset updated successfully.', 'ea-gaming-engine' ),
				'preset'  => $theme_manager->get_preset_data( null, $preset_id ),
			]
		);
	}

	/**
	 * Get course structure endpoint
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function get_course_structure( $request ) {
		$course_id = $request->get_param( 'id' );

		$learndash = new LearnDash();
		$structure = $learndash->get_course_structure( $course_id );

		return rest_ensure_response( $structure );
	}

	/**
	 * Get available games endpoint
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function get_available_games( $request ) {
		$games = [
			[
				'id'          => 'whack_a_question',
				'name'        => __( 'Whack-a-Question', 'ea-gaming-engine' ),
				'description' => __( 'Fast-paced question answering game', 'ea-gaming-engine' ),
				'enabled'     => true,
			],
			[
				'id'          => 'tic_tac_tactics',
				'name'        => __( 'Tic-Tac-Tactics', 'ea-gaming-engine' ),
				'description' => __( 'Strategic quiz-based tic-tac-toe', 'ea-gaming-engine' ),
				'enabled'     => true,
			],
			[
				'id'          => 'target_trainer',
				'name'        => __( 'Target Trainer', 'ea-gaming-engine' ),
				'description' => __( 'Aim and shoot correct answers', 'ea-gaming-engine' ),
				'enabled'     => true,
			],
		];

		return rest_ensure_response( $games );
	}

	/**
	 * Launch game endpoint
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function launch_game( $request ) {
		$game_type = $request->get_param( 'game_type' );
		$course_id = $request->get_param( 'course_id' );
		$quiz_id = $request->get_param( 'quiz_id' );

		// Check policy
		$user_id = get_current_user_id();
		$policy_engine = PolicyEngine::get_instance();
		$can_play = $policy_engine->can_user_play( $user_id, $course_id );

		if ( is_array( $can_play ) && ! $can_play['can_play'] ) {
			return new \WP_Error(
				'policy_blocked',
				$can_play['reason'],
				[ 'status' => 403 ]
			);
		}

		// Create session
		$game_engine = GameEngine::get_instance();
		$session_id = $game_engine->start_session(
			$user_id,
			$course_id,
			$game_type,
			[
				'metadata' => [
					'quiz_id' => $quiz_id,
				],
			]
		);

		if ( ! $session_id ) {
			return new \WP_Error(
				'launch_failed',
				__( 'Failed to launch game.', 'ea-gaming-engine' ),
				[ 'status' => 500 ]
			);
		}

		// Get game configuration
		$theme_manager = ThemeManager::get_instance();
		$theme = $theme_manager->get_theme_data( null );
		$preset = $theme_manager->get_preset_data( null );

		return rest_ensure_response(
			[
				'session_id' => $session_id,
				'game_type'  => $game_type,
				'theme'      => $theme,
				'preset'     => $preset,
				'quiz_id'    => $quiz_id,
			]
		);
	}

	/**
	 * Get session args
	 *
	 * @return array
	 */
	private function get_session_args() {
		return [
			'course_id' => [
				'required' => true,
				'type'     => 'integer',
			],
			'game_type' => [
				'required' => true,
				'type'     => 'string',
				'enum'     => [ 'whack_a_question', 'tic_tac_tactics', 'target_trainer' ],
			],
			'game_mode' => [
				'required' => false,
				'type'     => 'string',
				'default'  => 'arcade',
			],
			'theme'     => [
				'required' => false,
				'type'     => 'string',
			],
			'preset'    => [
				'required' => false,
				'type'     => 'string',
			],
			'metadata'  => [
				'required' => false,
				'type'     => 'object',
			],
		];
	}

	/**
	 * Get session update args
	 *
	 * @return array
	 */
	private function get_session_update_args() {
		return [
			'score'             => [
				'required' => false,
				'type'     => 'integer',
			],
			'questions_correct' => [
				'required' => false,
				'type'     => 'integer',
			],
			'questions_total'   => [
				'required' => false,
				'type'     => 'integer',
			],
		];
	}

	/**
	 * Get validate answer args
	 *
	 * @return array
	 */
	private function get_validate_answer_args() {
		return [
			'question_id' => [
				'required' => true,
				'type'     => 'integer',
			],
			'answer'      => [
				'required' => true,
			],
			'session_id'  => [
				'required' => false,
				'type'     => 'integer',
			],
		];
	}

	/**
	 * Get launch game args
	 *
	 * @return array
	 */
	private function get_launch_game_args() {
		return [
			'game_type' => [
				'required' => true,
				'type'     => 'string',
				'enum'     => [ 'whack_a_question', 'tic_tac_tactics', 'target_trainer' ],
			],
			'course_id' => [
				'required' => true,
				'type'     => 'integer',
			],
			'quiz_id'   => [
				'required' => false,
				'type'     => 'integer',
			],
		];
	}
}