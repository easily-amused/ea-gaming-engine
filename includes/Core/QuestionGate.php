<?php
/**
 * Question Gate service for server-side validation
 *
 * @package EAGamingEngine
 */

namespace EAGamingEngine\Core;

/**
 * QuestionGate class
 */
class QuestionGate {

	/**
	 * Session ID
	 *
	 * @var int
	 */
	private $session_id;

	/**
	 * Current question cache
	 *
	 * @var array
	 */
	private $current_question = null;

	/**
	 * Constructor
	 *
	 * @param int $session_id Game session ID.
	 */
	public function __construct( $session_id = null ) {
		$this->session_id = $session_id;
		$this->init_hooks();
	}

	/**
	 * Initialize hooks
	 *
	 * @return void
	 */
	private function init_hooks() {
		add_action( 'wp_ajax_ea_gaming_get_question', [ $this, 'ajax_get_question' ] );
		add_action( 'wp_ajax_ea_gaming_validate_answer', [ $this, 'ajax_validate_answer' ] );
		add_action( 'wp_ajax_ea_gaming_get_hint', [ $this, 'ajax_get_hint' ] );
	}

	/**
	 * Get a question from quiz
	 *
	 * @param int   $quiz_id Quiz ID.
	 * @param array $options Options.
	 * @return array|false
	 */
	public function get_question( $quiz_id, $options = [] ) {
		// Check if LearnDash is available
		if ( ! function_exists( 'learndash_get_quiz_questions' ) ) {
			return false;
		}

		// Get quiz questions
		$questions = learndash_get_quiz_questions( $quiz_id );
		
		if ( empty( $questions ) ) {
			return false;
		}

		// Filter out already answered questions if needed
		if ( ! empty( $options['exclude'] ) ) {
			$questions = array_filter(
				$questions,
				function ( $q ) use ( $options ) {
					return ! in_array( $q['question_id'], $options['exclude'], true );
				}
			);
		}

		// Get random question or specific one
		if ( ! empty( $options['question_id'] ) ) {
			$question = $this->get_specific_question( $questions, $options['question_id'] );
		} else {
			$question = $this->get_random_question( $questions, $options );
		}

		if ( ! $question ) {
			return false;
		}

		// Format question for frontend
		$formatted = $this->format_question( $question );

		// Cache the question with answer for validation
		$this->cache_question( $formatted );

		// Remove correct answer from response
		unset( $formatted['correct_answer'] );
		unset( $formatted['correct_answers'] );

		return $formatted;
	}

	/**
	 * Get random question
	 *
	 * @param array $questions Questions array.
	 * @param array $options Options.
	 * @return array|null
	 */
	private function get_random_question( $questions, $options = [] ) {
		if ( empty( $questions ) ) {
			return null;
		}

		// Apply difficulty filter if set
		if ( ! empty( $options['difficulty'] ) ) {
			$questions = array_filter(
				$questions,
				function ( $q ) use ( $options ) {
					$difficulty = get_post_meta( $q['question_id'], '_difficulty_level', true );
					return $difficulty === $options['difficulty'];
				}
			);
		}

		// Get random question
		$random_key = array_rand( $questions );
		return $questions[ $random_key ];
	}

	/**
	 * Get specific question
	 *
	 * @param array $questions Questions array.
	 * @param int   $question_id Question ID.
	 * @return array|null
	 */
	private function get_specific_question( $questions, $question_id ) {
		foreach ( $questions as $question ) {
			if ( $question['question_id'] == $question_id ) {
				return $question;
			}
		}
		return null;
	}

	/**
	 * Format question for frontend
	 *
	 * @param array $question Raw question data.
	 * @return array
	 */
	private function format_question( $question ) {
		$question_post = get_post( $question['question_id'] );
		
		if ( ! $question_post ) {
			return [];
		}

		// Get question meta
		$question_pro_id = get_post_meta( $question['question_id'], 'question_pro_id', true );
		
		// Get the actual question data from LearnDash
		$question_mapper = new \WpProQuiz_Model_QuestionMapper();
		$question_model  = $question_mapper->fetch( $question_pro_id );

		if ( ! $question_model ) {
			return [];
		}

		$formatted = [
			'id'       => $question['question_id'],
			'quiz_id'  => $question['quiz_id'] ?? 0,
			'title'    => $question_model->getTitle(),
			'question' => $question_model->getQuestion(),
			'type'     => $this->get_question_type( $question_model ),
			'points'   => $question_model->getPoints(),
			'answers'  => [],
		];

		// Format answers based on question type
		$answer_data = $question_model->getAnswerData();
		
		foreach ( $answer_data as $index => $answer ) {
			$formatted['answers'][] = [
				'id'     => $index,
				'text'   => $answer->getAnswer(),
				'html'   => $answer->isHtml(),
			];
		}

		// Shuffle answers if needed
		if ( $question_model->isAnswerPointsActivated() ) {
			shuffle( $formatted['answers'] );
		}

		// Store correct answer(s) for validation (will be removed before sending)
		$formatted['correct_answer'] = $this->get_correct_answers( $question_model );

		return $formatted;
	}

	/**
	 * Get question type
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
	 * Get correct answers
	 *
	 * @param object $question_model Question model.
	 * @return array
	 */
	private function get_correct_answers( $question_model ) {
		$correct = [];
		$answer_data = $question_model->getAnswerData();

		foreach ( $answer_data as $index => $answer ) {
			if ( $answer->isCorrect() ) {
				$correct[] = $index;
			}
		}

		return $correct;
	}

	/**
	 * Cache question for validation
	 *
	 * @param array $question Question data.
	 * @return void
	 */
	private function cache_question( $question ) {
		$cache_key = 'ea_gaming_question_' . $question['id'] . '_' . get_current_user_id();
		set_transient( $cache_key, $question, 5 * MINUTE_IN_SECONDS );
		$this->current_question = $question;
	}

	/**
	 * Get cached question
	 *
	 * @param int $question_id Question ID.
	 * @return array|false
	 */
	private function get_cached_question( $question_id ) {
		if ( $this->current_question && $this->current_question['id'] == $question_id ) {
			return $this->current_question;
		}

		$cache_key = 'ea_gaming_question_' . $question_id . '_' . get_current_user_id();
		return get_transient( $cache_key );
	}

	/**
	 * Validate answer
	 *
	 * @param int   $question_id Question ID.
	 * @param mixed $answer User answer.
	 * @return array
	 */
	public function validate_answer( $question_id, $answer ) {
		$question = $this->get_cached_question( $question_id );
		
		if ( ! $question ) {
			return [
				'valid'   => false,
				'correct' => false,
				'message' => __( 'Question expired or not found', 'ea-gaming-engine' ),
			];
		}

		// Check answer based on question type
		$is_correct = false;
		
		switch ( $question['type'] ) {
			case 'single_choice':
				$is_correct = in_array( $answer, $question['correct_answer'], true );
				break;
				
			case 'multiple_choice':
				if ( is_array( $answer ) ) {
					sort( $answer );
					$correct = $question['correct_answer'];
					sort( $correct );
					$is_correct = $answer === $correct;
				}
				break;
				
			case 'free_text':
				// For free text, we need more complex validation
				$is_correct = $this->validate_free_text( $answer, $question );
				break;
				
			default:
				$is_correct = false;
		}

		// Record attempt
		$this->record_attempt( $question_id, $answer, $is_correct );

		// Clear cached question
		$cache_key = 'ea_gaming_question_' . $question_id . '_' . get_current_user_id();
		delete_transient( $cache_key );

		return [
			'valid'   => true,
			'correct' => $is_correct,
			'points'  => $is_correct ? $question['points'] : 0,
			'message' => $is_correct ? __( 'Correct!', 'ea-gaming-engine' ) : __( 'Incorrect', 'ea-gaming-engine' ),
		];
	}

	/**
	 * Validate free text answer
	 *
	 * @param string $answer User answer.
	 * @param array  $question Question data.
	 * @return bool
	 */
	private function validate_free_text( $answer, $question ) {
		// This would need to be implemented based on LearnDash's free text validation
		// For now, simple string comparison
		$correct_answers = $question['correct_answer'];
		
		foreach ( $correct_answers as $correct ) {
			if ( strcasecmp( trim( $answer ), trim( $correct ) ) === 0 ) {
				return true;
			}
		}
		
		return false;
	}

	/**
	 * Record question attempt
	 *
	 * @param int   $question_id Question ID.
	 * @param mixed $answer User answer.
	 * @param bool  $is_correct Whether answer is correct.
	 * @return void
	 */
	private function record_attempt( $question_id, $answer, $is_correct ) {
		if ( ! $this->session_id ) {
			return;
		}

		global $wpdb;
		$table = $wpdb->prefix . 'ea_question_attempts';

		$data = [
			'session_id'   => $this->session_id,
			'question_id'  => $question_id,
			'quiz_id'      => $this->current_question['quiz_id'] ?? 0,
			'user_answer'  => maybe_serialize( $answer ),
			'is_correct'   => $is_correct ? 1 : 0,
			'points_earned' => $is_correct ? ( $this->current_question['points'] ?? 0 ) : 0,
		];

		$wpdb->insert( $table, $data );
	}

	/**
	 * Generate AI hint for question
	 *
	 * @param int $question_id Question ID.
	 * @param int $lesson_id Associated lesson ID.
	 * @return string
	 */
	public function generate_hint( $question_id, $lesson_id = null ) {
		$question = $this->get_cached_question( $question_id );
		
		if ( ! $question ) {
			return __( 'No hints available', 'ea-gaming-engine' );
		}

		// Get lesson content if available
		$lesson_content = '';
		if ( $lesson_id ) {
			$lesson = get_post( $lesson_id );
			if ( $lesson ) {
				$lesson_content = wp_strip_all_tags( $lesson->post_content );
			}
		}

		// Generate contextual hint
		$hint = $this->generate_contextual_hint( $question, $lesson_content );

		return apply_filters( 'ea_gaming_question_hint', $hint, $question_id, $lesson_id );
	}

	/**
	 * Generate contextual hint based on question and lesson
	 *
	 * @param array  $question Question data.
	 * @param string $lesson_content Lesson content.
	 * @return string
	 */
	private function generate_contextual_hint( $question, $lesson_content ) {
		// This is a simplified version - in production, you might use AI
		$hints = [
			__( 'Think about what you learned in the lesson.', 'ea-gaming-engine' ),
			__( 'Consider all the options carefully.', 'ea-gaming-engine' ),
			__( 'The answer is in the material you studied.', 'ea-gaming-engine' ),
			__( 'Review the key concepts from the lesson.', 'ea-gaming-engine' ),
		];

		// If we have lesson content, try to find relevant hint
		if ( $lesson_content ) {
			// Extract keywords from question
			$keywords = $this->extract_keywords( $question['question'] );
			
			// Search for keywords in lesson content
			foreach ( $keywords as $keyword ) {
				if ( stripos( $lesson_content, $keyword ) !== false ) {
					return sprintf(
						__( 'Remember what you learned about "%s" in the lesson.', 'ea-gaming-engine' ),
						$keyword
					);
				}
			}
		}

		// Return random generic hint
		return $hints[ array_rand( $hints ) ];
	}

	/**
	 * Extract keywords from text
	 *
	 * @param string $text Text to extract from.
	 * @return array
	 */
	private function extract_keywords( $text ) {
		// Remove common words and extract meaningful keywords
		$stop_words = [ 'the', 'is', 'at', 'which', 'on', 'a', 'an', 'as', 'are', 'was', 'were', 'been', 'be', 'have', 'has', 'had', 'what', 'when', 'where', 'who', 'why', 'how' ];
		
		$words = str_word_count( strtolower( $text ), 1 );
		$keywords = array_diff( $words, $stop_words );
		
		// Return top 3 longest words (usually more meaningful)
		usort(
			$keywords,
			function ( $a, $b ) {
				return strlen( $b ) - strlen( $a );
			}
		);
		
		return array_slice( $keywords, 0, 3 );
	}

	/**
	 * AJAX handler for getting question
	 *
	 * @return void
	 */
	public function ajax_get_question() {
		check_ajax_referer( 'ea-gaming-engine', 'nonce' );

		$quiz_id = intval( $_POST['quiz_id'] ?? 0 );
		
		if ( ! $quiz_id ) {
			wp_send_json_error( __( 'Invalid quiz ID', 'ea-gaming-engine' ) );
		}

		$options = [
			'difficulty' => sanitize_text_field( $_POST['difficulty'] ?? '' ),
			'exclude'    => array_map( 'intval', $_POST['exclude'] ?? [] ),
		];

		$question = $this->get_question( $quiz_id, $options );

		if ( $question ) {
			wp_send_json_success( $question );
		} else {
			wp_send_json_error( __( 'No questions available', 'ea-gaming-engine' ) );
		}
	}

	/**
	 * AJAX handler for validating answer
	 *
	 * @return void
	 */
	public function ajax_validate_answer() {
		check_ajax_referer( 'ea-gaming-engine', 'nonce' );

		$question_id = intval( $_POST['question_id'] ?? 0 );
		$answer      = $_POST['answer'] ?? null;

		if ( ! $question_id || $answer === null ) {
			wp_send_json_error( __( 'Invalid parameters', 'ea-gaming-engine' ) );
		}

		// Set session ID if provided
		if ( ! empty( $_POST['session_id'] ) ) {
			$this->session_id = intval( $_POST['session_id'] );
		}

		$result = $this->validate_answer( $question_id, $answer );

		if ( $result['valid'] ) {
			wp_send_json_success( $result );
		} else {
			wp_send_json_error( $result['message'] );
		}
	}

	/**
	 * AJAX handler for getting hint
	 *
	 * @return void
	 */
	public function ajax_get_hint() {
		check_ajax_referer( 'ea-gaming-engine', 'nonce' );

		$question_id = intval( $_POST['question_id'] ?? 0 );
		$lesson_id   = intval( $_POST['lesson_id'] ?? 0 );

		if ( ! $question_id ) {
			wp_send_json_error( __( 'Invalid question ID', 'ea-gaming-engine' ) );
		}

		$hint = $this->generate_hint( $question_id, $lesson_id );

		wp_send_json_success(
			[
				'hint' => $hint,
			]
		);
	}
}