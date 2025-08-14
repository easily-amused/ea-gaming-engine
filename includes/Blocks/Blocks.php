<?php
/**
 * Gutenberg Blocks
 *
 * @package EAGamingEngine
 */

namespace EAGamingEngine\Blocks;

/**
 * Blocks class
 */
class Blocks {

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'register_blocks' ) );
		add_filter( 'block_categories_all', array( $this, 'add_block_category' ), 10, 2 );
	}

	/**
	 * Register blocks
	 */
	public function register_blocks() {
		// Register block scripts.
		wp_register_script(
			'ea-gaming-blocks',
			EA_GAMING_ENGINE_URL . 'assets/dist/js/blocks.min.js',
			array( 'wp-blocks', 'wp-element', 'wp-editor', 'wp-components', 'wp-i18n' ),
			EA_GAMING_ENGINE_VERSION,
			true
		);

		wp_register_style(
			'ea-gaming-blocks',
			EA_GAMING_ENGINE_URL . 'assets/dist/css/blocks.min.css',
			array( 'wp-edit-blocks' ),
			EA_GAMING_ENGINE_VERSION
		);

		// Register blocks.
		register_block_type(
			'ea-gaming-engine/arcade',
			array(
				'editor_script'   => 'ea-gaming-blocks',
				'editor_style'    => 'ea-gaming-blocks',
				'render_callback' => array( $this, 'render_arcade_block' ),
				'attributes'      => array(
					'courseId'        => array(
						'type'    => 'number',
						'default' => 0,
					),
					'quizId'          => array(
						'type'    => 'number',
						'default' => 0,
					),
					'gameType'        => array(
						'type'    => 'string',
						'default' => '',
					),
					'theme'           => array(
						'type'    => 'string',
						'default' => '',
					),
					'preset'          => array(
						'type'    => 'string',
						'default' => '',
					),
					'showStats'       => array(
						'type'    => 'boolean',
						'default' => true,
					),
					'showLeaderboard' => array(
						'type'    => 'boolean',
						'default' => true,
					),
					'columns'         => array(
						'type'    => 'number',
						'default' => 3,
					),
				),
			)
		);

		register_block_type(
			'ea-gaming-engine/launcher',
			array(
				'editor_script'   => 'ea-gaming-blocks',
				'editor_style'    => 'ea-gaming-blocks',
				'render_callback' => array( $this, 'render_launcher_block' ),
				'attributes'      => array(
					'courseId'   => array(
						'type'    => 'number',
						'default' => 0,
					),
					'quizId'     => array(
						'type'    => 'number',
						'default' => 0,
					),
					'gameType'   => array(
						'type'    => 'string',
						'default' => 'whack_a_question',
					),
					'buttonText' => array(
						'type'    => 'string',
						'default' => __( 'Play Game', 'ea-gaming-engine' ),
					),
					'style'      => array(
						'type'    => 'string',
						'default' => 'default',
					),
				),
			)
		);

		register_block_type(
			'ea-gaming-engine/leaderboard',
			array(
				'editor_script'   => 'ea-gaming-blocks',
				'editor_style'    => 'ea-gaming-blocks',
				'render_callback' => array( $this, 'render_leaderboard_block' ),
				'attributes'      => array(
					'courseId'   => array(
						'type'    => 'number',
						'default' => 0,
					),
					'limit'      => array(
						'type'    => 'number',
						'default' => 10,
					),
					'period'     => array(
						'type'    => 'string',
						'default' => 'all',
					),
					'showAvatar' => array(
						'type'    => 'boolean',
						'default' => true,
					),
				),
			)
		);

		register_block_type(
			'ea-gaming-engine/stats',
			array(
				'editor_script'   => 'ea-gaming-blocks',
				'editor_style'    => 'ea-gaming-blocks',
				'render_callback' => array( $this, 'render_stats_block' ),
				'attributes'      => array(
					'userId'   => array(
						'type'    => 'number',
						'default' => 0,
					),
					'courseId' => array(
						'type'    => 'number',
						'default' => 0,
					),
					'style'    => array(
						'type'    => 'string',
						'default' => 'card',
					),
				),
			)
		);
	}

	/**
	 * Add block category
	 */
	public function add_block_category( $categories, $post ) {
		return array_merge(
			$categories,
			array(
				array(
					'slug'  => 'ea-gaming',
					'title' => __( 'EA Gaming Engine', 'ea-gaming-engine' ),
					'icon'  => 'games',
				),
			)
		);
	}

	/**
	 * Render arcade block
	 */
	public function render_arcade_block( $attributes ) {
		$atts = array(
			'course_id'        => $attributes['courseId'],
			'quiz_id'          => $attributes['quizId'],
			'game_type'        => $attributes['gameType'],
			'theme'            => $attributes['theme'],
			'preset'           => $attributes['preset'],
			'show_stats'       => $attributes['showStats'] ? 'true' : 'false',
			'show_leaderboard' => $attributes['showLeaderboard'] ? 'true' : 'false',
			'columns'          => $attributes['columns'],
		);

		return do_shortcode( '[ea_gaming_arcade ' . $this->build_shortcode_atts( $atts ) . ']' );
	}

	/**
	 * Render launcher block
	 */
	public function render_launcher_block( $attributes ) {
		$atts = array(
			'course_id'   => $attributes['courseId'],
			'quiz_id'     => $attributes['quizId'],
			'game_type'   => $attributes['gameType'],
			'button_text' => $attributes['buttonText'],
			'style'       => $attributes['style'],
		);

		return do_shortcode( '[ea_gaming_launcher ' . $this->build_shortcode_atts( $atts ) . ']' );
	}

	/**
	 * Render leaderboard block
	 */
	public function render_leaderboard_block( $attributes ) {
		$atts = array(
			'course_id'   => $attributes['courseId'],
			'limit'       => $attributes['limit'],
			'period'      => $attributes['period'],
			'show_avatar' => $attributes['showAvatar'] ? 'true' : 'false',
		);

		return do_shortcode( '[ea_gaming_leaderboard ' . $this->build_shortcode_atts( $atts ) . ']' );
	}

	/**
	 * Render stats block
	 */
	public function render_stats_block( $attributes ) {
		$atts = array(
			'user_id'   => $attributes['userId'],
			'course_id' => $attributes['courseId'],
			'style'     => $attributes['style'],
		);

		return do_shortcode( '[ea_gaming_stats ' . $this->build_shortcode_atts( $atts ) . ']' );
	}

	/**
	 * Build shortcode attributes string
	 */
	private function build_shortcode_atts( $atts ) {
		$output = array();
		foreach ( $atts as $key => $value ) {
			if ( $value !== '' && $value !== 0 ) {
				$output[] = $key . '="' . esc_attr( $value ) . '"';
			}
		}
		return implode( ' ', $output );
	}
}
