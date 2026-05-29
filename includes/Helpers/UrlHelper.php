<?php
/**
 * URL comparison helpers for scans.
 *
 * @package AssetControl
 */

declare(strict_types=1);

namespace AssetControl\Helpers;

defined( 'ABSPATH' ) || exit;
/**
 * Normalizes and compares admin scan targets to WordPress front URLs.
 */
final class UrlHelper {

	/**
	 * Whether the URL is the site front (home) address.
	 *
	 * Does not use Reading-settings "Home" page ID — visitors may see a theme
	 * front-page.php / custom-home.php while another page is set in Settings.
	 */
	public static function is_site_front_url( string $url ): bool {
		$target = self::normalize_for_compare( $url );
		if ( '' === $target ) {
			return false;
		}

		$candidates = array(
			self::normalize_for_compare( \home_url( '/' ) ),
			self::normalize_for_compare( \site_url( '/' ) ),
		);

		foreach ( $candidates as $candidate ) {
			if ( '' !== $candidate && $target === $candidate ) {
				return true;
			}
		}

		return false;
	}

	public static function normalize_for_compare( string $url ): string {
		$url = trim( rawurldecode( $url ) );
		if ( '' === $url ) {
			return '';
		}

		$clean = \esc_url_raw( $url );
		if ( '' === $clean ) {
			$clean = $url;
		}

		return strtolower( \untrailingslashit( $clean ) );
	}
}
