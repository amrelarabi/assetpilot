<?php
/**
 * Device condition (mobile / desktop).
 *
 * @package AssetControl
 */

declare(strict_types=1);

namespace AssetControl\Rules\Conditions;

defined( 'ABSPATH' ) || exit;
final class DeviceConditionHandler implements ConditionHandlerInterface {

	public function is_active( array $conditions ): bool {
		return ! empty( $conditions['device'] );
	}

	public function matches( array $conditions ): bool {
		$device    = (string) $conditions['device'];
		$is_mobile = wp_is_mobile();

		if ( 'mobile' === $device ) {
			return $is_mobile;
		}
		if ( 'desktop' === $device ) {
			return ! $is_mobile;
		}

		return true;
	}
}
