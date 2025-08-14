<?php
/**
 * LearnDash Integration
 *
 * @package EAGamingEngine
 */

namespace EAGamingEngine\Integrations;

use EAGamingEngine\Core\QuestionGate;

/**
 * LearnDash class
 */
class LearnDash {

	/**
	 * Course structure cache
	 *
	 * @var array
	 */
	private $course_cache = array();

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
		// Course display hooks.
		add_action( 'learndash-course-after', array( $this, 'display_game_launcher' ), 10, 2 );
		add_action( 'learndash-lesson-after', array( $this, 'display_mini_game' ), 10, 2 );
		add_action( 'learndash-quiz-before', array( $this, 'display_quiz_game_option' ), 10, 2 );

		// Progress hooks.
		add_action( 'ea_gaming_session_ended', array( $this, 'sync_progress_to_learndash' ), 10, 4 );
		add_filter( 'learndash_completion_redirect', array( $this, 'handle_game_completion_redirect' ), 10, 2 );

		// Content enhancement.
		add_filter( 'learndash_content', array( $this, 'enhance_content_with_games' ), 10, 2 );
		add_filter( 'learndash-focus-mode-can-complete', array( $this, 'check_game_completion' ), 10, 4 );

		// AJAX handlers.
		add_action( 'wp_ajax_ea_gaming_get_course_structure', array( $this, 'ajax_get_course_structure' ) );
		add_action( 'wp_ajax_ea_gaming_get_quiz_questions', array( $this, 'ajax_get_quiz_questions' ) );
	}

	/**
	 * Get course game structure
	 *
	 * @param int $course_id Course ID.
	 * @return array
	 */
	public function get_course_structure( $course_id ) {
		// Check cache.
		if ( isset( $this->course_cache[ $course_id ] ) ) {
			return $this->course_cache[ $course_id ];
		}

		$structure = array(
			'course_id'    => $course_id,
			'title'        => get_the_title( $course_id ),
			'worlds'       => array(), // Lessons as worlds.
			'boss_battles' => array(), // Quizzes as boss battles.
			'side_quests'  => array(), // Topics as side quests.
			'final_boss'   => null, // Course quiz as final boss.
		);

		// Get course lessons (worlds).
		$lessons = learndash_get_lesson_list( $course_id );

		foreach ( $lessons as $lesson ) {
			$world = array(
				'id'          => $lesson->ID,
				'title'       => $lesson->post_title,
				'description' => $lesson->post_excerpt,
				'levels'      => array(), // Topics.
				'gates'       => array(), // Lesson quizzes.
				'completed'   => $this->is_step_complete( get_current_user_id(), $course_id, $lesson->ID ),
			);

			// Get topics (levels).
			$topics = learndash_get_topic_list( $lesson->ID, $course_id );
			foreach ( $topics as $topic ) {
				$level = array(
					'id'        => $topic->ID,
					'title'     => $topic->post_title,
					'gates'     => array(), // Topic quizzes.
					'completed' => $this->is_step_complete( get_current_user_id(), $course_id, $topic->ID ),
				);

				// Get topic quizzes.
				$topic_quizzes = learndash_get_topic_quiz_list( $topic->ID, get_current_user_id(), $course_id );
				foreach ( $topic_quizzes as $quiz ) {
					$level['gates'][] = $this->format_quiz_gate( $quiz );
				}

				$world['levels'][] = $level;
			}

			// Get lesson quizzes.
			$lesson_quizzes = learndash_get_lesson_quiz_list( $lesson->ID, get_current_user_id(), $course_id );
			foreach ( $lesson_quizzes as $quiz ) {
				$world['gates'][] = $this->format_quiz_gate( $quiz );
			}

			$structure['worlds'][] = $world;
		}

		// Get course quizzes (final boss).
		$course_quizzes = learndash_get_course_quiz_list( $course_id, get_current_user_id() );
		if ( ! empty( $course_quizzes ) ) {
			foreach ( $course_quizzes as $quiz ) {
				$structure['boss_battles'][] = $this->format_quiz_gate( $quiz );
			}
			// Set the last course quiz as final boss.
			$structure['final_boss'] = end( $structure['boss_battles'] );
		}

		// Cache the structure.
		$this->course_cache[ $course_id ] = $structure;

		return $structure;
	}

	/**
	 * Format quiz as game gate
	 *
	 * @param object $quiz Quiz object.
	 * @return array
	 */
	private function format_quiz_gate( $quiz ) {
		$quiz_id = is_object( $quiz ) ? $quiz->ID : $quiz;

		// Get quiz questions count.
		$questions = learndash_get_quiz_questions( $quiz_id );

		return array(
			'id'             => $quiz_id,
			'title'          => get_the_title( $quiz_id ),
			'question_count' => count( $questions ),
			'difficulty'     => $this->calculate_quiz_difficulty( $quiz_id ),
			'type'           => $this->determine_gate_type( $quiz_id ),
			'completed'      => learndash_is_quiz_complete( get_current_user_id(), $quiz_id ),
			'best_score'     => $this->get_best_score( $quiz_id ),
		);
	}

	/**
	 * Map quiz to game questions
	 *
	 * @param int $quiz_id Quiz ID.
	 * @return array
	 */
	public function map_quiz_to_game( $quiz_id ) {
		$questions      = array();
		$quiz_questions = learndash_get_quiz_questions( $quiz_id );

		foreach ( $quiz_questions as $question_id => $meta ) {
			$questions[] = $this->format_question_for_game( $question_id, $quiz_id );
		}

		return array(
			'quiz_id'    => $quiz_id,
			'quiz_title' => get_the_title( $quiz_id ),
			'questions'  => $questions,
			'settings'   => $this->get_quiz_game_settings( $quiz_id ),
		);
	}

	/**
	 * Format question for game
	 *
	 * @param int $question_id Question ID.
	 * @param int $quiz_id Quiz ID.
	 * @return array
	 */
	private function format_question_for_game( $question_id, $quiz_id ) {
		$pro_id = get_post_meta( $question_id, 'question_pro_id', true );

		if ( ! $pro_id ) {
			return array();
		}

		$mapper = new \WpProQuiz_Model_QuestionMapper();
		$model  = $mapper->fetch( $pro_id );

		if ( ! $model ) {
			return array();
		}

		// Get answer data.
		$answers = array();
		foreach ( $model->getAnswerData() as $index => $answer ) {
			$answers[] = array(
				'id'      => $index,
				'text'    => $answer->getAnswer(),
				'html'    => $answer->isHtml(),
				'correct' => $answer->isCorrect(),
				'points'  => $answer->getPoints(),
			);
		}

		return array(
			'id'          => $question_id,
			'quiz_id'     => $quiz_id,
			'title'       => $model->getTitle(),
			'question'    => $model->getQuestion(),
			'type'        => $this->map_question_type( $model->getAnswerType() ),
			'points'      => $model->getPoints(),
			'difficulty'  => $this->calculate_question_difficulty( $model ),
			'answers'     => $answers,
			'hint'        => $model->getTipMsg(),
			'explanation' => $model->getCorrectMsg(),
		);
	}

	/**
	 * Map LearnDash question type to game type
	 *
	 * @param string $ld_type LearnDash question type.
	 * @return string
	 */
	private function map_question_type( $ld_type ) {
		$type_map = array(
			'single'       => 'single_choice',
			'multiple'     => 'multiple_choice',
			'free_answer'  => 'free_text',
			'sort_answer'  => 'sort',
			'matrix_sort'  => 'matrix',
			'cloze_answer' => 'fill_blank',
			'assessment'   => 'scale',
			'essay'        => 'essay',
		);

		return $type_map[ $ld_type ] ?? 'single_choice';
	}

	/**
	 * Sync game progress to LearnDash
	 *
	 * @param int   $session_id Session ID.
	 * @param int   $user_id User ID.
	 * @param int   $course_id Course ID.
	 * @param array $stats Session stats.
	 * @return void
	 */
	public function sync_progress_to_learndash( $session_id, $user_id, $course_id, $stats ) {
		global $wpdb;

		// Get session data.
		$cache_key = 'ea_gaming_session_' . $session_id;
		$session   = wp_cache_get( $cache_key, 'ea_gaming_engine' );

		if ( false === $session ) {
			$session = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM {$wpdb->prefix}ea_game_sessions WHERE id = %d",
					$session_id
				)
			);
			wp_cache_set( $cache_key, $session, 'ea_gaming_engine', 300 );
		}

		if ( ! $session ) {
			return;
		}

		// Get metadata.
		$metadata = json_decode( $session->metadata, true );
		$quiz_id  = $metadata['quiz_id'] ?? 0;

		if ( ! $quiz_id ) {
			return;
		}

		// Passing percentage key may differ between LD versions.
		$passing_percentage = 0;
		$pp1                = learndash_get_setting( $quiz_id, 'passingpercentage' );
		$pp2                = learndash_get_setting( $quiz_id, 'passing_percentage' );
		if ( is_numeric( $pp1 ) ) {
			$passing_percentage = (float) $pp1;
		} elseif ( is_numeric( $pp2 ) ) {
			$passing_percentage = (float) $pp2;
		} else {
			$passing_percentage = 80; // Sane default.
		}

		$correct          = (int) ( $stats['questions_correct'] ?? 0 );
		$total            = (int) ( $stats['questions_total'] ?? 0 );
		$score_percentage = ( $total > 0 ) ? ( ( $correct / $total ) * 100.0 ) : 0.0;

		if ( $score_percentage >= $passing_percentage ) {
			// Mark quiz as complete.
			learndash_process_mark_complete( $user_id, $quiz_id, false, $course_id );

			// Check if associated lesson/topic should be marked complete.
			$this->check_parent_completion( $user_id, $quiz_id, $course_id );
		}

		// Save quiz statistics for LearnDash reporting.
		$this->save_quiz_statistics( $user_id, $quiz_id, $stats );
	}

	/**
	 * Check and mark parent completion
	 *
	 * @param int $user_id User ID.
	 * @param int $quiz_id Quiz ID.
	 * @param int $course_id Course ID.
	 * @return void
	 */
	private function check_parent_completion( $user_id, $quiz_id, $course_id ) {
		// Get parent lesson or topic.
		$parent_id = learndash_get_lesson_id( $quiz_id, $course_id );

		if ( ! $parent_id ) {
			$parent_id = learndash_get_topic_id( $quiz_id, $course_id );
		}

		if ( $parent_id ) {
			// Check if all child steps are complete.
			$progress = learndash_get_course_progress( $user_id, $parent_id );

			if ( $progress['total'] === $progress['completed'] ) {
				learndash_process_mark_complete( $user_id, $parent_id, false, $course_id );
			}
		}
	}

	/**
	 * Save quiz statistics
	 *
	 * @param int   $user_id User ID.
	 * @param int   $quiz_id Quiz ID.
	 * @param array $stats Stats data.
	 * @return void
	 */
	private function save_quiz_statistics( $user_id, $quiz_id, $stats ) {
		// This would integrate with LearnDash's quiz statistics.
		// For now, store in user meta.
		$key      = 'ea_gaming_quiz_stats_' . $quiz_id;
		$existing = get_user_meta( $user_id, $key, true );
		if ( ! $existing ) {
			$existing = array();
		}

		$existing[] = array(
			'date'              => current_time( 'mysql' ),
			'score'             => $stats['score'] ?? 0,
			'questions_correct' => $stats['questions_correct'] ?? 0,
			'questions_total'   => $stats['questions_total'] ?? 0,
			'time_spent'        => $stats['duration'] ?? 0,
		);

		update_user_meta( $user_id, $key, $existing );
	}

	/**
	 * Calculate quiz difficulty
	 *
	 * @param int $quiz_id Quiz ID.
	 * @return string
	 */
	private function calculate_quiz_difficulty( $quiz_id ) {
		$questions      = learndash_get_quiz_questions( $quiz_id );
		$total_points   = 0;
		$question_count = count( $questions );

		if ( 0 === $question_count ) {
			return 'medium';
		}

		foreach ( $questions as $question_id => $meta ) {
			$pro_id = get_post_meta( $question_id, 'question_pro_id', true );
			if ( $pro_id ) {
				$mapper = new \WpProQuiz_Model_QuestionMapper();
				$model  = $mapper->fetch( $pro_id );
				if ( $model ) {
					$total_points += $model->getPoints();
				}
			}
		}

		$avg_points = $total_points / $question_count;

		if ( $avg_points <= 1 ) {
			return 'easy';
		} elseif ( $avg_points <= 3 ) {
			return 'medium';
		} else {
			return 'hard';
		}
	}

	/**
	 * Calculate question difficulty
	 *
	 * @param object $model Question model.
	 * @return string
	 */
	private function calculate_question_difficulty( $model ) {
		$points = $model->getPoints();
		$type   = $model->getAnswerType();

		// Complex question types are harder.
		$complex_types = array( 'matrix_sort', 'cloze_answer', 'essay' );

		if ( in_array( $type, $complex_types, true ) ) {
			return 'hard';
		}

		if ( $points <= 1 ) {
			return 'easy';
		} elseif ( $points <= 3 ) {
			return 'medium';
		} else {
			return 'hard';
		}
	}

	/**
	 * Determine gate type based on quiz
	 *
	 * @param int $quiz_id Quiz ID.
	 * @return string
	 */
	private function determine_gate_type( $quiz_id ) {
		// Check if it's a course quiz (final boss).
		$course_id      = learndash_get_course_id( $quiz_id );
		$course_quizzes = learndash_get_course_quiz_list( $course_id );

		foreach ( $course_quizzes as $quiz ) {
			if ( $quiz_id === $quiz->ID ) {
				return 'boss_battle';
			}
		}

		// Check question count.
		$questions = learndash_get_quiz_questions( $quiz_id );
		$count     = count( $questions );

		if ( 10 <= $count ) {
			return 'boss_battle';
		} elseif ( 5 <= $count ) {
			return 'mini_boss';
		} else {
			return 'gate';
		}
	}

	/**
	 * Get quiz game settings
	 *
	 * @param int $quiz_id Quiz ID.
	 * @return array
	 */
	private function get_quiz_game_settings( $quiz_id ) {
		$settings = learndash_get_setting( $quiz_id );

		return array(
			'passing_percentage' => $settings['passing_percentage'] ?? 80,
			'retry_restrictions' => $settings['retry_restrictions'] ?? 0,
			'time_limit'         => $settings['time_limit'] ?? 0,
			'random_questions'   => $settings['random_questions'] ?? false,
			'random_answers'     => $settings['random_answers'] ?? false,
			'show_points'        => $settings['show_points'] ?? true,
		);
	}

	/**
	 * Check if step is complete
	 *
	 * @param int $user_id User ID.
	 * @param int $course_id Course ID.
	 * @param int $step_id Step ID.
	 * @return bool
	 */
	private function is_step_complete( $user_id, $course_id, $step_id ) {
		return learndash_user_progress_is_step_complete( $user_id, $course_id, $step_id );
	}

	/**
	 * Get best score for quiz
	 *
	 * @param int $quiz_id Quiz ID.
	 * @return int
	 */
	private function get_best_score( $quiz_id ) {
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return 0;
		}

		$key   = 'ea_gaming_quiz_stats_' . $quiz_id;
		$stats = get_user_meta( $user_id, $key, true );

		if ( empty( $stats ) ) {
			return 0;
		}

		$best = 0;
		foreach ( $stats as $stat ) {
			if ( $stat['score'] > $best ) {
				$best = $stat['score'];
			}
		}

		return $best;
	}

	/**
	 * Display game launcher on course page
	 *
	 * @param int $course_id Course ID.
	 * @param int $user_id User ID.
	 * @return void
	 */
	public function display_game_launcher( $course_id, $user_id ) {
		if ( ! apply_filters( 'ea_gaming_engine_show_course_launcher', true, $course_id, $user_id ) ) {
			return;
		}

		$structure = $this->get_course_structure( $course_id );

		?>
		<div class="ea-gaming-course-launcher" data-course-id="<?php echo esc_attr( $course_id ); ?>">
			<h3><?php esc_html_e( 'Play Course as Game', 'ea-gaming-engine' ); ?></h3>
			<p><?php esc_html_e( 'Transform your learning experience into an interactive adventure!', 'ea-gaming-engine' ); ?></p>
			<button class="ea-gaming-launch-btn button button-primary" data-course-id="<?php echo esc_attr( $course_id ); ?>">
				<?php esc_html_e( 'Start Gaming Mode', 'ea-gaming-engine' ); ?>
			</button>
			<div class="ea-gaming-stats">
				<span><?php echo esc_html( sprintf( /* translators: %d: number of worlds/lessons */ __( 'Worlds: %d', 'ea-gaming-engine' ), count( $structure['worlds'] ) ) ); ?></span>
				<span><?php echo esc_html( sprintf( /* translators: %d: number of boss battles/quizzes */ __( 'Boss Battles: %d', 'ea-gaming-engine' ), count( $structure['boss_battles'] ) ) ); ?></span>
			</div>
		</div>
		<?php
	}

	/**
	 * Display mini game on lesson page
	 *
	 * @param int $lesson_id Lesson ID.
	 * @param int $user_id User ID.
	 * @return void
	 */
	public function display_mini_game( $lesson_id, $user_id ) {
		if ( ! apply_filters( 'ea_gaming_engine_show_lesson_game', true, $lesson_id, $user_id ) ) {
			return;
		}

		$course_id      = learndash_get_course_id( $lesson_id );
		$lesson_quizzes = learndash_get_lesson_quiz_list( $lesson_id, $user_id, $course_id );

		if ( empty( $lesson_quizzes ) ) {
			return;
		}

		?>
		<div class="ea-gaming-lesson-game" data-lesson-id="<?php echo esc_attr( $lesson_id ); ?>">
			<h4><?php esc_html_e( 'Challenge Gate', 'ea-gaming-engine' ); ?></h4>
			<p><?php esc_html_e( 'Complete this challenge to advance!', 'ea-gaming-engine' ); ?></p>
			<button class="ea-gaming-mini-game-btn button" data-lesson-id="<?php echo esc_attr( $lesson_id ); ?>">
				<?php esc_html_e( 'Play Challenge', 'ea-gaming-engine' ); ?>
			</button>
		</div>
		<?php
	}

	/**
	 * Display quiz game option
	 *
	 * @param int $quiz_id Quiz ID.
	 * @param int $user_id User ID.
	 * @return void
	 */
	public function display_quiz_game_option( $quiz_id, $user_id ) {
		if ( ! apply_filters( 'ea_gaming_engine_show_quiz_game', true, $quiz_id, $user_id ) ) {
			return;
		}

		$gate_type  = $this->determine_gate_type( $quiz_id );
		$gate_label = 'boss_battle' === $gate_type ? __( 'Boss Battle', 'ea-gaming-engine' ) : __( 'Challenge Gate', 'ea-gaming-engine' );

		?>
		<div class="ea-gaming-quiz-option" data-quiz-id="<?php echo esc_attr( $quiz_id ); ?>">
			<h4><?php echo esc_html( sprintf( /* translators: %s: game type label (Boss Battle or Challenge Gate) */ __( 'Play as %s', 'ea-gaming-engine' ), $gate_label ) ); ?></h4>
			<p><?php esc_html_e( 'Take this quiz as an interactive game!', 'ea-gaming-engine' ); ?></p>
			<button class="ea-gaming-quiz-game-btn button button-primary" data-quiz-id="<?php echo esc_attr( $quiz_id ); ?>">
				<?php esc_html_e( 'Start Game Mode', 'ea-gaming-engine' ); ?>
			</button>
		</div>
		<?php
	}

	/**
	 * Handle game completion redirect
	 *
	 * @param string $redirect_url Redirect URL.
	 * @param int    $post_id Post ID.
	 * @return string
	 */
	public function handle_game_completion_redirect( $redirect_url, $post_id ) {
		// Check if coming from game mode.
		if ( isset( $_GET['game_mode'] ) && $_GET['game_mode'] === 'true' ) {
			// Redirect to next game level instead.
			$next_step = $this->get_next_game_step( $post_id );
			if ( $next_step ) {
				return add_query_arg( 'game_mode', 'true', get_permalink( $next_step ) );
			}
		}

		return $redirect_url;
	}

	/**
	 * Get next game step
	 *
	 * @param int $current_step_id Current step ID.
	 * @return int|false
	 */
	private function get_next_game_step( $current_step_id ) {
		$course_id = learndash_get_course_id( $current_step_id );
		$progress  = learndash_get_course_progress( get_current_user_id(), $current_step_id );

		if ( ! empty( $progress['next'] ) ) {
			return $progress['next']->ID;
		}

		return false;
	}

	/**
	 * Enhance content with games
	 *
	 * @param string $content Content.
	 * @param object $post Post object.
	 * @return string
	 */
	public function enhance_content_with_games( $content, $post ) {
		// Add game elements to lesson/topic content.
		if ( in_array( $post->post_type, array( 'sfwd-lessons', 'sfwd-topic' ), true ) ) {
			$game_elements = apply_filters( 'ea_gaming_engine_content_elements', array(), $post );

			if ( ! empty( $game_elements ) ) {
				$content .= '<div class="ea-gaming-content-elements">';
				foreach ( $game_elements as $element ) {
					$content .= $element;
				}
				$content .= '</div>';
			}
		}

		return $content;
	}

	/**
	 * Check game completion for focus mode
	 *
	 * @param bool $can_complete Can complete.
	 * @param int  $step_id Step ID.
	 * @param int  $course_id Course ID.
	 * @param int  $user_id User ID.
	 * @return bool
	 */
	public function check_game_completion( $can_complete, $step_id, $course_id, $user_id ) {
		// Check if game requirements are met.
		$game_complete = get_user_meta( $user_id, 'ea_gaming_step_' . $step_id, true );

		if ( 'required' === $game_complete && ! $game_complete ) {
			return false;
		}

		return $can_complete;
	}

	/**
	 * AJAX handler for getting course structure
	 *
	 * @return void
	 */
	public function ajax_get_course_structure() {
		check_ajax_referer( 'ea-gaming-engine', 'nonce' );

		$course_id = intval( $_POST['course_id'] ?? 0 );

		if ( ! $course_id ) {
			wp_send_json_error( __( 'Invalid course ID', 'ea-gaming-engine' ) );
		}

		$structure = $this->get_course_structure( $course_id );
		wp_send_json_success( $structure );
	}

	/**
	 * AJAX handler for getting quiz questions
	 *
	 * @return void
	 */
	public function ajax_get_quiz_questions() {
		check_ajax_referer( 'ea-gaming-engine', 'nonce' );

		$quiz_id = intval( $_POST['quiz_id'] ?? 0 );

		if ( ! $quiz_id ) {
			wp_send_json_error( __( 'Invalid quiz ID', 'ea-gaming-engine' ) );
		}

		$questions = $this->map_quiz_to_game( $quiz_id );

		// Remove correct answers before sending to frontend.
		foreach ( $questions['questions'] as &$question ) {
			foreach ( $question['answers'] as &$answer ) {
				unset( $answer['correct'] );
			}
		}

		wp_send_json_success( $questions );
	}
}