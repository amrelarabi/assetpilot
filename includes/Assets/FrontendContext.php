<?php
/**
 * Bootstraps frontend enqueue context without HTTP loopback.
 *
 * @package AssetControl
 */

declare(strict_types=1);

namespace AssetControl\Assets;

defined( 'ABSPATH' ) || exit;
/**
 * @deprecated Use AssetCapture directly. Kept for backward compatibility.
 */
final class FrontendContext {

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public function collect_for_url( string $url ): array {
		$result = ( new AssetCapture() )->capture_for_url( $url );
		return $result['assets'];
	}
}
