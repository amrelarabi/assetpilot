/**
 * Assets Explorer screen.
 */
import { useState, useEffect, useMemo, useCallback } from '@wordpress/element';
import {
	Spinner,
	Button,
	TextControl,
	Notice,
} from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import { fetchAssets } from '../api';
import AssetDetailsDrawer from '../components/AssetDetailsDrawer';
import {
	setBulkRuleDraft,
	clearBulkRuleDraft,
	getBulkRuleDraft,
	scanUrlsMatch,
	bulkAssetKey,
} from '../bulkRuleSession';
import { adminPageUrl } from '../navigationUtils';
import { createRuleByUrl } from '../utils/ruleAssetUtils';
import PageAnalyzer from './PageAnalyzer';

const PER_PAGE = 20;

const assetKey = bulkAssetKey;

function SortableColumnHeader( { columnKey, label, sortKey, sortDir, onSort, className = '' } ) {
	const isActive = sortKey === columnKey;
	const thClass = [
		'assetpilot-sortable-th',
		isActive ? `is-sorted is-sorted--${ sortDir }` : '',
		className,
	]
		.filter( Boolean )
		.join( ' ' );

	const title = isActive
		? sprintf(
				/* translators: 1: current direction, 2: next direction */
				__( 'Sorted %1$s. Click to sort %2$s.', 'assetpilot' ),
				sortDir === 'asc' ? __( 'ascending', 'assetpilot' ) : __( 'descending', 'assetpilot' ),
				sortDir === 'asc' ? __( 'descending', 'assetpilot' ) : __( 'ascending', 'assetpilot' )
		  )
		: __( 'Click to sort', 'assetpilot' );

	return (
		<th
			className={ thClass }
			aria-sort={ isActive ? ( sortDir === 'asc' ? 'ascending' : 'descending' ) : 'none' }
		>
			<button type="button" className="assetpilot-sortable-th__btn" onClick={ () => onSort( columnKey ) } title={ title }>
				<span className="assetpilot-sortable-th__label">{ label }</span>
				<span className="assetpilot-sortable-th__icons" aria-hidden="true">
					<span
						className={ `assetpilot-sort-icon assetpilot-sort-icon--asc${
							isActive && sortDir === 'asc' ? ' is-active' : ''
						}` }
					/>
					<span
						className={ `assetpilot-sort-icon assetpilot-sort-icon--desc${
							isActive && sortDir === 'desc' ? ' is-active' : ''
						}` }
					/>
				</span>
			</button>
		</th>
	);
}

export default function AssetsExplorer( { onSelectAsset } ) {
	const [ assets, setAssets ] = useState( [] );
	const [ loading, setLoading ] = useState( true );
	const [ search, setSearch ] = useState( '' );
	const [ typeFilter, setTypeFilter ] = useState( '' );
	const [ originFilter, setOriginFilter ] = useState( '' );
	const [ scanId, setScanId ] = useState( () => {
		const preset = new URLSearchParams( window.location.search ).get( 'scan_id' );
		return preset ? parseInt( preset, 10 ) : null;
	} );
	const [ scanUrl, setScanUrl ] = useState( () => {
		const preset = new URLSearchParams( window.location.search ).get( 'scan_url' );
		if ( preset ) {
			return decodeURIComponent( preset );
		}
		return window.assetpilotAdmin?.homeUrl || '';
	} );
	const [ historySnapshot, setHistorySnapshot ] = useState( null );
	const [ scanMeta, setScanMeta ] = useState( null );
	const [ loadError, setLoadError ] = useState( '' );
	const [ page, setPage ] = useState( 1 );
	const [ sortKey, setSortKey ] = useState( 'handle' );
	const [ sortDir, setSortDir ] = useState( 'asc' );
	const [ drawerAsset, setDrawerAsset ] = useState( null );
	const [ selectedKeys, setSelectedKeys ] = useState( () => new Set() );
	const [ workspaceTab, setWorkspaceTab ] = useState( () => {
		const params = new URLSearchParams( window.location.search );
		return params.get( 'assetpilot_tab' ) === 'analyze' ? 'analyze' : 'explorer';
	} );
	const [ adminNotice, setAdminNotice ] = useState( '' );

	const highlightHandle = useMemo( () => {
		if ( onSelectAsset ) {
			return '';
		}
		return new URLSearchParams( window.location.search ).get( 'handle' ) || '';
	}, [ onSelectAsset ] );
	const highlightType = useMemo( () => {
		if ( onSelectAsset ) {
			return '';
		}
		return new URLSearchParams( window.location.search ).get( 'type' ) || '';
	}, [ onSelectAsset ] );

	const bulkEnabled = ! onSelectAsset;

	const normalizeAssets = ( data ) => {
		if ( Array.isArray( data ) ) {
			return data;
		}
		if ( data && typeof data === 'object' ) {
			return Object.values( data );
		}
		return [];
	};

	const restoreDraftSelection = useCallback( ( assetList, url ) => {
		const draft = getBulkRuleDraft();
		if ( ! draft?.assets?.length || ! scanUrlsMatch( draft.scanUrl, url ) ) {
			return;
		}
		const valid = new Set( assetList.map( assetKey ) );
		const keys = new Set(
			draft.assets.map( ( a ) => assetKey( a ) ).filter( ( k ) => valid.has( k ) )
		);
		if ( keys.size > 0 ) {
			setSelectedKeys( keys );
		}
	}, [] );

	const resetBulkSelection = useCallback( () => {
		setSelectedKeys( new Set() );
		clearBulkRuleDraft();
	}, [] );

	const loadFromHistory = useCallback( ( id ) => {
		resetBulkSelection();
		setLoading( true );
		setLoadError( '' );
		setPage( 1 );

		fetchAssets( { scan_id: id } )
			.then( ( res ) => {
				setAssets( normalizeAssets( res.assets ) );
				setScanMeta( res.meta || null );
				if ( res.scan_url ) {
					setScanUrl( res.scan_url );
				}
				setHistorySnapshot( {
					id,
					scanned_at: res.scanned_at || '',
				} );
			} )
			.catch( ( err ) => {
				setLoadError( err?.message || __( 'Failed to load saved scan.', 'assetpilot' ) );
				setAssets( [] );
				setScanMeta( null );
			} )
			.finally( () => setLoading( false ) );
	}, [ resetBulkSelection ] );

	const runScan = useCallback(
		( forceRefresh = false, { restoreDraft = false } = {} ) => {
			if ( ! restoreDraft ) {
				resetBulkSelection();
			}

			setLoading( true );
			setLoadError( '' );
			setPage( 1 );
			setHistorySnapshot( null );
			setScanId( null );

			const params = { scan_url: scanUrl || undefined };
			if ( forceRefresh ) {
				params.refresh = '1';
			}

			const urlForDraft = scanUrl;

			fetchAssets( params )
				.then( ( res ) => {
					const list = normalizeAssets( res.assets );
					setAssets( list );
					setScanMeta( res.meta || null );
					if ( restoreDraft ) {
						restoreDraftSelection( list, urlForDraft );
					}
				} )
				.catch( ( err ) => {
					const msg = err?.message || __( 'Failed to load assets.', 'assetpilot' );
					setLoadError(
						msg.includes( 'valid JSON' )
							? __( 'Scan interrupted — click Scan again. Check debug.log if this persists.', 'assetpilot' )
							: msg
					);
					setAssets( [] );
					setScanMeta( null );
				} )
				.finally( () => setLoading( false ) );
		},
		[ scanUrl, resetBulkSelection, restoreDraftSelection ]
	);

	useEffect( () => {
		if ( scanId ) {
			loadFromHistory( scanId );
			return;
		}
		const draft = getBulkRuleDraft();
		const restoreDraft = !! draft?.assets?.length;
		runScan( false, { restoreDraft } );
		// eslint-disable-next-line react-hooks/exhaustive-deps -- initial load only
	}, [] );

	const filtered = useMemo( () => {
		const term = search.trim().toLowerCase();
		return assets.filter( ( asset ) => {
			if ( typeFilter && asset.type !== typeFilter ) {
				return false;
			}
			if ( originFilter && asset.origin !== originFilter ) {
				return false;
			}
			if ( term ) {
				const haystack = `${ asset.handle } ${ asset.src || '' } ${ asset.source || '' }`.toLowerCase();
				if ( ! haystack.includes( term ) ) {
					return false;
				}
			}
			return true;
		} );
	}, [ assets, search, typeFilter, originFilter ] );

	const sorted = useMemo( () => {
		const list = [ ...filtered ];
		list.sort( ( a, b ) => {
			let av = a[ sortKey ] ?? '';
			let bv = b[ sortKey ] ?? '';
			if ( sortKey === 'size' ) {
				av = a.size ?? 0;
				bv = b.size ?? 0;
			}
			if ( av < bv ) {
				return sortDir === 'asc' ? -1 : 1;
			}
			if ( av > bv ) {
				return sortDir === 'asc' ? 1 : -1;
			}
			return 0;
		} );
		return list;
	}, [ filtered, sortKey, sortDir ] );

	const paginated = sorted.slice( ( page - 1 ) * PER_PAGE, page * PER_PAGE );
	const totalPages = Math.max( 1, Math.ceil( sorted.length / PER_PAGE ) );

	const selectedAssets = useMemo(
		() => assets.filter( ( asset ) => selectedKeys.has( assetKey( asset ) ) ),
		[ assets, selectedKeys ]
	);

	const filteredKeys = useMemo( () => sorted.map( assetKey ), [ sorted ] );
	const allFilteredSelected =
		filteredKeys.length > 0 && filteredKeys.every( ( key ) => selectedKeys.has( key ) );
	const someFilteredSelected =
		! allFilteredSelected && filteredKeys.some( ( key ) => selectedKeys.has( key ) );

	const toggleAssetSelection = ( asset, checked ) => {
		const key = assetKey( asset );
		setSelectedKeys( ( prev ) => {
			const next = new Set( prev );
			if ( checked ) {
				next.add( key );
			} else {
				next.delete( key );
			}
			return next;
		} );
	};

	const toggleSelectAllFiltered = () => {
		setSelectedKeys( ( prev ) => {
			const next = new Set( prev );
			if ( allFilteredSelected ) {
				filteredKeys.forEach( ( key ) => next.delete( key ) );
			} else {
				filteredKeys.forEach( ( key ) => next.add( key ) );
			}
			return next;
		} );
	};

	const clearSelection = () => {
		resetBulkSelection();
	};

	useEffect( () => {
		setPage( 1 );
	}, [ search, typeFilter, originFilter ] );

	useEffect( () => {
		if ( ! bulkEnabled ) {
			return;
		}
		const params = new URLSearchParams( window.location.search );
		if ( params.get( 'assetpilot_notice' ) === 'select_asset' ) {
			setAdminNotice(
				__(
					'Select an asset in the table below, then click Create rule — or select several and use Configure bulk rule.',
					'assetpilot'
				)
			);
			params.delete( 'assetpilot_notice' );
			const next = `${ window.location.pathname }?${ params.toString() }`;
			window.history.replaceState( {}, '', next );
		}
	}, [ bulkEnabled ] );

	useEffect( () => {
		if ( ! highlightHandle || ! highlightType || ! sorted.length ) {
			return;
		}
		const key = `${ highlightType }:${ highlightHandle }`;
		const index = sorted.findIndex( ( asset ) => assetKey( asset ) === key );
		if ( index >= 0 ) {
			setPage( Math.floor( index / PER_PAGE ) + 1 );
		}
	}, [ highlightHandle, highlightType, sorted ] );

	const goToCreateRule = ( asset ) => {
		if ( onSelectAsset ) {
			onSelectAsset( asset );
			return;
		}
		clearBulkRuleDraft();
		const url = new URL( window.location.href );
		url.searchParams.set( 'page', 'assetpilot-create' );
		url.searchParams.delete( 'bulk' );
		url.searchParams.set( 'handle', asset.handle );
		url.searchParams.set( 'type', asset.type );
		if ( scanUrl ) {
			url.searchParams.set( 'scan_url', scanUrl );
		}
		window.location.href = url.toString();
	};

	const toggleSort = ( key ) => {
		if ( sortKey === key ) {
			setSortDir( sortDir === 'asc' ? 'desc' : 'asc' );
		} else {
			setSortKey( key );
			setSortDir( 'asc' );
		}
	};

	const scriptCount = assets.filter( ( a ) => a.type === 'script' ).length;
	const styleCount = assets.filter( ( a ) => a.type === 'style' ).length;
	const imageCount = assets.filter( ( a ) => a.type === 'image' ).length;
	const fontCount = assets.filter( ( a ) => a.type === 'font' ).length;

	const scanHistoryUrl =
		window.assetpilotAdmin?.scanHistoryPageUrl ||
		adminPageUrl( 'assetpilot-scan-history' );

	return (
		<div className="assetpilot-assets">
			{ bulkEnabled && (
				<nav className="assetpilot-assets-tabs" aria-label={ __( 'Assets workspace', 'assetpilot' ) }>
					<button
						type="button"
						className={ `assetpilot-assets-tabs__btn${
							workspaceTab === 'explorer' ? ' is-active' : ''
						}` }
						onClick={ () => setWorkspaceTab( 'explorer' ) }
					>
						{ __( 'Assets Explorer', 'assetpilot' ) }
					</button>
					<button
						type="button"
						className={ `assetpilot-assets-tabs__btn${
							workspaceTab === 'analyze' ? ' is-active' : ''
						}` }
						onClick={ () => setWorkspaceTab( 'analyze' ) }
					>
						{ __( 'Quick analyze', 'assetpilot' ) }
					</button>
					<a className="assetpilot-assets-tabs__link" href={ scanHistoryUrl }>
						{ __( 'Scan history', 'assetpilot' ) }
					</a>
				</nav>
			) }

			{ bulkEnabled && workspaceTab === 'analyze' && <PageAnalyzer embedded /> }

			{ ( ! bulkEnabled || workspaceTab === 'explorer' ) && (
				<>
			{ adminNotice && (
				<Notice className="assetpilot-notice" status="info" isDismissible onRemove={ () => setAdminNotice( '' ) }>
					{ adminNotice }
				</Notice>
			) }
			<div className="assetpilot-card assetpilot-scan-card">
				<div className="assetpilot-scan-card__main">
					<div className="assetpilot-scan-card__field">
						<TextControl
							label={ __( 'Page URL to scan', 'assetpilot' ) }
							value={ scanUrl }
							onChange={ setScanUrl }
							__nextHasNoMarginBottom
						/>
					</div>
					<div className="assetpilot-scan-card__actions">
						<Button
							variant="primary"
							onClick={ () => runScan( false ) }
							disabled={ loading }
						>
							{ loading ? __( 'Scanning…', 'assetpilot' ) : __( 'Scan', 'assetpilot' ) }
						</Button>
						<Button
							variant="secondary"
							onClick={ () => runScan( true ) }
							disabled={ loading }
						>
							{ __( 'Scan fresh', 'assetpilot' ) }
						</Button>
					</div>
				</div>
				<p className="assetpilot-scan-card__help">
					{ __(
						'Scans enqueue queues plus images and fonts found in page HTML. Use Add by URL for assets not listed after a scan.',
						'assetpilot'
					) }
				</p>
				{ bulkEnabled && (
					<p className="assetpilot-scan-card__actions">
						<Button variant="secondary" href={ createRuleByUrl( { scanUrl } ) }>
							{ __( 'Add rule by URL (image/font)', 'assetpilot' ) }
						</Button>
					</p>
				) }
			</div>

			{ historySnapshot && (
				<Notice className="assetpilot-notice" status="info" isDismissible={ false }>
					{ sprintf(
						/* translators: 1: scan id, 2: scan date */
						__( 'Viewing saved scan #%1$d from %2$s. Click Scan to run a new live scan.', 'assetpilot' ),
						historySnapshot.id,
						historySnapshot.scanned_at || __( 'scan history', 'assetpilot' )
					) }{ ' ' }
					<Button variant="link" href={ window.assetpilotAdmin?.scanHistoryPageUrl }>
						{ __( 'Scan History', 'assetpilot' ) }
					</Button>
				</Notice>
			) }

			{ loadError && (
				<Notice className="assetpilot-notice" status="error" isDismissible={ false }>
					{ loadError }
				</Notice>
			) }

			{ ! loadError && ! loading && scanMeta?.site_front_scan && (
				<Notice className="assetpilot-notice" status="info" isDismissible={ false }>
					{ __(
						'Homepage scan uses a live page render (your theme front template), not only the page selected under Settings → Reading.',
						'assetpilot'
					) }
				</Notice>
			) }

			{ ! loadError && ! loading && (
				<div className="assetpilot-summary">
					<div className="assetpilot-summary__stat">
						<span className="assetpilot-summary__value">{ assets.length }</span>
						<span className="assetpilot-summary__label">{ __( 'Assets found', 'assetpilot' ) }</span>
					</div>
					<div className="assetpilot-summary__stat">
						<span className="assetpilot-summary__value">{ scriptCount }</span>
						<span className="assetpilot-summary__label">{ __( 'Scripts', 'assetpilot' ) }</span>
					</div>
					<div className="assetpilot-summary__stat">
						<span className="assetpilot-summary__value">{ styleCount }</span>
						<span className="assetpilot-summary__label">{ __( 'Styles', 'assetpilot' ) }</span>
					</div>
					<div className="assetpilot-summary__stat">
						<span className="assetpilot-summary__value">{ imageCount }</span>
						<span className="assetpilot-summary__label">{ __( 'Images', 'assetpilot' ) }</span>
					</div>
					<div className="assetpilot-summary__stat">
						<span className="assetpilot-summary__value">{ fontCount }</span>
						<span className="assetpilot-summary__label">{ __( 'Fonts', 'assetpilot' ) }</span>
					</div>
					{ scanMeta?.from_cache && (
						<>
							<span className="assetpilot-badge assetpilot-badge--muted">{ __( 'Cached', 'assetpilot' ) }</span>
							<Button variant="link" onClick={ () => runScan( true ) } disabled={ loading }>
								{ __( 'Clear cache & rescan', 'assetpilot' ) }
							</Button>
						</>
					) }
				</div>
			) }

			{ bulkEnabled && selectedAssets.length > 0 && (
				<div className="assetpilot-assets-bulk assetpilot-assets-bulk--compact">
					<div className="assetpilot-assets-bulk__head">
						<strong>
							{ sprintf(
								/* translators: %d: selected asset count */
								__( '%d assets selected', 'assetpilot' ),
								selectedAssets.length
							) }
						</strong>
						<p className="assetpilot-assets-bulk__hint">
							{ __(
								'Open the rule wizard to choose one action and conditions for all selected assets.',
								'assetpilot'
							) }
						</p>
					</div>
					<div className="assetpilot-assets-bulk__actions">
						<Button
							variant="primary"
							onClick={ () => {
								setBulkRuleDraft( {
									assets: selectedAssets.map( ( a ) => ( {
										handle: a.handle,
										type: a.type,
										src: a.src,
									} ) ),
									scanUrl,
								} );
								const base =
									window.assetpilotAdmin?.createPageUrl ||
									`${ window.assetpilotAdmin?.adminUrl || '/wp-admin/' }admin.php?page=assetpilot-create`;
								const url = new URL( base, window.location.origin );
								url.searchParams.set( 'bulk', '1' );
								if ( scanUrl ) {
									url.searchParams.set( 'scan_url', scanUrl );
								}
								window.location.href = url.toString();
							} }
						>
							{ __( 'Configure bulk rule', 'assetpilot' ) }
						</Button>
						<Button variant="secondary" onClick={ clearSelection }>
							{ __( 'Clear selection', 'assetpilot' ) }
						</Button>
					</div>
				</div>
			) }

			{ ! loading && assets.length > 0 && (
				<div className="assetpilot-toolbar">
					<div className="assetpilot-toolbar-search">
						<label className="screen-reader-text" htmlFor="assetpilot-toolbar-search">
							{ __( 'Filter by handle or URL', 'assetpilot' ) }
						</label>
						<span className="assetpilot-toolbar-search__icon" aria-hidden="true" />
						<input
							id="assetpilot-toolbar-search"
							type="text"
							className="assetpilot-toolbar-field assetpilot-toolbar-field--search"
							value={ search }
							onChange={ ( e ) => setSearch( e.target.value ) }
							placeholder={ __( 'Filter by handle or URL…', 'assetpilot' ) }
						/>
					</div>
					<label className="screen-reader-text" htmlFor="assetpilot-toolbar-type">
						{ __( 'Type', 'assetpilot' ) }
					</label>
					<select
						id="assetpilot-toolbar-type"
						className="assetpilot-toolbar-field assetpilot-toolbar-field--select"
						value={ typeFilter }
						onChange={ ( e ) => setTypeFilter( e.target.value ) }
					>
						<option value="">{ __( 'All types', 'assetpilot' ) }</option>
						<option value="script">{ __( 'Scripts', 'assetpilot' ) }</option>
						<option value="style">{ __( 'Styles', 'assetpilot' ) }</option>
						<option value="image">{ __( 'Images', 'assetpilot' ) }</option>
						<option value="font">{ __( 'Fonts', 'assetpilot' ) }</option>
					</select>
					<label className="screen-reader-text" htmlFor="assetpilot-toolbar-origin">
						{ __( 'Origin', 'assetpilot' ) }
					</label>
					<select
						id="assetpilot-toolbar-origin"
						className="assetpilot-toolbar-field assetpilot-toolbar-field--select"
						value={ originFilter }
						onChange={ ( e ) => setOriginFilter( e.target.value ) }
					>
						<option value="">{ __( 'All origins', 'assetpilot' ) }</option>
						<option value="core">{ __( 'Core', 'assetpilot' ) }</option>
						<option value="plugin">{ __( 'Plugin', 'assetpilot' ) }</option>
						<option value="theme">{ __( 'Theme', 'assetpilot' ) }</option>
						<option value="html">{ __( 'From HTML', 'assetpilot' ) }</option>
					</select>
					<span className="assetpilot-toolbar__count">
						{ sorted.length === assets.length
							? `${ sorted.length } ${ __( 'items', 'assetpilot' ) }`
							: `${ sorted.length } / ${ assets.length }` }
					</span>
				</div>
			) }

			{ loading ? (
				<div className="assetpilot-loading-panel">
					<Spinner />
					<p>{ __( 'Rendering page and collecting assets…', 'assetpilot' ) }</p>
				</div>
			) : (
				<div className="assetpilot-table-wrap">
					<table className="wp-list-table widefat fixed striped assetpilot-assets-table">
						<thead>
							<tr>
								{ bulkEnabled && (
									<th className="column-cb check-column">
										<input
											type="checkbox"
											className="assetpilot-assets-table__cb"
											checked={ allFilteredSelected }
											ref={ ( input ) => {
												if ( input ) {
													input.indeterminate = someFilteredSelected;
												}
											} }
											onChange={ toggleSelectAllFiltered }
											aria-label={
												allFilteredSelected
													? __( 'Deselect all matching assets', 'assetpilot' )
													: __( 'Select all matching assets', 'assetpilot' )
											}
											title={ sprintf(
												/* translators: %d: asset count */
												__( 'Select all %d assets matching filters', 'assetpilot' ),
												sorted.length
											) }
										/>
									</th>
								) }
								<SortableColumnHeader
									className="column-handle"
									columnKey="handle"
									label={ __( 'Handle', 'assetpilot' ) }
									sortKey={ sortKey }
									sortDir={ sortDir }
									onSort={ toggleSort }
								/>
								<th>{ __( 'Type', 'assetpilot' ) }</th>
								<th className="column-source">{ __( 'Source', 'assetpilot' ) }</th>
								<SortableColumnHeader
									columnKey="size"
									label={ __( 'Size', 'assetpilot' ) }
									sortKey={ sortKey }
									sortDir={ sortDir }
									onSort={ toggleSort }
								/>
								<th className="column-deps">{ __( 'Dependencies', 'assetpilot' ) }</th>
								<th className="column-actions">{ __( 'Actions', 'assetpilot' ) }</th>
							</tr>
						</thead>
						<tbody>
							{ paginated.length === 0 ? (
								<tr>
									<td colSpan={ bulkEnabled ? 7 : 6 } className="assetpilot-empty-cell">
										<p>{ __( 'No assets match your filters.', 'assetpilot' ) }</p>
										{ assets.length === 0 && (
											<Button variant="secondary" onClick={ runScan }>
												{ __( 'Scan again', 'assetpilot' ) }
											</Button>
										) }
									</td>
								</tr>
							) : (
								paginated.map( ( asset ) => {
									const key = assetKey( asset );
									const isSelected = selectedKeys.has( key );
									const isHighlighted =
										highlightHandle === asset.handle &&
										highlightType === asset.type;

									return (
									<tr
										key={ key }
										className={ `assetpilot-assets-table__row${
											isSelected ? ' is-selected' : ''
										}${ isHighlighted ? ' is-highlighted' : '' }` }
										onClick={ () => setDrawerAsset( asset ) }
										onKeyDown={ ( e ) => {
											if ( e.key === 'Enter' || e.key === ' ' ) {
												e.preventDefault();
												setDrawerAsset( asset );
											}
										} }
										tabIndex={ 0 }
										role="button"
										aria-label={ sprintf(
											/* translators: %s: asset handle */
											__( 'View details for %s', 'assetpilot' ),
											asset.handle
										) }
									>
										{ bulkEnabled && (
											<td
												className="column-cb check-column"
												onClick={ ( e ) => e.stopPropagation() }
											>
												<input
													type="checkbox"
													className="assetpilot-assets-table__cb"
													checked={ isSelected }
													onChange={ ( e ) =>
														toggleAssetSelection( asset, e.target.checked )
													}
													aria-label={ sprintf(
														/* translators: %s: asset handle */
														__( 'Select %s', 'assetpilot' ),
														asset.handle
													) }
												/>
											</td>
										) }
										<td className="column-handle">
											<code className="assetpilot-handle">{ asset.handle }</code>
										</td>
										<td>
											<span className={ `assetpilot-badge assetpilot-badge--${ asset.type }` }>
												{ asset.type }
											</span>
										</td>
										<td className="column-source">
											<span className="assetpilot-source-name">{ asset.source || '—' }</span>
											<span className={ `assetpilot-badge assetpilot-badge--origin assetpilot-badge--${ asset.origin || 'unknown' }` }>
												{ asset.origin || '—' }
											</span>
										</td>
										<td>{ asset.size ? `${ ( asset.size / 1024 ).toFixed( 1 ) } KB` : '—' }</td>
										<td className="column-deps">
											{ ( asset.deps || [] ).length > 0 ? (
												<span className="assetpilot-deps" title={ ( asset.deps || [] ).join( ', ' ) }>
													{ ( asset.deps || [] ).join( ', ' ) }
												</span>
											) : (
												'—'
											) }
										</td>
										<td className="column-actions">
											<Button
												variant="secondary"
												isSmall
												onClick={ ( e ) => {
													e.stopPropagation();
													goToCreateRule( asset );
												} }
											>
												{ __( 'Create rule', 'assetpilot' ) }
											</Button>
										</td>
									</tr>
									);
								} )
							) }
						</tbody>
					</table>

					{ sorted.length > PER_PAGE && (
						<div className="assetpilot-pagination">
							<Button
								variant="secondary"
								disabled={ page <= 1 }
								onClick={ () => setPage( page - 1 ) }
							>
								{ __( 'Previous', 'assetpilot' ) }
							</Button>
							<span className="assetpilot-pagination__info">
								{ __( 'Page', 'assetpilot' ) } { page } { __( 'of', 'assetpilot' ) } { totalPages }
							</span>
							<Button
								variant="secondary"
								disabled={ page >= totalPages }
								onClick={ () => setPage( page + 1 ) }
							>
								{ __( 'Next', 'assetpilot' ) }
							</Button>
						</div>
					) }
				</div>
			) }
				</>
			) }

			{ drawerAsset && (
				<AssetDetailsDrawer
					asset={ drawerAsset }
					scanUrl={ scanUrl }
					onClose={ () => setDrawerAsset( null ) }
					onCreateRule={ ( selected ) => {
						setDrawerAsset( null );
						goToCreateRule( selected );
					} }
				/>
			) }
		</div>
	);
}
