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
		add_action( 'init', [ $this, 'register_blocks' ] );
		add_filter( 'block_categories_all', [ $this, 'add_block_category' ], 10, 2 );
	}

	/**
	 * Register blocks
	 */
	public function register_blocks() {
		// Register block scripts
		wp_register_script(
			'ea-gaming-blocks',
			EA_GAMING_ENGINE_URL . 'assets/js/blocks.js',
			[ 'wp-blocks', 'wp-element', 'wp-editor', 'wp-components', 'wp-i18n' ],
			EA_GAMING_ENGINE_VERSION,
			true
		);

		wp_register_style(
			'ea-gaming-blocks',
			EA_GAMING_ENGINE_URL . 'assets/css/blocks.css',
			[ 'wp-edit-blocks' ],
			EA_GAMING_ENGINE_VERSION
		);

		// Register blocks
		register_block_type(
			'ea-gaming-engine/arcade',
			[
				'editor_script'   => 'ea-gaming-blocks',
				'editor_style'    => 'ea-gaming-blocks',
				'render_callback' => [ $this, 'render_arcade_block' ],
				'attributes'      => [
					'courseId'        => [ 'type' => 'number', 'default' => 0 ],
					'quizId'          => [ 'type' => 'number', 'default' => 0 ],
					'gameType'        => [ 'type' => 'string', 'default' => '' ],
					'theme'           => [ 'type' => 'string', 'default' => '' ],
					'preset'          => [ 'type' => 'string', 'default' => '' ],
					'showStats'       => [ 'type' => 'boolean', 'default' => true ],
					'showLeaderboard' => [ 'type' => 'boolean', 'default' => true ],
					'columns'         => [ 'type' => 'number', 'default' => 3 ],
				],
			]
		);

		register_block_type(
			'ea-gaming-engine/launcher',
			[
				'editor_script'   => 'ea-gaming-blocks',
				'editor_style'    => 'ea-gaming-blocks',
				'render_callback' => [ $this, 'render_launcher_block' ],
				'attributes'      => [
					'courseId'   => [ 'type' => 'number', 'default' => 0 ],
					'quizId'     => [ 'type' => 'number', 'default' => 0 ],
					'gameType'   => [ 'type' => 'string', 'default' => 'whack_a_question' ],
					'buttonText' => [ 'type' => 'string', 'default' => 'Play Game' ],
					'style'      => [ 'type' => 'string', 'default' => 'default' ],
				],
			]
		);

		register_block_type(
			'ea-gaming-engine/leaderboard',
			[
				'editor_script'   => 'ea-gaming-blocks',
				'editor_style'    => 'ea-gaming-blocks',
				'render_callback' => [ $this, 'render_leaderboard_block' ],
				'attributes'      => [
					'courseId'   => [ 'type' => 'number', 'default' => 0 ],
					'limit'      => [ 'type' => 'number', 'default' => 10 ],
					'period'     => [ 'type' => 'string', 'default' => 'all' ],
					'showAvatar' => [ 'type' => 'boolean', 'default' => true ],
				],
			]
		);

		register_block_type(
			'ea-gaming-engine/stats',
			[
				'editor_script'   => 'ea-gaming-blocks',
				'editor_style'    => 'ea-gaming-blocks',
				'render_callback' => [ $this, 'render_stats_block' ],
				'attributes'      => [
					'userId'   => [ 'type' => 'number', 'default' => 0 ],
					'courseId' => [ 'type' => 'number', 'default' => 0 ],
					'style'    => [ 'type' => 'string', 'default' => 'card' ],
				],
			]
		);
	}

	/**
	 * Add block category
	 */
	public function add_block_category( $categories, $post ) {
		return array_merge(
			$categories,
			[
				[
					'slug'  => 'ea-gaming',
					'title' => __( 'EA Gaming Engine', 'ea-gaming-engine' ),
					'icon'  => 'games',
				],
			]
		);
	}

	/**
	 * Render arcade block
	 */
	public function render_arcade_block( $attributes ) {
		$atts = [
			'course_id'        => $attributes['courseId'],
			'quiz_id'          => $attributes['quizId'],
			'game_type'        => $attributes['gameType'],
			'theme'            => $attributes['theme'],
			'preset'           => $attributes['preset'],
			'show_stats'       => $attributes['showStats'] ? 'true' : 'false',
			'show_leaderboard' => $attributes['showLeaderboard'] ? 'true' : 'false',
			'columns'          => $attributes['columns'],
		];

		return do_shortcode( '[ea_gaming_arcade ' . $this->build_shortcode_atts( $atts ) . ']' );
	}

	/**
	 * Render launcher block
	 */
	public function render_launcher_block( $attributes ) {
		$atts = [
			'course_id'   => $attributes['courseId'],
			'quiz_id'     => $attributes['quizId'],
			'game_type'   => $attributes['gameType'],
			'button_text' => $attributes['buttonText'],
			'style'       => $attributes['style'],
		];

		return do_shortcode( '[ea_gaming_launcher ' . $this->build_shortcode_atts( $atts ) . ']' );
	}

	/**
	 * Render leaderboard block
	 */
	public function render_leaderboard_block( $attributes ) {
		$atts = [
			'course_id'   => $attributes['courseId'],
			'limit'       => $attributes['limit'],
			'period'      => $attributes['period'],
			'show_avatar' => $attributes['showAvatar'] ? 'true' : 'false',
		];

		return do_shortcode( '[ea_gaming_leaderboard ' . $this->build_shortcode_atts( $atts ) . ']' );
	}

	/**
	 * Render stats block
	 */
	public function render_stats_block( $attributes ) {
		$atts = [
			'user_id'   => $attributes['userId'],
			'course_id' => $attributes['courseId'],
			'style'     => $attributes['style'],
		];

		return do_shortcode( '[ea_gaming_stats ' . $this->build_shortcode_atts( $atts ) . ']' );
	}

	/**
	 * Build shortcode attributes string
	 */
	private function build_shortcode_atts( $atts ) {
		$output = [];
		foreach ( $atts as $key => $value ) {
			if ( $value !== '' && $value !== 0 ) {
				$output[] = $key . '="' . esc_attr( $value ) . '"';
			}
		}
		return implode( ' ', $output );
	}
}