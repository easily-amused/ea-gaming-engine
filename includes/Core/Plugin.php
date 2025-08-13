<?php
/**
 * Main plugin class
 *
 * @package EAGamingEngine
 */

namespace EAGamingEngine\Core;

use EAGamingEngine\Admin\Admin;
use EAGamingEngine\Frontend\Frontend;
use EAGamingEngine\Core\GameEngine;
use EAGamingEngine\Core\PolicyEngine;
use EAGamingEngine\Core\ThemeManager;
use EAGamingEngine\Integrations\LearnDash;
use EAGamingEngine\REST\RestAPI;
use EAGamingEngine\Blocks\Blocks;

/**
 * Plugin main class
 */
class Plugin {

	/**
	 * Instance
	 *
	 * @var Plugin
	 */
	private static $instance = null;

	/**
	 * Plugin components
	 *
	 * @var array
	 */
	private $components = [];

	/**
	 * Get instance
	 *
	 * @return Plugin
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor
	 */
	private function __construct() {
		$this->init_hooks();
		$this->init_components();
	}

	/**
	 * Initialize hooks
	 *
	 * @return void
	 */
	private function init_hooks() {
		add_action( 'init', [ $this, 'init' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'admin_enqueue_scripts' ] );
	}

	/**
	 * Initialize components
	 *
	 * @return void
	 */
	private function init_components() {
		// Core components
		$this->components['game_engine']    = new GameEngine();
		$this->components['policy_engine']  = new PolicyEngine();
		$this->components['theme_manager']  = new ThemeManager();
		
		// Integrations
		if ( class_exists( 'SFWD_LMS' ) ) {
			$this->components['learndash'] = new LearnDash();
		}

		// REST API
		$this->components['rest_api'] = new RestAPI();

		// Admin
		if ( is_admin() ) {
			$this->components['admin'] = new Admin();
		}

		// Frontend
		if ( ! is_admin() ) {
			$this->components['frontend'] = new Frontend();
		}

		// Blocks
		$this->components['blocks'] = new Blocks();
	}

	/**
	 * Initialize plugin
	 *
	 * @return void
	 */
	public function init() {
		// Register custom post types
		$this->register_post_types();

		// Register taxonomies
		$this->register_taxonomies();

		// Flush rewrite rules if needed
		$this->maybe_flush_rewrite_rules();
	}

	/**
	 * Register custom post types
	 *
	 * @return void
	 */
	private function register_post_types() {
		// Game Sessions CPT
		register_post_type(
			'ea_game_session',
			[
				'labels'              => [
					'name'          => __( 'Game Sessions', 'ea-gaming-engine' ),
					'singular_name' => __( 'Game Session', 'ea-gaming-engine' ),
				],
				'public'              => false,
				'show_ui'             => false,
				'show_in_menu'        => false,
				'show_in_rest'        => true,
				'rest_base'           => 'game-sessions',
				'rest_controller_class' => 'WP_REST_Posts_Controller',
				'capability_type'     => 'post',
				'map_meta_cap'        => true,
				'supports'            => [ 'title', 'author', 'custom-fields' ],
				'rewrite'             => false,
				'query_var'           => false,
			]
		);
	}

	/**
	 * Register taxonomies
	 *
	 * @return void
	 */
	private function register_taxonomies() {
		// Game Type taxonomy
		register_taxonomy(
			'ea_game_type',
			[ 'ea_game_session' ],
			[
				'labels'              => [
					'name'          => __( 'Game Types', 'ea-gaming-engine' ),
					'singular_name' => __( 'Game Type', 'ea-gaming-engine' ),
				],
				'public'              => false,
				'show_ui'             => false,
				'show_in_menu'        => false,
				'show_in_rest'        => true,
				'rest_base'           => 'game-types',
				'rest_controller_class' => 'WP_REST_Terms_Controller',
				'hierarchical'        => false,
				'rewrite'             => false,
				'query_var'           => false,
			]
		);
	}

	/**
	 * Maybe flush rewrite rules
	 *
	 * @return void
	 */
	private function maybe_flush_rewrite_rules() {
		if ( get_option( 'ea_gaming_engine_flush_rules' ) ) {
			flush_rewrite_rules();
			delete_option( 'ea_gaming_engine_flush_rules' );
		}
	}

	/**
	 * Enqueue frontend scripts
	 *
	 * @return void
	 */
	public function enqueue_scripts() {
		// Main frontend styles
		wp_enqueue_style(
			'ea-gaming-engine',
			EA_GAMING_ENGINE_URL . 'assets/css/frontend.css',
			[],
			EA_GAMING_ENGINE_VERSION
		);

		// Main frontend script
		wp_enqueue_script(
			'ea-gaming-engine',
			EA_GAMING_ENGINE_URL . 'assets/js/frontend.js',
			[ 'jquery', 'wp-api-fetch', 'wp-i18n' ],
			EA_GAMING_ENGINE_VERSION,
			true
		);

		// Localize script
		wp_localize_script(
			'ea-gaming-engine',
			'eaGamingEngine',
			[
				'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
				'apiUrl'    => home_url( '/wp-json/ea-gaming/v1/' ),
				'nonce'     => wp_create_nonce( 'ea-gaming-engine' ),
				'userId'    => get_current_user_id(),
				'isLoggedIn' => is_user_logged_in(),
				'i18n'      => [
					'loading'    => __( 'Loading...', 'ea-gaming-engine' ),
					'error'      => __( 'An error occurred', 'ea-gaming-engine' ),
					'tryAgain'   => __( 'Try Again', 'ea-gaming-engine' ),
					'correct'    => __( 'Correct!', 'ea-gaming-engine' ),
					'incorrect'  => __( 'Incorrect', 'ea-gaming-engine' ),
					'gameOver'   => __( 'Game Over', 'ea-gaming-engine' ),
					'score'      => __( 'Score', 'ea-gaming-engine' ),
					'playAgain'  => __( 'Play Again', 'ea-gaming-engine' ),
				],
			]
		);

		// Phaser library for games
		wp_enqueue_script(
			'phaser',
			EA_GAMING_ENGINE_URL . 'assets/lib/phaser.min.js',
			[],
			'3.70.0',
			true
		);

		// Game engine scripts (conditionally loaded)
		if ( $this->should_load_games() ) {
			wp_enqueue_script(
				'ea-gaming-engine-games',
				EA_GAMING_ENGINE_URL . 'assets/games/dist/bundle.js',
				[ 'phaser', 'ea-gaming-engine' ],
				EA_GAMING_ENGINE_VERSION,
				true
			);
		}
	}

	/**
	 * Enqueue admin scripts
	 *
	 * @param string $hook Hook suffix.
	 * @return void
	 */
	public function admin_enqueue_scripts( $hook ) {
		// Admin styles
		wp_enqueue_style(
			'ea-gaming-engine-admin',
			EA_GAMING_ENGINE_URL . 'assets/css/admin.css',
			[ 'wp-components' ],
			EA_GAMING_ENGINE_VERSION
		);

		// Admin scripts
		wp_enqueue_script(
			'ea-gaming-engine-admin',
			EA_GAMING_ENGINE_URL . 'assets/js/admin.js',
			[ 'jquery', 'wp-api-fetch', 'wp-i18n', 'wp-components', 'wp-element' ],
			EA_GAMING_ENGINE_VERSION,
			true
		);

		// Localize admin script
		wp_localize_script(
			'ea-gaming-engine-admin',
			'eaGamingEngineAdmin',
			[
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'apiUrl'  => home_url( '/wp-json/ea-gaming/v1/' ),
				'nonce'   => wp_create_nonce( 'ea-gaming-engine-admin' ),
				'i18n'    => [
					'saved'       => __( 'Settings saved', 'ea-gaming-engine' ),
					'saveError'   => __( 'Error saving settings', 'ea-gaming-engine' ),
					'confirmDelete' => __( 'Are you sure you want to delete this?', 'ea-gaming-engine' ),
				],
			]
		);
	}

	/**
	 * Check if games should be loaded
	 *
	 * @return bool
	 */
	private function should_load_games() {
		// Load on single course pages
		if ( is_singular( 'sfwd-courses' ) ) {
			return true;
		}

		// Load on lesson/topic/quiz pages
		if ( is_singular( [ 'sfwd-lessons', 'sfwd-topic', 'sfwd-quiz' ] ) ) {
			return true;
		}

		// Load if shortcode is present
		global $post;
		if ( $post && has_shortcode( $post->post_content, 'ea_gaming_arcade' ) ) {
			return true;
		}

		// Load if block is present
		if ( $post && has_block( 'ea-gaming-engine/arcade', $post ) ) {
			return true;
		}

		return apply_filters( 'ea_gaming_engine_should_load_games', false );
	}

	/**
	 * Get component
	 *
	 * @param string $name Component name.
	 * @return mixed|null
	 */
	public function get_component( $name ) {
		return isset( $this->components[ $name ] ) ? $this->components[ $name ] : null;
	}
}