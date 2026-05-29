<?php
/**
 * Injects preload link tags.
 *
 * @package AssetControl
 */

declare(strict_types=1);

namespace AssetControl\Assets\Runtime;

defined( 'ABSPATH' ) || exit;
use AssetControl\Assets\AssetUrlResolver;

/**
 * Outputs <link rel="preload"> tags in wp_head.
 */
final class PreloadInjector {

	private ?RuntimeEngine $engine = null;

	private AssetUrlResolver $url_resolver;

	public function __construct() {
		$this->url_resolver = new AssetUrlResolver();
	}

	public function init( RuntimeEngine $engine ): void {
		$this->engine = $engine;
	}

	/**
	 * @param array<int, array<string, mixed>> $rules
	 */
	public function output( array $rules ): void {
		foreach ( $rules as $rule ) {
			$this->render_preload( $rule );
		}
	}

	/**
	 * @param array<string, mixed> $rule
	 */
	private function render_preload( array $rule ): void {
		$config = is_array( $rule['action_config'] ) ? $rule['action_config'] : array();
		$href   = $config['href'] ?? $config['src'] ?? '';
		$type   = $rule['asset_type'];

		if ( '' === $href ) {
			if ( in_array( $type, array( 'image', 'font' ), true ) ) {
				$handle = $rule['asset_handle'];
				$href   = str_contains( $handle, '://' ) || str_starts_with( $handle, '//' )
					? $this->url_resolver->resolve_custom_url( $handle )
					: '';
			} else {
				$href = $this->url_resolver->resolve_handle( $rule['asset_handle'], $type );
			}
		}

		if ( '' === $href ) {
			return;
		}

		$as          = $config['as'] ?? $this->default_as( $type );
		$crossorigin = ! empty( $config['crossorigin'] ) || 'font' === $as;
		$priority    = $config['fetchpriority'] ?? '';

		$attrs = array(
			'rel'  => 'preload',
			'href' => esc_url( $href ),
			'as'   => esc_attr( $as ),
		);

		if ( $crossorigin ) {
			$attrs['crossorigin'] = 'anonymous';
		}

		if ( in_array( $priority, array( 'high', 'low' ), true ) ) {
			$attrs['fetchpriority'] = esc_attr( $priority );
		}

		$attr_string = '';
		foreach ( $attrs as $key => $value ) {
			$attr_string .= sprintf( ' %s="%s"', $key, $value );
		}

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- attributes escaped above
		echo '<link' . $attr_string . " />\n";
	}

	private function default_as( string $asset_type ): string {
		$map = array(
			'script' => 'script',
			'style'  => 'style',
			'font'   => 'font',
			'image'  => 'image',
		);
		return $map[ $asset_type ] ?? 'fetch';
	}
}
