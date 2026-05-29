/**
 * Scan History screen — timeline, reopen, compare.
 */
import { useState, useEffect, useCallback } from '@wordpress/element';
import { Button, Spinner, TextControl, Notice } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import { fetchScans, compareScans, deleteScan } from '../api';

function formatBytes( bytes ) {
	const n = Number( bytes ) || 0;
	if ( n < 1024 ) {
		return `${ n } B`;
	}
	if ( n < 1024 * 1024 ) {
		return `${ ( n / 1024 ).toFixed( 1 ) } KB`;
	}
	return `${ ( n / ( 1024 * 1024 ) ).toFixed( 2 ) } MB`;
}

function formatDate( value ) {
	if ( ! value ) {
		return '—';
	}
	try {
		return new Date( value.replace( ' ', 'T' ) + 'Z' ).toLocaleString();
	} catch {
		return value;
	}
}

function assetsExplorerUrl( scanId ) {
	const base = window.assetpilotAdmin?.assetsPageUrl || 'admin.php?page=assetpilot-assets';
	const join = base.includes( '?' ) ? '&' : '?';
	return `${ base }${ join }scan_id=${ scanId }`;
}

export default function ScanHistory() {
	const [ scans, setScans ] = useState( [] );
	const [ total, setTotal ] = useState( 0 );
	const [ page, setPage ] = useState( 1 );
	const [ search, setSearch ] = useState( '' );
	const [ loading, setLoading ] = useState( true );
	const [ error, setError ] = useState( '' );
	const [ selected, setSelected ] = useState( [] );
	const [ comparing, setComparing ] = useState( false );
	const [ compareResult, setCompareResult ] = useState( null );
	const [ compareError, setCompareError ] = useState( '' );
	const [ retention, setRetention ] = useState( null );

	const perPage = 20;

	const load = useCallback( () => {
		setLoading( true );
		setError( '' );
		fetchScans( { page, per_page: perPage, search: search || undefined } )
			.then( ( res ) => {
				setScans( res.scans || [] );
				setTotal( res.total || 0 );
				if ( res.retention ) {
					setRetention( res.retention );
				}
			} )
			.catch( ( err ) => {
				setError( err?.message || __( 'Failed to load scan history.', 'assetpilot' ) );
				setScans( [] );
			} )
			.finally( () => setLoading( false ) );
	}, [ page, search ] );

	useEffect( load, [ load ] );

	const toggleSelect = ( id ) => {
		setSelected( ( prev ) => {
			if ( prev.includes( id ) ) {
				return prev.filter( ( x ) => x !== id );
			}
			if ( prev.length >= 2 ) {
				return [ prev[ 1 ], id ];
			}
			return [ ...prev, id ];
		} );
		setCompareResult( null );
		setCompareError( '' );
	};

	const runCompare = () => {
		if ( selected.length !== 2 ) {
			return;
		}
		setComparing( true );
		setCompareError( '' );
		compareScans( selected[ 0 ], selected[ 1 ] )
			.then( ( res ) => setCompareResult( res ) )
			.catch( ( err ) => {
				setCompareError( err?.message || __( 'Compare failed.', 'assetpilot' ) );
				setCompareResult( null );
			} )
			.finally( () => setComparing( false ) );
	};

	const handleDelete = async ( id ) => {
		if ( ! window.confirm( __( 'Delete this scan snapshot?', 'assetpilot' ) ) ) {
			return;
		}
		await deleteScan( id );
		setSelected( ( prev ) => prev.filter( ( x ) => x !== id ) );
		load();
	};

	const totalPages = Math.max( 1, Math.ceil( total / perPage ) );

	const groupByDate = ( items ) => {
		const groups = {};
		items.forEach( ( scan ) => {
			const day = ( scan.scanned_at || '' ).slice( 0, 10 ) || __( 'Unknown date', 'assetpilot' );
			if ( ! groups[ day ] ) {
				groups[ day ] = [];
			}
			groups[ day ].push( scan );
		} );
		return groups;
	};

	const grouped = groupByDate( scans );

	return (
		<div className="assetpilot-scan-history">
			<p className="assetpilot-scan-history__lead">
				{ __(
					'Every Assets Explorer scan is saved here. Re-open a snapshot or compare two scans to see what changed.',
					'assetpilot'
				) }
			</p>
			{ retention && (
				<p className="assetpilot-scan-history__retention description">
					{ sprintf(
						/* translators: 1: max scan rows, 2: retention days, 3: current stored count */
						__(
							'Automatic cleanup keeps the newest scans (max %1$d snapshots, or %2$d days). Currently stored: %3$d.',
							'assetpilot'
						),
						retention.max_rows,
						retention.retention_days,
						total
					) }
				</p>
			) }

			<div className="assetpilot-scan-history__toolbar">
				<TextControl
					label={ __( 'Search URL', 'assetpilot' ) }
					value={ search }
					onChange={ ( val ) => {
						setSearch( val );
						setPage( 1 );
					} }
					__nextHasNoMarginBottom
				/>
				<Button variant="secondary" onClick={ load } disabled={ loading }>
					{ __( 'Refresh', 'assetpilot' ) }
				</Button>
				<Button
					variant="primary"
					onClick={ runCompare }
					disabled={ selected.length !== 2 || comparing }
				>
					{ comparing ? __( 'Comparing…', 'assetpilot' ) : __( 'Compare selected', 'assetpilot' ) }
				</Button>
			</div>

			{ error && (
				<Notice className="assetpilot-notice" status="warning" isDismissible={ false }>
					{ error }
				</Notice>
			) }
			{ compareError && (
				<Notice className="assetpilot-notice" status="warning" isDismissible={ false }>
					{ compareError }
				</Notice>
			) }

			{ compareResult && (
				<div className="assetpilot-scan-compare">
					<h3>{ __( 'Comparison', 'assetpilot' ) }</h3>
					<p className="assetpilot-scan-compare__meta">
						{ sprintf(
							/* translators: 1: scan A URL, 2: scan B URL */
							__( '%1$s vs %2$s', 'assetpilot' ),
							compareResult.scan_a?.scan_url || '',
							compareResult.scan_b?.scan_url || ''
						) }
					</p>
					<ul className="assetpilot-scan-compare__stats">
						<li>
							{ sprintf(
								__( 'Added: %d', 'assetpilot' ),
								compareResult.added?.length || 0
							) }
						</li>
						<li>
							{ sprintf(
								__( 'Removed: %d', 'assetpilot' ),
								compareResult.removed?.length || 0
							) }
						</li>
						<li>
							{ sprintf(
								__( 'Changed: %d', 'assetpilot' ),
								compareResult.changed?.length || 0
							) }
						</li>
						<li>
							{ sprintf(
								__( 'Unchanged: %d', 'assetpilot' ),
								compareResult.unchanged || 0
							) }
						</li>
					</ul>
					{ ( compareResult.added?.length > 0 || compareResult.removed?.length > 0 ) && (
						<div className="assetpilot-scan-compare__lists">
							{ compareResult.added?.length > 0 && (
								<div>
									<h4>{ __( 'Added assets', 'assetpilot' ) }</h4>
									<ul>
										{ compareResult.added.slice( 0, 15 ).map( ( a ) => (
											<li key={ `add-${ a.handle }-${ a.type }` }>
												<code>{ a.handle }</code> ({ a.type })
											</li>
										) ) }
									</ul>
								</div>
							) }
							{ compareResult.removed?.length > 0 && (
								<div>
									<h4>{ __( 'Removed assets', 'assetpilot' ) }</h4>
									<ul>
										{ compareResult.removed.slice( 0, 15 ).map( ( a ) => (
											<li key={ `rem-${ a.handle }-${ a.type }` }>
												<code>{ a.handle }</code> ({ a.type })
											</li>
										) ) }
									</ul>
								</div>
							) }
						</div>
					) }
				</div>
			) }

			{ loading ? (
				<div className="assetpilot-loading-panel">
					<Spinner />
				</div>
			) : scans.length === 0 ? (
				<div className="assetpilot-empty-state">
					<p>{ __( 'No scans saved yet. Run a scan in Assets Explorer.', 'assetpilot' ) }</p>
					<Button variant="primary" href={ window.assetpilotAdmin?.assetsPageUrl }>
						{ __( 'Open Assets Explorer', 'assetpilot' ) }
					</Button>
				</div>
			) : (
				<>
					{ Object.entries( grouped ).map( ( [ day, dayScans ] ) => (
						<section key={ day } className="assetpilot-scan-timeline__day">
							<h3 className="assetpilot-scan-timeline__date">{ day }</h3>
							<table className="wp-list-table widefat fixed striped assetpilot-scan-table">
								<thead>
									<tr>
										<th className="check-column" />
										<th>{ __( 'URL', 'assetpilot' ) }</th>
										<th>{ __( 'Assets', 'assetpilot' ) }</th>
										<th>{ __( 'Scripts', 'assetpilot' ) }</th>
										<th>{ __( 'Styles', 'assetpilot' ) }</th>
										<th>{ __( 'Total size', 'assetpilot' ) }</th>
										<th>{ __( 'Scanned', 'assetpilot' ) }</th>
										<th>{ __( 'Actions', 'assetpilot' ) }</th>
									</tr>
								</thead>
								<tbody>
									{ dayScans.map( ( scan ) => (
										<tr key={ scan.id }>
											<td>
												<input
													type="checkbox"
													checked={ selected.includes( scan.id ) }
													onChange={ () => toggleSelect( scan.id ) }
													aria-label={ __( 'Select for compare', 'assetpilot' ) }
												/>
											</td>
											<td>
												<code className="assetpilot-scan-table__url">{ scan.scan_url }</code>
											</td>
											<td>{ scan.asset_count }</td>
											<td>{ scan.script_count }</td>
											<td>{ scan.style_count }</td>
											<td>{ formatBytes( scan.total_size ) }</td>
											<td>{ formatDate( scan.scanned_at ) }</td>
											<td className="assetpilot-rule-actions">
												<Button variant="link" href={ assetsExplorerUrl( scan.id ) }>
													{ __( 'Open', 'assetpilot' ) }
												</Button>
												{ ' · ' }
												<Button
													variant="link"
													isDestructive
													onClick={ () => handleDelete( scan.id ) }
												>
													{ __( 'Delete', 'assetpilot' ) }
												</Button>
											</td>
										</tr>
									) ) }
								</tbody>
							</table>
						</section>
					) ) }

					<div className="assetpilot-pagination">
						<Button
							variant="secondary"
							disabled={ page <= 1 }
							onClick={ () => setPage( ( p ) => Math.max( 1, p - 1 ) ) }
						>
							{ __( 'Previous', 'assetpilot' ) }
						</Button>
						<span>
							{ sprintf(
								/* translators: 1: current page, 2: total pages */
								__( 'Page %1$d of %2$d', 'assetpilot' ),
								page,
								totalPages
							) }
						</span>
						<Button
							variant="secondary"
							disabled={ page >= totalPages }
							onClick={ () => setPage( ( p ) => p + 1 ) }
						>
							{ __( 'Next', 'assetpilot' ) }
						</Button>
					</div>
				</>
			) }
		</div>
	);
}
