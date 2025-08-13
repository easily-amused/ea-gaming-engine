<?php
/**
 * Frontend functionality
 *
 * @package EAGamingEngine
 */

namespace EAGamingEngine\Frontend;

use EAGamingEngine\Core\ThemeManager;
use EAGamingEngine\Core\PolicyEngine;

/**
 * Frontend class
 */
class Frontend {

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
		// Shortcodes
		add_shortcode( 'ea_gaming_arcade', [ $this, 'render_arcade_shortcode' ] );
		add_shortcode( 'ea_gaming_launcher', [ $this, 'render_launcher_shortcode' ] );
		add_shortcode( 'ea_gaming_leaderboard', [ $this, 'render_leaderboard_shortcode' ] );
		add_shortcode( 'ea_gaming_stats', [ $this, 'render_stats_shortcode' ] );

		// Content filters
		add_filter( 'the_content', [ $this, 'add_game_launcher_to_content' ], 20 );
		
		// Body classes
		add_filter( 'body_class', [ $this, 'add_body_classes' ] );

		// AJAX handlers
		add_action( 'wp_ajax_ea_gaming_launch', [ $this, 'ajax_launch_game' ] );
		add_action( 'wp_ajax_ea_gaming_get_leaderboard', [ $this, 'ajax_get_leaderboard' ] );
		add_action( 'wp_ajax_nopriv_ea_gaming_get_leaderboard', [ $this, 'ajax_get_leaderboard' ] );

		// Game modal
		add_action( 'wp_footer', [ $this, 'render_game_modal' ] );
	}

	/**
	 * Render arcade shortcode
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function render_arcade_shortcode( $atts ) {
		$atts = shortcode_atts(
			[
				'course_id'  => 0,
				'quiz_id'    => 0,
				'game_type'  => '',
				'theme'      => '',
				'preset'     => '',
				'show_stats' => 'true',
				'show_leaderboard' => 'true',
				'columns'    => 3,
			],
			$atts,
			'ea_gaming_arcade'
		);

		if ( ! is_user_logged_in() ) {
			return '<div class="ea-gaming-notice">' . __( 'Please log in to play games.', 'ea-gaming-engine' ) . '</div>';
		}

		// Check if gaming is enabled
		if ( ! get_option( 'ea_gaming_engine_enabled', true ) ) {
			return '';
		}

		// Get course ID if not provided
		if ( ! $atts['course_id'] ) {
			$atts['course_id'] = get_the_ID();
			
			// Try to get course ID from context
			if ( function_exists( 'learndash_get_course_id' ) ) {
				$atts['course_id'] = learndash_get_course_id( $atts['course_id'] );
			}
		}

		// Check policies
		$policy_engine = new PolicyEngine();
		$can_play = $policy_engine->can_user_play( get_current_user_id(), $atts['course_id'] );

		ob_start();
		?>
		<div class="ea-gaming-arcade" 
			data-course-id="<?php echo esc_attr( $atts['course_id'] ); ?>"
			data-quiz-id="<?php echo esc_attr( $atts['quiz_id'] ); ?>"
			data-columns="<?php echo esc_attr( $atts['columns'] ); ?>">
			
			<?php if ( is_array( $can_play ) && ! $can_play['can_play'] ) : ?>
				<div class="ea-gaming-policy-notice">
					<span class="dashicons dashicons-lock"></span>
					<p><?php echo esc_html( $can_play['reason'] ); ?></p>
				</div>
			<?php else : ?>
				<div class="ea-gaming-arcade-header">
					<h3><?php esc_html_e( 'Gaming Arcade', 'ea-gaming-engine' ); ?></h3>
					<div class="ea-gaming-arcade-controls">
						<button class="ea-gaming-theme-switcher" title="<?php esc_attr_e( 'Switch Theme', 'ea-gaming-engine' ); ?>">
							<span class="dashicons dashicons-art"></span>
						</button>
						<button class="ea-gaming-preset-switcher" title="<?php esc_attr_e( 'Change Difficulty', 'ea-gaming-engine' ); ?>">
							<span class="dashicons dashicons-performance"></span>
						</button>
					</div>
				</div>

				<div class="ea-gaming-arcade-games">
					<?php echo $this->render_game_cards( $atts['game_type'] ); ?>
				</div>

				<?php if ( $atts['show_stats'] === 'true' ) : ?>
					<div class="ea-gaming-arcade-stats">
						<?php echo $this->render_player_stats( $atts['course_id'] ); ?>
					</div>
				<?php endif; ?>

				<?php if ( $atts['show_leaderboard'] === 'true' ) : ?>
					<div class="ea-gaming-arcade-leaderboard">
						<?php echo $this->render_leaderboard( $atts['course_id'], 5 ); ?>
					</div>
				<?php endif; ?>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render launcher shortcode
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function render_launcher_shortcode( $atts ) {
		$atts = shortcode_atts(
			[
				'course_id' => 0,
				'quiz_id'   => 0,
				'game_type' => 'whack_a_question',
				'button_text' => __( 'Play Game', 'ea-gaming-engine' ),
				'style'     => 'default',
			],
			$atts,
			'ea_gaming_launcher'
		);

		if ( ! is_user_logged_in() ) {
			return '';
		}

		// Get course/quiz IDs from context if not provided
		if ( ! $atts['course_id'] && ! $atts['quiz_id'] ) {
			$post_id = get_the_ID();
			$post_type = get_post_type( $post_id );

			if ( $post_type === 'sfwd-quiz' ) {
				$atts['quiz_id'] = $post_id;
				$atts['course_id'] = learndash_get_course_id( $post_id );
			} elseif ( in_array( $post_type, [ 'sfwd-courses', 'sfwd-lessons', 'sfwd-topic' ], true ) ) {
				$atts['course_id'] = learndash_get_course_id( $post_id );
			}
		}

		$button_class = 'ea-gaming-launcher-btn ea-gaming-launcher-' . $atts['style'];

		ob_start();
		?>
		<button class="<?php echo esc_attr( $button_class ); ?>"
			data-course-id="<?php echo esc_attr( $atts['course_id'] ); ?>"
			data-quiz-id="<?php echo esc_attr( $atts['quiz_id'] ); ?>"
			data-game-type="<?php echo esc_attr( $atts['game_type'] ); ?>">
			<span class="dashicons dashicons-games"></span>
			<?php echo esc_html( $atts['button_text'] ); ?>
		</button>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render leaderboard shortcode
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function render_leaderboard_shortcode( $atts ) {
		$atts = shortcode_atts(
			[
				'course_id' => 0,
				'limit'     => 10,
				'period'    => 'all',
				'show_avatar' => 'true',
			],
			$atts,
			'ea_gaming_leaderboard'
		);

		// Get course ID from context if not provided
		if ( ! $atts['course_id'] && function_exists( 'learndash_get_course_id' ) ) {
			$atts['course_id'] = learndash_get_course_id();
		}

		return $this->render_leaderboard( $atts['course_id'], $atts['limit'], $atts['period'], $atts['show_avatar'] === 'true' );
	}

	/**
	 * Render stats shortcode
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function render_stats_shortcode( $atts ) {
		$atts = shortcode_atts(
			[
				'user_id'   => get_current_user_id(),
				'course_id' => 0,
				'style'     => 'card',
			],
			$atts,
			'ea_gaming_stats'
		);

		if ( ! $atts['user_id'] ) {
			return '';
		}

		// Get course ID from context if not provided
		if ( ! $atts['course_id'] && function_exists( 'learndash_get_course_id' ) ) {
			$atts['course_id'] = learndash_get_course_id();
		}

		return $this->render_player_stats( $atts['course_id'], $atts['user_id'], $atts['style'] );
	}

	/**
	 * Render game cards
	 *
	 * @param string $filter_type Filter by game type.
	 * @return string
	 */
	private function render_game_cards( $filter_type = '' ) {
		$games = [
			'whack_a_question' => [
				'name'        => __( 'Whack-a-Question', 'ea-gaming-engine' ),
				'description' => __( 'Fast-paced question answering', 'ea-gaming-engine' ),
				'icon'        => 'hammer',
				'color'       => '#7C3AED',
			],
			'tic_tac_tactics' => [
				'name'        => __( 'Tic-Tac-Tactics', 'ea-gaming-engine' ),
				'description' => __( 'Strategic quiz tic-tac-toe', 'ea-gaming-engine' ),
				'icon'        => 'grid-view',
				'color'       => '#EC4899',
			],
			'target_trainer' => [
				'name'        => __( 'Target Trainer', 'ea-gaming-engine' ),
				'description' => __( 'Aim and shoot correct answers', 'ea-gaming-engine' ),
				'icon'        => 'location',
				'color'       => '#10B981',
			],
		];

		if ( $filter_type && isset( $games[ $filter_type ] ) ) {
			$games = [ $filter_type => $games[ $filter_type ] ];
		}

		ob_start();
		foreach ( $games as $game_type => $game ) :
			$enabled = get_option( 'ea_gaming_engine_games' )[ $game_type ]['enabled'] ?? true;
			if ( ! $enabled ) {
				continue;
			}
			?>
			<div class="ea-gaming-card" data-game-type="<?php echo esc_attr( $game_type ); ?>">
				<div class="ea-gaming-card-icon" style="background-color: <?php echo esc_attr( $game['color'] ); ?>">
					<span class="dashicons dashicons-<?php echo esc_attr( $game['icon'] ); ?>"></span>
				</div>
				<h4><?php echo esc_html( $game['name'] ); ?></h4>
				<p><?php echo esc_html( $game['description'] ); ?></p>
				<button class="ea-gaming-play-btn" data-game-type="<?php echo esc_attr( $game_type ); ?>">
					<?php esc_html_e( 'Play Now', 'ea-gaming-engine' ); ?>
				</button>
			</div>
			<?php
		endforeach;
		return ob_get_clean();
	}

	/**
	 * Render player stats
	 *
	 * @param int    $course_id Course ID.
	 * @param int    $user_id User ID.
	 * @param string $style Display style.
	 * @return string
	 */
	private function render_player_stats( $course_id = 0, $user_id = 0, $style = 'card' ) {
		if ( ! $user_id ) {
			$user_id = get_current_user_id();
		}

		if ( ! $user_id ) {
			return '';
		}

		global $wpdb;
		$table = $wpdb->prefix . 'ea_player_stats';

		$where = 'user_id = %d';
		$params = [ $user_id ];

		if ( $course_id ) {
			$where .= ' AND course_id = %d';
			$params[] = $course_id;
		}

		$stats = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT 
					SUM(total_games_played) as games_played,
					SUM(total_score) as total_score,
					SUM(total_questions_correct) as questions_correct,
					SUM(total_questions_answered) as questions_total,
					MAX(streak_best) as best_streak
				FROM $table
				WHERE $where",
				$params
			)
		);

		if ( ! $stats || $stats->games_played == 0 ) {
			return '<div class="ea-gaming-no-stats">' . __( 'No games played yet', 'ea-gaming-engine' ) . '</div>';
		}

		$accuracy = $stats->questions_total > 0 ? round( ( $stats->questions_correct / $stats->questions_total ) * 100 ) : 0;

		ob_start();
		?>
		<div class="ea-gaming-stats ea-gaming-stats-<?php echo esc_attr( $style ); ?>">
			<h4><?php esc_html_e( 'Your Stats', 'ea-gaming-engine' ); ?></h4>
			<div class="ea-gaming-stats-grid">
				<div class="ea-gaming-stat">
					<span class="ea-gaming-stat-value"><?php echo esc_html( $stats->games_played ); ?></span>
					<span class="ea-gaming-stat-label"><?php esc_html_e( 'Games Played', 'ea-gaming-engine' ); ?></span>
				</div>
				<div class="ea-gaming-stat">
					<span class="ea-gaming-stat-value"><?php echo esc_html( number_format( $stats->total_score ) ); ?></span>
					<span class="ea-gaming-stat-label"><?php esc_html_e( 'Total Score', 'ea-gaming-engine' ); ?></span>
				</div>
				<div class="ea-gaming-stat">
					<span class="ea-gaming-stat-value"><?php echo esc_html( $accuracy ); ?>%</span>
					<span class="ea-gaming-stat-label"><?php esc_html_e( 'Accuracy', 'ea-gaming-engine' ); ?></span>
				</div>
				<div class="ea-gaming-stat">
					<span class="ea-gaming-stat-value"><?php echo esc_html( $stats->best_streak ); ?></span>
					<span class="ea-gaming-stat-label"><?php esc_html_e( 'Best Streak', 'ea-gaming-engine' ); ?></span>
				</div>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render leaderboard
	 *
	 * @param int    $course_id Course ID.
	 * @param int    $limit Limit results.
	 * @param string $period Time period.
	 * @param bool   $show_avatar Show user avatars.
	 * @return string
	 */
	private function render_leaderboard( $course_id = 0, $limit = 10, $period = 'all', $show_avatar = true ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ea_player_stats';

		$query = "SELECT 
			ps.user_id,
			u.display_name,
			ps.total_score,
			ps.total_games_played,
			ps.streak_best
		FROM $table ps
		JOIN {$wpdb->users} u ON ps.user_id = u.ID";

		$where = [];
		$params = [];

		if ( $course_id ) {
			$where[] = 'ps.course_id = %d';
			$params[] = $course_id;
		}

		// Add period filter
		if ( $period !== 'all' ) {
			switch ( $period ) {
				case 'today':
					$where[] = 'ps.last_played >= %s';
					$params[] = current_time( 'Y-m-d 00:00:00' );
					break;
				case 'week':
					$where[] = 'ps.last_played >= %s';
					$params[] = current_time( 'Y-m-d 00:00:00', strtotime( '-7 days' ) );
					break;
				case 'month':
					$where[] = 'ps.last_played >= %s';
					$params[] = current_time( 'Y-m-d 00:00:00', strtotime( '-30 days' ) );
					break;
			}
		}

		if ( ! empty( $where ) ) {
			$query .= ' WHERE ' . implode( ' AND ', $where );
		}

		$query .= ' ORDER BY ps.total_score DESC LIMIT %d';
		$params[] = $limit;

		$results = $wpdb->get_results( $wpdb->prepare( $query, $params ) );

		if ( empty( $results ) ) {
			return '<div class="ea-gaming-no-leaderboard">' . __( 'No leaderboard data yet', 'ea-gaming-engine' ) . '</div>';
		}

		ob_start();
		?>
		<div class="ea-gaming-leaderboard">
			<h4><?php esc_html_e( 'Leaderboard', 'ea-gaming-engine' ); ?></h4>
			<ol class="ea-gaming-leaderboard-list">
				<?php
				$current_user_id = get_current_user_id();
				foreach ( $results as $index => $player ) :
					$is_current_user = $player->user_id == $current_user_id;
					$position = $index + 1;
					?>
					<li class="<?php echo $is_current_user ? 'ea-gaming-current-user' : ''; ?>">
						<span class="ea-gaming-position"><?php echo esc_html( $position ); ?></span>
						<?php if ( $show_avatar ) : ?>
							<?php echo get_avatar( $player->user_id, 32 ); ?>
						<?php endif; ?>
						<span class="ea-gaming-player-name"><?php echo esc_html( $player->display_name ); ?></span>
						<span class="ea-gaming-score"><?php echo esc_html( number_format( $player->total_score ) ); ?></span>
					</li>
				<?php endforeach; ?>
			</ol>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Add game launcher to content
	 *
	 * @param string $content Content.
	 * @return string
	 */
	public function add_game_launcher_to_content( $content ) {
		// Only add to LearnDash content
		if ( ! in_array( get_post_type(), [ 'sfwd-courses', 'sfwd-lessons', 'sfwd-topic', 'sfwd-quiz' ], true ) ) {
			return $content;
		}

		// Check if auto-insert is enabled
		if ( ! apply_filters( 'ea_gaming_auto_insert_launcher', true ) ) {
			return $content;
		}

		// Don't add if shortcode already exists
		if ( has_shortcode( $content, 'ea_gaming_arcade' ) || has_shortcode( $content, 'ea_gaming_launcher' ) ) {
			return $content;
		}

		$post_type = get_post_type();
		$launcher = '';

		if ( $post_type === 'sfwd-courses' ) {
			$launcher = do_shortcode( '[ea_gaming_arcade show_leaderboard="true"]' );
		} elseif ( $post_type === 'sfwd-quiz' ) {
			$launcher = do_shortcode( '[ea_gaming_launcher button_text="' . __( 'Play as Game', 'ea-gaming-engine' ) . '"]' );
		}

		if ( $launcher ) {
			$content = $launcher . $content;
		}

		return $content;
	}

	/**
	 * Add body classes
	 *
	 * @param array $classes Body classes.
	 * @return array
	 */
	public function add_body_classes( $classes ) {
		if ( get_option( 'ea_gaming_engine_enabled', true ) ) {
			$classes[] = 'ea-gaming-enabled';
			
			$theme_manager = ThemeManager::get_instance();
			$current_theme = $theme_manager->get_current_theme();
			$classes[] = 'ea-gaming-theme-' . $current_theme;
		}

		return $classes;
	}

	/**
	 * Render game modal
	 *
	 * @return void
	 */
	public function render_game_modal() {
		if ( ! is_user_logged_in() || ! get_option( 'ea_gaming_engine_enabled', true ) ) {
			return;
		}

		// Only load on pages that might need it
		if ( ! is_singular( [ 'sfwd-courses', 'sfwd-lessons', 'sfwd-topic', 'sfwd-quiz' ] ) && ! has_shortcode( get_post()->post_content ?? '', 'ea_gaming' ) ) {
			return;
		}

		?>
		<div id="ea-gaming-modal" class="ea-gaming-modal" style="display: none;">
			<div class="ea-gaming-modal-overlay"></div>
			<div class="ea-gaming-modal-content">
				<button class="ea-gaming-modal-close">
					<span class="dashicons dashicons-no"></span>
				</button>
				<div class="ea-gaming-modal-header">
					<h2 id="ea-gaming-modal-title"></h2>
					<div class="ea-gaming-modal-controls">
						<button class="ea-gaming-fullscreen" title="<?php esc_attr_e( 'Fullscreen', 'ea-gaming-engine' ); ?>">
							<span class="dashicons dashicons-fullscreen-alt"></span>
						</button>
						<button class="ea-gaming-sound-toggle" title="<?php esc_attr_e( 'Toggle Sound', 'ea-gaming-engine' ); ?>">
							<span class="dashicons dashicons-controls-volumeon"></span>
						</button>
					</div>
				</div>
				<div class="ea-gaming-modal-body">
					<div id="ea-gaming-container">
						<!-- Game will be loaded here -->
					</div>
				</div>
				<div class="ea-gaming-modal-footer">
					<div class="ea-gaming-session-info">
						<span class="ea-gaming-score">
							<?php esc_html_e( 'Score:', 'ea-gaming-engine' ); ?> 
							<strong id="ea-gaming-score">0</strong>
						</span>
						<span class="ea-gaming-timer">
							<?php esc_html_e( 'Time:', 'ea-gaming-engine' ); ?> 
							<strong id="ea-gaming-timer">00:00</strong>
						</span>
					</div>
					<button class="ea-gaming-quit button">
						<?php esc_html_e( 'Quit Game', 'ea-gaming-engine' ); ?>
					</button>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * AJAX handler for launching game
	 *
	 * @return void
	 */
	public function ajax_launch_game() {
		check_ajax_referer( 'ea-gaming-engine', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( __( 'Please log in to play', 'ea-gaming-engine' ) );
		}

		$game_type = sanitize_text_field( $_POST['game_type'] ?? 'whack_a_question' );
		$course_id = intval( $_POST['course_id'] ?? 0 );
		$quiz_id = intval( $_POST['quiz_id'] ?? 0 );

		// Check policies
		$policy_engine = new PolicyEngine();
		$can_play = $policy_engine->can_user_play( get_current_user_id(), $course_id );

		if ( is_array( $can_play ) && ! $can_play['can_play'] ) {
			wp_send_json_error( $can_play['reason'] );
		}

		// Get theme and preset
		$theme_manager = ThemeManager::get_instance();
		$theme = $theme_manager->get_theme_data( null );
		$preset = $theme_manager->get_preset_data( null );

		// Prepare game configuration
		$config = [
			'game_type' => $game_type,
			'course_id' => $course_id,
			'quiz_id'   => $quiz_id,
			'theme'     => $theme,
			'preset'    => $preset,
			'api_url'   => home_url( '/wp-json/ea-gaming/v1/' ),
			'nonce'     => wp_create_nonce( 'wp_rest' ),
		];

		wp_send_json_success( $config );
	}

	/**
	 * AJAX handler for getting leaderboard
	 *
	 * @return void
	 */
	public function ajax_get_leaderboard() {
		$course_id = intval( $_POST['course_id'] ?? 0 );
		$limit = intval( $_POST['limit'] ?? 10 );
		$period = sanitize_text_field( $_POST['period'] ?? 'all' );

		$leaderboard_html = $this->render_leaderboard( $course_id, $limit, $period );

		wp_send_json_success( [ 'html' => $leaderboard_html ] );
	}
}