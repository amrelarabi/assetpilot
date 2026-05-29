/**
 * REST API client.
 */
import apiFetch from '@wordpress/api-fetch';
import { enrichApiError, getApiErrorMessage } from './apiErrors';

export { getApiErrorMessage };

apiFetch.use( ( options, next ) => {
	const nonce = window.assetpilotAdmin?.nonce;
	if ( nonce ) {
		options.headers = {
			...options.headers,
			'X-WP-Nonce': nonce,
		};
	}
	return next( options ).catch( ( err ) => {
		throw enrichApiError( err );
	} );
} );

const API = '/assetpilot/v1';

export const fetchPageContext = ( url ) =>
	apiFetch( {
		path: `${ API }/page-context?url=${ encodeURIComponent( url ) }`,
	} );

export const fetchDashboard = ( params = {} ) => {
	const query = new URLSearchParams();
	Object.entries( params ).forEach( ( [ key, value ] ) => {
		if ( value !== undefined && value !== null && value !== '' ) {
			query.set( key, String( value ) );
		}
	} );
	const qs = query.toString();
	return apiFetch( { path: `${ API }/dashboard${ qs ? `?${ qs }` : '' }` } );
};

const isInvalidJsonError = ( err ) =>
	err?.code === 'invalid_json' ||
	( err?.message && err.message.includes( 'valid JSON' ) );

export const fetchAssets = async ( params = {}, retry = 1 ) => {
	const query = new URLSearchParams( params ).toString();
	const path = `${ API }/assets${ query ? `?${ query }` : '' }`;

	for ( let attempt = 0; attempt <= retry; attempt++ ) {
		try {
			return await apiFetch( { path } );
		} catch ( err ) {
			if ( attempt >= retry || ! isInvalidJsonError( err ) ) {
				throw err;
			}
		}
	}

	return { assets: [], total: 0 };
};

export const fetchDependencies = ( handle, type, action ) =>
	apiFetch( {
		path: `${ API }/assets/${ encodeURIComponent( handle ) }/dependencies?type=${ type }&action=${ action }`,
	} );

export const fetchAssetDetails = ( handle, type, { scanUrl = '', snapshot = {} } = {} ) => {
	const query = new URLSearchParams( { type: type || 'script' } );
	if ( scanUrl ) {
		query.set( 'scan_url', scanUrl );
	}
	if ( snapshot.enqueued ) {
		query.set( 'enqueued', '1' );
	}
	if ( snapshot.size ) {
		query.set( 'size', String( snapshot.size ) );
	}
	if ( snapshot.src ) {
		query.set( 'src', snapshot.src );
	}
	if ( snapshot.origin ) {
		query.set( 'origin', snapshot.origin );
	}
	if ( snapshot.source ) {
		query.set( 'source', snapshot.source );
	}
	if ( snapshot.version ) {
		query.set( 'version', snapshot.version );
	}
	return apiFetch( {
		path: `${ API }/assets/${ encodeURIComponent( handle ) }/details?${ query.toString() }`,
	} );
};

export const fetchRules = ( params = {} ) => {
	const query = new URLSearchParams();
	Object.entries( params ).forEach( ( [ key, value ] ) => {
		if ( value !== undefined && value !== null && value !== '' ) {
			query.set( key, String( value ) );
		}
	} );
	const qs = query.toString();
	return apiFetch( { path: `${ API }/rules${ qs ? `?${ qs }` : '' }` } );
};

export const bulkRulesAction = ( action, ids ) =>
	apiFetch( {
		path: `${ API }/rules/bulk`,
		method: 'POST',
		data: { action, ids },
	} );

export const bulkCreateRules = ( data ) =>
	apiFetch( {
		path: `${ API }/rules/bulk-create`,
		method: 'POST',
		data,
	} );

export const fetchRule = ( id ) => apiFetch( { path: `${ API }/rules/${ id }` } );

const unwrapRuleResponse = ( res ) => ( res?.rule ? res.rule : res );

export const validateRule = ( data, ruleId = null, { scanUrl = '' } = {} ) =>
	apiFetch( {
		path: `${ API }/rules/validate`,
		method: 'POST',
		data: {
			rule: data,
			...( ruleId ? { rule_id: ruleId } : {} ),
			...( scanUrl ? { scan_url: scanUrl } : {} ),
		},
	} );

export const createRule = async ( data ) => {
	const res = await apiFetch( { path: `${ API }/rules`, method: 'POST', data } );
	return unwrapRuleResponse( res );
};

export const updateRule = async ( id, data ) => {
	const res = await apiFetch( { path: `${ API }/rules/${ id }`, method: 'PUT', data } );
	return unwrapRuleResponse( res );
};

export const deleteRule = ( id ) =>
	apiFetch( { path: `${ API }/rules/${ id }`, method: 'DELETE' } );

export const duplicateRule = ( id ) =>
	apiFetch( { path: `${ API }/rules/${ id }/duplicate`, method: 'POST' } );

export const analyzePage = ( url ) =>
	apiFetch( { path: `${ API }/analyze`, method: 'POST', data: { url } } );

export const verifyRules = ( url, filters = {} ) =>
	apiFetch( {
		path: `${ API }/verify`,
		method: 'POST',
		data: { url, ...filters },
	} );

export const fetchScans = ( { page = 1, per_page = 20, search = '' } = {} ) => {
	const query = new URLSearchParams();
	query.set( 'page', String( page ) );
	query.set( 'per_page', String( per_page ) );
	if ( search ) {
		query.set( 'search', search );
	}
	return apiFetch( { path: `${ API }/scans?${ query.toString() }` } );
};

export const fetchScan = ( id ) => apiFetch( { path: `${ API }/scans/${ id }` } );

export const compareScans = ( a, b ) =>
	apiFetch( { path: `${ API }/scans/compare?a=${ a }&b=${ b }` } );

export const deleteScan = ( id ) =>
	apiFetch( { path: `${ API }/scans/${ id }`, method: 'DELETE' } );

export const fetchSettings = () => apiFetch( { path: `${ API }/settings` } );

export const updateSettings = ( data ) =>
	apiFetch( { path: `${ API }/settings`, method: 'PUT', data } );

export const fetchLogs = ( params = {} ) => {
	const query = new URLSearchParams();
	Object.entries( params ).forEach( ( [ key, value ] ) => {
		if ( value !== undefined && value !== null && value !== '' ) {
			query.set( key, String( value ) );
		}
	} );
	const qs = query.toString();
	return apiFetch( { path: `${ API }/logs${ qs ? `?${ qs }` : '' }` } );
};

export const clearLogs = () => apiFetch( { path: `${ API }/logs`, method: 'DELETE' } );

export const fetchRecommendations = ( params = {} ) => {
	const query = new URLSearchParams();
	Object.entries( params ).forEach( ( [ key, value ] ) => {
		if ( value !== undefined && value !== null && value !== '' ) {
			query.set( key, String( value ) );
		}
	} );
	const qs = query.toString();
	return apiFetch( { path: `${ API }/recommendations${ qs ? `?${ qs }` : '' }` } );
};

export const fetchDependencyGraph = ( params = {} ) => {
	const query = new URLSearchParams();
	Object.entries( params ).forEach( ( [ key, value ] ) => {
		if ( value !== undefined && value !== null && value !== '' ) {
			query.set( key, String( value ) );
		}
	} );
	const qs = query.toString();
	return apiFetch( { path: `${ API }/dependency-graph${ qs ? `?${ qs }` : '' }` } );
};
