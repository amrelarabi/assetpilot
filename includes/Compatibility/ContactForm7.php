<?php
/**
 * Contact Form 7 compatibility.
 *
 * @package AssetControl
 */

declare(strict_types=1);

namespace AssetControl\Compatibility;

defined( 'ABSPATH' ) || exit;
/**
 * CF7 asset handle registration awareness.
 */
final class ContactForm7 {

	public function init(): void {
		if ( ! defined( 'WPCF7_VERSION' ) ) {
			return;
		}

		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
		add_action( 'wp_enqueue_scripts', array( $this, 'register_known_handles' ), 5 );
	}

	public function register_known_handles(): void {
		// CF7 registers wpcf7 and wpcf7-recaptcha handles — documented for admin UI hints.
		do_action( 'assetpilot_cf7_compatible' );
	}
}
