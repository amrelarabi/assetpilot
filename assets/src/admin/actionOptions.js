/**
 * Actions available per asset type (matches PHP runtime support).
 */
import { __ } from '@wordpress/i18n';

/** @type {Record<string, { label: string, types: string[] }>} */
const ACTION_META = {
	disable: {
		label: __( 'Disable', 'assetpilot' ),
		types: [ 'script', 'style', 'image', 'font' ],
	},
	defer: {
		label: __( 'Defer', 'assetpilot' ),
		types: [ 'script' ],
	},
	async: {
		label: __( 'Async', 'assetpilot' ),
		types: [ 'script' ],
	},
	preload: {
		label: __( 'Preload', 'assetpilot' ),
		types: [ 'script', 'style', 'image', 'font' ],
	},
	fetchpriority: {
		label: __( 'Fetch Priority', 'assetpilot' ),
		types: [ 'script', 'image' ],
	},
};

/**
 * @param {string} assetType script|style|image|font
 * @returns {{ label: string, value: string }[]}
 */
export function getActionOptionsForType( assetType ) {
	if ( ! assetType ) {
		return [];
	}

	return Object.entries( ACTION_META )
		.filter( ( [ , meta ] ) => meta.types.includes( assetType ) )
		.map( ( [ value, meta ] ) => ( {
			label: meta.label,
			value,
		} ) );
}

/**
 * Actions valid for every asset type in the selection (scripts only, styles only, or intersection).
 *
 * @param {string[]} assetTypes e.g. ['script'], ['style'], or ['script','style']
 * @returns {{ label: string, value: string }[]}
 */
export function getActionOptionsForAssetTypes( assetTypes ) {
	const unique = [ ...new Set( ( assetTypes || [] ).filter( Boolean ) ) ];

	if ( 0 === unique.length ) {
		return [];
	}

	if ( 1 === unique.length ) {
		return getActionOptionsForType( unique[ 0 ] );
	}

	return Object.entries( ACTION_META )
		.filter( ( [ , meta ] ) => unique.every( ( type ) => meta.types.includes( type ) ) )
		.map( ( [ value, meta ] ) => ( {
			label: meta.label,
			value,
		} ) );
}

/**
 * @param {Array<{ type?: string }>} assets
 */
export function getActionOptionsForAssets( assets ) {
	return getActionOptionsForAssetTypes( ( assets || [] ).map( ( asset ) => asset.type ) );
}

/**
 * @param {string} action
 * @param {string} assetType
 */
export function isActionAllowedForType( action, assetType ) {
	return ACTION_META[ action ]?.types.includes( assetType ) ?? false;
}

/**
 * @param {string} action
 * @param {string} assetType
 */
export function resolveActionForType( action, assetType ) {
	if ( isActionAllowedForType( action, assetType ) ) {
		return action;
	}
	return 'disable';
}
