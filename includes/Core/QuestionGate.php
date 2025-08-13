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
		add_action( 'wp_ajax_ea_gaming_get_question', array( $this, 'ajax_get_question' ) );
		add_action( 'wp_ajax_ea_gaming_validate_answer', array( $this, 'ajax_validate_answer' ) );
	}

	/**
	 * Get a question from quiz
	 *
	 * @param int   $quiz_id Quiz ID.
	 * @param array $options Options.
	 * @return array|false
	 */
	public function get_question( $quiz_id, $options = array() ) {
		if ( ! function_exists( 'learndash_get_quiz_questions' ) ) {
			return false;
		}

		$ld_questions_map = learndash_get_quiz_questions( $quiz_id );
		if ( empty( $ld_questions_map ) || ! is_array( $ld_questions_map ) ) {
			return false;
		}

		// Extract IDs
		$question_ids = array_keys( $ld_questions_map );

		// Exclude answered
		if ( ! empty( $options['exclude'] ) && is_array( $options['exclude'] ) ) {
			$exclude      = array_map( 'intval', $options['exclude'] );
			$question_ids = array_values( array_diff( $question_ids, $exclude ) );
		}

		// Difficulty filter (optional, uses post meta _difficulty_level)
		if ( ! empty( $options['difficulty'] ) ) {
			$question_ids = array_values(
				array_filter(
					$question_ids,
					function ( $qid ) use ( $options ) {
						$difficulty = get_post_meta( $qid, '_difficulty_level', true );
						return $difficulty === $options['difficulty'];
					}
				)
			);
		}

		if ( empty( $question_ids ) ) {
			return false;
		}

		// Pick a question
		$chosen_id = ! empty( $options['question_id'] ) && in_array( (int) $options['question_id'], $question_ids, true )
			? (int) $options['question_id']
			: $question_ids[ array_rand( $question_ids ) ];

		// Format for frontend
		$formatted = $this->format_question( $chosen_id, (int) $quiz_id );
		if ( empty( $formatted ) ) {
			return false;
		}

		// Cache full data (with correct answers) for validation
		$this->cache_question( $formatted );

		// Remove correctness details before sending to client
		unset( $formatted['correct_answer'], $formatted['correct_answers'] );

		// Add hint capability information
		$formatted = apply_filters( 'ea_gaming_question_data', $formatted, $chosen_id );

		return $formatted;
	}

	/**
	 * Format question for frontend by Post ID
	 *
	 * @param int $question_id Question Post ID.
	 * @param int $quiz_id Quiz ID.
	 * @return array
	 */
	private function format_question( $question_id, $quiz_id ) {
		$question_post = get_post( $question_id );

		if ( ! $question_post ) {
			return array();
		}

		// Load ProQuiz question model
		$question_pro_id = get_post_meta( $question_id, 'question_pro_id', true );
		$question_pro_id = (int) $question_pro_id;

		if ( empty( $question_pro_id ) ) {
			return array();
		}

		$question_mapper = new \WpProQuiz_Model_QuestionMapper();
		$question_model  = $question_mapper->fetch( $question_pro_id );

		if ( ! $question_model ) {
			return array();
		}

		$answer_data = $question_model->getAnswerData();
		$answers     = array();

		// Build answers list with stable IDs (index from model)
		foreach ( $answer_data as $index => $answer ) {
			$answers[] = array(
				'id'   => $index,
				'text' => $answer->getAnswer(),
				'html' => $answer->isHtml(),
			);
		}

		// Shuffle answers if ProQuiz indicates random order
		if ( method_exists( $question_model, 'isAnswerRandom' ) && $question_model->isAnswerRandom() ) {
			shuffle( $answers );
		}

		$formatted = array(
			'id'       => $question_id,
			'quiz_id'  => $quiz_id,
			'title'    => $question_model->getTitle(),
			'question' => $question_model->getQuestion(),
			'type'     => $this->get_question_type( $question_model ),
			'points'   => (int) $question_model->getPoints(),
			'answers'  => $answers,
		);

		// For validation server-side
		$formatted['correct_answer'] = $this->get_correct_answers( $question_model );

		return $formatted;
	}

	/**
	 * Get random question
	 *
	 * @param array $questions Questions array.
	 * @param array $options Options.
	 * @return array|null
	 */
	private function get_random_question( $questions, $options = array() ) {
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
	 * Get question type
	 *
	 * @param object $question_model Question model.
	 * @return string
	 */
	private function get_question_type( $question_model ) {
		$type_mapping = array(
			'single'       => 'single_choice',
			'multiple'     => 'multiple_choice',
			'free_answer'  => 'free_text',
			'sort_answer'  => 'sort',
			'matrix_sort'  => 'matrix',
			'cloze_answer' => 'fill_blank',
			'assessment'   => 'assessment',
			'essay'        => 'essay',
		);

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
		$correct     = array();
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
			return array(
				'valid'   => false,
				'correct' => false,
				'message' => __( 'Question expired or not found', 'ea-gaming-engine' ),
			);
		}

		$is_correct = false;

		switch ( $question['type'] ) {
			case 'single_choice':
				// Expect single answer ID (number or string numeric)
				$is_correct = in_array( (int) $answer, array_map( 'intval', (array) $question['correct_answer'] ), true );
				break;

			case 'multiple_choice':
				// Expect array of IDs
				if ( is_array( $answer ) ) {
					$given   = array_map( 'intval', $answer );
					$correct = array_map( 'intval', (array) $question['correct_answer'] );
					sort( $given );
					sort( $correct );
					$is_correct = ( $given === $correct );
				}
				break;

			case 'free_text':
			case 'fill_blank':
				$is_correct = $this->validate_free_text( $answer, $question );
				break;

			default:
				$is_correct = false;
		}

		// Record attempt
		$this->record_attempt( $question['id'], $answer, $is_correct, $question['quiz_id'], $question['points'] );

		// Clear cached question
		$cache_key = 'ea_gaming_question_' . $question_id . '_' . get_current_user_id();
		delete_transient( $cache_key );

		return array(
			'valid'   => true,
			'correct' => $is_correct,
			'points'  => $is_correct ? (int) $question['points'] : 0,
			'message' => $is_correct ? __( 'Correct!', 'ea-gaming-engine' ) : __( 'Incorrect', 'ea-gaming-engine' ),
		);
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

		$data = array(
			'session_id'    => $this->session_id,
			'question_id'   => $question_id,
			'quiz_id'       => $this->current_question['quiz_id'] ?? 0,
			'user_answer'   => maybe_serialize( $answer ),
			'is_correct'    => $is_correct ? 1 : 0,
			'points_earned' => $is_correct ? ( $this->current_question['points'] ?? 0 ) : 0,
		);

		$wpdb->insert( $table, $data );
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

		$options = array(
			'difficulty' => sanitize_text_field( $_POST['difficulty'] ?? '' ),
			'exclude'    => array_map( 'intval', $_POST['exclude'] ?? array() ),
		);

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
}
