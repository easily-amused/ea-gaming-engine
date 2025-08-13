<?php
/**
 * Policy Rules Engine
 *
 * @package EAGamingEngine
 */

namespace EAGamingEngine\Core;

/**
 * PolicyEngine class
 */
class PolicyEngine {

	/**
	 * Policy types
	 *
	 * @var array
	 */
	private $policy_types = [
		'free_play',
		'quiet_hours',
		'study_first',
		'parent_control',
		'daily_limit',
		'course_specific',
	];

	/**
	 * Active policies cache
	 *
	 * @var array
	 */
	private $active_policies = null;

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
		add_action( 'init', [ $this, 'load_default_policies' ] );
		add_action( 'ea_gaming_engine_policy_check', [ $this, 'evaluate_policies' ] );
		add_filter( 'ea_gaming_can_play', [ $this, 'filter_can_play' ], 10, 3 );
	}

	/**
	 * Load default policies
	 *
	 * @return void
	 */
	public function load_default_policies() {
		global $wpdb;
		$table = $wpdb->prefix . 'ea_game_policies';

		// Check if default policies exist
		$count = $wpdb->get_var( "SELECT COUNT(*) FROM $table" );

		if ( $count == 0 ) {
			$this->create_default_policies();
		}
	}

	/**
	 * Create default policies
	 *
	 * @return void
	 */
	private function create_default_policies() {
		global $wpdb;
		$table = $wpdb->prefix . 'ea_game_policies';

		$default_policies = [
			[
				'name'       => __( 'Free Play Window', 'ea-gaming-engine' ),
				'rule_type'  => 'free_play',
				'conditions' => wp_json_encode(
					[
						'start_time' => '15:00',
						'end_time'   => '17:00',
						'days'       => [ 'mon', 'tue', 'wed', 'thu', 'fri' ],
					]
				),
				'actions'    => wp_json_encode(
					[
						'allow_free_play' => true,
						'no_tickets_required' => true,
					]
				),
				'priority'   => 10,
				'active'     => 0,
			],
			[
				'name'       => __( 'Quiet Hours', 'ea-gaming-engine' ),
				'rule_type'  => 'quiet_hours',
				'conditions' => wp_json_encode(
					[
						'start_time' => '22:00',
						'end_time'   => '07:00',
					]
				),
				'actions'    => wp_json_encode(
					[
						'block_access' => true,
						'message'      => __( 'Games are not available during quiet hours.', 'ea-gaming-engine' ),
					]
				),
				'priority'   => 5,
				'active'     => 0,
			],
			[
				'name'       => __( 'Study First', 'ea-gaming-engine' ),
				'rule_type'  => 'study_first',
				'conditions' => wp_json_encode(
					[
						'require_lesson_view' => true,
						'minimum_time'        => 600, // 10 minutes
					]
				),
				'actions'    => wp_json_encode(
					[
						'redirect_to_lesson' => true,
						'message'            => __( 'Please complete the lesson before playing games.', 'ea-gaming-engine' ),
					]
				),
				'priority'   => 20,
				'active'     => 1,
			],
		];

		foreach ( $default_policies as $policy ) {
			$wpdb->insert( $table, $policy );
		}
	}

	/**
	 * Get active policies
	 *
	 * @param bool $force_refresh Force refresh from database.
	 * @return array
	 */
	public function get_active_policies( $force_refresh = false ) {
		if ( $this->active_policies !== null && ! $force_refresh ) {
			return $this->active_policies;
		}

		global $wpdb;
		$table = $wpdb->prefix . 'ea_game_policies';

		$policies = $wpdb->get_results(
			"SELECT * FROM $table 
			WHERE active = 1 
			ORDER BY priority ASC, id ASC"
		);

		$this->active_policies = [];

		foreach ( $policies as $policy ) {
			$this->active_policies[] = [
				'id'         => $policy->id,
				'name'       => $policy->name,
				'rule_type'  => $policy->rule_type,
				'conditions' => json_decode( $policy->conditions, true ),
				'actions'    => json_decode( $policy->actions, true ),
				'priority'   => $policy->priority,
			];
		}

		return $this->active_policies;
	}

	/**
	 * Check if user can play
	 *
	 * @param int $user_id User ID.
	 * @param int $course_id Course ID.
	 * @return bool|array True if can play, array with reason if not.
	 */
	public function can_user_play( $user_id, $course_id = null ) {
		$policies = $this->get_active_policies();
		$context = $this->get_user_context( $user_id, $course_id );

		foreach ( $policies as $policy ) {
			$result = $this->evaluate_policy( $policy, $context );

			if ( $result['block'] ) {
				return [
					'can_play' => false,
					'reason'   => $result['message'],
					'policy'   => $policy['name'],
				];
			}
		}

		return apply_filters( 'ea_gaming_can_user_play', true, $user_id, $course_id );
	}

	/**
	 * Get user context for policy evaluation
	 *
	 * @param int $user_id User ID.
	 * @param int $course_id Course ID.
	 * @return array
	 */
	private function get_user_context( $user_id, $course_id = null ) {
		$context = [
			'user_id'      => $user_id,
			'course_id'    => $course_id,
			'current_time' => current_time( 'H:i' ),
			'current_day'  => strtolower( current_time( 'D' ) ),
			'timezone'     => wp_timezone_string(),
		];

		// Add user meta
		$user = get_user_by( 'ID', $user_id );
		if ( $user ) {
			$context['user_roles'] = $user->roles;
			$context['user_email'] = $user->user_email;
		}

		// Add course progress if course ID provided
		if ( $course_id && function_exists( 'learndash_course_progress' ) ) {
			$progress = learndash_course_progress(
				[
					'user_id'   => $user_id,
					'course_id' => $course_id,
					'array'     => true,
				]
			);
			$context['course_progress'] = $progress;
		}

		// Add today's play stats
		$context['today_stats'] = $this->get_today_stats( $user_id );

		// Check parent controls if integration exists
		if ( class_exists( 'EA_Student_Parent_Access' ) ) {
			$context['parent_controls'] = $this->get_parent_controls( $user_id );
		}

		return apply_filters( 'ea_gaming_policy_context', $context, $user_id, $course_id );
	}

	/**
	 * Evaluate a single policy
	 *
	 * @param array $policy Policy data.
	 * @param array $context User context.
	 * @return array
	 */
	private function evaluate_policy( $policy, $context ) {
		$result = [
			'block'   => false,
			'message' => '',
			'actions' => [],
		];

		switch ( $policy['rule_type'] ) {
			case 'free_play':
				$result = $this->evaluate_free_play( $policy, $context );
				break;

			case 'quiet_hours':
				$result = $this->evaluate_quiet_hours( $policy, $context );
				break;

			case 'study_first':
				$result = $this->evaluate_study_first( $policy, $context );
				break;

			case 'parent_control':
				$result = $this->evaluate_parent_control( $policy, $context );
				break;

			case 'daily_limit':
				$result = $this->evaluate_daily_limit( $policy, $context );
				break;

			case 'course_specific':
				$result = $this->evaluate_course_specific( $policy, $context );
				break;

			default:
				$result = apply_filters( 'ea_gaming_evaluate_custom_policy', $result, $policy, $context );
		}

		return $result;
	}

	/**
	 * Evaluate free play policy
	 *
	 * @param array $policy Policy data.
	 * @param array $context User context.
	 * @return array
	 */
	private function evaluate_free_play( $policy, $context ) {
		$conditions = $policy['conditions'];
		$current_time = $context['current_time'];
		$current_day = $context['current_day'];

		// Check if today is included in free play days
		if ( ! empty( $conditions['days'] ) && ! in_array( $current_day, $conditions['days'], true ) ) {
			return [
				'block'   => false,
				'message' => '',
				'actions' => [],
			];
		}

		// Check if current time is within free play window
		$start = $conditions['start_time'];
		$end = $conditions['end_time'];

		if ( $this->is_time_between( $current_time, $start, $end ) ) {
			return [
				'block'   => false,
				'message' => '',
				'actions' => $policy['actions'],
			];
		}

		return [
			'block'   => false,
			'message' => '',
			'actions' => [],
		];
	}

	/**
	 * Evaluate quiet hours policy
	 *
	 * @param array $policy Policy data.
	 * @param array $context User context.
	 * @return array
	 */
	private function evaluate_quiet_hours( $policy, $context ) {
		$conditions = $policy['conditions'];
		$current_time = $context['current_time'];

		$start = $conditions['start_time'];
		$end = $conditions['end_time'];

		// Handle overnight quiet hours
		if ( $start > $end ) {
			// Quiet hours span midnight
			if ( $current_time >= $start || $current_time <= $end ) {
				return [
					'block'   => true,
					'message' => $policy['actions']['message'] ?? __( 'Games are not available during quiet hours.', 'ea-gaming-engine' ),
					'actions' => $policy['actions'],
				];
			}
		} else {
			// Normal time range
			if ( $this->is_time_between( $current_time, $start, $end ) ) {
				return [
					'block'   => true,
					'message' => $policy['actions']['message'] ?? __( 'Games are not available during quiet hours.', 'ea-gaming-engine' ),
					'actions' => $policy['actions'],
				];
			}
		}

		return [
			'block'   => false,
			'message' => '',
			'actions' => [],
		];
	}

	/**
	 * Evaluate study first policy
	 *
	 * @param array $policy Policy data.
	 * @param array $context User context.
	 * @return array
	 */
	private function evaluate_study_first( $policy, $context ) {
		if ( ! $context['course_id'] ) {
			return [
				'block'   => false,
				'message' => '',
				'actions' => [],
			];
		}

		$conditions = $policy['conditions'];

		// Check if lesson view is required
		if ( ! empty( $conditions['require_lesson_view'] ) ) {
			$last_lesson_view = $this->get_last_lesson_view( $context['user_id'], $context['course_id'] );
			
			if ( ! $last_lesson_view ) {
				return [
					'block'   => true,
					'message' => $policy['actions']['message'] ?? __( 'Please complete a lesson before playing games.', 'ea-gaming-engine' ),
					'actions' => $policy['actions'],
				];
			}

			// Check minimum time requirement
			if ( ! empty( $conditions['minimum_time'] ) ) {
				$time_spent = time() - $last_lesson_view;
				if ( $time_spent < $conditions['minimum_time'] ) {
					$remaining = $conditions['minimum_time'] - $time_spent;
					return [
						'block'   => true,
						'message' => sprintf(
							__( 'Please study for %d more minutes before playing games.', 'ea-gaming-engine' ),
							ceil( $remaining / 60 )
						),
						'actions' => $policy['actions'],
					];
				}
			}
		}

		return [
			'block'   => false,
			'message' => '',
			'actions' => [],
		];
	}

	/**
	 * Evaluate parent control policy
	 *
	 * @param array $policy Policy data.
	 * @param array $context User context.
	 * @return array
	 */
	private function evaluate_parent_control( $policy, $context ) {
		if ( empty( $context['parent_controls'] ) ) {
			return [
				'block'   => false,
				'message' => '',
				'actions' => [],
			];
		}

		$controls = $context['parent_controls'];

		// Check if parent has blocked games
		if ( ! empty( $controls['games_blocked'] ) ) {
			return [
				'block'   => true,
				'message' => __( 'Games have been disabled by your parent/guardian.', 'ea-gaming-engine' ),
				'actions' => $policy['actions'],
			];
		}

		// Check time restrictions
		if ( ! empty( $controls['time_restrictions'] ) ) {
			$current_time = $context['current_time'];
			$allowed_start = $controls['time_restrictions']['start'];
			$allowed_end = $controls['time_restrictions']['end'];

			if ( ! $this->is_time_between( $current_time, $allowed_start, $allowed_end ) ) {
				return [
					'block'   => true,
					'message' => sprintf(
						__( 'Games are only available between %s and %s.', 'ea-gaming-engine' ),
						$allowed_start,
						$allowed_end
					),
					'actions' => $policy['actions'],
				];
			}
		}

		// Check ticket/token requirements
		if ( ! empty( $controls['require_tickets'] ) ) {
			$tickets = $this->get_user_tickets( $context['user_id'] );
			if ( $tickets <= 0 ) {
				return [
					'block'   => true,
					'message' => __( 'You need tickets to play. Ask your parent/guardian for more.', 'ea-gaming-engine' ),
					'actions' => $policy['actions'],
				];
			}
		}

		return [
			'block'   => false,
			'message' => '',
			'actions' => [],
		];
	}

	/**
	 * Evaluate daily limit policy
	 *
	 * @param array $policy Policy data.
	 * @param array $context User context.
	 * @return array
	 */
	private function evaluate_daily_limit( $policy, $context ) {
		$conditions = $policy['conditions'];
		$today_stats = $context['today_stats'];

		// Check games played limit
		if ( ! empty( $conditions['max_games_per_day'] ) ) {
			if ( $today_stats['games_played'] >= $conditions['max_games_per_day'] ) {
				return [
					'block'   => true,
					'message' => __( 'You have reached your daily game limit.', 'ea-gaming-engine' ),
					'actions' => $policy['actions'],
				];
			}
		}

		// Check time played limit
		if ( ! empty( $conditions['max_time_per_day'] ) ) {
			if ( $today_stats['time_played'] >= $conditions['max_time_per_day'] ) {
				return [
					'block'   => true,
					'message' => __( 'You have reached your daily play time limit.', 'ea-gaming-engine' ),
					'actions' => $policy['actions'],
				];
			}
		}

		return [
			'block'   => false,
			'message' => '',
			'actions' => [],
		];
	}

	/**
	 * Evaluate course specific policy
	 *
	 * @param array $policy Policy data.
	 * @param array $context User context.
	 * @return array
	 */
	private function evaluate_course_specific( $policy, $context ) {
		if ( ! $context['course_id'] ) {
			return [
				'block'   => false,
				'message' => '',
				'actions' => [],
			];
		}

		$conditions = $policy['conditions'];

		// Check if course is in blocked list
		if ( ! empty( $conditions['blocked_courses'] ) ) {
			if ( in_array( $context['course_id'], $conditions['blocked_courses'], true ) ) {
				return [
					'block'   => true,
					'message' => __( 'Games are not available for this course.', 'ea-gaming-engine' ),
					'actions' => $policy['actions'],
				];
			}
		}

		// Check minimum progress requirement
		if ( ! empty( $conditions['minimum_progress'] ) && ! empty( $context['course_progress'] ) ) {
			$progress = $context['course_progress']['percentage'] ?? 0;
			if ( $progress < $conditions['minimum_progress'] ) {
				return [
					'block'   => true,
					'message' => sprintf(
						__( 'Complete at least %d%% of the course to unlock games.', 'ea-gaming-engine' ),
						$conditions['minimum_progress']
					),
					'actions' => $policy['actions'],
				];
			}
		}

		return [
			'block'   => false,
			'message' => '',
			'actions' => [],
		];
	}

	/**
	 * Check if time is between start and end
	 *
	 * @param string $current Current time (H:i format).
	 * @param string $start Start time (H:i format).
	 * @param string $end End time (H:i format).
	 * @return bool
	 */
	private function is_time_between( $current, $start, $end ) {
		$current = strtotime( $current );
		$start = strtotime( $start );
		$end = strtotime( $end );

		return ( $current >= $start && $current <= $end );
	}

	/**
	 * Get today's play statistics for user
	 *
	 * @param int $user_id User ID.
	 * @return array
	 */
	private function get_today_stats( $user_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ea_game_sessions';

		$today_start = current_time( 'Y-m-d 00:00:00' );
		$today_end = current_time( 'Y-m-d 23:59:59' );

		$stats = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT 
					COUNT(*) as games_played,
					SUM(duration) as time_played
				FROM $table
				WHERE user_id = %d
				AND created_at BETWEEN %s AND %s",
				$user_id,
				$today_start,
				$today_end
			)
		);

		return [
			'games_played' => $stats->games_played ?? 0,
			'time_played'  => $stats->time_played ?? 0,
		];
	}

	/**
	 * Get last lesson view time
	 *
	 * @param int $user_id User ID.
	 * @param int $course_id Course ID.
	 * @return int|false Timestamp or false.
	 */
	private function get_last_lesson_view( $user_id, $course_id ) {
		// This would integrate with LearnDash activity tracking
		// For now, return a mock value
		$key = 'ea_gaming_last_lesson_' . $user_id . '_' . $course_id;
		return get_transient( $key );
	}

	/**
	 * Get parent controls for user
	 *
	 * @param int $user_id User ID.
	 * @return array
	 */
	private function get_parent_controls( $user_id ) {
		// This would integrate with Student-Parent Access plugin
		return apply_filters( 'ea_gaming_parent_controls', [], $user_id );
	}

	/**
	 * Get user tickets/tokens
	 *
	 * @param int $user_id User ID.
	 * @return int
	 */
	private function get_user_tickets( $user_id ) {
		return get_user_meta( $user_id, 'ea_gaming_tickets', true ) ?: 0;
	}

	/**
	 * Filter can play hook
	 *
	 * @param bool $can_play Can play status.
	 * @param int  $user_id User ID.
	 * @param int  $course_id Course ID.
	 * @return bool|array
	 */
	public function filter_can_play( $can_play, $user_id, $course_id ) {
		if ( ! $can_play ) {
			return $can_play;
		}

		return $this->can_user_play( $user_id, $course_id );
	}

	/**
	 * Evaluate all policies (cron job)
	 *
	 * @return void
	 */
	public function evaluate_policies() {
		// This would run periodic policy checks
		// For example, sending notifications when free play starts
		do_action( 'ea_gaming_policies_evaluated' );
	}
}