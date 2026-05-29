/**
 * Rule impact and risk preview (review step).
 */
import { __, sprintf } from '@wordpress/i18n';

const RISK_CLASS = {
	low: 'assetpilot-risk--low',
	medium: 'assetpilot-risk--medium',
	high: 'assetpilot-risk--high',
};

export default function RuleImpactPreview( { impactPreview, loading } ) {
	if ( loading ) {
		return (
			<div className="assetpilot-impact-preview assetpilot-impact-preview--loading">
				<p>{ __( 'Estimating impact…', 'assetpilot' ) }</p>
			</div>
		);
	}

	if ( ! impactPreview ) {
		return null;
	}

	const { impact, risk } = impactPreview;
	const lines = impact?.summary_lines || [];

	return (
		<div className="assetpilot-impact-preview">
			<h3 className="assetpilot-impact-preview__title">
				{ __( 'Estimated impact', 'assetpilot' ) }
			</h3>

			{ risk?.level && (
				<p className={ `assetpilot-risk ${ RISK_CLASS[ risk.level ] || '' }` }>
					<span className="assetpilot-risk__label">{ risk.label }</span>
					{ risk.reasons?.length > 0 && (
						<span className="assetpilot-risk__reasons">
							{ risk.reasons.join( ' ' ) }
						</span>
					) }
				</p>
			) }

			{ impact?.bulk_asset_count > 1 && (
				<p className="assetpilot-impact-preview__bulk">
					{ sprintf(
						/* translators: %d: number of assets */
						__( 'Dependency and risk checks include all %d assets in this bulk rule.', 'assetpilot' ),
						impact.bulk_asset_count
					) }
				</p>
			) }

			{ lines.length > 0 ? (
				<>
					<p className="assetpilot-impact-preview__lead">
						{ __( 'This rule may affect:', 'assetpilot' ) }
					</p>
					<ul className="assetpilot-impact-preview__list">
						{ lines.map( ( line ) => (
							<li key={ line }>{ line }</li>
						) ) }
					</ul>
				</>
			) : (
				<p className="assetpilot-impact-preview__empty">
					{ __( 'Add conditions to see where this rule applies.', 'assetpilot' ) }
				</p>
			) }

			{ impact?.uses_scan_history && (
				<p className="assetpilot-impact-preview__note">
					{ __( 'Page matches are based on Assets Explorer scan history (no live crawl).', 'assetpilot' ) }
				</p>
			) }
		</div>
	);
}
