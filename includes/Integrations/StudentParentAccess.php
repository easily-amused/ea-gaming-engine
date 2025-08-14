<?php
/**
 * Student-Parent Access Integration (optional)
 *
 * Integrates EA Gaming Engine with EA Student-Parent Access (SPA) plugin when available.
 * Provides:
 * - Detection of SPA availability
 * - Parent control data via ea_gaming_parent_controls
 * - Enforcement via ea_gaming_can_user_play
 * - Time window checks and ticket requirement validation
 * - Simple fallback admin settings (when SPA is not active)
 *
 * @package EAGamingEngine
 */

namespace EAGamingEngine\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class StudentParentAccess {

	/**
	 * Whether Student-Parent Access plugin (SPA) appears active.
	 *
	 * @var bool
	 */
	private $spa_active = false;

	/**
	 * Option key for fallback parent controls settings.
	 */
	private const OPTION_KEY = 'ea_gaming_engine_parent_controls';

	/**
	 * Constructor.
	 * Wires up filters/actions and detects SPA.
	 */
	public function __construct() {
		$this->spa_active = $this->detect_spa();

		// Provide parent control data to PolicyEngine context.
		add_filter( 'ea_gaming_parent_controls', array( $this, 'filter_parent_controls' ), 10, 2 );

		// Enforce parent controls at the PolicyEngine decision point.
		// Run early enough (8) to short-circuit if needed, but allow other integrations even earlier if any.
		add_filter( 'ea_gaming_can_user_play', array( $this, 'filter_can_user_play' ), 8, 3 );

		// Simple fallback admin settings (optional; used if SPA is not active or returns no data).
		add_action( 'admin_init', array( $this, 'register_settings' ) );

		// Hook into our custom action to add menu at the right place.
		add_action( 'ea_gaming_engine_add_admin_menus', array( $this, 'add_admin_menu' ) );

		// Listen for plugin activation/deactivation.
		add_action( 'activated_plugin', array( $this, 'check_plugin_activation' ), 10, 2 );
		add_action( 'deactivated_plugin', array( $this, 'check_plugin_deactivation' ), 10, 2 );
	}

	/**
	 * Detect if the EA Student-Parent Access plugin is available.
	 *
	 * @return bool
	 */
	private function detect_spa(): bool {
		// Heuristics: class existence or dedicated filter/function exposed by SPA.
		if ( class_exists( 'EA_Student_Parent_Access' ) ) {
			return true;
		}

		if ( function_exists( 'ea_spa_get_child_controls' ) || has_filter( 'ea_spa_get_child_controls' ) ) {
			return true;
		}

		// As a last attempt, check for a version constant if the SPA plugin defines one.
		if ( defined( 'EA_STUDENT_PARENT_ACCESS_VERSION' ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Parent controls provider for PolicyEngine.
	 *
	 * @param array $controls Existing controls (from other providers).
	 * @param int   $user_id  User ID.
	 * @return array
	 */
	public function filter_parent_controls( array $controls, int $user_id ): array {
		$spa = $this->get_spa_controls( $user_id );

		// If SPA didn't provide data, optionally use fallback settings.
		if ( empty( $spa ) ) {
			$fallback = $this->get_fallback_controls();
			if ( ! empty( $fallback ) ) {
				$spa = $fallback;
			}
		}

		// Merge (SPA/fallback first to ensure it provides defaults, allow existing controls to override if already set).
		$controls = wp_parse_args( $controls, $spa );

		return $controls;
	}

	/**
	 * Enforce parent controls (games blocked, time window, tickets).
	 *
	 * @param bool|array $can_play Boolean true/false or an array with can_play=false and reason from a previous integration.
	 * @param int        $user_id  User ID.
	 * @param int        $course_id Course ID.
	 * @return bool|array
	 */
	public function filter_can_user_play( $can_play, int $user_id, int $course_id ) {
		// If already blocked by another rule, keep the result.
		if ( is_array( $can_play ) && isset( $can_play['can_play'] ) && false === $can_play['can_play'] ) {
			return $can_play;
		}

		$controls = apply_filters( 'ea_gaming_parent_controls', array(), $user_id );

		// 1) Hard block from parent/guardian.
		if ( ! empty( $controls['games_blocked'] ) ) {
			return array(
				'can_play' => false,
				'reason'   => __( 'Games have been disabled by your parent/guardian.', 'ea-gaming-engine' ),
				'policy'   => 'parent_control',
			);
		}

		// 2) Time restriction window check.
		if ( ! empty( $controls['time_restrictions']['start'] ) && ! empty( $controls['time_restrictions']['end'] ) ) {
			$current = current_time( 'H:i' );
			$start   = $controls['time_restrictions']['start'];
			$end     = $controls['time_restrictions']['end'];

			if ( ! $this->is_time_between( $current, $start, $end ) ) {
				return array(
					'can_play' => false,
					'reason'   => sprintf(
						/* translators: %1$s: start time, %2$s: end time */
						__( 'Games are only available between %1$s and %2$s.', 'ea-gaming-engine' ),
						$start,
						$end
					),
					'policy'   => 'parent_control',
				);
			}
		}

		// 3) Ticket requirement check.
		if ( ! empty( $controls['require_tickets'] ) ) {
			$tickets = $this->get_user_tickets( $user_id );
			if ( $tickets <= 0 ) {
				return array(
					'can_play' => false,
					'reason'   => __( 'You need tickets to play. Please ask your parent/guardian for more.', 'ea-gaming-engine' ),
					'policy'   => 'parent_control',
				);
			}
		}

		return $can_play;
	}

	/**
	 * Pull controls from SPA if possible and normalize to EA Gaming Engine's structure.
	 *
	 * Structure returned:
	 * [
	 *   'games_blocked'     => bool,
	 *   'time_restrictions' => [ 'start' => 'HH:MM', 'end' => 'HH:MM' ],
	 *   'require_tickets'   => bool,
	 * ]
	 *
	 * @param int $user_id User ID.
	 * @return array
	 */
	private function get_spa_controls( int $user_id ): array {
		if ( ! $this->spa_active ) {
			return array();
		}

		$spa_controls = array();

		// Preferred: dedicated function.
		if ( function_exists( 'ea_spa_get_child_controls' ) ) {
			$spa_controls = (array) call_user_func( 'ea_spa_get_child_controls', $user_id );
		} elseif ( has_filter( 'ea_spa_get_child_controls' ) ) {
			// Or via filter.
			$spa_controls = (array) apply_filters( 'ea_spa_get_child_controls', array(), $user_id );
		} else {
			// Fallback to a common user meta key if SPA stores structured data there.
			$spa_controls = (array) get_user_meta( $user_id, 'ea_spa_parent_controls', true );
		}

		if ( empty( $spa_controls ) ) {
			return array();
		}

		$normalized = array(
			'games_blocked'     => false,
			'time_restrictions' => array(),
			'require_tickets'   => false,
		);

		// Normalize "games blocked".
		if ( isset( $spa_controls['games_blocked'] ) ) {
			$normalized['games_blocked'] = (bool) $spa_controls['games_blocked'];
		} elseif ( isset( $spa_controls['block_games'] ) ) {
			$normalized['games_blocked'] = (bool) $spa_controls['block_games'];
		}

		// Normalize time window (several potential key names).
		$start = '';
		$end   = '';

		if ( isset( $spa_controls['time_restrictions']['start'], $spa_controls['time_restrictions']['end'] ) ) {
			$start = (string) $spa_controls['time_restrictions']['start'];
			$end   = (string) $spa_controls['time_restrictions']['end'];
		} elseif ( isset( $spa_controls['allowed_start'], $spa_controls['allowed_end'] ) ) {
			$start = (string) $spa_controls['allowed_start'];
			$end   = (string) $spa_controls['allowed_end'];
		} elseif ( isset( $spa_controls['time_start'], $spa_controls['time_end'] ) ) {
			$start = (string) $spa_controls['time_start'];
			$end   = (string) $spa_controls['time_end'];
		} elseif ( isset( $spa_controls['access_window']['start'], $spa_controls['access_window']['end'] ) ) {
			$start = (string) $spa_controls['access_window']['start'];
			$end   = (string) $spa_controls['access_window']['end'];
		}

		if ( $start && $end ) {
			$normalized['time_restrictions'] = array(
				'start' => $start,
				'end'   => $end,
			);
		}

		// Normalize ticket requirement.
		if ( isset( $spa_controls['require_tickets'] ) ) {
			$normalized['require_tickets'] = (bool) $spa_controls['require_tickets'];
		} elseif ( isset( $spa_controls['tickets_required'] ) ) {
			$normalized['require_tickets'] = (bool) $spa_controls['tickets_required'];
		}

		// Sync SPA ticket balance into EA Gaming tickets meta if provided.
		$spa_tickets = $this->get_spa_user_tickets( $user_id );
		if ( is_numeric( $spa_tickets ) ) {
			update_user_meta( $user_id, 'ea_gaming_tickets', (int) $spa_tickets );
		}

		return $normalized;
	}

	/**
	 * Retrieve SPA user tickets, if SPA exposes them.
	 *
	 * @param int $user_id User ID.
	 * @return int|null
	 */
	private function get_spa_user_tickets( int $user_id ): ?int {
		if ( ! $this->spa_active ) {
			return null;
		}

		// Preferred: dedicated function.
		if ( function_exists( 'ea_spa_get_user_tickets' ) ) {
			$t = (int) call_user_func( 'ea_spa_get_user_tickets', $user_id );
			return $t;
		}

		// Or via filter.
		if ( has_filter( 'ea_spa_get_user_tickets' ) ) {
			$t = (int) apply_filters( 'ea_spa_get_user_tickets', 0, $user_id );
			return $t;
		}

		// Or meta SPA may set.
		$meta = get_user_meta( $user_id, 'ea_spa_tickets', true );
		if ( '' !== $meta && null !== $meta ) {
			return (int) $meta;
		}

		return null;
	}

	/**
	 * Get fallback controls from plugin options (used when SPA is not active or no data provided).
	 *
	 * @return array
	 */
	private function get_fallback_controls(): array {
		$opt = get_option(
			self::OPTION_KEY,
			array(
				'enabled'         => false,
				'block_games'     => false,
				'time_start'      => '',
				'time_end'        => '',
				'require_tickets' => false,
				'default_tickets' => 0,
			)
		);

		if ( empty( $opt['enabled'] ) ) {
			return array();
		}

		$controls = array(
			'games_blocked'     => (bool) ( $opt['block_games'] ?? false ),
			'time_restrictions' => array(),
			'require_tickets'   => (bool) ( $opt['require_tickets'] ?? false ),
		);

		$start = isset( $opt['time_start'] ) ? trim( (string) $opt['time_start'] ) : '';
		$end   = isset( $opt['time_end'] ) ? trim( (string) $opt['time_end'] ) : '';

		if ( $start && $end ) {
			$controls['time_restrictions'] = array(
				'start' => $start,
				'end'   => $end,
			);
		}

		return $controls;
	}

	/**
	 * Get user tickets from EA Gaming (synced from SPA if present).
	 *
	 * @param int $user_id User ID.
	 * @return int
	 */
	private function get_user_tickets( int $user_id ): int {
		$tickets = get_user_meta( $user_id, 'ea_gaming_tickets', true );
		if ( '' === $tickets || null === $tickets ) {
			return 0;
		}
		return (int) $tickets;
	}

	/**
	 * Check if a time falls between start and end, supporting overnight windows.
	 *
	 * @param string $current Current time (H:i).
	 * @param string $start   Start time (H:i).
	 * @param string $end     End time (H:i).
	 * @return bool
	 */
	private function is_time_between( string $current, string $start, string $end ): bool {
		$cur = strtotime( $current );
		$st  = strtotime( $start );
		$en  = strtotime( $end );

		// Overnight window (e.g., 22:00 -> 07:00).
		if ( $st > $en ) {
			return ( $cur >= $st || $cur <= $en );
		}

		return ( $cur >= $st && $cur <= $en );
	}

	/**
	 * Register fallback settings to WP options.
	 *
	 * @return void
	 */
	public function register_settings(): void {
		register_setting(
			'ea_gaming_parent',
			self::OPTION_KEY,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
				'default'           => array(
					'enabled'         => false,
					'block_games'     => false,
					'time_start'      => '',
					'time_end'        => '',
					'require_tickets' => false,
					'default_tickets' => 0,
				),
			)
		);
	}

	/**
	 * Add submenu for Parent Controls under EA Gaming Engine.
	 * Only shows when Student-Parent Access plugin is active.
	 *
	 * @return void
	 */
	public function add_admin_menu(): void {
		// Only add menu if SPA is active.
		if ( ! $this->spa_active ) {
			return;
		}

		// Get dynamic label from SPA plugin.
		$parent_label = $this->get_parent_label();
		$menu_title   = sprintf( __( '%s Controls', 'ea-gaming-engine' ), $parent_label );

		add_submenu_page(
			'ea-gaming-engine',
			$menu_title,
			$menu_title,
			'manage_options',
			'ea-gaming-parent-controls',
			array( $this, 'render_admin_page' )
		);
	}

	/**
	 * Render the Parent Controls settings page.
	 *
	 * @return void
	 */
	public function render_admin_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Get dynamic labels.
		$parent_label  = $this->get_parent_label();
		$parent_plural = $this->get_parent_plural_label();
		$child_label   = $this->get_child_label();
		$child_plural  = $this->get_child_plural_label();

		// Get current users with parent controls enabled.
		$controlled_users = $this->get_controlled_users();

		?>
		<div class="wrap ea-gaming-admin-wrap">
			<div class="ea-gaming-admin-header">
				<h1>
					<span class="dashicons dashicons-admin-users"></span>
					<?php echo esc_html( sprintf( __( '%s Controls', 'ea-gaming-engine' ), $parent_label ) ); ?>
				</h1>
			</div>

			<div class="ea-gaming-settings-section">
				<h2><?php esc_html_e( 'Integration Status', 'ea-gaming-engine' ); ?></h2>
				<div class="ea-gaming-notice <?php echo $this->spa_active ? 'notice-success' : 'notice-info'; ?>">
					<p>
						<strong><?php esc_html_e( 'EA Student-Parent Access:', 'ea-gaming-engine' ); ?></strong>
						<?php if ( $this->spa_active ) : ?>
							<span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span>
							<?php
							/* translators: %1$s: Parent label, %2$s: Child label */
							printf(
								esc_html__( 'Connected! Gaming controls are synchronized with %1$s settings for %2$s accounts.', 'ea-gaming-engine' ),
								esc_html( strtolower( $parent_plural ) ),
								esc_html( strtolower( $child_plural ) )
							);
							?>
						<?php else : ?>
							<span class="dashicons dashicons-warning" style="color: #ffb900;"></span>
							<?php esc_html_e( 'Not detected. Install and activate the EA Student-Parent Access plugin to enable full integration.', 'ea-gaming-engine' ); ?>
						<?php endif; ?>
					</p>
				</div>
			</div>

			<?php if ( $this->spa_active ) : ?>
				<div class="ea-gaming-settings-section">
					<h2><?php echo esc_html( sprintf( __( 'Active %s Controls', 'ea-gaming-engine' ), $parent_label ) ); ?></h2>
					
					<?php if ( ! empty( $controlled_users ) ) : ?>
						<table class="wp-list-table widefat fixed striped">
							<thead>
								<tr>
									<th><?php echo esc_html( $child_label ); ?></th>
									<th><?php echo esc_html( $parent_label ); ?></th>
									<th><?php esc_html_e( 'Gaming Status', 'ea-gaming-engine' ); ?></th>
									<th><?php esc_html_e( 'Time Restrictions', 'ea-gaming-engine' ); ?></th>
									<th><?php esc_html_e( 'Tickets', 'ea-gaming-engine' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $controlled_users as $user_data ) : ?>
									<tr>
										<td>
											<strong><?php echo esc_html( $user_data['child_name'] ); ?></strong>
											<br><small><?php echo esc_html( $user_data['child_email'] ); ?></small>
										</td>
										<td><?php echo esc_html( $user_data['parent_name'] ); ?></td>
										<td>
											<?php if ( $user_data['games_blocked'] ) : ?>
												<span class="ea-gaming-policy-status inactive"><?php esc_html_e( 'Blocked', 'ea-gaming-engine' ); ?></span>
											<?php else : ?>
												<span class="ea-gaming-policy-status active"><?php esc_html_e( 'Allowed', 'ea-gaming-engine' ); ?></span>
											<?php endif; ?>
										</td>
										<td>
											<?php if ( ! empty( $user_data['time_restrictions'] ) ) : ?>
												<?php echo esc_html( $user_data['time_restrictions']['start'] . ' - ' . $user_data['time_restrictions']['end'] ); ?>
											<?php else : ?>
												<em><?php esc_html_e( 'No restrictions', 'ea-gaming-engine' ); ?></em>
											<?php endif; ?>
										</td>
										<td>
											<?php if ( $user_data['require_tickets'] ) : ?>
												<strong><?php echo esc_html( $user_data['tickets'] ); ?></strong> <?php esc_html_e( 'tickets', 'ea-gaming-engine' ); ?>
											<?php else : ?>
												<em><?php esc_html_e( 'Not required', 'ea-gaming-engine' ); ?></em>
											<?php endif; ?>
										</td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					<?php else : ?>
						<p>
						<?php
						/* translators: %1$s: Parent label, %2$s: Child label */
						printf(
							esc_html__( 'No %2$s accounts have %1$s controls configured yet.', 'ea-gaming-engine' ),
							esc_html( strtolower( $parent_label ) ),
							esc_html( strtolower( $child_label ) )
						);
						?>
						</p>
					<?php endif; ?>
				</div>

				<div class="ea-gaming-settings-section">
					<h2><?php esc_html_e( 'How It Works', 'ea-gaming-engine' ); ?></h2>
					<ol style="line-height: 1.8;">
						<li>
							<?php
							/* translators: %s: Parent label plural */
							printf(
								esc_html__( '%s configure gaming restrictions in the Student-Parent Access settings', 'ea-gaming-engine' ),
								esc_html( $parent_plural )
							);
							?>
						</li>
						<li><?php esc_html_e( 'Gaming Engine automatically enforces these restrictions when games are launched', 'ea-gaming-engine' ); ?></li>
						<li><?php esc_html_e( 'Time windows, ticket requirements, and access blocks are checked in real-time', 'ea-gaming-engine' ); ?></li>
						<li>
							<?php
							/* translators: %s: Child label plural */
							printf(
								esc_html__( '%s see appropriate messages when restrictions apply', 'ea-gaming-engine' ),
								esc_html( $child_plural )
							);
							?>
						</li>
					</ol>
				</div>

			<?php else : ?>
				<div class="ea-gaming-settings-section">
					<h2><?php esc_html_e( 'Get Started', 'ea-gaming-engine' ); ?></h2>
					<p><?php esc_html_e( 'To enable parent/teacher controls for gaming:', 'ea-gaming-engine' ); ?></p>
					<ol>
						<li><?php esc_html_e( 'Install the EA Student-Parent Access plugin', 'ea-gaming-engine' ); ?></li>
						<li><?php esc_html_e( 'Configure parent-child relationships', 'ea-gaming-engine' ); ?></li>
						<li><?php esc_html_e( 'Set gaming restrictions per child', 'ea-gaming-engine' ); ?></li>
					</ol>
					<p>
						<a href="<?php echo esc_url( admin_url( 'plugin-install.php?s=EA+Student+Parent+Access&tab=search&type=term' ) ); ?>" class="button button-primary">
							<?php esc_html_e( 'Install Student-Parent Access Plugin', 'ea-gaming-engine' ); ?>
						</a>
					</p>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Sanitize fallback settings.
	 *
	 * @param array $input Raw input.
	 * @return array
	 */
	public function sanitize_settings( $input ): array {
		$out                    = array();
		$out['enabled']         = ! empty( $input['enabled'] ) ? true : false;
		$out['block_games']     = ! empty( $input['block_games'] ) ? true : false;
		$out['time_start']      = isset( $input['time_start'] ) ? preg_replace( '/[^0-9:]/', '', (string) $input['time_start'] ) : '';
		$out['time_end']        = isset( $input['time_end'] ) ? preg_replace( '/[^0-9:]/', '', (string) $input['time_end'] ) : '';
		$out['require_tickets'] = ! empty( $input['require_tickets'] ) ? true : false;
		$out['default_tickets'] = isset( $input['default_tickets'] ) ? (int) $input['default_tickets'] : 0;

		return $out;
	}

	/**
	 * Get the "Parent" label from SPA plugin (could be Teacher, Guardian, etc).
	 *
	 * @return string
	 */
	private function get_parent_label(): string {
		// Try to use the SPA Custom Label class if available.
		if ( class_exists( 'EA_Student_Parent_Access_Custom_Label' ) ) {
			$label = \EA_Student_Parent_Access_Custom_Label::get_label( 'parent' );
			if ( ! empty( $label ) ) {
				return $label;
			}
		}

		// Fallback to default.
		return __( 'Parent', 'ea-gaming-engine' );
	}

	/**
	 * Get the "Child" label from SPA plugin (could be Student, Learner, etc).
	 *
	 * @return string
	 */
	private function get_child_label(): string {
		// Try to use the SPA Custom Label class if available.
		if ( class_exists( 'EA_Student_Parent_Access_Custom_Label' ) ) {
			$label = \EA_Student_Parent_Access_Custom_Label::get_label( 'child' );
			if ( ! empty( $label ) ) {
				return $label;
			}
		}

		// Fallback to default.
		return __( 'Child', 'ea-gaming-engine' );
	}

	/**
	 * Get the "Parents" plural label from SPA plugin.
	 *
	 * @return string
	 */
	private function get_parent_plural_label(): string {
		// Try to use the SPA Custom Label class if available.
		if ( class_exists( 'EA_Student_Parent_Access_Custom_Label' ) ) {
			$label = \EA_Student_Parent_Access_Custom_Label::get_label( 'parents' );
			if ( ! empty( $label ) ) {
				return $label;
			}
		}

		// Fallback to default.
		return __( 'Parents', 'ea-gaming-engine' );
	}

	/**
	 * Get the "Children" plural label from SPA plugin.
	 *
	 * @return string
	 */
	private function get_child_plural_label(): string {
		// Try to use the SPA Custom Label class if available.
		if ( class_exists( 'EA_Student_Parent_Access_Custom_Label' ) ) {
			$label = \EA_Student_Parent_Access_Custom_Label::get_label( 'children' );
			if ( ! empty( $label ) ) {
				return $label;
			}
		}

		// Fallback to default.
		return __( 'Children', 'ea-gaming-engine' );
	}

	/**
	 * Get list of users with parent controls.
	 *
	 * @return array
	 */
	private function get_controlled_users(): array {
		$controlled_users = array();

		// Check if SPA has a function to get parent-child relationships.
		if ( function_exists( 'ea_spa_get_parent_child_relationships' ) ) {
			$relationships = ea_spa_get_parent_child_relationships();

			foreach ( $relationships as $relationship ) {
				$child_id  = $relationship['child_id'] ?? 0;
				$parent_id = $relationship['parent_id'] ?? 0;

				if ( $child_id && $parent_id ) {
					$child  = get_userdata( $child_id );
					$parent = get_userdata( $parent_id );

					if ( $child && $parent ) {
						$controls = $this->get_spa_controls( $child_id );
						$tickets  = $this->get_user_tickets( $child_id );

						$controlled_users[] = array(
							'child_id'          => $child_id,
							'child_name'        => $child->display_name,
							'child_email'       => $child->user_email,
							'parent_id'         => $parent_id,
							'parent_name'       => $parent->display_name,
							'games_blocked'     => $controls['games_blocked'] ?? false,
							'time_restrictions' => $controls['time_restrictions'] ?? array(),
							'require_tickets'   => $controls['require_tickets'] ?? false,
							'tickets'           => $tickets,
						);
					}
				}
			}
		} else {
			// Fallback: Check for users with parent control meta.
			$args = array(
				'meta_query' => array(
					array(
						'key'     => 'ea_spa_parent_controls',
						'compare' => 'EXISTS',
					),
				),
				'number'     => 50,
			);

			$users = get_users( $args );

			foreach ( $users as $user ) {
				$controls = $this->get_spa_controls( $user->ID );
				$tickets  = $this->get_user_tickets( $user->ID );

				// Try to get parent info.
				$parent_id   = get_user_meta( $user->ID, 'ea_spa_parent_id', true );
				$parent_name = __( 'Unknown', 'ea-gaming-engine' );

				if ( $parent_id ) {
					$parent = get_userdata( $parent_id );
					if ( $parent ) {
						$parent_name = $parent->display_name;
					}
				}

				if ( ! empty( $controls ) ) {
					$controlled_users[] = array(
						'child_id'          => $user->ID,
						'child_name'        => $user->display_name,
						'child_email'       => $user->user_email,
						'parent_id'         => $parent_id,
						'parent_name'       => $parent_name,
						'games_blocked'     => $controls['games_blocked'] ?? false,
						'time_restrictions' => $controls['time_restrictions'] ?? array(),
						'require_tickets'   => $controls['require_tickets'] ?? false,
						'tickets'           => $tickets,
					);
				}
			}
		}

		return $controlled_users;
	}

	/**
	 * Check plugin activation and refresh SPA active status.
	 *
	 * @param string $plugin Plugin file path.
	 * @param bool   $network_wide Network-wide activation.
	 * @return void
	 */
	public function check_plugin_activation( $plugin, $network_wide ): void {
		// Check if it's the SPA plugin being activated.
		if ( strpos( $plugin, 'ea-student-parent-access' ) !== false ) {
			$this->spa_active = true;
			// Clear any menu cache if needed.
			wp_cache_delete( 'ea_gaming_admin_menu', 'ea_gaming_engine' );
		}
	}

	/**
	 * Check plugin deactivation and refresh SPA active status.
	 *
	 * @param string $plugin Plugin file path.
	 * @param bool   $network_wide Network-wide deactivation.
	 * @return void
	 */
	public function check_plugin_deactivation( $plugin, $network_wide ): void {
		// Check if it's the SPA plugin being deactivated.
		if ( strpos( $plugin, 'ea-student-parent-access' ) !== false ) {
			$this->spa_active = false;
			// Clear any menu cache if needed.
			wp_cache_delete( 'ea_gaming_admin_menu', 'ea_gaming_engine' );
		}
	}
}