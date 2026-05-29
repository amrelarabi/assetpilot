<?php
/**
 * Theme compatibility (GeneratePress, Astra, Hello Elementor).
 *
 * @package AssetControl
 */

declare(strict_types=1);

namespace AssetControl\Compatibility;

defined( 'ABSPATH' ) || exit;
/**
 * Popular theme compatibility.
 */
final class Themes {

	/** @var array<string, string> */
	private array $supported = array(
		'generatepress'   => 'GeneratePress',
		'astra'           => 'Astra',
		'hello-elementor' => 'Hello Elementor',
	);

	public function init(): void {
		$theme = wp_get_theme()->get_stylesheet();
		if ( isset( $this->supported[ $theme ] ) ) {
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
			add_action( 'wp_enqueue_scripts', array( $this, 'theme_assets_ready' ), 100 );
		}
	}

	public function theme_assets_ready(): void {
		do_action( 'assetpilot_theme_assets_registered', wp_get_theme()->get_stylesheet() );
	}
}
