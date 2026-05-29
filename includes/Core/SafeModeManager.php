<?php
/**
 * Safe mode and automatic runtime suspension.
 *
 * @package AssetControl
 */

declare(strict_types=1);

namespace AssetControl\Core;

defined( 'ABSPATH' ) || exit;
/**
 * Disables frontend runtime modifications only (admin, REST, and asset scan stay available).
 */
final class SafeModeManager {

	private const QUERY_SAFE_MODE   = 'assetpilot-safe-mode';
	private const QUERY_RESUME      = 'assetpilot-resume-runtime';
	private const COOKIE_MANUAL     = 'assetpilot_safe_mode';
	private const OPTION_MANUAL     = 'assetpilot_manual_safe_mode';
	private const OPTION_SUSPEND    = 'assetpilot_runtime_suspend';

	public function init(): void {
		add_action( 'admin_init', array( $this, 'handle_admin_query_actions' ) );
		add_action( 'admin_init', array( $this, 'maybe_sync_cookie_from_option' ) );
		add_action( 'admin_notices', array( $this, 'render_admin_notices' ) );
	}

	public static function is_manual_enabled(): bool {
		if ( defined( 'ASSETPILOT_SAFE_MODE' ) && ASSETPILOT_SAFE_MODE ) {
			return true;
		}

		// Site-wide flag — safe mode must affect all visitors, not only the admin browser.
		if ( (bool) get_option( self::OPTION_MANUAL, false ) ) {
			return true;
		}

		// Legacy: cookie-only installs from before site-wide safe mode.
		if ( self::has_cookie() ) {
			update_option( self::OPTION_MANUAL, '1', true );
			return true;
		}

		return false;
	}

	public static function is_auto_suspended(): bool {
		$data = get_option( self::OPTION_SUSPEND, array() );
		if ( ! is_array( $data ) || empty( $data['until'] ) ) {
			return false;
		}

		$until = (int) $data['until'];
		if ( $until <= time() ) {
			self::clear_auto_suspend();
			return false;
		}

		return true;
	}

	public static function is_runtime_disabled(): bool {
		return self::is_manual_enabled() || self::is_auto_suspended();
	}

	/**
	 * @return array{until: int, reason: string, failure_count: int, triggered_at: int}|null
	 */
	public static function get_suspend_info(): ?array {
		if ( ! self::is_auto_suspended() ) {
			return null;
		}

		$data = get_option( self::OPTION_SUSPEND, array() );
		if ( ! is_array( $data ) ) {
			return null;
		}

		return array(
			'until'          => (int) ( $data['until'] ?? 0 ),
			'reason'         => (string) ( $data['reason'] ?? 'fatal_errors' ),
			'failure_count'  => (int) ( $data['failure_count'] ?? 0 ),
			'triggered_at'   => (int) ( $data['triggered_at'] ?? 0 ),
		);
	}

	public static function set_auto_suspend( int $failure_count, string $reason = 'fatal_errors' ): void {
		$duration = (int) apply_filters( 'assetpilot_runtime_suspend_seconds', 30 * MINUTE_IN_SECONDS );

		update_option(
			self::OPTION_SUSPEND,
			array(
				'until'          => time() + max( 60, $duration ),
				'reason'         => $reason,
				'failure_count'  => $failure_count,
				'triggered_at'   => time(),
			),
			false
		);
	}

	public static function clear_auto_suspend(): void {
		delete_option( self::OPTION_SUSPEND );
		RuntimeHealthMonitor::reset_failures();
	}

	public function handle_admin_query_actions(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET[ self::QUERY_RESUME ] ) && '1' === sanitize_text_field( wp_unslash( $_GET[ self::QUERY_RESUME ] ) ) ) {
			self::clear_auto_suspend();
			wp_safe_redirect( remove_query_arg( self::QUERY_RESUME ) );
			exit;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $_GET[ self::QUERY_SAFE_MODE ] ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$enable = '1' === sanitize_text_field( wp_unslash( $_GET[ self::QUERY_SAFE_MODE ] ) );

		if ( $enable ) {
			self::set_manual_enabled( true );
		} else {
			self::set_manual_enabled( false );
		}

		wp_safe_redirect( remove_query_arg( self::QUERY_SAFE_MODE ) );
		exit;
	}

	public function maybe_sync_cookie_from_option(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( self::is_manual_enabled() && ! self::has_cookie() ) {
			self::set_cookie( true );
		}
	}

	public static function set_manual_enabled( bool $enabled ): void {
		if ( $enabled ) {
			update_option( self::OPTION_MANUAL, '1', true );
			self::set_cookie( true );
			return;
		}

		delete_option( self::OPTION_MANUAL );
		self::set_cookie( false );
	}

	private static function has_cookie(): bool {
		return isset( $_COOKIE[ self::COOKIE_MANUAL ] ) && '1' === $_COOKIE[ self::COOKIE_MANUAL ];
	}

	private static function set_cookie( bool $enabled ): void {
		if ( $enabled ) {
			setcookie( self::COOKIE_MANUAL, '1', time() + DAY_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true );
			$_COOKIE[ self::COOKIE_MANUAL ] = '1';
			return;
		}

		setcookie( self::COOKIE_MANUAL, '', time() - DAY_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true );
		unset( $_COOKIE[ self::COOKIE_MANUAL ] );
	}

	public function render_admin_notices(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( self::is_manual_enabled() ) {
			$disable_url = add_query_arg( self::QUERY_SAFE_MODE, '0', admin_url() );
			?>
			<div class="notice notice-warning">
				<p>
					<strong><?php esc_html_e( 'AssetPilot Safe Mode is active.', 'assetpilot' ); ?></strong>
					<?php esc_html_e( 'Runtime asset modifications are disabled.', 'assetpilot' ); ?>
					<a href="<?php echo esc_url( $disable_url ); ?>"><?php esc_html_e( 'Disable Safe Mode', 'assetpilot' ); ?></a>
				</p>
			</div>
			<?php
			return;
		}

		$info = self::get_suspend_info();
		if ( null === $info ) {
			return;
		}

		$resume_url = add_query_arg( self::QUERY_RESUME, '1', admin_url( 'admin.php?page=assetpilot-settings' ) );
		$until      = wp_date( get_option( 'time_format' ) . ' ' . get_option( 'date_format' ), $info['until'] );
		?>
		<div class="notice notice-error">
			<p>
				<strong><?php esc_html_e( 'AssetPilot paused runtime rules automatically.', 'assetpilot' ); ?></strong>
				<?php
				printf(
					/* translators: 1: failure count, 2: datetime when suspension ends */
					esc_html__( 'Detected %1$d frontend errors in a short window. Runtime modifications stay off until %2$s.', 'assetpilot' ),
					(int) $info['failure_count'],
					esc_html( $until )
				);
				?>
				<a href="<?php echo esc_url( $resume_url ); ?>"><?php esc_html_e( 'Resume runtime now', 'assetpilot' ); ?></a>
			</p>
		</div>
		<?php
	}

	public static function recovery_url(): string {
		return add_query_arg( self::QUERY_SAFE_MODE, '1', admin_url() );
	}

	public static function resume_runtime_url(): string {
		return add_query_arg( self::QUERY_RESUME, '1', admin_url( 'admin.php?page=assetpilot-settings' ) );
	}
}
