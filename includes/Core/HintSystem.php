<?php
/**
 * AI NPC Hint System for contextual learning support
 *
 * @package EAGamingEngine
 */

namespace EAGamingEngine\Core;

/**
 * HintSystem class
 */
class HintSystem {

	/**
	 * Hint cooldown in seconds
	 *
	 * @var int
	 */
	private $hint_cooldown = 30;

	/**
	 * Maximum hints per question
	 *
	 * @var int
	 */
	private $max_hints_per_question = 3;

	/**
	 * Hint levels (progressive difficulty)
	 *
	 * @var array
	 */
	private $hint_levels = [
		1 => 'subtle',
		2 => 'guided',
		3 => 'obvious',
	];

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->init_hooks();
		$this->load_settings();
	}

	/**
	 * Initialize hooks
	 *
	 * @return void
	 */
	private function init_hooks() {
		add_action( 'wp_ajax_ea_gaming_get_hint', [ $this, 'ajax_get_hint' ] );
		add_action( 'wp_ajax_nopriv_ea_gaming_get_hint', [ $this, 'ajax_get_hint' ] );
		add_filter( 'ea_gaming_question_data', [ $this, 'add_hint_capability' ], 10, 2 );
		add_action( 'ea_gaming_engine_daily_cleanup', [ $this, 'cleanup_old_hint_records' ] );
	}

	/**
	 * Load settings from database
	 *
	 * @return void
	 */
	private function load_settings() {
		$settings = get_option( 'ea_gaming_engine_hint_settings', [] );
		
		$this->hint_cooldown = $settings['cooldown'] ?? 30;
		$this->max_hints_per_question = $settings['max_hints'] ?? 3;
	}

	/**
	 * Get contextual hint for a question
	 *
	 * @param int $question_id Question ID.
	 * @param int $user_id User ID.
	 * @param int $course_id Course ID.
	 * @param int $session_id Game session ID.
	 * @return array|false
	 */
	public function get_hint( $question_id, $user_id, $course_id, $session_id = null ) {
		// Check if hints are enabled
		if ( ! $this->are_hints_enabled( $user_id ) ) {
			return [
				'success' => false,
				'message' => __( 'Hints are not available for your current profile.', 'ea-gaming-engine' ),
			];
		}

		// Check cooldown
		if ( ! $this->check_cooldown( $question_id, $user_id ) ) {
			$remaining = $this->get_remaining_cooldown( $question_id, $user_id );
			return [
				'success' => false,
				'message' => sprintf(
					/* translators: %d: number of seconds to wait */
					__( 'Please wait %d seconds before requesting another hint.', 'ea-gaming-engine' ),
					$remaining
				),
				'cooldown' => $remaining,
			];
		}

		// Check hint limit
		$hint_count = $this->get_hint_count( $question_id, $user_id );
		if ( $hint_count >= $this->max_hints_per_question ) {
			return [
				'success' => false,
				'message' => __( 'Maximum hints reached for this question.', 'ea-gaming-engine' ),
			];
		}

		// Get question data
		$question_gate = new QuestionGate( $session_id );
		$question = $this->get_question_data( $question_id );
		
		if ( ! $question ) {
			return [
				'success' => false,
				'message' => __( 'Question not found or expired.', 'ea-gaming-engine' ),
			];
		}

		// Get lesson content for context
		$lesson_context = $this->get_lesson_context( $question_id, $course_id );

		// Determine hint level
		$hint_level = min( $hint_count + 1, count( $this->hint_levels ) );

		// Generate contextual hint
		$hint_text = $this->generate_contextual_hint( $question, $lesson_context, $hint_level );

		// Record hint usage
		$this->record_hint_usage( $question_id, $user_id, $session_id, $hint_level, $hint_text );

		return [
			'success' => true,
			'hint' => $hint_text,
			'level' => $this->hint_levels[ $hint_level ],
			'remaining_hints' => $this->max_hints_per_question - $hint_level,
			'cooldown' => $this->hint_cooldown,
		];
	}

	/**
	 * Check if hints are enabled for user
	 *
	 * @param int $user_id User ID.
	 * @return bool
	 */
	private function are_hints_enabled( $user_id ) {
		$theme_manager = new ThemeManager();
		$preset_data = $theme_manager->get_preset_data( $user_id );
		
		return $preset_data['hints'] ?? false;
	}

	/**
	 * Check hint cooldown
	 *
	 * @param int $question_id Question ID.
	 * @param int $user_id User ID.
	 * @return bool
	 */
	private function check_cooldown( $question_id, $user_id ) {
		$cache_key = "ea_gaming_hint_cooldown_{$question_id}_{$user_id}";
		$last_hint_time = get_transient( $cache_key );
		
		if ( $last_hint_time === false ) {
			return true;
		}

		return ( time() - $last_hint_time ) >= $this->hint_cooldown;
	}

	/**
	 * Get remaining cooldown time
	 *
	 * @param int $question_id Question ID.
	 * @param int $user_id User ID.
	 * @return int
	 */
	private function get_remaining_cooldown( $question_id, $user_id ) {
		$cache_key = "ea_gaming_hint_cooldown_{$question_id}_{$user_id}";
		$last_hint_time = get_transient( $cache_key );
		
		if ( $last_hint_time === false ) {
			return 0;
		}

		$elapsed = time() - $last_hint_time;
		return max( 0, $this->hint_cooldown - $elapsed );
	}

	/**
	 * Get hint count for question and user
	 *
	 * @param int $question_id Question ID.
	 * @param int $user_id User ID.
	 * @return int
	 */
	private function get_hint_count( $question_id, $user_id ) {
		global $wpdb;
		
		$table = $wpdb->prefix . 'ea_hint_usage';
		
		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} 
				WHERE question_id = %d AND user_id = %d 
				AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)",
				$question_id,
				$user_id
			)
		);

		return (int) $count;
	}

	/**
	 * Get question data from cache or database
	 *
	 * @param int $question_id Question ID.
	 * @return array|false
	 */
	private function get_question_data( $question_id ) {
		// Try to get from cache first
		$cache_key = 'ea_gaming_question_' . $question_id . '_' . get_current_user_id();
		$question = get_transient( $cache_key );
		
		if ( $question !== false ) {
			return $question;
		}

		// Get from database
		$question_post = get_post( $question_id );
		if ( ! $question_post ) {
			return false;
		}

		$question_pro_id = get_post_meta( $question_id, 'question_pro_id', true );
		if ( empty( $question_pro_id ) ) {
			return false;
		}

		$question_mapper = new \WpProQuiz_Model_QuestionMapper();
		$question_model = $question_mapper->fetch( $question_pro_id );
		
		if ( ! $question_model ) {
			return false;
		}

		return [
			'id' => $question_id,
			'title' => $question_model->getTitle(),
			'question' => $question_model->getQuestion(),
			'type' => $this->get_question_type( $question_model ),
			'answers' => $this->format_answers( $question_model->getAnswerData() ),
			'category' => $question_model->getCategoryName(),
		];
	}

	/**
	 * Get lesson context for hint generation
	 *
	 * @param int $question_id Question ID.
	 * @param int $course_id Course ID.
	 * @return array
	 */
	private function get_lesson_context( $question_id, $course_id ) {
		$context = [
			'content' => '',
			'keywords' => [],
			'concepts' => [],
		];

		// Find associated lesson
		$lesson_id = $this->find_associated_lesson( $question_id, $course_id );
		
		if ( $lesson_id ) {
			$lesson = get_post( $lesson_id );
			if ( $lesson ) {
				$context['content'] = wp_strip_all_tags( $lesson->post_content );
				$context['title'] = $lesson->post_title;
				$context['keywords'] = $this->extract_keywords( $context['content'] );
				$context['concepts'] = $this->extract_concepts( $context['content'] );
			}
		}

		// Also check course content
		$course = get_post( $course_id );
		if ( $course ) {
			$course_content = wp_strip_all_tags( $course->post_content );
			$context['course_keywords'] = $this->extract_keywords( $course_content );
		}

		return $context;
	}

	/**
	 * Find lesson associated with question
	 *
	 * @param int $question_id Question ID.
	 * @param int $course_id Course ID.
	 * @return int|false
	 */
	private function find_associated_lesson( $question_id, $course_id ) {
		// Get quiz ID from question
		$quiz_id = get_post_meta( $question_id, 'quiz_id', true );
		
		if ( ! $quiz_id ) {
			// Try to find from question mapping
			global $wpdb;
			$quiz_id = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT quiz_id FROM {$wpdb->prefix}ea_question_attempts 
					WHERE question_id = %d 
					ORDER BY created_at DESC 
					LIMIT 1",
					$question_id
				)
			);
		}

		if ( ! $quiz_id ) {
			return false;
		}

		// Find lesson that contains this quiz
		$lessons = learndash_get_course_lessons_list( $course_id );
		
		foreach ( $lessons as $lesson ) {
			$lesson_quizzes = learndash_get_lesson_quiz_list( $lesson['post']->ID, get_current_user_id(), $course_id );
			
			foreach ( $lesson_quizzes as $lesson_quiz ) {
				if ( $lesson_quiz['post']->ID == $quiz_id ) {
					return $lesson['post']->ID;
				}
			}
		}

		return false;
	}

	/**
	 * Generate contextual hint based on question and lesson content
	 *
	 * @param array $question Question data.
	 * @param array $lesson_context Lesson context.
	 * @param int   $hint_level Hint level (1-3).
	 * @return string
	 */
	private function generate_contextual_hint( $question, $lesson_context, $hint_level ) {
		$hints = [];

		// Level 1: Subtle hints
		if ( $hint_level === 1 ) {
			$hints = $this->generate_subtle_hints( $question, $lesson_context );
		}
		// Level 2: Guided hints
		elseif ( $hint_level === 2 ) {
			$hints = $this->generate_guided_hints( $question, $lesson_context );
		}
		// Level 3: Obvious hints
		else {
			$hints = $this->generate_obvious_hints( $question, $lesson_context );
		}

		// Return random hint from level or fallback
		if ( ! empty( $hints ) ) {
			return $hints[ array_rand( $hints ) ];
		}

		// Fallback generic hints
		return $this->get_fallback_hint( $hint_level );
	}

	/**
	 * Generate subtle hints (level 1)
	 *
	 * @param array $question Question data.
	 * @param array $lesson_context Lesson context.
	 * @return array
	 */
	private function generate_subtle_hints( $question, $lesson_context ) {
		$hints = [];

		// Context-based hints
		if ( ! empty( $lesson_context['title'] ) ) {
			$hints[] = sprintf(
				/* translators: %s: lesson title */
				__( 'Think about what you learned in "%s".', 'ea-gaming-engine' ),
				$lesson_context['title']
			);
		}

		// Keyword-based hints
		if ( ! empty( $lesson_context['keywords'] ) ) {
			$keyword = $lesson_context['keywords'][0];
			if ( stripos( $question['question'], $keyword ) !== false ) {
				$hints[] = sprintf(
					/* translators: %s: lesson keyword/concept */
					__( 'Focus on the concept of "%s" from the lesson.', 'ea-gaming-engine' ),
					$keyword
				);
			}
		}

		// Question type specific hints
		switch ( $question['type'] ) {
			case 'single_choice':
				$hints[] = __( 'Only one answer is correct. Consider each option carefully.', 'ea-gaming-engine' );
				break;
			case 'multiple_choice':
				$hints[] = __( 'Multiple answers may be correct. Think about all possibilities.', 'ea-gaming-engine' );
				break;
			case 'free_text':
				$hints[] = __( 'Think about the key terms you studied in the lesson.', 'ea-gaming-engine' );
				break;
		}

		return $hints;
	}

	/**
	 * Generate guided hints (level 2)
	 *
	 * @param array $question Question data.
	 * @param array $lesson_context Lesson context.
	 * @return array
	 */
	private function generate_guided_hints( $question, $lesson_context ) {
		$hints = [];

		// Extract question keywords and match with lesson content
		$question_keywords = $this->extract_keywords( $question['question'] );
		
		foreach ( $question_keywords as $keyword ) {
			if ( ! empty( $lesson_context['content'] ) && stripos( $lesson_context['content'], $keyword ) !== false ) {
				// Extract sentence containing the keyword from lesson
				$sentences = preg_split( '/[.!?]+/', $lesson_context['content'] );
				foreach ( $sentences as $sentence ) {
					if ( stripos( $sentence, $keyword ) !== false ) {
						$hints[] = sprintf(
							/* translators: %s: relevant sentence from lesson content */
							__( 'Remember: "%s"', 'ea-gaming-engine' ),
							trim( $sentence )
						);
						break;
					}
				}
			}
		}

		// Category-based hints
		if ( ! empty( $question['category'] ) ) {
			$hints[] = sprintf(
				/* translators: %s: question category/topic */
				__( 'This question is about %s. What did you learn about this topic?', 'ea-gaming-engine' ),
				$question['category']
			);
		}

		// Answer elimination hints for multiple choice
		if ( in_array( $question['type'], [ 'single_choice', 'multiple_choice' ] ) && ! empty( $question['answers'] ) ) {
			$hints[] = __( 'Try to eliminate answers that seem obviously wrong first.', 'ea-gaming-engine' );
			
			if ( count( $question['answers'] ) > 2 ) {
				$hints[] = sprintf(
					/* translators: %d: number of answer options */
					__( 'Look for clues in the question that might point to specific answers among the %d options.', 'ea-gaming-engine' ),
					count( $question['answers'] )
				);
			}
		}

		return $hints;
	}

	/**
	 * Generate obvious hints (level 3)
	 *
	 * @param array $question Question data.
	 * @param array $lesson_context Lesson context.
	 * @return array
	 */
	private function generate_obvious_hints( $question, $lesson_context ) {
		$hints = [];

		// Direct concept matching
		$concepts = $lesson_context['concepts'] ?? [];
		$question_text = strtolower( $question['question'] );
		
		foreach ( $concepts as $concept ) {
			if ( stripos( $question_text, strtolower( $concept ) ) !== false ) {
				$hints[] = sprintf(
					/* translators: %s: key concept from the question */
					__( 'The answer is directly related to %s. Look for the option that best represents this concept.', 'ea-gaming-engine' ),
					$concept
				);
			}
		}

		// Process of elimination for multiple choice
		if ( in_array( $question['type'], [ 'single_choice', 'multiple_choice' ] ) ) {
			$hints[] = __( 'Read each answer choice and ask yourself: "Does this make sense based on what I learned?"', 'ea-gaming-engine' );
			$hints[] = __( 'The correct answer should directly relate to the main concept discussed in the lesson.', 'ea-gaming-engine' );
		}

		// Fill in the blank hints
		if ( $question['type'] === 'fill_blank' ) {
			$keywords = $lesson_context['keywords'] ?? [];
			if ( ! empty( $keywords ) ) {
				$hints[] = sprintf(
					/* translators: %s: comma-separated list of important keywords */
					__( 'Think about important terms like: %s', 'ea-gaming-engine' ),
					implode( ', ', array_slice( $keywords, 0, 3 ) )
				);
			}
		}

		return $hints;
	}

	/**
	 * Get fallback hint when context is insufficient
	 *
	 * @param int $hint_level Hint level.
	 * @return string
	 */
	private function get_fallback_hint( $hint_level ) {
		$fallbacks = [
			1 => [
				__( 'Take a moment to think about what you learned.', 'ea-gaming-engine' ),
				__( 'Consider the key concepts from the lesson.', 'ea-gaming-engine' ),
				__( 'Look for clues in the question wording.', 'ea-gaming-engine' ),
			],
			2 => [
				__( 'Break down the question into smaller parts.', 'ea-gaming-engine' ),
				__( 'Think about the main topic you studied recently.', 'ea-gaming-engine' ),
				__( 'Which answer choice seems most logical?', 'ea-gaming-engine' ),
			],
			3 => [
				__( 'Focus on the key terms in the question and match them to what you learned.', 'ea-gaming-engine' ),
				__( 'Eliminate answers that clearly don\'t fit, then choose from the remaining options.', 'ea-gaming-engine' ),
				__( 'The correct answer should be something specifically covered in your recent lesson.', 'ea-gaming-engine' ),
			],
		];

		$level_hints = $fallbacks[ $hint_level ] ?? $fallbacks[1];
		return $level_hints[ array_rand( $level_hints ) ];
	}

	/**
	 * Extract keywords from text
	 *
	 * @param string $text Text to analyze.
	 * @return array
	 */
	private function extract_keywords( $text ) {
		// Remove common stop words
		$stop_words = [
			'the', 'is', 'at', 'which', 'on', 'a', 'an', 'as', 'are', 'was', 'were', 'been', 'be',
			'have', 'has', 'had', 'what', 'when', 'where', 'who', 'why', 'how', 'this', 'that',
			'these', 'those', 'i', 'you', 'he', 'she', 'it', 'we', 'they', 'and', 'or', 'but',
			'if', 'then', 'else', 'when', 'while', 'for', 'to', 'of', 'in', 'by', 'with', 'from'
		];

		$words = str_word_count( strtolower( $text ), 1 );
		$keywords = array_diff( $words, $stop_words );

		// Filter by length (meaningful words are usually 3+ characters)
		$keywords = array_filter( $keywords, function( $word ) {
			return strlen( $word ) >= 3;
		});

		// Sort by length (longer words are often more meaningful)
		usort( $keywords, function( $a, $b ) {
			return strlen( $b ) - strlen( $a );
		});

		return array_slice( array_unique( $keywords ), 0, 10 );
	}

	/**
	 * Extract key concepts from lesson content
	 *
	 * @param string $content Lesson content.
	 * @return array
	 */
	private function extract_concepts( $content ) {
		$concepts = [];

		// Look for text in quotes (often definitions or key concepts)
		if ( preg_match_all( '/"([^"]+)"/', $content, $matches ) ) {
			$concepts = array_merge( $concepts, $matches[1] );
		}

		// Look for bold or emphasized text (WordPress often uses ** for emphasis)
		if ( preg_match_all( '/\*\*([^*]+)\*\*/', $content, $matches ) ) {
			$concepts = array_merge( $concepts, $matches[1] );
		}

		// Look for text in headings (h1-h6)
		if ( preg_match_all( '/<h[1-6][^>]*>([^<]+)<\/h[1-6]>/', $content, $matches ) ) {
			$concepts = array_merge( $concepts, $matches[1] );
		}

		// Look for capitalized phrases (often proper nouns or important concepts)
		if ( preg_match_all( '/\b[A-Z][a-z]+ [A-Z][a-z]+\b/', $content, $matches ) ) {
			$concepts = array_merge( $concepts, $matches[0] );
		}

		return array_unique( array_slice( $concepts, 0, 5 ) );
	}

	/**
	 * Get question type from model
	 *
	 * @param object $question_model Question model.
	 * @return string
	 */
	private function get_question_type( $question_model ) {
		$type_mapping = [
			'single'        => 'single_choice',
			'multiple'      => 'multiple_choice',
			'free_answer'   => 'free_text',
			'sort_answer'   => 'sort',
			'matrix_sort'   => 'matrix',
			'cloze_answer'  => 'fill_blank',
			'assessment'    => 'assessment',
			'essay'         => 'essay',
		];

		$type = $question_model->getAnswerType();
		return $type_mapping[ $type ] ?? 'single_choice';
	}

	/**
	 * Format answers from model data
	 *
	 * @param array $answer_data Answer data from model.
	 * @return array
	 */
	private function format_answers( $answer_data ) {
		$answers = [];
		
		foreach ( $answer_data as $index => $answer ) {
			$answers[] = [
				'id' => $index,
				'text' => $answer->getAnswer(),
				'html' => $answer->isHtml(),
			];
		}

		return $answers;
	}

	/**
	 * Record hint usage
	 *
	 * @param int    $question_id Question ID.
	 * @param int    $user_id User ID.
	 * @param int    $session_id Session ID.
	 * @param int    $hint_level Hint level.
	 * @param string $hint_text Hint text.
	 * @return void
	 */
	private function record_hint_usage( $question_id, $user_id, $session_id, $hint_level, $hint_text ) {
		global $wpdb;

		$table = $wpdb->prefix . 'ea_hint_usage';

		$data = [
			'question_id' => $question_id,
			'user_id' => $user_id,
			'session_id' => $session_id,
			'hint_level' => $hint_level,
			'hint_text' => $hint_text,
			'created_at' => current_time( 'mysql' ),
		];

		$wpdb->insert( $table, $data );

		// Set cooldown
		$cache_key = "ea_gaming_hint_cooldown_{$question_id}_{$user_id}";
		set_transient( $cache_key, time(), $this->hint_cooldown );
	}

	/**
	 * Add hint capability to question data
	 *
	 * @param array $question_data Question data.
	 * @param int   $question_id Question ID.
	 * @return array
	 */
	public function add_hint_capability( $question_data, $question_id ) {
		$user_id = get_current_user_id();
		
		if ( ! $user_id ) {
			return $question_data;
		}

		$question_data['hints_available'] = $this->are_hints_enabled( $user_id );
		$question_data['hints_remaining'] = $this->max_hints_per_question - $this->get_hint_count( $question_id, $user_id );
		$question_data['hint_cooldown'] = $this->get_remaining_cooldown( $question_id, $user_id );

		return $question_data;
	}

	/**
	 * AJAX handler for getting hints
	 *
	 * @return void
	 */
	public function ajax_get_hint() {
		check_ajax_referer( 'ea-gaming-engine', 'nonce' );

		$question_id = intval( $_POST['question_id'] ?? 0 );
		$course_id = intval( $_POST['course_id'] ?? 0 );
		$session_id = intval( $_POST['session_id'] ?? 0 );
		$user_id = get_current_user_id();

		if ( ! $question_id || ! $user_id ) {
			wp_send_json_error( __( 'Invalid parameters', 'ea-gaming-engine' ) );
		}

		$result = $this->get_hint( $question_id, $user_id, $course_id, $session_id );

		if ( $result['success'] ) {
			wp_send_json_success( $result );
		} else {
			wp_send_json_error( $result['message'], $result );
		}
	}

	/**
	 * Cleanup old hint records
	 *
	 * @return void
	 */
	public function cleanup_old_hint_records() {
		global $wpdb;

		$table = $wpdb->prefix . 'ea_hint_usage';

		// Delete records older than 7 days
		$wpdb->query(
			"DELETE FROM {$table} WHERE created_at < DATE_SUB(NOW(), INTERVAL 7 DAY)"
		);
	}

	/**
	 * Create hint usage table
	 *
	 * @return void
	 */
	public static function create_hint_table() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();
		$table = $wpdb->prefix . 'ea_hint_usage';

		$sql = "CREATE TABLE IF NOT EXISTS $table (
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
		dbDelta( $sql );
	}
}