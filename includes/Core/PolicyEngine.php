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
	 * Instance
	 *
	 * @var PolicyEngine
	 */
	private static $instance = null;

	/**
	 * Policy types
	 *
	 * @var array
	 */
	private static $policy_types = array(
		'free_play'       => 'Free Play',
		'quiet_hours'     => 'Quiet Hours',
		'study_first'     => 'Study First',
		'parent_control'  => 'Parent Control',
		'daily_limit'     => 'Daily Limit',
		'course_specific' => 'Course Specific',
	);

	/**
	 * Active policies cache
	 *
	 * @var array
	 */
	private $active_policies = null;

	/**
	 * Get instance
	 *
	 * @return PolicyEngine
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Get policy types
	 *
	 * @return array
	 */
	public static function get_policy_types() {
		return self::$policy_types;
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
		add_action( 'init', array( $this, 'load_default_policies' ) );
		add_action( 'ea_gaming_engine_policy_check', array( $this, 'evaluate_policies' ) );
		add_filter( 'ea_gaming_can_play', array( $this, 'filter_can_play' ), 10, 3 );
	}

	/**
	 * Load default policies
	 *
	 * @return void
	 */
	public function load_default_policies() {
		global $wpdb;
		$table = $wpdb->prefix . 'ea_game_policies';

		// Check if default policies exist.
		$cache_key = 'ea_gaming_policy_count';
		$count     = wp_cache_get( $cache_key, 'ea_gaming_engine' );

		if ( false === $count ) {
			$count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table}" ) );
			wp_cache_set( $cache_key, $count, 'ea_gaming_engine', 600 );
		}

		if ( 0 === $count ) {
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

		$default_policies = array(
			array(
				'name'       => __( 'Free Play Window', 'ea-gaming-engine' ),
				'rule_type'  => 'free_play',
				'conditions' => wp_json_encode(
					array(
						'start_time' => '15:00',
						'end_time'   => '17:00',
						'days'       => array( 'mon', 'tue', 'wed', 'thu', 'fri' ),
					)
				),
				'actions'    => wp_json_encode(
					array(
						'allow_free_play'     => true,
						'no_tickets_required' => true,
					)
				),
				'priority'   => 10,
				'active'     => 0,
			),
			array(
				'name'       => __( 'Quiet Hours', 'ea-gaming-engine' ),
				'rule_type'  => 'quiet_hours',
				'conditions' => wp_json_encode(
					array(
						'start_time' => '22:00',
						'end_time'   => '07:00',
					)
				),
				'actions'    => wp_json_encode(
					array(
						'block_access' => true,
						'message'      => __( 'Games are not available during quiet hours.', 'ea-gaming-engine' ),
					)
				),
				'priority'   => 5,
				'active'     => 0,
			),
			array(
				'name'       => __( 'Study First', 'ea-gaming-engine' ),
				'rule_type'  => 'study_first',
				'conditions' => wp_json_encode(
					array(
						'require_lesson_view' => true,
						'minimum_time'        => 600, // 10 minutes
					)
				),
				'actions'    => wp_json_encode(
					array(
						'redirect_to_lesson' => true,
						'message'            => __( 'Please complete the lesson before playing games.', 'ea-gaming-engine' ),
					)
				),
				'priority'   => 20,
				'active'     => 1,
			),
		);

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
		if ( null !== $this->active_policies && ! $force_refresh ) {
			return $this->active_policies;
		}

		global $wpdb;
		$table = $wpdb->prefix . 'ea_game_policies';

		$cache_key = 'ea_gaming_active_policies';
		$policies  = wp_cache_get( $cache_key, 'ea_gaming_engine' );

		if ( false === $policies || $force_refresh ) {
			$policies = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$table} 
					WHERE active = 1 
					ORDER BY priority ASC, id ASC"
				)
			);
			wp_cache_set( $cache_key, $policies, 'ea_gaming_engine', 600 );
		}

		$this->active_policies = array();

		foreach ( $policies as $policy ) {
			$this->active_policies[] = array(
				'id'         => $policy->id,
				'name'       => $policy->name,
				'rule_type'  => $policy->rule_type,
				'conditions' => json_decode( $policy->conditions, true ),
				'actions'    => json_decode( $policy->actions, true ),
				'priority'   => $policy->priority,
			);
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
		$context  = $this->get_user_context( $user_id, $course_id );

		foreach ( $policies as $policy ) {
			$result = $this->evaluate_policy( $policy, $context );

			if ( $result['block'] ) {
				return array(
					'can_play' => false,
					'reason'   => $result['message'],
					'policy'   => $policy['name'],
				);
			}
		}

		return apply_filters( 'ea_gaming_engine_can_user_play', true, $user_id, $course_id );
	}

	/**
	 * Get user context for policy evaluation
	 *
	 * @param int $user_id User ID.
	 * @param int $course_id Course ID.
	 * @return array
	 */
	private function get_user_context( $user_id, $course_id = null ) {
		$context = array(
			'user_id'      => $user_id,
			'course_id'    => $course_id,
			'current_time' => current_time( 'H:i' ),
			'current_day'  => strtolower( current_time( 'D' ) ),
			'timezone'     => wp_timezone_string(),
		);

		// Add user meta.
		$user = get_user_by( 'ID', $user_id );
		if ( $user ) {
			$context['user_roles'] = $user->roles;
			$context['user_email'] = $user->user_email;
		}

		// Add course progress if course ID provided.
		if ( $course_id && function_exists( 'learndash_course_progress' ) ) {
			$progress                   = learndash_course_progress(
				array(
					'user_id'   => $user_id,
					'course_id' => $course_id,
					'array'     => true,
				)
			);
			$context['course_progress'] = $progress;
		}

		// Add today's play stats.
		$context['today_stats'] = $this->get_today_stats( $user_id );

		// Check parent controls if integration exists.
		if ( class_exists( 'EA_Student_Parent_Access' ) ) {
			$context['parent_controls'] = $this->get_parent_controls( $user_id );
		}

		return apply_filters( 'ea_gaming_engine_policy_context', $context, $user_id, $course_id );
	}

	/**
	 * Evaluate a single policy
	 *
	 * @param array $policy Policy data.
	 * @param array $context User context.
	 * @return array
	 */
	private function evaluate_policy( $policy, $context ) {
		$result = array(
			'block'   => false,
			'message' => '',
			'actions' => array(),
		);

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
				$result = apply_filters( 'ea_gaming_engine_evaluate_custom_policy', $result, $policy, $context );
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
		$conditions   = $policy['conditions'];
		$current_time = $context['current_time'];
		$current_day  = $context['current_day'];

		// Check if today is included in free play days.
		if ( ! empty( $conditions['days'] ) && ! in_array( $current_day, $conditions['days'], true ) ) {
			return array(
				'block'   => false,
				'message' => '',
				'actions' => array(),
			);
		}

		// Check if current time is within free play window.
		$start = $conditions['start_time'];
		$end   = $conditions['end_time'];

		if ( $this->is_time_between( $current_time, $start, $end ) ) {
			return array(
				'block'   => false,
				'message' => '',
				'actions' => $policy['actions'],
			);
		}

		return array(
			'block'   => false,
			'message' => '',
			'actions' => array(),
		);
	}

	/**
	 * Evaluate quiet hours policy
	 *
	 * @param array $policy Policy data.
	 * @param array $context User context.
	 * @return array
	 */
	private function evaluate_quiet_hours( $policy, $context ) {
		$conditions   = $policy['conditions'];
		$current_time = $context['current_time'];

		$start = $conditions['start_time'];
		$end   = $conditions['end_time'];

		// Handle overnight quiet hours.
		if ( $start > $end ) {
			// Quiet hours span midnight.
			if ( $current_time >= $start || $current_time <= $end ) {
				return array(
					'block'   => true,
					'message' => $policy['actions']['message'] ?? __( 'Games are not available during quiet hours.', 'ea-gaming-engine' ),
					'actions' => $policy['actions'],
				);
			}
		} elseif ( $this->is_time_between( $current_time, $start, $end ) ) {
			// Normal time range.
			return array(
				'block'   => true,
				'message' => $policy['actions']['message'] ?? __( 'Games are not available during quiet hours.', 'ea-gaming-engine' ),
				'actions' => $policy['actions'],
			);
		}

		return array(
			'block'   => false,
			'message' => '',
			'actions' => array(),
		);
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
			return array(
				'block'   => false,
				'message' => '',
				'actions' => array(),
			);
		}

		$conditions = $policy['conditions'];

		// Check if lesson view is required.
		if ( ! empty( $conditions['require_lesson_view'] ) ) {
			$last_lesson_view = $this->get_last_lesson_view( $context['user_id'], $context['course_id'] );

			if ( ! $last_lesson_view ) {
				return array(
					'block'   => true,
					'message' => $policy['actions']['message'] ?? __( 'Please complete a lesson before playing games.', 'ea-gaming-engine' ),
					'actions' => $policy['actions'],
				);
			}

			// Check minimum time requirement.
			if ( ! empty( $conditions['minimum_time'] ) ) {
				$time_spent = time() - $last_lesson_view;
				if ( $time_spent < $conditions['minimum_time'] ) {
					$remaining = $conditions['minimum_time'] - $time_spent;
					return array(
						'block'   => true,
						'message' => sprintf(
							/* translators: %d: number of minutes to study */
							__( 'Please study for %d more minutes before playing games.', 'ea-gaming-engine' ),
							ceil( $remaining / 60 )
						),
						'actions' => $policy['actions'],
					);
				}
			}
		}

		return array(
			'block'   => false,
			'message' => '',
			'actions' => array(),
		);
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
			return array(
				'block'   => false,
				'message' => '',
				'actions' => array(),
			);
		}

		$controls = $context['parent_controls'];

		// Check if parent has blocked games.
		if ( ! empty( $controls['games_blocked'] ) ) {
			return array(
				'block'   => true,
				'message' => __( 'Games have been disabled by your parent/guardian.', 'ea-gaming-engine' ),
				'actions' => $policy['actions'],
			);
		}

		// Check time restrictions.
		if ( ! empty( $controls['time_restrictions'] ) ) {
			$current_time  = $context['current_time'];
			$allowed_start = $controls['time_restrictions']['start'];
			$allowed_end   = $controls['time_restrictions']['end'];

			if ( ! $this->is_time_between( $current_time, $allowed_start, $allowed_end ) ) {
				return array(
					'block'   => true,
					'message' => sprintf(
						/* translators: %1$s: start time, %2$s: end time */
						__( 'Games are only available between %1$s and %2$s.', 'ea-gaming-engine' ),
						$allowed_start,
						$allowed_end
					),
					'actions' => $policy['actions'],
				);
			}
		}

		// Check ticket/token requirements.
		if ( ! empty( $controls['require_tickets'] ) ) {
			$tickets = $this->get_user_tickets( $context['user_id'] );
			if ( $tickets <= 0 ) {
				return array(
					'block'   => true,
					'message' => __( 'You need tickets to play. Ask your parent/guardian for more.', 'ea-gaming-engine' ),
					'actions' => $policy['actions'],
				);
			}
		}

		return array(
			'block'   => false,
			'message' => '',
			'actions' => array(),
		);
	}

	/**
	 * Evaluate daily limit policy
	 *
	 * @param array $policy Policy data.
	 * @param array $context User context.
	 * @return array
	 */
	private function evaluate_daily_limit( $policy, $context ) {
		$conditions  = $policy['conditions'];
		$today_stats = $context['today_stats'];

		// Check games played limit.
		if ( ! empty( $conditions['max_games_per_day'] ) ) {
			if ( $today_stats['games_played'] >= $conditions['max_games_per_day'] ) {
				return array(
					'block'   => true,
					'message' => __( 'You have reached your daily game limit.', 'ea-gaming-engine' ),
					'actions' => $policy['actions'],
				);
			}
		}

		// Check time played limit.
		if ( ! empty( $conditions['max_time_per_day'] ) ) {
			if ( $today_stats['time_played'] >= $conditions['max_time_per_day'] ) {
				return array(
					'block'   => true,
					'message' => __( 'You have reached your daily play time limit.', 'ea-gaming-engine' ),
					'actions' => $policy['actions'],
				);
			}
		}

		return array(
			'block'   => false,
			'message' => '',
			'actions' => array(),
		);
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
			return array(
				'block'   => false,
				'message' => '',
				'actions' => array(),
			);
		}

		$conditions = $policy['conditions'];

		// Check if course is in blocked list.
		if ( ! empty( $conditions['blocked_courses'] ) ) {
			if ( in_array( $context['course_id'], $conditions['blocked_courses'], true ) ) {
				return array(
					'block'   => true,
					'message' => __( 'Games are not available for this course.', 'ea-gaming-engine' ),
					'actions' => $policy['actions'],
				);
			}
		}

		// Check minimum progress requirement.
		if ( ! empty( $conditions['minimum_progress'] ) && ! empty( $context['course_progress'] ) ) {
			$progress = $context['course_progress']['percentage'] ?? 0;
			if ( $progress < $conditions['minimum_progress'] ) {
				return array(
					'block'   => true,
					'message' => sprintf(
						/* translators: %d: percentage of course completion required */
						__( 'Complete at least %d%% of the course to unlock games.', 'ea-gaming-engine' ),
						$conditions['minimum_progress']
					),
					'actions' => $policy['actions'],
				);
			}
		}

		return array(
			'block'   => false,
			'message' => '',
			'actions' => array(),
		);
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
		$start   = strtotime( $start );
		$end     = strtotime( $end );

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

		$today_start = gmdate( 'Y-m-d 00:00:00' );
		$today_end   = gmdate( 'Y-m-d 23:59:59' );

		$cache_key = 'ea_gaming_today_stats_' . $user_id . '_' . gmdate( 'Ymd' );
		$stats     = wp_cache_get( $cache_key, 'ea_gaming_engine' );

		if ( false === $stats ) {
			$stats = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT 
						COUNT(*) as games_played,
						SUM(duration) as time_played
					FROM {$table}
					WHERE user_id = %d
					AND created_at BETWEEN %s AND %s",
					$user_id,
					$today_start,
					$today_end
				)
			);
			wp_cache_set( $cache_key, $stats, 'ea_gaming_engine', 3600 ); // Cache for 1 hour.
		}

		return array(
			'games_played' => isset( $stats->games_played ) ? $stats->games_played : 0,
			'time_played'  => isset( $stats->time_played ) ? $stats->time_played : 0,
		);
	}

	/**
	 * Get last lesson view time
	 *
	 * @param int $user_id User ID.
	 * @param int $course_id Course ID.
	 * @return int|false Timestamp or false.
	 */
	private function get_last_lesson_view( $user_id, $course_id ) {
		// This would integrate with LearnDash activity tracking.
		// For now, return a mock value.
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
		// This would integrate with Student-Parent Access plugin.
		return apply_filters( 'ea_gaming_engine_parent_controls', array(), $user_id );
	}

	/**
	 * Get user tickets/tokens
	 *
	 * @param int $user_id User ID.
	 * @return int
	 */
	private function get_user_tickets( $user_id ) {
		$tickets = get_user_meta( $user_id, 'ea_gaming_tickets', true );
		return $tickets ? $tickets : 0;
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
		// This would run periodic policy checks.
		// For example, sending notifications when free play starts.
		do_action( 'ea_gaming_engine_policies_evaluated' );
	}
}
