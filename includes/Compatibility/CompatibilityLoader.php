<?php
/**
 * Third-party compatibility loader.
 *
 * @package AssetControl
 */

declare(strict_types=1);

namespace AssetControl\Compatibility;

defined( 'ABSPATH' ) || exit;
/**
 * Loads compatibility modules for popular plugins and themes.
 */
final class CompatibilityLoader {

	public function init(): void {
		add_action( 'plugins_loaded', array( $this, 'load_modules' ), 20 );
	}

	public function load_modules(): void {
		$modules = array(
			WooCommerce::class,
			Elementor::class,
			Gutenberg::class,
			ContactForm7::class,
			Themes::class,
		);

		foreach ( $modules as $class ) {
			if ( class_exists( $class ) ) {
				( new $class() )->init();
			}
		}
	}
}
