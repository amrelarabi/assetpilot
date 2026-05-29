<?php
/**
 * Core WordPress asset protection warnings.
 *
 * @package AssetControl
 */

declare(strict_types=1);

namespace AssetControl\Validation\Validators;

defined( 'ABSPATH' ) || exit;
use AssetControl\Validation\RuleValidationContext;
use AssetControl\Validation\RuleValidatorInterface;
use AssetControl\Validation\ValidationResult;

/**
 * Warns (never blocks) when touching critical core bundles.
 */
final class CoreAssetProtectionValidator implements RuleValidatorInterface {

	/**
	 * @var array<string, string> handle => label
	 */
	private const PROTECTED = array(
		'jquery'        => 'jQuery',
		'jquery-core'   => 'jQuery',
		'wp-hooks'      => 'wp-hooks',
		'react'         => 'React',
		'react-dom'     => 'React',
		'wp-element'    => 'wp-element',
	);

	public function validate( RuleValidationContext $context ): array {
		$handle = $context->handle();
		$key    = strtolower( $handle );

		if ( ! isset( self::PROTECTED[ $key ] ) ) {
			return array();
		}

		$label = self::PROTECTED[ $key ];

		return array(
			array(
				'code'     => 'core_asset_affected',
				'severity' => ValidationResult::SEVERITY_WARNING,
				'message'  => sprintf(
					/* translators: 1: core label, 2: action */
					__( 'You are modifying core asset "%1$s" (%2$s). This can break the block editor, admin bar, or plugin scripts.', 'assetpilot' ),
					$label,
					$context->action()
				),
			),
		);
	}
}
