/**
 * Dashboard screen.
 */
import { useState, useEffect, useMemo } from '@wordpress/element';
import { Spinner, Button, Notice } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { fetchDashboard, fetchAssets } from '../api';
import DashboardScanProgress from '../components/DashboardScanProgress';
import { assetsExplorerUrl, adminPageUrl } from '../navigationUtils';

const formatSize = ( bytes ) => {
	if ( ! bytes ) {
		return '—';
	}
	if ( bytes >= 1024 * 1024 ) {
		return `${ ( bytes / ( 1024 * 1024 ) ).toFixed( 1 ) } MB`;
	}
	return `${ ( bytes / 1024 ).toFixed( 1 ) } KB`;
};

const pickLargestAssets = ( assets, limit = 5 ) => {
	const sorted = [ ...( assets || [] ) ].sort(
		( a, b ) => ( b.size || 0 ) - ( a.size || 0 )
	);
	return sorted.slice( 0, limit );
};

export default function Dashboard() {
	const homeUrl = useMemo(
		() => window.assetpilotAdmin?.homeUrl || '/',
		[]
	);

	const [ data, setData ] = useState( null );
	const [ booting, setBooting ] = useState( true );
	const [ scanning, setScanning ] = useState( false );
	const [ scanError, setScanError ] = useState( '' );
	const [ fromCache, setFromCache ] = useState( false );

	const runHomepageScan = async ( rulesSummary ) => {
		setScanning( true );
		setScanError( '' );
		setFromCache( false );

		try {
			const result = await fetchAssets( { scan_url: homeUrl } );
			const assets = result.assets || [];
			setFromCache( !! result.meta?.from_cache );
			setData( {
				...rulesSummary,
				scan_url: homeUrl,
				total_assets: assets.length,
				largest_assets: pickLargestAssets( assets ),
			} );
		} catch ( err ) {
			setScanError(
				err?.message || __( 'Could not scan the homepage. Try Assets Explorer.', 'assetpilot' )
			);
			setData( rulesSummary );
		} finally {
			setScanning( false );
		}
	};

	useEffect( () => {
		let cancelled = false;

		const load = async () => {
			setBooting( true );
			setScanError( '' );

			try {
				const summary = await fetchDashboard( { summary_only: true } );
				if ( cancelled ) {
					return;
				}

				const rulesSummary = {
					...summary,
					scan_url: summary.scan_url || homeUrl,
					total_assets: summary.total_assets ?? 0,
					largest_assets: summary.largest_assets || [],
				};

				setData( rulesSummary );
				setBooting( false );
				await runHomepageScan( rulesSummary );
			} catch ( err ) {
				if ( cancelled ) {
					return;
				}
				setScanError(
					err?.message || __( 'Failed to load dashboard.', 'assetpilot' )
				);
				setBooting( false );
				setScanning( false );
			}
		};

		load();

		return () => {
			cancelled = true;
		};
		// eslint-disable-next-line react-hooks/exhaustive-deps -- run once on mount
	}, [ homeUrl ] );

	if ( booting && ! data ) {
		return (
			<div className="assetpilot-dashboard assetpilot-dashboard--boot">
				<DashboardScanProgress scanUrl={ homeUrl } scanning={ true } />
			</div>
		);
	}

	const largest = data?.largest_assets || [];
	const recentRules = data?.recent_rules || [];
	const showScanPanel = scanning || scanError;
	const scanTarget = data?.scan_url || homeUrl;
	const assetsCtaUrl = assetsExplorerUrl( { scanUrl: scanTarget } );

	return (
		<div className="assetpilot-dashboard">
			<section className="assetpilot-card assetpilot-dashboard-cta">
				<div className="assetpilot-dashboard-cta__body">
					<h2 className="assetpilot-dashboard-cta__title">
						{ __( 'Scan & manage assets', 'assetpilot' ) }
					</h2>
					<p className="assetpilot-dashboard-cta__lead">
						{ __(
							'Create rules from Assets Explorer after scanning a page. Pick an asset, choose an action, and set where the rule applies.',
							'assetpilot'
						) }
					</p>
					<div className="assetpilot-dashboard-cta__actions">
						<Button variant="primary" href={ assetsCtaUrl }>
							{ __( 'Open Assets Explorer', 'assetpilot' ) }
						</Button>
						<Button
							variant="secondary"
							href={
								window.assetpilotAdmin?.rulesPageUrl ||
								adminPageUrl( 'assetpilot-rules' )
							}
						>
							{ __( 'View rules', 'assetpilot' ) }
						</Button>
						<Button
							variant="secondary"
							href={
								window.assetpilotAdmin?.recommendationsPageUrl ||
								adminPageUrl( 'assetpilot-recommendations' )
							}
						>
							{ __( 'Recommendations', 'assetpilot' ) }
						</Button>
						<Button
							variant="link"
							href={
								window.assetpilotAdmin?.scanHistoryPageUrl ||
								adminPageUrl( 'assetpilot-scan-history' )
							}
						>
							{ __( 'Scan history', 'assetpilot' ) }
						</Button>
					</div>
				</div>
			</section>

			{ showScanPanel && (
				<DashboardScanProgress
					scanUrl={ data?.scan_url || homeUrl }
					scanning={ scanning }
					error={ scanError }
				/>
			) }

			{ ! scanning && fromCache && ! scanError && (
				<Notice status="info" isDismissible={ false } className="assetpilot-dashboard-cache-notice">
					{ __( 'Homepage assets were loaded from a recent scan cache.', 'assetpilot' ) }
				</Notice>
			) }

			{ ! scanning && scanError && (
				<p className="assetpilot-dashboard__retry">
					<Button
						variant="secondary"
						onClick={ () => data && runHomepageScan( data ) }
					>
						{ __( 'Retry homepage scan', 'assetpilot' ) }
					</Button>
					<Button variant="link" href={ adminPageUrl( 'assetpilot-assets' ) }>
						{ __( 'Open Assets Explorer', 'assetpilot' ) }
					</Button>
				</p>
			) }

			<div className="assetpilot-summary assetpilot-dashboard-summary">
				<div className="assetpilot-summary__stat">
					<span className="assetpilot-summary__value">
						{ scanning ? (
							<span className="assetpilot-dashboard-stat-pending" aria-hidden>
								<Spinner />
							</span>
						) : (
							data?.total_assets ?? 0
						) }
					</span>
					<span className="assetpilot-summary__label">{ __( 'Assets on homepage', 'assetpilot' ) }</span>
				</div>
				<div className="assetpilot-summary__stat">
					<span className="assetpilot-summary__value">{ data?.total_rules ?? 0 }</span>
					<span className="assetpilot-summary__label">{ __( 'Rules', 'assetpilot' ) }</span>
				</div>
				<div className="assetpilot-summary__stat">
					<span className="assetpilot-summary__value">{ data?.enabled_rules ?? 0 }</span>
					<span className="assetpilot-summary__label">{ __( 'Enabled rules', 'assetpilot' ) }</span>
				</div>
			</div>

			<div className={ `assetpilot-dashboard-panels${ scanning ? ' assetpilot-dashboard-panels--scanning' : '' }` }>
				<section className="assetpilot-card assetpilot-dashboard-section assetpilot-dashboard-section--primary">
					<div className="assetpilot-dashboard-section__head">
						<h2 className="assetpilot-dashboard-section__title">{ __( 'Largest assets', 'assetpilot' ) }</h2>
						<a className="assetpilot-dashboard-section__link" href={ adminPageUrl( 'assetpilot-assets' ) }>
							{ __( 'View all assets', 'assetpilot' ) }
						</a>
					</div>
					{ scanning ? (
						<p className="assetpilot-dashboard-section__empty assetpilot-dashboard-section__empty--scan">
							{ __( 'Waiting for homepage scan to finish…', 'assetpilot' ) }
						</p>
					) : largest.length === 0 ? (
						<p className="assetpilot-dashboard-section__empty">
							{ __( 'No assets detected. Scan your site in Assets Explorer.', 'assetpilot' ) }
						</p>
					) : (
						<div className="assetpilot-table-wrap assetpilot-table-wrap--flush">
							<table className="wp-list-table widefat striped assetpilot-dashboard-table">
								<thead>
									<tr>
										<th>{ __( 'Handle', 'assetpilot' ) }</th>
										<th>{ __( 'Type', 'assetpilot' ) }</th>
										<th>{ __( 'Size', 'assetpilot' ) }</th>
										<th>{ __( 'Source', 'assetpilot' ) }</th>
									</tr>
								</thead>
								<tbody>
									{ largest.map( ( asset ) => (
										<tr key={ `${ asset.type }-${ asset.handle }` }>
											<td>
												<a
													href={ assetsExplorerUrl( {
														scanUrl: scanTarget,
														handle: asset.handle,
														type: asset.type,
													} ) }
												>
													<code className="assetpilot-handle">{ asset.handle }</code>
												</a>
											</td>
											<td>
												<span className={ `assetpilot-badge assetpilot-badge--${ asset.type }` }>
													{ asset.type }
												</span>
											</td>
											<td>{ formatSize( asset.size ) }</td>
											<td>
												<span className="assetpilot-source-name">{ asset.source || '—' }</span>
											</td>
										</tr>
									) ) }
								</tbody>
							</table>
						</div>
					) }
				</section>

				<div className="assetpilot-dashboard-panels__split">
					<section className="assetpilot-card assetpilot-dashboard-section assetpilot-dashboard-section--promo">
						<div className="assetpilot-dashboard-section__head">
							<h2 className="assetpilot-dashboard-section__title">
								{ __( 'Optimization suggestions', 'assetpilot' ) }
							</h2>
						</div>
						<p className="assetpilot-dashboard-section__lead">
							{ __(
								'Find large assets, render-blocking scripts, duplicate libraries, and low-usage files from your scans.',
								'assetpilot'
							) }
						</p>
						<div className="assetpilot-dashboard-section__foot">
							<Button
								variant="secondary"
								href={
									window.assetpilotAdmin?.recommendationsPageUrl ||
									adminPageUrl( 'assetpilot-recommendations' )
								}
							>
								{ __( 'View recommendations', 'assetpilot' ) }
							</Button>
						</div>
					</section>

					<section className="assetpilot-card assetpilot-dashboard-section assetpilot-dashboard-section--rules">
						<div className="assetpilot-dashboard-section__head">
							<h2 className="assetpilot-dashboard-section__title">{ __( 'Recent rules', 'assetpilot' ) }</h2>
							<a className="assetpilot-dashboard-section__link" href={ adminPageUrl( 'assetpilot-rules' ) }>
								{ __( 'View all rules', 'assetpilot' ) }
							</a>
						</div>
						{ recentRules.length === 0 ? (
							<p className="assetpilot-dashboard-section__empty">
								{ __( 'No rules yet.', 'assetpilot' ) }{ ' ' }
								<a href={ assetsCtaUrl }>
									{ __( 'Scan assets and create a rule', 'assetpilot' ) }
								</a>
							</p>
						) : (
							<ul className="assetpilot-dashboard-rules">
								{ recentRules.map( ( rule ) => (
									<li key={ rule.id } className="assetpilot-dashboard-rules__item">
										<div className="assetpilot-dashboard-rules__main">
											<code className="assetpilot-handle">{ rule.asset_handle }</code>
											{ rule.asset_type && (
												<span className={ `assetpilot-badge assetpilot-badge--${ rule.asset_type }` }>
													{ rule.asset_type }
												</span>
											) }
										</div>
										<div className="assetpilot-dashboard-rules__meta">
											<span className="assetpilot-badge assetpilot-badge--muted">{ rule.action_type }</span>
											<span
												className={
													rule.enabled
														? 'assetpilot-badge assetpilot-badge--plugin'
														: 'assetpilot-badge assetpilot-badge--muted'
												}
											>
												{ rule.enabled
													? __( 'Enabled', 'assetpilot' )
													: __( 'Disabled', 'assetpilot' ) }
											</span>
										</div>
									</li>
								) ) }
							</ul>
						) }
					</section>
				</div>
			</div>
		</div>
	);
}
