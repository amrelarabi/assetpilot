/**
 * Runtime verification status badge.
 */
import { __ } from '@wordpress/i18n';

const LABELS = {
	verified: __( 'Verified', 'assetpilot' ),
	partial: __( 'Partially applied', 'assetpilot' ),
	failed: __( 'Failed', 'assetpilot' ),
	skipped: __( 'Skipped', 'assetpilot' ),
	unavailable: __( 'Unavailable', 'assetpilot' ),
};

export default function VerificationBadge( { verification, showMessage = false, showUrl = false } ) {
	if ( ! verification?.status ) {
		return null;
	}

	const status = verification.status;
	const label = LABELS[ status ] || status;
	const title = [ verification.message, verification.url ].filter( Boolean ).join( ' — ' ) || label;

	return (
		<span className="assetpilot-verify" title={ title }>
			<span className={ `assetpilot-badge assetpilot-badge--verify assetpilot-badge--verify-${ status }` }>
				{ label }
			</span>
			{ showMessage && verification.message && (
				<span className="assetpilot-verify__message">{ verification.message }</span>
			) }
			{ showUrl && verification.url && (
				<span className="assetpilot-verify__url">
					<code>{ verification.url }</code>
				</span>
			) }
		</span>
	);
}
