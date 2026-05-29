/**
 * Structured validation issues from the rule pipeline.
 */
import { Notice } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

const SEVERITY_ORDER = [ 'danger', 'warning', 'info' ];

const SEVERITY_LABELS = {
	danger: __( 'Critical', 'assetpilot' ),
	warning: __( 'Warning', 'assetpilot' ),
	info: __( 'Notice', 'assetpilot' ),
};

const STATUS_MAP = {
	danger: 'error',
	warning: 'warning',
	info: 'info',
};

export default function ValidationWarnings( { validation, className = '' } ) {
	const issues = validation?.issues || [];
	if ( ! issues.length ) {
		return null;
	}

	const grouped = SEVERITY_ORDER.reduce( ( acc, severity ) => {
		acc[ severity ] = issues.filter( ( i ) => ( i.severity || 'warning' ) === severity );
		return acc;
	}, {} );

	return (
		<div className={ `assetpilot-validation ${ className }`.trim() }>
			{ SEVERITY_ORDER.map( ( severity ) => {
				const list = grouped[ severity ];
				if ( ! list?.length ) {
					return null;
				}
				return (
					<Notice
						key={ severity }
						className="assetpilot-validation__notice"
						status={ STATUS_MAP[ severity ] || 'warning' }
						isDismissible={ false }
					>
						<p className="assetpilot-validation__heading">
							<strong>{ SEVERITY_LABELS[ severity ] }</strong>
							{ validation?.requires_confirmation && severity === 'danger' && (
								<span className="assetpilot-validation__confirm-hint">
									{ ' ' }
									— { __( 'confirmation required to save', 'assetpilot' ) }
								</span>
							) }
						</p>
						<ul className="assetpilot-validation__list">
							{ list.map( ( issue ) => (
								<li key={ `${ issue.code }-${ issue.message }` }>{ issue.message }</li>
							) ) }
						</ul>
					</Notice>
				);
			} ) }
		</div>
	);
}
