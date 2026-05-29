/**
 * Asset details slide-over drawer.
 */
import { useState, useEffect, useCallback } from '@wordpress/element';
import { Button, Spinner } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import { fetchAssetDetails, duplicateRule } from '../api';
import VerificationBadge from './VerificationBadge';

function DetailRow( { label, children } ) {
	return (
		<div className="assetpilot-drawer-detail">
			<span className="assetpilot-drawer-detail__label">{ label }</span>
			<span className="assetpilot-drawer-detail__value">{ children }</span>
		</div>
	);
}

function DependencyList( { title, items, emptyLabel } ) {
	if ( ! items?.length ) {
		return (
			<div className="assetpilot-drawer-section">
				<h3 className="assetpilot-drawer-section__title">{ title }</h3>
				<p className="assetpilot-drawer-muted">{ emptyLabel }</p>
			</div>
		);
	}

	return (
		<div className="assetpilot-drawer-section">
			<h3 className="assetpilot-drawer-section__title">{ title }</h3>
			<ul className="assetpilot-drawer-deps">
				{ items.map( ( row, i ) => (
					<li
						key={ `${ row.handle }-${ i }` }
						className="assetpilot-drawer-deps__item"
						style={ { paddingLeft: `${ 12 + ( row.depth || 0 ) * 16 }px` } }
					>
						<code>{ row.handle }</code>
						{ row.circular && (
							<span className="assetpilot-drawer-deps__circular">{ __( '(circular)', 'assetpilot' ) }</span>
						) }
					</li>
				) ) }
			</ul>
		</div>
	);
}

export default function AssetDetailsDrawer( { asset, scanUrl, onClose, onCreateRule } ) {
	const [ details, setDetails ] = useState( null );
	const [ loading, setLoading ] = useState( true );
	const [ error, setError ] = useState( '' );
	const [ duplicatingId, setDuplicatingId ] = useState( null );

	const load = useCallback( () => {
		if ( ! asset?.handle ) {
			return;
		}
		setLoading( true );
		setError( '' );
		fetchAssetDetails( asset.handle, asset.type, { scanUrl, snapshot: asset } )
			.then( setDetails )
			.catch( ( err ) => {
				setError( err?.message || __( 'Failed to load asset details.', 'assetpilot' ) );
				setDetails( null );
			} )
			.finally( () => setLoading( false ) );
	}, [ asset, scanUrl ] );

	useEffect( () => {
		load();
	}, [ load ] );

	useEffect( () => {
		const onKey = ( e ) => {
			if ( e.key === 'Escape' ) {
				onClose();
			}
		};
		document.addEventListener( 'keydown', onKey );
		document.body.classList.add( 'assetpilot-drawer-open' );
		return () => {
			document.removeEventListener( 'keydown', onKey );
			document.body.classList.remove( 'assetpilot-drawer-open' );
		};
	}, [ onClose ] );

	const rulesPageUrl =
		window.assetpilotAdmin?.rulesPageUrl || 'admin.php?page=assetpilot-rules';

	const viewRulesUrl = () => {
		const base = rulesPageUrl.includes( 'http' )
			? rulesPageUrl
			: `${ window.assetpilotAdmin?.adminUrl || '/wp-admin/' }${ rulesPageUrl }`;
		const url = new URL( base, window.location.origin );
		url.searchParams.set( 'asset_handle', asset.handle );
		url.searchParams.set( 'asset_type', asset.type );
		return url.toString();
	};

	const handleDuplicate = async ( ruleId ) => {
		setDuplicatingId( ruleId );
		try {
			await duplicateRule( ruleId );
			load();
		} catch ( err ) {
			setError( err?.message || __( 'Could not duplicate rule.', 'assetpilot' ) );
		} finally {
			setDuplicatingId( null );
		}
	};

	const info = details?.asset || asset;
	const runtime = details?.runtime || {};
	const deps = details?.dependencies || {};
	const usage = details?.usage || {};
	const rules = details?.rules || [];

	const chain = deps.dependency_chain || [];
	const originLabel = {
		plugin: __( 'Plugin', 'assetpilot' ),
		theme: __( 'Theme', 'assetpilot' ),
		core: __( 'Core', 'assetpilot' ),
		external: __( 'External', 'assetpilot' ),
		html: __( 'From page HTML', 'assetpilot' ),
		custom: __( 'Custom URL', 'assetpilot' ),
		unknown: __( 'Unknown', 'assetpilot' ),
	}[ info?.origin ] || info?.origin || '—';

	const isMediaFromHtml =
		[ 'image', 'font' ].includes( asset.type ) &&
		( asset.origin === 'html' || info?.source === 'html' || info?.from_html );

	return (
		<div className="assetpilot-drawer" role="dialog" aria-modal="true" aria-labelledby="assetpilot-drawer-title">
			<button type="button" className="assetpilot-drawer__backdrop" onClick={ onClose } aria-label={ __( 'Close', 'assetpilot' ) } />
			<div className="assetpilot-drawer__panel">
				<header className="assetpilot-drawer__header">
					<div>
						<h2 id="assetpilot-drawer-title" className="assetpilot-drawer__title">
							<code>{ asset.handle }</code>
						</h2>
						<span className={ `assetpilot-badge assetpilot-badge--${ asset.type }` }>{ asset.type }</span>
					</div>
					<Button icon="no-alt" label={ __( 'Close', 'assetpilot' ) } onClick={ onClose } />
				</header>

				<div className="assetpilot-drawer__body">
					{ loading && (
						<div className="assetpilot-drawer-loading">
							<Spinner />
						</div>
					) }

					{ error && ! loading && <p className="assetpilot-drawer-error">{ error }</p> }

					{ ! loading && (
						<>
							<div className="assetpilot-drawer-actions">
								<Button variant="primary" onClick={ () => onCreateRule( asset ) }>
									{ __( 'Create rule', 'assetpilot' ) }
								</Button>
								<Button variant="secondary" href={ viewRulesUrl() }>
									{ __( 'View rules', 'assetpilot' ) }
								</Button>
							</div>

							<div className="assetpilot-drawer-section">
								<h3 className="assetpilot-drawer-section__title">{ __( 'Basic info', 'assetpilot' ) }</h3>
								<DetailRow label={ __( 'Handle', 'assetpilot' ) }>
									<code>{ info.handle }</code>
								</DetailRow>
								<DetailRow label={ __( 'Type', 'assetpilot' ) }>{ info.type }</DetailRow>
								<DetailRow label={ __( 'Source', 'assetpilot' ) }>{ info.source || '—' }</DetailRow>
								<DetailRow label={ __( 'Origin', 'assetpilot' ) }>{ originLabel }</DetailRow>
								<DetailRow label={ __( 'URL', 'assetpilot' ) }>
									{ info.src ? (
										<a href={ info.src } target="_blank" rel="noopener noreferrer" className="assetpilot-drawer-url">
											{ info.src }
										</a>
									) : (
										'—'
									) }
								</DetailRow>
								<DetailRow label={ __( 'Version', 'assetpilot' ) }>{ info.version || '—' }</DetailRow>
								<DetailRow label={ __( 'File size', 'assetpilot' ) }>
									{ info.size ? `${ ( info.size / 1024 ).toFixed( 1 ) } KB` : '—' }
								</DetailRow>
								{ info.type === 'style' && (
									<DetailRow label={ __( 'Media', 'assetpilot' ) }>{ info.media || 'all' }</DetailRow>
								) }
							</div>

							<div className="assetpilot-drawer-section">
								<h3 className="assetpilot-drawer-section__title">{ __( 'Dependencies', 'assetpilot' ) }</h3>
								{ chain.length > 0 && (
									<p className="assetpilot-drawer-chain">
										{ chain.join( ' → ' ) }
										{ chain[ chain.length - 1 ] !== asset.handle ? ` → ${ asset.handle }` : '' }
									</p>
								) }
								{ ( deps.direct_dependencies || [] ).length > 0 && (
									<p className="assetpilot-drawer-muted">
										{ __( 'Direct:', 'assetpilot' ) }{ ' ' }
										{ ( deps.direct_dependencies || [] ).join( ', ' ) }
									</p>
								) }
							</div>

							{ isMediaFromHtml ? (
								<p className="assetpilot-drawer-muted">
									{ __(
										'This asset was discovered in page HTML, not the WordPress script/style registry. Dependency data applies to registered handles only.',
										'assetpilot'
									) }
								</p>
							) : (
								<>
									<DependencyList
										title={ __( 'Dependency tree', 'assetpilot' ) }
										items={ deps.dependencies }
										emptyLabel={ __( 'No registered dependencies.', 'assetpilot' ) }
									/>

									<DependencyList
										title={ __( 'Dependents', 'assetpilot' ) }
										items={ deps.dependents }
										emptyLabel={ __( 'Nothing depends on this asset.', 'assetpilot' ) }
									/>
								</>
							) }

							<div className="assetpilot-drawer-section">
								<h3 className="assetpilot-drawer-section__title">{ __( 'Runtime', 'assetpilot' ) }</h3>
								<DetailRow label={ __( 'On scanned page', 'assetpilot' ) }>
									{ runtime.loaded_on_scan ? __( 'Yes', 'assetpilot' ) : __( 'No', 'assetpilot' ) }
								</DetailRow>
								<DetailRow label={ __( 'Enqueue order', 'assetpilot' ) }>
									{ runtime.enqueue_order
										? sprintf(
												/* translators: 1: position, 2: total */
												__( '%1$d of %2$d', 'assetpilot' ),
												runtime.enqueue_order,
												runtime.queue_length || 0
										  )
										: '—' }
								</DetailRow>
								<DetailRow label={ __( 'Active rules', 'assetpilot' ) }>
									{ runtime.active_rules_count ?? 0 }
								</DetailRow>
								<ul className="assetpilot-drawer-flags">
									{ runtime.has_preload_rule && <li>{ __( 'Preload rule', 'assetpilot' ) }</li> }
									{ runtime.has_defer_rule && <li>{ __( 'Defer rule', 'assetpilot' ) }</li> }
									{ runtime.has_async_rule && <li>{ __( 'Async rule', 'assetpilot' ) }</li> }
									{ runtime.has_disable_rule && <li>{ __( 'Disable rule', 'assetpilot' ) }</li> }
									{ ! runtime.has_preload_rule &&
										! runtime.has_defer_rule &&
										! runtime.has_async_rule &&
										! runtime.has_disable_rule && (
											<li className="assetpilot-drawer-muted">{ __( 'No action rules', 'assetpilot' ) }</li>
										) }
								</ul>
							</div>

							<div className="assetpilot-drawer-section">
								<h3 className="assetpilot-drawer-section__title">{ __( 'Page usage', 'assetpilot' ) }</h3>
								<DetailRow label={ __( 'Scanned pages', 'assetpilot' ) }>
									{ usage.count ?? 0 }
								</DetailRow>
								{ ( usage.recent_pages || [] ).length > 0 ? (
									<ul className="assetpilot-drawer-pages">
										{ usage.recent_pages.map( ( row ) => (
											<li key={ row.url }>
												<a href={ row.url } target="_blank" rel="noopener noreferrer">
													{ row.url }
												</a>
												{ row.scanned_at && (
													<span className="assetpilot-drawer-muted"> — { row.scanned_at }</span>
												) }
											</li>
										) ) }
									</ul>
								) : (
									<p className="assetpilot-drawer-muted">
										{ __( 'Scan more pages to build usage history.', 'assetpilot' ) }
									</p>
								) }
							</div>

							{ rules.length > 0 && (
								<div className="assetpilot-drawer-section">
									<h3 className="assetpilot-drawer-section__title">{ __( 'Rules for this asset', 'assetpilot' ) }</h3>
									<ul className="assetpilot-drawer-rules">
										{ rules.map( ( rule ) => (
											<li key={ rule.id } className="assetpilot-drawer-rules__item">
												<div className="assetpilot-drawer-rules__main">
													<span className="assetpilot-drawer-rules__action">{ rule.action_type }</span>
													<VerificationBadge verification={ rule.verification } />
												</div>
												<span className="assetpilot-drawer-rules__meta">
													{ rule.enabled
														? __( 'Enabled', 'assetpilot' )
														: __( 'Disabled', 'assetpilot' ) }
													{ ' · ' }
													{ rule.condition_summary }
												</span>
												<Button
													variant="secondary"
													isSmall
													isBusy={ duplicatingId === rule.id }
													disabled={ duplicatingId !== null }
													onClick={ () => handleDuplicate( rule.id ) }
												>
													{ __( 'Duplicate', 'assetpilot' ) }
												</Button>
											</li>
										) ) }
									</ul>
								</div>
							) }
						</>
					) }
				</div>
			</div>
		</div>
	);
}
