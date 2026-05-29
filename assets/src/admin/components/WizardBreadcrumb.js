/**
 * Breadcrumb trail for create / edit / bulk rule wizard.
 */
import { __ } from '@wordpress/i18n';

export default function WizardBreadcrumb( { items = [] } ) {
	if ( ! items.length ) {
		return null;
	}

	return (
		<nav className="assetpilot-wizard-breadcrumb" aria-label={ __( 'Wizard navigation', 'assetpilot' ) }>
			<ol className="assetpilot-wizard-breadcrumb__list">
				{ items.map( ( item, index ) => {
					const isLast = index === items.length - 1;
					return (
						<li
							key={ `${ item.label }-${ index }` }
							className={ `assetpilot-wizard-breadcrumb__item${ isLast ? ' is-current' : '' }` }
						>
							{ ! isLast && item.href ? (
								<a href={ item.href }>{ item.label }</a>
							) : (
								<span aria-current={ isLast ? 'page' : undefined }>{ item.label }</span>
							) }
						</li>
					);
				} ) }
			</ol>
		</nav>
	);
}
