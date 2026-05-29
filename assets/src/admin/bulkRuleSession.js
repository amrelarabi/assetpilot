/**
 * Pass bulk asset selection from Assets Explorer to Create Rule wizard.
 */
const STORAGE_KEY = 'assetpilot_bulk_rule_draft';
const SELECTION_KEY = 'assetpilot_bulk_selection_keys';

export const bulkAssetKey = ( asset ) => `${ asset.type }:${ asset.handle }`;

const normalizeScanUrl = ( url ) =>
	( url || '' ).trim().replace( /\/+$/, '' ).toLowerCase();

export function scanUrlsMatch( a, b ) {
	const left  = normalizeScanUrl( a );
	const right = normalizeScanUrl( b );
	if ( ! left || ! right ) {
		return left === right;
	}
	return left === right;
}

/**
 * @returns {Set<string>}
 */
export function getBulkSelectionKeys() {
	try {
		const raw = sessionStorage.getItem( SELECTION_KEY );
		if ( ! raw ) {
			return new Set();
		}
		const parsed = JSON.parse( raw );
		if ( ! Array.isArray( parsed ) ) {
			return new Set();
		}
		return new Set( parsed.filter( ( k ) => typeof k === 'string' && k.includes( ':' ) ) );
	} catch {
		return new Set();
	}
}

/**
 * @param {Set<string>|string[]} keys
 */
export function setBulkSelectionKeys( keys ) {
	try {
		const list = keys instanceof Set ? [ ...keys ] : keys;
		if ( ! list.length ) {
			sessionStorage.removeItem( SELECTION_KEY );
			return;
		}
		sessionStorage.setItem( SELECTION_KEY, JSON.stringify( list ) );
	} catch {
		// Ignore quota errors.
	}
}

/**
 * @param {Array<{handle: string, type: string}>} assets
 */
export function syncBulkSelectionFromAssets( assets ) {
	if ( ! assets?.length ) {
		return;
	}
	setBulkSelectionKeys( assets.map( bulkAssetKey ) );
}

/**
 * @param {{ assets: Array<{handle: string, type: string}>, scanUrl?: string }} payload
 */
export function setBulkRuleDraft( payload ) {
	try {
		sessionStorage.setItem( STORAGE_KEY, JSON.stringify( payload ) );
		if ( payload?.assets?.length ) {
			syncBulkSelectionFromAssets( payload.assets );
		}
	} catch {
		// Ignore quota errors.
	}
}

/**
 * URL back to Assets Explorer while keeping bulk draft + checkbox selection.
 *
 * @param {string} [scanUrl]
 * @returns {string}
 */
export function getAssetsExplorerBulkUrl( scanUrl = '' ) {
	const base =
		window.assetpilotAdmin?.assetsPageUrl ||
		`${ window.assetpilotAdmin?.adminUrl || '/wp-admin/' }admin.php?page=assetpilot-assets`;
	const url = new URL( base, window.location.origin );
	const draft = getBulkRuleDraft();
	const resolvedScan = scanUrl || draft?.scanUrl || '';
	if ( resolvedScan ) {
		url.searchParams.set( 'scan_url', resolvedScan );
	}
	return url.toString();
}

/**
 * @returns {{ assets: Array<{handle: string, type: string, src?: string}>, scanUrl: string }|null}
 */
export function getBulkRuleDraft() {
	try {
		const raw = sessionStorage.getItem( STORAGE_KEY );
		if ( ! raw ) {
			return null;
		}
		const parsed = JSON.parse( raw );
		if ( ! parsed?.assets?.length ) {
			return null;
		}
		return {
			assets: parsed.assets,
			scanUrl: parsed.scanUrl || '',
		};
	} catch {
		return null;
	}
}

export function clearBulkRuleDraft() {
	try {
		sessionStorage.removeItem( STORAGE_KEY );
		sessionStorage.removeItem( SELECTION_KEY );
	} catch {
		// Ignore.
	}
}

/** Bulk wizard only when opened via Assets Explorer with ?bulk=1. */
export function isBulkRuleMode() {
	return new URLSearchParams( window.location.search ).get( 'bulk' ) === '1';
}
