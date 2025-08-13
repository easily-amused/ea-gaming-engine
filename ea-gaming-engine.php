<?php
/**
 * @wordpress-plugin
 * Plugin Name:       EA Gaming Engine
 * Plugin URI:        https://honorswp.com/ea-gaming-engine
 * Description:       Transform LearnDash courses into interactive educational games with dynamic question gates, themed experiences, and engaging gameplay.
 * Version:           1.0.0
 * Requires PHP:      7.4
 * Requires at least: 5.9
 * Author:            HonorsWP
 * Author URI:        https://honorswp.com
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       ea-gaming-engine
 * Domain Path:       /languages
 *
 * @package EAGamingEngine
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

// Plugin version
define( 'EA_GAMING_ENGINE_VERSION', '1.0.0' );

// Plugin paths
define( 'EA_GAMING_ENGINE_PATH', plugin_dir_path( __FILE__ ) );
define( 'EA_GAMING_ENGINE_URL', plugin_dir_url( __FILE__ ) );
define( 'EA_GAMING_ENGINE_FILE', __FILE__ );
define( 'EA_GAMING_ENGINE_BASENAME', plugin_basename( __FILE__ ) );

// Plugin requirements
define( 'EA_GAMING_ENGINE_MIN_PHP', '7.4' );
define( 'EA_GAMING_ENGINE_MIN_WP', '5.9' );

// Autoloader
require_once EA_GAMING_ENGINE_PATH . 'vendor/autoload.php';

use EA\Licensing\License;
use EAGamingEngine\Core\Plugin;

/**
 * Check plugin requirements
 *
 * @return bool
 */
function ea_gaming_engine_requirements_met() {
	global $wp_version;

	if ( version_compare( PHP_VERSION, EA_GAMING_ENGINE_MIN_PHP, '<' ) ) {
		return false;
	}

	if ( version_compare( $wp_version, EA_GAMING_ENGINE_MIN_WP, '<' ) ) {
		return false;
	}

	// Check for LearnDash
	if ( ! class_exists( 'SFWD_LMS' ) ) {
		return false;
	}

	return true;
}

/**
 * Show requirements error
 *
 * @return void
 */
function ea_gaming_engine_requirements_error() {
	global $wp_version;

	$error = '<div class="notice notice-error"><p>';
	$error .= sprintf(
		__( '<strong>EA Gaming Engine</strong> requires PHP %1$s+, WordPress %2$s+, and LearnDash to be installed and activated.', 'ea-gaming-engine' ),
		EA_GAMING_ENGINE_MIN_PHP,
		EA_GAMING_ENGINE_MIN_WP
	);
	$error .= sprintf(
		__( ' You are running PHP %1$s and WordPress %2$s.', 'ea-gaming-engine' ),
		PHP_VERSION,
		$wp_version
	);

	if ( ! class_exists( 'SFWD_LMS' ) ) {
		$error .= __( ' LearnDash is not installed or activated.', 'ea-gaming-engine' );
	}

	$error .= '</p></div>';

	echo wp_kses_post( $error );
}

/**
 * Plugin activation
 *
 * @return void
 */
function ea_gaming_engine_activate() {
	if ( ! ea_gaming_engine_requirements_met() ) {
		deactivate_plugins( EA_GAMING_ENGINE_BASENAME );
		wp_die(
			esc_html__( 'EA Gaming Engine requires PHP 7.4+, WordPress 5.9+, and LearnDash to be installed and activated.', 'ea-gaming-engine' ),
			esc_html__( 'Plugin Activation Error', 'ea-gaming-engine' ),
			array( 'back_link' => true )
		);
	}

	require_once EA_GAMING_ENGINE_PATH . 'includes/Core/Activator.php';
	EAGamingEngine\Core\Activator::activate();
}

/**
 * Plugin deactivation
 *
 * @return void
 */
function ea_gaming_engine_deactivate() {
	require_once EA_GAMING_ENGINE_PATH . 'includes/Core/Deactivator.php';
	EAGamingEngine\Core\Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'ea_gaming_engine_activate' );
register_deactivation_hook( __FILE__, 'ea_gaming_engine_deactivate' );

/**
 * Initialize the plugin
 *
 * @return void
 */
function ea_gaming_engine_init() {
	// Load text domain
	load_plugin_textdomain(
		'ea-gaming-engine',
		false,
		dirname( EA_GAMING_ENGINE_BASENAME ) . '/languages/'
	);

	// Check requirements
	if ( ! ea_gaming_engine_requirements_met() ) {
		add_action( 'admin_notices', 'ea_gaming_engine_requirements_error' );
		return;
	}

	// Initialize the plugin
	Plugin::get_instance();
}
add_action( 'plugins_loaded', 'ea_gaming_engine_init' );

/**
 * Initialize Licensing
 */
add_action(
	'init',
	function () {
		// Initialize License
		new License(
			__( 'EA Gaming Engine', 'ea-gaming-engine' ),
			9999, // Product ID - update with actual ID
			'ea-gaming-engine',
			__FILE__,
			EA_GAMING_ENGINE_VERSION
		);
	}
);