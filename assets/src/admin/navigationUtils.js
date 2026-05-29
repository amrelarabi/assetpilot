/**
 * Admin navigation URL helpers.
 */

export function adminPageUrl( page, params = {} ) {
	const base = window.assetpilotAdmin?.adminUrl || '/wp-admin/';
	const url = new URL( `${ base }admin.php`, window.location.origin );
	url.searchParams.set( 'page', page );
	Object.entries( params ).forEach( ( [ key, value ] ) => {
		if ( value !== undefined && value !== null && value !== '' ) {
			url.searchParams.set( key, value );
		}
	} );
	return url.toString();
}

export function assetsExplorerUrl( { scanUrl = '', tab = '', handle = '', type = '' } = {} ) {
	const params = {};
	if ( scanUrl ) {
		params.scan_url = scanUrl;
	}
	if ( tab ) {
		params.assetpilot_tab = tab;
	}
	if ( handle ) {
		params.handle = handle;
	}
	if ( type ) {
		params.type = type;
	}
	return adminPageUrl( 'assetpilot-assets', params );
}

export function rulesListUrl( extra = {} ) {
	return adminPageUrl( 'assetpilot-rules', extra );
}
