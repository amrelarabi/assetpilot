/**
 * Convert between API condition objects and UI condition rows.
 */

export const defaultConditions = () => ( {
	scope: '',
	post_type: [],
	singular_type: [],
	include_ids: [],
	exclude_ids: [],
	archive: [],
	woocommerce: [],
	device: '',
	logged_in: null,
	url_contains: '',
	url_path: '',
	scan_page_url: '',
	url_match_type: 'contains',
	query_contains: '',
	user_roles: [],
	global: false,
} );

export const createRowId = () => `row-${ Date.now() }-${ Math.random().toString( 36 ).slice( 2, 9 ) }`;

/**
 * @returns {{ id: string, mode: string, scope: string, target: string, postId: number|null }}
 */
export const createEmptyRow = () => ( {
	id: createRowId(),
	mode: 'include',
	scope: 'singular',
	target: 'all',
	detail: '',
	postId: null,
	postLabel: '',
} );

/**
 * @param {Object} conditions
 * @returns {Array<Object>}
 */
export function conditionsToRows( conditions, options = {} ) {
	if ( ! conditions || conditions.global ) {
		return [
			{
				id: createRowId(),
				mode: 'include',
				scope: 'entire_site',
				target: '',
				detail: '',
				postId: null,
				postLabel: '',
			},
		];
	}

	const rows = [];

	const allTypeValues = ( options.postTypes || [] ).map( ( pt ) => pt.value );
	const singularTypes = conditions.singular_type || [];
	const isAllSingular =
		allTypeValues.length > 0 &&
		allTypeValues.length === singularTypes.length &&
		allTypeValues.every( ( t ) => singularTypes.includes( t ) );

	if ( isAllSingular && singularTypes.length > 0 ) {
		rows.push( {
			id: createRowId(),
			mode: 'include',
			scope: 'singular',
			target: 'all',
			detail: '',
			postId: null,
			postLabel: '',
		} );
	} else {
		singularTypes.forEach( ( type ) => {
			rows.push( {
				id: createRowId(),
				mode: 'include',
				scope: 'singular',
				target: 'post_type',
				detail: type,
				postId: null,
				postLabel: '',
			} );
		} );
	}

	( conditions.include_ids || [] ).forEach( ( id ) => {
		rows.push( {
			id: createRowId(),
			mode: 'include',
			scope: 'singular',
			target: 'specific',
			postId: Number( id ),
			detail: '',
			postLabel: '',
		} );
	} );

	( conditions.exclude_ids || [] ).forEach( ( id ) => {
		rows.push( {
			id: createRowId(),
			mode: 'exclude',
			scope: 'singular',
			target: 'specific',
			postId: Number( id ),
			detail: '',
			postLabel: '',
		} );
	} );

	( conditions.archive || [] ).forEach( ( archive ) => {
		rows.push( {
			id: createRowId(),
			mode: 'include',
			scope: 'archive',
			target: archive,
			detail: archive,
			postId: null,
			postLabel: '',
		} );
	} );

	( conditions.post_type || [] ).forEach( ( type ) => {
		rows.push( {
			id: createRowId(),
			mode: 'include',
			scope: 'post_type_archive',
			target: type,
			detail: type,
			postId: null,
			postLabel: '',
		} );
	} );

	( conditions.woocommerce || [] ).forEach( ( page ) => {
		rows.push( {
			id: createRowId(),
			mode: 'include',
			scope: 'woocommerce',
			target: page,
			detail: page,
			postId: null,
			postLabel: '',
		} );
	} );

	if ( conditions.scan_page_url ) {
		rows.push( {
			id: createRowId(),
			mode: 'include',
			scope: 'scan_page',
			target: conditions.scan_page_url,
			detail: conditions.scan_page_url,
			postId: null,
			postLabel: '',
		} );
	}

	const urlPath = conditions.url_path || conditions.url_contains;
	if ( urlPath ) {
		rows.push( {
			id: createRowId(),
			mode: 'include',
			scope: 'url',
			target: urlPath,
			detail: conditions.url_match_type || 'contains',
			postId: null,
			postLabel: '',
		} );
	}

	if ( conditions.query_contains ) {
		rows.push( {
			id: createRowId(),
			mode: 'include',
			scope: 'query',
			target: conditions.query_contains,
			detail: conditions.query_contains,
			postId: null,
			postLabel: '',
		} );
	}

	( conditions.user_roles || [] ).forEach( ( role ) => {
		rows.push( {
			id: createRowId(),
			mode: 'include',
			scope: 'role',
			target: role,
			detail: role,
			postId: null,
			postLabel: '',
		} );
	} );

	if ( conditions.device ) {
		rows.push( {
			id: createRowId(),
			mode: 'include',
			scope: 'device',
			target: conditions.device,
			detail: conditions.device,
			postId: null,
			postLabel: '',
		} );
	}

	if ( conditions.logged_in !== null && conditions.logged_in !== undefined ) {
		rows.push( {
			id: createRowId(),
			mode: 'include',
			scope: 'auth',
			target: conditions.logged_in ? '1' : '0',
			detail: conditions.logged_in ? '1' : '0',
			postId: null,
			postLabel: '',
		} );
	}

	if ( rows.length === 0 ) {
		return [ createEmptyRow() ];
	}

	return rows;
}

/**
 * @param {Array<Object>} rows
 * @param {{ postTypes: Array }} options
 * @returns {Object}
 */
export function rowsToConditions( rows, options = {} ) {
	const { postTypes = [] } = options;
	const conditions = defaultConditions();

	const hasEntireSite = rows.some(
		( row ) => row.scope === 'entire_site' && row.mode === 'include'
	);

	if ( hasEntireSite ) {
		conditions.global = true;
		conditions.scope = 'global';
		return conditions;
	}

	rows.forEach( ( row ) => {
		if ( row.scope === 'singular' ) {
			if ( row.mode === 'exclude' && row.target === 'specific' && row.postId ) {
				conditions.exclude_ids.push( Number( row.postId ) );
				return;
			}
			if ( row.mode !== 'include' ) {
				return;
			}
			if ( row.target === 'all' ) {
				conditions.singular_type = postTypes.map( ( pt ) => pt.value );
				return;
			}
			if ( row.target === 'post_type' && row.detail ) {
				if ( ! conditions.singular_type.includes( row.detail ) ) {
					conditions.singular_type.push( row.detail );
				}
				return;
			}
			if ( row.target === 'specific' && row.postId ) {
				const id = Number( row.postId );
				if ( ! conditions.include_ids.includes( id ) ) {
					conditions.include_ids.push( id );
				}
			}
			return;
		}

		if ( row.mode !== 'include' ) {
			return;
		}

		switch ( row.scope ) {
			case 'archive':
				if ( row.target && ! conditions.archive.includes( row.target ) ) {
					conditions.archive.push( row.target );
				}
				break;
			case 'post_type_archive':
				if ( row.target && ! conditions.post_type.includes( row.target ) ) {
					conditions.post_type.push( row.target );
				}
				break;
			case 'woocommerce':
				if ( row.target && ! conditions.woocommerce.includes( row.target ) ) {
					conditions.woocommerce.push( row.target );
				}
				break;
			case 'scan_page':
				if ( row.target ) {
					conditions.scan_page_url = row.target;
				}
				break;
			case 'url':
				if ( row.target ) {
					conditions.url_path = row.target;
					conditions.url_contains = row.target;
					conditions.url_match_type = row.detail || 'contains';
				}
				break;
			case 'query':
				if ( row.target ) {
					conditions.query_contains = row.target;
				}
				break;
			case 'role':
				if ( row.target && ! conditions.user_roles.includes( row.target ) ) {
					conditions.user_roles.push( row.target );
				}
				break;
			case 'device':
				conditions.device = row.target || '';
				break;
			case 'auth':
				conditions.logged_in = row.target === '1';
				break;
			default:
				break;
		}
	} );

	return conditions;
}
