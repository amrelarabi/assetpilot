/**
 * Admin entry point.
 */
import { createRoot, render } from '@wordpress/element';
import App from './App';

const root = document.getElementById( 'assetpilot-admin-root' );

if ( root ) {
	if ( createRoot ) {
		createRoot( root ).render( <App /> );
	} else {
		render( <App />, root );
	}
}
