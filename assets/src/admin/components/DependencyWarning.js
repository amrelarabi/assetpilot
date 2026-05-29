/**
 * Dependency warning component.
 */
import { Notice } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

export default function DependencyWarning( { warnings, dependents } ) {
	if ( ! warnings?.length ) {
		return null;
	}

	return (
		<Notice status="warning" isDismissible={ false }>
			<p><strong>{ __( 'Dependency Warning', 'assetpilot' ) }</strong></p>
			<ul>
				{ warnings.map( ( w, i ) => (
					<li key={ i }>{ w }</li>
				) ) }
			</ul>
			{ dependents?.length > 0 && (
				<p>
					{ __( 'Dependents:', 'assetpilot' ) }{ ' ' }
					{ dependents.map( ( d ) => d.handle ).join( ', ' ) }
				</p>
			) }
		</Notice>
	);
}
