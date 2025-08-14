<?php
/**
 * Theme Manager for game skins and visual styles
 *
 * @package EAGamingEngine
 */

namespace EAGamingEngine\Core;

/**
 * ThemeManager class
 */
class ThemeManager {

	/**
	 * Instance
	 *
	 * @var ThemeManager
	 */
	private static $instance = null;

	/**
	 * Available themes
	 *
	 * @var array
	 */
	private $themes = array();

	/**
	 * Available profile presets
	 *
	 * @var array
	 */
	private $presets = array();

	/**
	 * Current theme
	 *
	 * @var string
	 */
	private $current_theme = 'playful';

	/**
	 * Current preset
	 *
	 * @var string
	 */
	private $current_preset = 'classic';

	/**
	 * Get instance
	 *
	 * @return ThemeManager
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
		$this->init_themes();
		$this->init_presets();
		$this->init_hooks();
	}

	/**
	 * Initialize hooks
	 *
	 * @return void
	 */
	private function init_hooks() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_theme_styles' ) );
		add_filter( 'ea_gaming_theme_data', array( $this, 'get_theme_data' ), 10, 2 );
		add_filter( 'ea_gaming_preset_data', array( $this, 'get_preset_data' ), 10, 2 );
		add_action( 'wp_ajax_ea_gaming_switch_theme', array( $this, 'ajax_switch_theme' ) );
		add_action( 'wp_ajax_ea_gaming_switch_preset', array( $this, 'ajax_switch_preset' ) );
	}

	/**
	 * Initialize themes
	 *
	 * @return void
	 */
	private function init_themes() {
		$this->themes = array(
			'playful'     => array(
				'id'          => 'playful',
				'name'        => __( 'Playful', 'ea-gaming-engine' ),
				'description' => __( 'Vibrant and energetic theme for younger learners', 'ea-gaming-engine' ),
				'colors'      => array(
					'primary'         => '#7C3AED',
					'primary-dark'    => '#6D28D9',
					'primary-light'   => '#A78BFA',
					'secondary'       => '#EC4899',
					'secondary-dark'  => '#DB2777',
					'secondary-light' => '#F9A8D4',
					'success'         => '#10B981',
					'success-dark'    => '#059669',
					'success-light'   => '#34D399',
					'danger'          => '#EF4444',
					'danger-dark'     => '#DC2626',
					'danger-light'    => '#F87171',
					'warning'         => '#F59E0B',
					'warning-dark'    => '#D97706',
					'warning-light'   => '#FCD34D',
					'info'            => '#3B82F6',
					'info-dark'       => '#2563EB',
					'info-light'      => '#60A5FA',
					'background'      => '#FEF3C7',
					'surface'         => '#FFFFFF',
					'text-primary'    => '#1F2937',
					'text-secondary'  => '#6B7280',
				),
				'fonts'       => array(
					'heading' => "'Fredoka One', cursive",
					'body'    => "'Nunito', sans-serif",
					'game'    => "'Press Start 2P', monospace",
				),
				'animations'  => array(
					'bounce'           => true,
					'shake'            => true,
					'pulse'            => true,
					'confetti'         => true,
					'particle_effects' => true,
				),
				'sounds'      => array(
					'volume'  => 0.7,
					'effects' => true,
					'music'   => true,
					'voice'   => true,
				),
				'ui'          => array(
					'border_radius' => '16px',
					'shadow_style'  => 'playful',
					'button_style'  => 'rounded',
					'icon_style'    => 'cartoon',
				),
			),
			'minimal_pro' => array(
				'id'          => 'minimal_pro',
				'name'        => __( 'Minimal Pro', 'ea-gaming-engine' ),
				'description' => __( 'Clean and professional theme for adult learners', 'ea-gaming-engine' ),
				'colors'      => array(
					'primary'         => '#1F2937',
					'primary-dark'    => '#111827',
					'primary-light'   => '#374151',
					'secondary'       => '#6B7280',
					'secondary-dark'  => '#4B5563',
					'secondary-light' => '#9CA3AF',
					'success'         => '#059669',
					'success-dark'    => '#047857',
					'success-light'   => '#10B981',
					'danger'          => '#DC2626',
					'danger-dark'     => '#B91C1C',
					'danger-light'    => '#EF4444',
					'warning'         => '#D97706',
					'warning-dark'    => '#B45309',
					'warning-light'   => '#F59E0B',
					'info'            => '#2563EB',
					'info-dark'       => '#1E40AF',
					'info-light'      => '#3B82F6',
					'background'      => '#F9FAFB',
					'surface'         => '#FFFFFF',
					'text-primary'    => '#111827',
					'text-secondary'  => '#6B7280',
				),
				'fonts'       => array(
					'heading' => "'Inter', sans-serif",
					'body'    => "'Inter', sans-serif",
					'game'    => "'JetBrains Mono', monospace",
				),
				'animations'  => array(
					'bounce'           => false,
					'shake'            => false,
					'pulse'            => false,
					'confetti'         => false,
					'particle_effects' => false,
				),
				'sounds'      => array(
					'volume'  => 0.3,
					'effects' => true,
					'music'   => false,
					'voice'   => false,
				),
				'ui'          => array(
					'border_radius' => '8px',
					'shadow_style'  => 'subtle',
					'button_style'  => 'square',
					'icon_style'    => 'linear',
				),
			),
			'neon'        => array(
				'id'          => 'neon',
				'name'        => __( 'Neon Cyber', 'ea-gaming-engine' ),
				'description' => __( 'Futuristic cyberpunk theme with neon aesthetics', 'ea-gaming-engine' ),
				'colors'      => array(
					'primary'         => '#00D9FF',
					'primary-dark'    => '#00A8CC',
					'primary-light'   => '#33E0FF',
					'secondary'       => '#FF00FF',
					'secondary-dark'  => '#CC00CC',
					'secondary-light' => '#FF33FF',
					'success'         => '#00FF88',
					'success-dark'    => '#00CC6A',
					'success-light'   => '#33FF9F',
					'danger'          => '#FF0055',
					'danger-dark'     => '#CC0044',
					'danger-light'    => '#FF3377',
					'warning'         => '#FFAA00',
					'warning-dark'    => '#CC8800',
					'warning-light'   => '#FFBB33',
					'info'            => '#8800FF',
					'info-dark'       => '#6A00CC',
					'info-light'      => '#9F33FF',
					'background'      => '#0A0A0F',
					'surface'         => '#1A1A2E',
					'text-primary'    => '#FFFFFF',
					'text-secondary'  => '#B8B8D0',
				),
				'fonts'       => array(
					'heading' => "'Orbitron', monospace",
					'body'    => "'Exo 2', sans-serif",
					'game'    => "'Share Tech Mono', monospace",
				),
				'animations'  => array(
					'bounce'           => true,
					'shake'            => true,
					'pulse'            => true,
					'confetti'         => false,
					'particle_effects' => true,
				),
				'sounds'      => array(
					'volume'  => 0.8,
					'effects' => true,
					'music'   => true,
					'voice'   => false,
				),
				'ui'          => array(
					'border_radius' => '4px',
					'shadow_style'  => 'neon-glow',
					'button_style'  => 'cyber',
					'icon_style'    => 'tech',
				),
			),
		);

		// Allow themes to be filtered.
		$this->themes = apply_filters( 'ea_gaming_themes', $this->themes );
	}

	/**
	 * Initialize presets
	 *
	 * @return void
	 */
	private function init_presets() {
		$this->presets = array(
			'chill'      => array(
				'id'          => 'chill',
				'name'        => __( 'Chill Mode', 'ea-gaming-engine' ),
				'description' => __( 'Relaxed gameplay with hints and slower pace', 'ea-gaming-engine' ),
				'settings'    => array(
					'speed_multiplier' => 0.8,
					'ai_difficulty'    => 'easy',
					'hints_enabled'    => true,
					'hint_cooldown'    => 10, // Seconds.
					'effects_enabled'  => true,
					'timer_enabled'    => false,
					'lives'            => 5,
					'continue_on_fail' => true,
					'auto_advance'     => false,
					'show_progress'    => true,
					'accessibility'    => array(
						'high_contrast' => false,
						'reduce_motion' => false,
						'larger_text'   => false,
						'audio_cues'    => true,
					),
				),
			),
			'classic'    => array(
				'id'          => 'classic',
				'name'        => __( 'Classic Mode', 'ea-gaming-engine' ),
				'description' => __( 'Standard gameplay experience', 'ea-gaming-engine' ),
				'settings'    => array(
					'speed_multiplier' => 1.0,
					'ai_difficulty'    => 'medium',
					'hints_enabled'    => false,
					'hint_cooldown'    => 30,
					'effects_enabled'  => true,
					'timer_enabled'    => true,
					'lives'            => 3,
					'continue_on_fail' => true,
					'auto_advance'     => true,
					'show_progress'    => true,
					'accessibility'    => array(
						'high_contrast' => false,
						'reduce_motion' => false,
						'larger_text'   => false,
						'audio_cues'    => true,
					),
				),
			),
			'pro'        => array(
				'id'          => 'pro',
				'name'        => __( 'Pro Mode', 'ea-gaming-engine' ),
				'description' => __( 'Challenging gameplay for experienced players', 'ea-gaming-engine' ),
				'settings'    => array(
					'speed_multiplier' => 1.5,
					'ai_difficulty'    => 'hard',
					'hints_enabled'    => false,
					'hint_cooldown'    => 60,
					'effects_enabled'  => false,
					'timer_enabled'    => true,
					'lives'            => 1,
					'continue_on_fail' => false,
					'auto_advance'     => true,
					'show_progress'    => false,
					'accessibility'    => array(
						'high_contrast' => false,
						'reduce_motion' => true,
						'larger_text'   => false,
						'audio_cues'    => false,
					),
				),
			),
			'accessible' => array(
				'id'          => 'accessible',
				'name'        => __( 'Accessible Mode', 'ea-gaming-engine' ),
				'description' => __( 'Optimized for accessibility needs', 'ea-gaming-engine' ),
				'settings'    => array(
					'speed_multiplier' => 0.6,
					'ai_difficulty'    => 'easy',
					'hints_enabled'    => true,
					'hint_cooldown'    => 5,
					'effects_enabled'  => false,
					'timer_enabled'    => false,
					'lives'            => 99,
					'continue_on_fail' => true,
					'auto_advance'     => false,
					'show_progress'    => true,
					'accessibility'    => array(
						'high_contrast' => true,
						'reduce_motion' => true,
						'larger_text'   => true,
						'audio_cues'    => true,
					),
				),
			),
		);

		// Allow presets to be filtered.
		$this->presets = apply_filters( 'ea_gaming_presets', $this->presets );
	}

	/**
	 * Get current theme
	 *
	 * @return string
	 */
	public function get_current_theme() {
		// Check user preference first.
		$user_id = get_current_user_id();
		if ( $user_id ) {
			$user_theme = get_user_meta( $user_id, 'ea_gaming_theme', true );
			if ( $user_theme && isset( $this->themes[ $user_theme ] ) ) {
				return $user_theme;
			}
		}

		// Fall back to global setting.
		return get_option( 'ea_gaming_engine_default_theme', 'playful' );
	}

	/**
	 * Get current preset
	 *
	 * @return string
	 */
	public function get_current_preset() {
		// Check user preference first.
		$user_id = get_current_user_id();
		if ( $user_id ) {
			$user_preset = get_user_meta( $user_id, 'ea_gaming_preset', true );
			if ( $user_preset && isset( $this->presets[ $user_preset ] ) ) {
				return $user_preset;
			}
		}

		// Fall back to global setting.
		return get_option( 'ea_gaming_engine_default_preset', 'classic' );
	}

	/**
	 * Get theme data
	 *
	 * @param mixed  $data Default data.
	 * @param string $theme_id Theme ID.
	 * @return array
	 */
	public function get_theme_data( $data, $theme_id = null ) {
		if ( ! $theme_id ) {
			$theme_id = $this->get_current_theme();
		}

		if ( isset( $this->themes[ $theme_id ] ) ) {
			return $this->themes[ $theme_id ];
		}

		// Return default theme if not found.
		return $this->themes['playful'];
	}

	/**
	 * Get preset data
	 *
	 * @param mixed  $data Default data.
	 * @param string $preset_id Preset ID.
	 * @return array
	 */
	public function get_preset_data( $data, $preset_id = null ) {
		if ( ! $preset_id ) {
			$preset_id = $this->get_current_preset();
		}

		if ( isset( $this->presets[ $preset_id ] ) ) {
			return $this->presets[ $preset_id ];
		}

		// Return default preset if not found.
		return $this->presets['classic'];
	}

	/**
	 * Set user theme
	 *
	 * @param int    $user_id User ID.
	 * @param string $theme_id Theme ID.
	 * @return bool
	 */
	public function set_user_theme( $user_id, $theme_id ) {
		if ( ! isset( $this->themes[ $theme_id ] ) ) {
			return false;
		}

		update_user_meta( $user_id, 'ea_gaming_theme', $theme_id );
		return true;
	}

	/**
	 * Set user preset
	 *
	 * @param int    $user_id User ID.
	 * @param string $preset_id Preset ID.
	 * @return bool
	 */
	public function set_user_preset( $user_id, $preset_id ) {
		if ( ! isset( $this->presets[ $preset_id ] ) ) {
			return false;
		}

		update_user_meta( $user_id, 'ea_gaming_preset', $preset_id );
		return true;
	}

	/**
	 * Enqueue theme styles
	 *
	 * @return void
	 */
	public function enqueue_theme_styles() {
		$theme = $this->get_theme_data( null );

		// Generate CSS variables.
		$css_vars = $this->generate_css_variables( $theme );

		// Add inline styles.
		wp_add_inline_style( 'ea-gaming-engine', $css_vars );

		// Load Google Fonts if needed.
		$this->load_theme_fonts( $theme );
	}

	/**
	 * Generate CSS variables from theme
	 *
	 * @param array $theme Theme data.
	 * @return string
	 */
	private function generate_css_variables( $theme ) {
		$css = ':root {';

		// Add color variables.
		foreach ( $theme['colors'] as $name => $value ) {
			$css .= '--ea-gaming-' . $name . ': ' . $value . ';';
		}

		// Add font variables.
		foreach ( $theme['fonts'] as $name => $value ) {
			$css .= '--ea-gaming-font-' . $name . ': ' . $value . ';';
		}

		// Add UI variables.
		foreach ( $theme['ui'] as $name => $value ) {
			$name = str_replace( '_', '-', $name );
			$css .= '--ea-gaming-' . $name . ': ' . $value . ';';
		}

		$css .= '}';

		// Add theme-specific styles.
		$css .= $this->get_theme_specific_styles( $theme['id'] );

		return $css;
	}

	/**
	 * Get theme-specific styles
	 *
	 * @param string $theme_id Theme ID.
	 * @return string
	 */
	private function get_theme_specific_styles( $theme_id ) {
		$css = '';

		switch ( $theme_id ) {
			case 'playful':
				$css .= '
				.ea-gaming-container {
					background: linear-gradient(135deg, var(--ea-gaming-background) 0%, var(--ea-gaming-primary-light) 100%);
				}
				.ea-gaming-button {
					box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1), 0 1px 3px rgba(0, 0, 0, 0.08);
					transition: transform 0.2s;
				}
				.ea-gaming-button:hover {
					transform: translateY(-2px);
				}';
				break;

			case 'minimal_pro':
				$css .= '
				.ea-gaming-container {
					border: 1px solid var(--ea-gaming-secondary-light);
				}
				.ea-gaming-button {
					border: 2px solid var(--ea-gaming-primary);
					transition: background-color 0.2s, color 0.2s;
				}';
				break;

			case 'neon':
				$css .= '
				.ea-gaming-container {
					background: var(--ea-gaming-background);
					box-shadow: 0 0 20px rgba(0, 217, 255, 0.5);
				}
				.ea-gaming-button {
					border: 1px solid var(--ea-gaming-primary);
					box-shadow: 0 0 10px var(--ea-gaming-primary), inset 0 0 10px rgba(0, 217, 255, 0.2);
					text-shadow: 0 0 5px currentColor;
				}
				.ea-gaming-text-glow {
					text-shadow: 0 0 10px currentColor;
				}';
				break;
		}

		return $css;
	}

	/**
	 * Load theme fonts
	 *
	 * @param array $theme Theme data.
	 * @return void
	 */
	private function load_theme_fonts( $theme ) {
		$google_fonts = array();

		// Map fonts to Google Fonts URLs.
		$font_map = array(
			'Fredoka One'     => 'Fredoka+One',
			'Nunito'          => 'Nunito:wght@400;700',
			'Press Start 2P'  => 'Press+Start+2P',
			'Inter'           => 'Inter:wght@400;500;600;700',
			'JetBrains Mono'  => 'JetBrains+Mono:wght@400;700',
			'Orbitron'        => 'Orbitron:wght@400;700;900',
			'Exo 2'           => 'Exo+2:wght@400;700',
			'Share Tech Mono' => 'Share+Tech+Mono',
		);

		foreach ( $theme['fonts'] as $font ) {
			$font_name = explode( ',', str_replace( "'", '', $font ) )[0];
			if ( isset( $font_map[ $font_name ] ) ) {
				$google_fonts[] = $font_map[ $font_name ];
			}
		}

		if ( ! empty( $google_fonts ) ) {
			$fonts_url = 'https://fonts.googleapis.com/css2?family=' . implode( '&family=', $google_fonts ) . '&display=swap';
			wp_enqueue_style( 'ea-gaming-google-fonts', $fonts_url, array(), null );
		}
	}

	/**
	 * Get all available themes
	 *
	 * @return array
	 */
	public function get_all_themes() {
		return $this->themes;
	}

	/**
	 * Get all available presets
	 *
	 * @return array
	 */
	public function get_all_presets() {
		return $this->presets;
	}

	/**
	 * AJAX handler for switching theme
	 *
	 * @return void
	 */
	public function ajax_switch_theme() {
		check_ajax_referer( 'ea-gaming-engine', 'nonce' );

		$user_id  = get_current_user_id();
		$theme_id = sanitize_text_field( $_POST['theme_id'] ?? '' );

		if ( ! $user_id || ! $theme_id ) {
			wp_send_json_error( __( 'Invalid parameters', 'ea-gaming-engine' ) );
		}

		if ( $this->set_user_theme( $user_id, $theme_id ) ) {
			wp_send_json_success(
				array(
					'message' => __( 'Theme updated successfully', 'ea-gaming-engine' ),
					'theme'   => $this->get_theme_data( null, $theme_id ),
				)
			);
		} else {
			wp_send_json_error( __( 'Invalid theme', 'ea-gaming-engine' ) );
		}
	}

	/**
	 * AJAX handler for switching preset
	 *
	 * @return void
	 */
	public function ajax_switch_preset() {
		check_ajax_referer( 'ea-gaming-engine', 'nonce' );

		$user_id   = get_current_user_id();
		$preset_id = sanitize_text_field( $_POST['preset_id'] ?? '' );

		if ( ! $user_id || ! $preset_id ) {
			wp_send_json_error( __( 'Invalid parameters', 'ea-gaming-engine' ) );
		}

		if ( $this->set_user_preset( $user_id, $preset_id ) ) {
			wp_send_json_success(
				array(
					'message' => __( 'Preset updated successfully', 'ea-gaming-engine' ),
					'preset'  => $this->get_preset_data( null, $preset_id ),
				)
			);
		} else {
			wp_send_json_error( __( 'Invalid preset', 'ea-gaming-engine' ) );
		}
	}
}
