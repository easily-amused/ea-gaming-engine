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
		add_filter( 'ea_gaming_parent_controls', [ $this, 'filter_parent_controls' ], 10, 2 );

		// Enforce parent controls at the PolicyEngine decision point.
		// Run early enough (8) to short-circuit if needed, but allow other integrations even earlier if any.
		add_filter( 'ea_gaming_can_user_play', [ $this, 'filter_can_user_play' ], 8, 3 );

		// Simple fallback admin settings (optional; used if SPA is not active or returns no data).
		add_action( 'admin_init', [ $this, 'register_settings' ] );
		add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
	}

	/**
		* Detect if the EA Student-Parent Access plugin is available.
		*
		* @return bool
		*/
	private function detect_spa() : bool {
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
	public function filter_parent_controls( array $controls, int $user_id ) : array {
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

		$controls = apply_filters( 'ea_gaming_parent_controls', [], $user_id );

		// 1) Hard block from parent/guardian.
		if ( ! empty( $controls['games_blocked'] ) ) {
			return [
				'can_play' => false,
				'reason'   => __( 'Games have been disabled by your parent/guardian.', 'ea-gaming-engine' ),
				'policy'   => 'parent_control',
			];
		}

		// 2) Time restriction window check.
		if ( ! empty( $controls['time_restrictions']['start'] ) && ! empty( $controls['time_restrictions']['end'] ) ) {
			$current = current_time( 'H:i' );
			$start   = $controls['time_restrictions']['start'];
			$end     = $controls['time_restrictions']['end'];

			if ( ! $this->is_time_between( $current, $start, $end ) ) {
				return [
					'can_play' => false,
					'reason'   => sprintf(
						/* translators: %1$s: start time, %2$s: end time */
						__( 'Games are only available between %1$s and %2$s.', 'ea-gaming-engine' ),
						$start,
						$end
					),
					'policy'   => 'parent_control',
				];
			}
		}

		// 3) Ticket requirement check.
		if ( ! empty( $controls['require_tickets'] ) ) {
			$tickets = $this->get_user_tickets( $user_id );
			if ( $tickets <= 0 ) {
				return [
					'can_play' => false,
					'reason'   => __( 'You need tickets to play. Please ask your parent/guardian for more.', 'ea-gaming-engine' ),
					'policy'   => 'parent_control',
				];
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
	private function get_spa_controls( int $user_id ) : array {
		if ( ! $this->spa_active ) {
			return [];
		}

		$spa_controls = [];

		// Preferred: dedicated function.
		if ( function_exists( 'ea_spa_get_child_controls' ) ) {
			$spa_controls = (array) call_user_func( 'ea_spa_get_child_controls', $user_id );
		} elseif ( has_filter( 'ea_spa_get_child_controls' ) ) {
			// Or via filter.
			$spa_controls = (array) apply_filters( 'ea_spa_get_child_controls', [], $user_id );
		} else {
			// Fallback to a common user meta key if SPA stores structured data there.
			$spa_controls = (array) get_user_meta( $user_id, 'ea_spa_parent_controls', true );
		}

		if ( empty( $spa_controls ) ) {
			return [];
		}

		$normalized = [
			'games_blocked'     => false,
			'time_restrictions' => [],
			'require_tickets'   => false,
		];

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
			$normalized['time_restrictions'] = [
				'start' => $start,
				'end'   => $end,
			];
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
	private function get_spa_user_tickets( int $user_id ) : ?int {
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
	private function get_fallback_controls() : array {
		$opt = get_option(
			self::OPTION_KEY,
			[
				'enabled'         => false,
				'block_games'     => false,
				'time_start'      => '',
				'time_end'        => '',
				'require_tickets' => false,
				'default_tickets' => 0,
			]
		);

		if ( empty( $opt['enabled'] ) ) {
			return [];
		}

		$controls = [
			'games_blocked'     => (bool) ( $opt['block_games'] ?? false ),
			'time_restrictions' => [],
			'require_tickets'   => (bool) ( $opt['require_tickets'] ?? false ),
		];

		$start = isset( $opt['time_start'] ) ? trim( (string) $opt['time_start'] ) : '';
		$end   = isset( $opt['time_end'] ) ? trim( (string) $opt['time_end'] ) : '';

		if ( $start && $end ) {
			$controls['time_restrictions'] = [
				'start' => $start,
				'end'   => $end,
			];
		}

		return $controls;
	}

	/**
		* Get user tickets from EA Gaming (synced from SPA if present).
		*
		* @param int $user_id User ID.
		* @return int
		*/
	private function get_user_tickets( int $user_id ) : int {
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
	private function is_time_between( string $current, string $start, string $end ) : bool {
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
	public function register_settings() : void {
		register_setting(
			'ea_gaming_parent',
			self::OPTION_KEY,
			[
				'type'              => 'array',
				'sanitize_callback' => [ $this, 'sanitize_settings' ],
				'default'           => [
					'enabled'         => false,
					'block_games'     => false,
					'time_start'      => '',
					'time_end'        => '',
					'require_tickets' => false,
					'default_tickets' => 0,
				],
			]
		);
	}

	/**
		* Add submenu for Parent Controls under EA Gaming Engine.
		*
		* @return void
		*/
	public function add_admin_menu() : void {
		add_submenu_page(
			'ea-gaming-engine',
			__( 'Parent Controls', 'ea-gaming-engine' ),
			__( 'Parent Controls', 'ea-gaming-engine' ),
			'manage_options',
			'ea-gaming-parent-controls',
			[ $this, 'render_admin_page' ]
		);
	}

	/**
		* Render the simple fallback Parent Controls settings page.
		*
		* @return void
		*/
	public function render_admin_page() : void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$opt = get_option(
			self::OPTION_KEY,
			[
				'enabled'         => false,
				'block_games'     => false,
				'time_start'      => '',
				'time_end'        => '',
				'require_tickets' => false,
				'default_tickets' => 0,
			]
		);

		?>
		<div class="wrap ea-gaming-admin-wrap">
			<h1><?php esc_html_e( 'Parent Controls (Fallback)', 'ea-gaming-engine' ); ?></h1>
			<p>
				<?php
				if ( $this->spa_active ) {
					esc_html_e( 'EA Student-Parent Access plugin detected. These fallback settings are only used if SPA does not provide controls for a user.', 'ea-gaming-engine' );
				} else {
					esc_html_e( 'EA Student-Parent Access plugin not detected. Use these fallback settings to apply basic controls.', 'ea-gaming-engine' );
				}
				?>
			</p>
			<form method="post" action="options.php">
				<?php
				settings_fields( 'ea_gaming_parent' );
				?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Enable Fallback Controls', 'ea-gaming-engine' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[enabled]" value="1" <?php checked( ! empty( $opt['enabled'] ) ); ?> />
								<?php esc_html_e( 'Apply these settings when SPA is not active or has no data for the user', 'ea-gaming-engine' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Block All Games', 'ea-gaming-engine' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[block_games]" value="1" <?php checked( ! empty( $opt['block_games'] ) ); ?> />
								<?php esc_html_e( 'Prevent playing any games', 'ea-gaming-engine' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Allowed Time Window', 'ea-gaming-engine' ); ?></th>
						<td>
							<label>
								<?php esc_html_e( 'Start (HH:MM)', 'ea-gaming-engine' ); ?>
								<input type="time" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[time_start]" value="<?php echo esc_attr( $opt['time_start'] ); ?>" placeholder="15:00" />
							</label>
								
							<label>
								<?php esc_html_e( 'End (HH:MM)', 'ea-gaming-engine' ); ?>
								<input type="time" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[time_end]" value="<?php echo esc_attr( $opt['time_end'] ); ?>" placeholder="19:00" />
							</label>
							<p class="description"><?php esc_html_e( 'Leave blank to disable time window restriction.', 'ea-gaming-engine' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Require Tickets', 'ea-gaming-engine' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[require_tickets]" value="1" <?php checked( ! empty( $opt['require_tickets'] ) ); ?> />
								<?php esc_html_e( 'Users must have tickets to play', 'ea-gaming-engine' ); ?>
							</label>
							<p class="description"><?php esc_html_e( 'Ticket balances are stored per user under the "ea_gaming_tickets" user meta or provided by SPA if present.', 'ea-gaming-engine' ); ?></p>
						</td>
					</tr>
				</table>
				<?php submit_button( __( 'Save Changes', 'ea-gaming-engine' ) ); ?>
			</form>
		</div>
		<?php
	}

	/**
		* Sanitize fallback settings.
		*
		* @param array $input Raw input.
		* @return array
		*/
	public function sanitize_settings( $input ) : array {
		$out                     = [];
		$out['enabled']          = ! empty( $input['enabled'] ) ? true : false;
		$out['block_games']      = ! empty( $input['block_games'] ) ? true : false;
		$out['time_start']       = isset( $input['time_start'] ) ? preg_replace( '/[^0-9:]/', '', (string) $input['time_start'] ) : '';
		$out['time_end']         = isset( $input['time_end'] ) ? preg_replace( '/[^0-9:]/', '', (string) $input['time_end'] ) : '';
		$out['require_tickets']  = ! empty( $input['require_tickets'] ) ? true : false;
		$out['default_tickets']  = isset( $input['default_tickets'] ) ? (int) $input['default_tickets'] : 0;

		return $out;
	}
}