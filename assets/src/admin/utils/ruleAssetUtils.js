/**
 * Display helpers for rule asset handles vs URLs.
 */
import { __, sprintf } from '@wordpress/i18n';

export function ruleTargetUrl( rule ) {
	const config = rule?.action_config;
	if ( ! config || typeof config !== 'object' ) {
		return '';
	}
	return ( config.href || config.src || '' ).trim();
}

export function isUrlLikeHandle( handle ) {
	const value = ( handle || '' ).trim();
	return value.includes( '://' ) || value.startsWith( '//' );
}

export function isUrlBasedRule( rule ) {
	const url = ruleTargetUrl( rule );
	if ( url ) {
		return true;
	}
	if ( isUrlLikeHandle( rule?.asset_handle ) ) {
		return true;
	}
	return [ 'image', 'font' ].includes( rule?.asset_type );
}

export function truncateUrl( url, max = 48 ) {
	if ( ! url || url.length <= max ) {
		return url;
	}
	const half = Math.floor( ( max - 1 ) / 2 );
	return `${ url.slice( 0, half ) }…${ url.slice( -half ) }`;
}

export function ruleBulkAssetCount( rule ) {
	const config = rule?.action_config;
	if ( ! config?.bulk_group || ! Array.isArray( config.bulk_assets ) ) {
		return 0;
	}
	return config.bulk_assets.length;
}

/**
 * @returns {{ label: string, isBulk: boolean, isUrl: boolean, url: string, title: string }}
 */
export function ruleAssetDisplay( rule ) {
	const bulkCount = ruleBulkAssetCount( rule );
	if ( bulkCount > 1 ) {
		return {
			label: sprintf(
				/* translators: %d: number of assets in bulk rule */
				__( '%d assets (bulk)', 'assetpilot' ),
				bulkCount
			),
			isBulk: true,
			isUrl: false,
			url: '',
			title: rule?.asset_handle || '',
		};
	}

	const url = ruleTargetUrl( rule ) || ( isUrlLikeHandle( rule?.asset_handle ) ? rule.asset_handle : '' );
	const isUrl = !! url || isUrlBasedRule( rule );

	if ( isUrl && url ) {
		return {
			label: truncateUrl( url ),
			isBulk: false,
			isUrl: true,
			url,
			title: url,
		};
	}

	return {
		label: rule?.asset_handle || '—',
		isBulk: false,
		isUrl: false,
		url: '',
		title: rule?.asset_handle || '',
	};
}

export function createRuleByUrl( { scanUrl = '' } = {} ) {
	const url = new URL( window.location.href );
	url.searchParams.set( 'page', 'assetpilot-create' );
	url.searchParams.delete( 'bulk' );
	url.searchParams.delete( 'handle' );
	url.searchParams.delete( 'type' );
	url.searchParams.set( 'custom_asset', '1' );
	if ( scanUrl ) {
		url.searchParams.set( 'scan_url', scanUrl );
	}
	return url.toString();
}
