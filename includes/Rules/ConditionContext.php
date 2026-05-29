<?php
/**
 * Request fingerprint for condition evaluation caching.
 *
 * @package AssetControl
 */

declare(strict_types=1);

namespace AssetControl\Rules;

defined( 'ABSPATH' ) || exit;
use AssetControl\Helpers\Cache;

/**
 * Stable per-request context key for condition match memoization.
 */
final class ConditionContext {

	public static function fingerprint(): string {
		return Cache::request(
			'assetpilot_condition_fingerprint',
			static function (): string {
				$user = wp_get_current_user();
				$roles = ( $user instanceof \WP_User && ! empty( $user->roles ) )
					? implode( ',', $user->roles )
					: '';

				$request_uri = '';
				if ( isset( $_SERVER['REQUEST_URI'] ) ) {
					$request_uri = sanitize_text_field( wp_unslash( (string) $_SERVER['REQUEST_URI'] ) );
				}

				$request_method = 'GET';
				if ( isset( $_SERVER['REQUEST_METHOD'] ) ) {
					$request_method = sanitize_text_field( wp_unslash( (string) $_SERVER['REQUEST_METHOD'] ) );
				}

				$parts = array(
					$request_uri,
					is_user_logged_in() ? '1' : '0',
					$roles,
					(string) get_queried_object_id(),
					is_singular() ? 'singular' : ( is_archive() ? 'archive' : 'other' ),
					$request_method,
				);

				return md5( implode( "\n", $parts ) );
			}
		);
	}
}
